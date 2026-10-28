package id.my.sisinden.app.core.tracking

import android.annotation.SuppressLint
import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Context
import android.content.Intent
import android.content.pm.ServiceInfo
import android.location.Location
import android.location.LocationListener
import android.location.LocationManager
import android.os.BatteryManager
import android.os.Build
import android.os.IBinder
import android.os.Looper
import android.util.Log
import androidx.core.app.NotificationCompat
import androidx.core.app.ServiceCompat
import androidx.core.content.ContextCompat
import id.my.sisinden.app.MainActivity
import id.my.sisinden.app.R
import id.my.sisinden.app.SindenApplication
import id.my.sisinden.app.core.network.ApiResult
import id.my.sisinden.app.data.model.LocationPoint
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.cancel
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import java.time.Instant

/**
 * Foreground service pelacakan lokasi realtime selama personel login.
 *
 * - Titik GPS/jaringan diambil berkala dan dikirim berkelompok ke
 *   POST /api/mobile/location (titik yang gagal terkirim disimpan sementara
 *   di memori dan dikirim ulang).
 * - Notifikasi permanen memberi tahu personel bahwa pelacakan aktif.
 * - Dihentikan oleh [TrackingController] saat logout atau izin lokasi dicabut.
 */
class LocationTrackingService : Service() {

    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.Main.immediate)
    private val queueLock = Mutex()
    private val pending = ArrayDeque<LocationPoint>()
    private var uploadJob: Job? = null
    private var lastAccepted: Location? = null
    private var listening = false
    private var fastMode = true
    private var lastMovedAt = System.currentTimeMillis()

    private val listener = LocationListener { location -> onLocation(location) }

    override fun onBind(intent: Intent?): IBinder? = null

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        if (!LocationAccess.hasPrecise(this)) {
            // Tanpa izin lokasi service tidak boleh berjalan sebagai foreground lokasi.
            stopSelf()
            return START_NOT_STICKY
        }
        createChannel()
        ServiceCompat.startForeground(
            this,
            NOTIFICATION_ID,
            buildNotification(),
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) ServiceInfo.FOREGROUND_SERVICE_TYPE_LOCATION else 0,
        )
        startListening()
        if (uploadJob?.isActive != true) {
            uploadJob = scope.launch { uploadLoop() }
        }
        return START_STICKY
    }

    @SuppressLint("MissingPermission")
    private fun startListening() {
        if (listening) return
        val lm = getSystemService(Context.LOCATION_SERVICE) as? LocationManager ?: return
        requestUpdates(lm, fast = true)
        // Kirim posisi terakhir yang diketahui segera agar pemantau tidak menunggu fix pertama.
        listOf(LocationManager.GPS_PROVIDER, LocationManager.NETWORK_PROVIDER)
            .mapNotNull { runCatching { lm.getLastKnownLocation(it) }.getOrNull() }
            .filter { System.currentTimeMillis() - it.time < 10 * 60 * 1000 }
            .maxByOrNull { it.time }
            ?.let { onLocation(it) }
    }

    /**
     * Mode cepat (bergerak): GPS tiap [FAST_INTERVAL_MS] seperti aplikasi ojek daring.
     * Mode hemat (diam > [IDLE_AFTER_MS]): GPS tiap [SLOW_INTERVAL_MS] agar baterai awet.
     */
    @SuppressLint("MissingPermission")
    private fun requestUpdates(lm: LocationManager, fast: Boolean) {
        if (listening) lm.removeUpdates(listener)
        listening = false
        fastMode = fast
        val gpsInterval = if (fast) FAST_INTERVAL_MS else SLOW_INTERVAL_MS
        listOf(LocationManager.GPS_PROVIDER to gpsInterval, LocationManager.NETWORK_PROVIDER to maxOf(gpsInterval, NETWORK_INTERVAL_MS))
            .filter { (provider, _) -> runCatching { lm.allProviders.contains(provider) }.getOrDefault(false) }
            .forEach { (provider, interval) ->
                runCatching {
                    lm.requestLocationUpdates(provider, interval, 0f, listener, Looper.getMainLooper())
                    listening = true
                }.onFailure { Log.w(TAG, "Provider $provider tidak tersedia", it) }
            }
    }

    private fun setFastMode(fast: Boolean) {
        if (fast == fastMode) return
        val lm = getSystemService(Context.LOCATION_SERVICE) as? LocationManager ?: return
        requestUpdates(lm, fast)
    }

    /**
     * Saring titik: saat bergerak setiap titik baru (≥ [MIN_MOVING_GAP_MS]) diterima;
     * saat diam cukup satu titik per [STATIONARY_GAP_MS]. Titik jaringan yang jauh
     * kurang akurat tidak menimpa titik GPS yang masih segar.
     */
    private fun onLocation(location: Location) {
        val last = lastAccepted
        val gap = if (last == null) Long.MAX_VALUE else location.time - last.time
        if (gap < MIN_MOVING_GAP_MS) return
        val distance = last?.distanceTo(location) ?: Float.MAX_VALUE
        // Abaikan "loncatan" GPS saat diam: jarak harus melebihi ketidakpastian titik.
        val jitter = if (location.hasAccuracy()) location.accuracy else 0f
        val moving = (location.hasSpeed() && location.speed >= MOVING_SPEED_MPS) || distance >= maxOf(MOVING_DISTANCE_M, jitter)
        val worse = last != null && location.hasAccuracy() && last.hasAccuracy() &&
            location.accuracy > last.accuracy * 3 && gap < STATIONARY_GAP_MS
        if (worse) return
        if (!moving && gap < STATIONARY_GAP_MS) return

        if (moving) lastMovedAt = System.currentTimeMillis()
        setFastMode(System.currentTimeMillis() - lastMovedAt < IDLE_AFTER_MS)
        lastAccepted = location

        val point = pointOf(location, Instant.ofEpochMilli(location.time).toString(), location.provider)
        scope.launch {
            queueLock.withLock {
                pending.addLast(point)
                while (pending.size > MAX_PENDING) pending.removeFirst()
            }
        }
    }

    private suspend fun uploadLoop() {
        val repository = (application as SindenApplication).repository
        var lastSentAt = 0L
        var failures = 0
        while (scope.isActive) {
            // Gagal beruntun (mis. sinyal hilang) → jeda bertahap agar tidak menguras baterai.
            delay(UPLOAD_INTERVAL_MS * (1 + failures.coerceAtMost(5)))
            if (repository.session.token == null) continue
            // Detak "masih online": HP diam di dalam ruangan bisa tidak mendapat titik GPS baru.
            val now = System.currentTimeMillis()
            val last = lastAccepted
            if (now - lastSentAt >= HEARTBEAT_MS && last != null) {
                queueLock.withLock { if (pending.isEmpty()) pending.addLast(pointOf(last, Instant.ofEpochMilli(now).toString(), "heartbeat")) }
            }
            val batch = queueLock.withLock { pending.take(MAX_BATCH) }
            if (batch.isEmpty()) continue
            when (val res = repository.reportLocation(batch)) {
                is ApiResult.Success -> {
                    queueLock.withLock { repeat(batch.size.coerceAtMost(pending.size)) { pending.removeFirst() } }
                    lastSentAt = now
                    failures = 0
                }
                is ApiResult.Failure -> {
                    failures++
                    Log.w(TAG, "Gagal kirim lokasi: ${res.error.message}")
                }
            }
        }
    }

    private fun pointOf(location: Location, recordedAt: String, provider: String?) = LocationPoint(
        latitude = location.latitude,
        longitude = location.longitude,
        accuracy = if (location.hasAccuracy()) location.accuracy else null,
        speed = if (location.hasSpeed()) location.speed else null,
        heading = if (location.hasBearing()) location.bearing else null,
        battery = batteryLevel(),
        isCharging = isCharging(),
        provider = provider,
        recordedAt = recordedAt,
    )

    private fun batteryLevel(): Int? {
        val bm = getSystemService(Context.BATTERY_SERVICE) as? BatteryManager ?: return null
        return bm.getIntProperty(BatteryManager.BATTERY_PROPERTY_CAPACITY).takeIf { it in 0..100 }
    }

    private fun isCharging(): Boolean? {
        val bm = getSystemService(Context.BATTERY_SERVICE) as? BatteryManager ?: return null
        return bm.isCharging
    }

    private fun createChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            // Android mewajibkan notifikasi untuk foreground service lokasi. Dibuat seminim
            // mungkin (IMPORTANCE_MIN: tanpa suara, tanpa ikon status, tersembunyi di bagian
            // bawah). Persetujuan pelacakan sudah diambil saat aktivasi akun.
            val channel = NotificationChannel(CHANNEL_ID, "Layanan Sinden 2", NotificationManager.IMPORTANCE_MIN).apply {
                description = "Menjaga aplikasi tetap berjalan selama Anda login"
                setShowBadge(false)
                setSound(null, null)
                enableVibration(false)
                enableLights(false)
                lockscreenVisibility = Notification.VISIBILITY_SECRET
            }
            (getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager).createNotificationChannel(channel)
        }
    }

    private fun buildNotification(): Notification {
        val open = PendingIntent.getActivity(
            this,
            NOTIFICATION_ID,
            Intent(this, MainActivity::class.java).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP),
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
        )
        return NotificationCompat.Builder(this, CHANNEL_ID)
            .setSmallIcon(R.drawable.ic_sinden_mark)
            .setContentTitle("Sinden 2")
            .setContentText("Aplikasi sedang berjalan.")
            .setOngoing(true)
            .setSilent(true)
            .setOnlyAlertOnce(true)
            .setShowWhen(false)
            .setPriority(NotificationCompat.PRIORITY_MIN)
            .setVisibility(NotificationCompat.VISIBILITY_SECRET)
            .setCategory(NotificationCompat.CATEGORY_SERVICE)
            .setForegroundServiceBehavior(NotificationCompat.FOREGROUND_SERVICE_IMMEDIATE)
            .setContentIntent(open)
            .build()
    }

    override fun onDestroy() {
        (getSystemService(Context.LOCATION_SERVICE) as? LocationManager)?.removeUpdates(listener)
        listening = false
        scope.cancel()
        super.onDestroy()
    }

    companion object {
        private const val TAG = "SindenTracking"
        private const val CHANNEL_ID = "sinden_tracking_channel"
        private const val NOTIFICATION_ID = 7301
        // Bergerak: titik tiap ~3 dtk & dikirim tiap ~4 dtk (jeda tampil di pemantau ± 5–10 dtk).
        private const val FAST_INTERVAL_MS = 3_000L
        private const val SLOW_INTERVAL_MS = 20_000L
        private const val NETWORK_INTERVAL_MS = 10_000L
        private const val MIN_MOVING_GAP_MS = 2_500L
        private const val STATIONARY_GAP_MS = 30_000L
        private const val IDLE_AFTER_MS = 2 * 60_000L
        private const val MOVING_SPEED_MPS = 1.0f
        private const val MOVING_DISTANCE_M = 8f
        private const val UPLOAD_INTERVAL_MS = 4_000L
        private const val HEARTBEAT_MS = 60_000L
        private const val MAX_BATCH = 100
        private const val MAX_PENDING = 500

        fun start(context: Context) {
            ContextCompat.startForegroundService(context, Intent(context, LocationTrackingService::class.java))
        }

        fun stop(context: Context) {
            context.stopService(Intent(context, LocationTrackingService::class.java))
        }
    }
}
