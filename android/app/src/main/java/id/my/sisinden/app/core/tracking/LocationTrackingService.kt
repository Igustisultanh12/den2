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
        listOf(LocationManager.GPS_PROVIDER, LocationManager.NETWORK_PROVIDER)
            .filter { runCatching { lm.allProviders.contains(it) }.getOrDefault(false) }
            .forEach { provider ->
                runCatching {
                    lm.requestLocationUpdates(provider, UPDATE_INTERVAL_MS, 0f, listener, Looper.getMainLooper())
                    listening = true
                }.onFailure { Log.w(TAG, "Provider $provider tidak tersedia", it) }
            }
        // Kirim posisi terakhir yang diketahui segera agar pemantau tidak menunggu fix pertama.
        listOf(LocationManager.GPS_PROVIDER, LocationManager.NETWORK_PROVIDER)
            .mapNotNull { runCatching { lm.getLastKnownLocation(it) }.getOrNull() }
            .filter { System.currentTimeMillis() - it.time < 10 * 60 * 1000 }
            .maxByOrNull { it.time }
            ?.let { onLocation(it) }
    }

    /**
     * Saring titik: terima bila lebih akurat dari titik terakhir, atau bila titik
     * terakhir sudah cukup lama (GPS & jaringan tidak saling menimpa tiap detik).
     */
    private fun onLocation(location: Location) {
        val last = lastAccepted
        val newer = last == null || location.time - last.time >= MIN_ACCEPT_GAP_MS
        val moreAccurate = last != null && location.hasAccuracy() && (!last.hasAccuracy() || location.accuracy < last.accuracy)
        if (!newer && !moreAccurate) return
        lastAccepted = location

        val point = LocationPoint(
            latitude = location.latitude,
            longitude = location.longitude,
            accuracy = if (location.hasAccuracy()) location.accuracy else null,
            speed = if (location.hasSpeed()) location.speed else null,
            heading = if (location.hasBearing()) location.bearing else null,
            battery = batteryLevel(),
            isCharging = isCharging(),
            provider = location.provider,
            recordedAt = Instant.ofEpochMilli(location.time).toString(),
        )
        scope.launch {
            queueLock.withLock {
                pending.addLast(point)
                while (pending.size > MAX_PENDING) pending.removeFirst()
            }
        }
    }

    private suspend fun uploadLoop() {
        val repository = (application as SindenApplication).repository
        while (scope.isActive) {
            delay(UPLOAD_INTERVAL_MS)
            if (repository.session.token == null) continue
            val batch = queueLock.withLock { pending.take(MAX_BATCH) }
            if (batch.isEmpty()) continue
            when (val res = repository.reportLocation(batch)) {
                is ApiResult.Success -> queueLock.withLock { repeat(batch.size.coerceAtMost(pending.size)) { pending.removeFirst() } }
                is ApiResult.Failure -> Log.w(TAG, "Gagal kirim lokasi: ${res.error.message}")
            }
        }
    }

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
            val channel = NotificationChannel(CHANNEL_ID, "Pelacakan Lokasi Dinas", NotificationManager.IMPORTANCE_LOW).apply {
                description = "Menandakan lokasi Anda sedang dibagikan ke pimpinan selama login SISINDEN"
                setShowBadge(false)
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
            .setContentTitle("Pelacakan lokasi dinas aktif")
            .setContentText("Lokasi Anda dibagikan ke Admin & Komandan selama login SISINDEN.")
            .setOngoing(true)
            .setOnlyAlertOnce(true)
            .setPriority(NotificationCompat.PRIORITY_LOW)
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
        private const val UPDATE_INTERVAL_MS = 30_000L
        private const val MIN_ACCEPT_GAP_MS = 25_000L
        private const val UPLOAD_INTERVAL_MS = 30_000L
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
