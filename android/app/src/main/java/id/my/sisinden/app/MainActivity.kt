package id.my.sisinden.app

import android.Manifest
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import android.os.Bundle
import android.view.WindowManager
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import id.my.sisinden.app.core.ui.theme.SindenTheme
import id.my.sisinden.app.core.ui.theme.TextScale
import id.my.sisinden.app.core.ui.theme.ThemeMode
import id.my.sisinden.app.ui.navigation.SindenRoot

class MainActivity : ComponentActivity() {

    /** Intent deep link yang datang saat aplikasi sudah berjalan (launchMode singleTask). */
    private val pendingIntent = mutableStateOf<Intent?>(null)

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        // Cegah screenshot & rekam layar; isi aplikasi juga disamarkan di daftar aplikasi terbaru.
        window.setFlags(WindowManager.LayoutParams.FLAG_SECURE, WindowManager.LayoutParams.FLAG_SECURE)
        enableEdgeToEdge()

        val app = application as SindenApplication

        // Deep link peluncuran (mis. dari notifikasi) ditangani sekali oleh MainNavigation
        // setelah PIN dibuka, bukan otomatis oleh NavController setiap kali layar dibuat ulang.
        if (savedInstanceState == null && intent?.data != null) {
            pendingIntent.value = Intent(intent)
            intent = Intent(intent).setData(null)
        }

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU &&
            checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED
        ) {
            requestPermissions(arrayOf(Manifest.permission.POST_NOTIFICATIONS), 101)
        }

        setContent {
            val themeMode by app.repository.themeModeFlow.collectAsStateWithLifecycle(initialValue = ThemeMode.SYSTEM)
            val textScale by app.repository.textScaleFlow.collectAsStateWithLifecycle(initialValue = TextScale.NORMAL)
            val newIntent by pendingIntent
            SindenTheme(themeMode = themeMode, textScale = textScale) {
                SindenRoot(
                    repository = app.repository,
                    newIntent = newIntent,
                    onIntentHandled = { pendingIntent.value = null },
                )
            }
        }
    }

    override fun onUserInteraction() {
        super.onUserInteraction()
        (application as SindenApplication).appLock.touch()
    }

    override fun onStart() {
        super.onStart()
        (application as SindenApplication).appLock.onForeground()
        id.my.sisinden.app.core.notification.CommsState.appForeground = true
    }

    override fun onStop() {
        super.onStop()
        // Rotasi layar bukan "keluar aplikasi".
        if (!isChangingConfigurations) (application as SindenApplication).appLock.onBackground()
        id.my.sisinden.app.core.notification.CommsState.appForeground = false
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        pendingIntent.value = intent
    }
}
