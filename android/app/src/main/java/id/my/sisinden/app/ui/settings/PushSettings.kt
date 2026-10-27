package id.my.sisinden.app.ui.settings

import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.width
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.NotificationsActive
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import id.my.sisinden.app.core.network.ApiResult
import id.my.sisinden.app.core.notification.PushManager
import id.my.sisinden.app.core.ui.components.SindenCard
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.data.repository.SindenRepository
import kotlinx.coroutines.launch

/**
 * Status notifikasi push (Firebase) & uji kirim ke HP ini. Firebase diatur Admin
 * di Pengaturan web (google-services.json + service account), tanpa build ulang APK.
 */
@Composable
internal fun PushCard(repository: SindenRepository) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    var ready by remember { mutableStateOf(PushManager.isReady(context)) }
    var busy by remember { mutableStateOf(false) }
    var result by remember { mutableStateOf<Pair<Boolean, String>?>(null) }

    SindenCard(Modifier.fillMaxWidth()) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Icon(Icons.Outlined.NotificationsActive, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
            Spacer(Modifier.width(Spacing.md))
            Text("Notifikasi push", style = MaterialTheme.typography.titleSmall, modifier = Modifier.weight(1f))
            Text(
                if (ready) "Aktif" else "Belum aktif",
                style = MaterialTheme.typography.labelLarge,
                color = if (ready) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        Spacer(Modifier.height(Spacing.sm))
        Text(
            if (ready) "Warta dinas & Chat Dinas tetap muncul walau aplikasi ditutup."
            else "Admin belum mengatur Firebase di Pengaturan web, atau HP belum terhubung ke server.",
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        result?.let { (ok, msg) ->
            Spacer(Modifier.height(Spacing.xs))
            Text(msg, style = MaterialTheme.typography.bodySmall, color = if (ok) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.error)
        }
        Spacer(Modifier.height(Spacing.sm))
        OutlinedButton(
            enabled = !busy,
            modifier = Modifier.fillMaxWidth(),
            onClick = {
                busy = true
                result = null
                scope.launch {
                    // Segarkan konfigurasi Firebase dari server & daftarkan ulang token HP ini.
                    (repository.getConfig() as? ApiResult.Success)?.data?.push?.let { PushManager.configure(context, it) }
                    PushManager.register(context, repository)
                    ready = PushManager.isReady(context)
                    result = when (val res = repository.pushTest()) {
                        is ApiResult.Success -> (res.data.status == "success") to (res.data.message ?: "Permintaan uji dikirim.")
                        is ApiResult.Failure -> false to res.error.message
                    }
                    busy = false
                }
            },
        ) { Text(if (busy) "Mengirim…" else "Uji notifikasi push") }
    }
}
