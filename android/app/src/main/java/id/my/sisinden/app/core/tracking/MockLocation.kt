package id.my.sisinden.app.core.tracking

import android.annotation.SuppressLint
import android.content.Context
import android.location.Location
import android.location.LocationListener
import android.location.LocationManager
import android.os.Build
import android.os.Looper
import id.my.sisinden.app.data.model.LoginLocation
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlinx.coroutines.withTimeoutOrNull
import kotlin.coroutines.resume

/** true bila titik berasal dari aplikasi lokasi palsu (Fake GPS / mock location). */
@Suppress("DEPRECATION")
fun Location.isMockCompat(): Boolean =
    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) isMock else isFromMockProvider

/**
 * Status deteksi lokasi palsu selama aplikasi berjalan. Bila terdeteksi, [LocationGate]
 * menutup aplikasi dengan layar peringatan dan server mengakhiri sesi personel.
 */
object MockLocation {
    private val _detected = MutableStateFlow(false)
    val detected: StateFlow<Boolean> = _detected.asStateFlow()

    fun report(location: Location) {
        if (location.isMockCompat()) _detected.value = true
    }

    fun markDetected() {
        _detected.value = true
    }

    /** Periksa ulang dengan titik lokasi baru; status dibersihkan bila titik asli. */
    suspend fun recheck(context: Context): Boolean {
        val loc = LoginLocationProvider.currentLocation(context) ?: return _detected.value
        _detected.value = loc.isMockCompat()
        return _detected.value
    }
}

/** Mengambil satu titik lokasi segar (mis. saat login) beserta penanda lokasi palsu. */
object LoginLocationProvider {

    /** Lokasi untuk dikirim saat login; koordinat null bila GPS mati / izin belum diberikan. */
    suspend fun capture(context: Context, timeoutMs: Long = 12_000): LoginLocation {
        val gpsOn = LocationAccess.isLocationEnabled(context)
        if (!gpsOn || !LocationAccess.hasPrecise(context)) {
            return LoginLocation(gpsEnabled = gpsOn)
        }
        val loc = currentLocation(context, timeoutMs)
        return LoginLocation(
            latitude = loc?.latitude,
            longitude = loc?.longitude,
            accuracy = loc?.takeIf { it.hasAccuracy() }?.accuracy,
            isMock = loc?.isMockCompat() ?: false,
            gpsEnabled = true,
        )
    }

    @SuppressLint("MissingPermission")
    suspend fun currentLocation(context: Context, timeoutMs: Long = 12_000): Location? {
        if (!LocationAccess.hasPrecise(context)) return null
        val lm = context.getSystemService(Context.LOCATION_SERVICE) as? LocationManager ?: return null
        val providers = listOf(LocationManager.GPS_PROVIDER, LocationManager.NETWORK_PROVIDER)
            .filter { runCatching { lm.isProviderEnabled(it) }.getOrDefault(false) }
        if (providers.isEmpty()) return null

        val fresh = withTimeoutOrNull(timeoutMs) {
            suspendCancellableCoroutine<Location?> { cont ->
                val holder = arrayOfNulls<LocationListener>(1)
                val listener = LocationListener { location ->
                    holder[0]?.let { l -> runCatching { lm.removeUpdates(l) } }
                    if (cont.isActive) cont.resume(location)
                }
                holder[0] = listener
                providers.forEach { p ->
                    runCatching { lm.requestLocationUpdates(p, 0L, 0f, listener, Looper.getMainLooper()) }
                }
                cont.invokeOnCancellation { runCatching { lm.removeUpdates(listener) } }
            }
        }
        if (fresh != null) return fresh

        // Fallback: posisi terakhir yang masih segar (≤ 10 menit).
        return providers
            .mapNotNull { runCatching { lm.getLastKnownLocation(it) }.getOrNull() }
            .filter { System.currentTimeMillis() - it.time < 10 * 60 * 1000 }
            .maxByOrNull { it.time }
    }
}
