package id.my.sisinden.app

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import id.my.sisinden.app.core.ui.theme.SindenTheme
import id.my.sisinden.app.ui.navigation.SindenApp

class MainActivity : ComponentActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()

        val app = application as SindenApplication
        val repository = app.repository

        if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.TIRAMISU) {
            if (checkSelfPermission(android.Manifest.permission.POST_NOTIFICATIONS) != android.content.pm.PackageManager.PERMISSION_GRANTED) {
                requestPermissions(arrayOf(android.Manifest.permission.POST_NOTIFICATIONS), 101)
            }
        }

        setContent {
            val isDarkModePref by repository.isDarkModeFlow.collectAsState(initial = null)
            val useDarkTheme = isDarkModePref ?: isSystemInDarkTheme()

            SindenTheme(darkTheme = useDarkTheme) {
                SindenApp(repository = repository)
            }
        }
    }
}
