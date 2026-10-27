package id.my.sisinden.app.core.notification

/** Apakah aplikasi sedang tampil di layar (diatur MainActivity onResume/onPause). */
object AppVisibility {
    @Volatile
    var appForeground: Boolean = false
}
