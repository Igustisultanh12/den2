package id.my.sisinden.app.core.tracking

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.location.LocationManager
import android.os.Build
import androidx.core.content.ContextCompat
import androidx.core.location.LocationManagerCompat

/** Status kesiapan lokasi yang wajib dipenuhi sebelum aplikasi dapat dipakai. */
data class LocationReadiness(
    val preciseGranted: Boolean,
    val backgroundGranted: Boolean,
    val locationEnabled: Boolean,
) {
    val ready: Boolean get() = preciseGranted && backgroundGranted && locationEnabled
}

object LocationAccess {

    fun hasPrecise(context: Context): Boolean =
        ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED

    /** Android 9 ke bawah tidak memiliki izin latar terpisah. */
    fun hasBackground(context: Context): Boolean =
        Build.VERSION.SDK_INT < Build.VERSION_CODES.Q ||
            ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_BACKGROUND_LOCATION) == PackageManager.PERMISSION_GRANTED

    fun isLocationEnabled(context: Context): Boolean {
        val lm = context.getSystemService(Context.LOCATION_SERVICE) as? LocationManager ?: return false
        return LocationManagerCompat.isLocationEnabled(lm)
    }

    fun readiness(context: Context) = LocationReadiness(
        preciseGranted = hasPrecise(context),
        backgroundGranted = hasBackground(context),
        locationEnabled = isLocationEnabled(context),
    )
}
