package id.my.sisinden.app.ui.settings

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.selection.selectableGroup
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.DarkMode
import androidx.compose.material.icons.outlined.Dns
import androidx.compose.material.icons.outlined.LightMode
import androidx.compose.material.icons.outlined.SettingsBrightness
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.RadioButton
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.semantics.Role
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import id.my.sisinden.app.BuildConfig
import id.my.sisinden.app.core.data.SessionStore
import id.my.sisinden.app.core.notification.NotificationHelper
import id.my.sisinden.app.core.ui.components.SindenCard
import id.my.sisinden.app.core.ui.components.SindenScaffold
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.core.ui.theme.ThemeMode
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.ui.auth.ServerDialog
import kotlinx.coroutines.launch

@Composable
fun SettingsScreen(repository: SindenRepository, onBack: () -> Unit) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val theme by repository.themeModeFlow.collectAsStateWithLifecycle(initialValue = ThemeMode.SYSTEM)
    val hideBalance by repository.hideBalanceFlow.collectAsStateWithLifecycle(initialValue = false)
    val baseUrl by repository.baseUrlFlow.collectAsStateWithLifecycle(initialValue = SessionStore.DEFAULT_BASE_URL)
    var sound by rememberSaveable { mutableStateOf(NotificationHelper.isNotificationSoundEnabled(context)) }
    var showServer by rememberSaveable { mutableStateOf(false) }

    SindenScaffold(title = "Pengaturan", onBack = onBack) { padding ->
        Column(
            Modifier.fillMaxSize().padding(padding).verticalScroll(rememberScrollState()).padding(horizontal = Spacing.screen),
            verticalArrangement = Arrangement.spacedBy(Spacing.md),
        ) {
            SindenCard(Modifier.fillMaxWidth()) {
                Text("Tampilan", style = MaterialTheme.typography.titleSmall)
                Spacer(Modifier.height(Spacing.sm))
                Column(Modifier.selectableGroup()) {
                    listOf(
                        Triple(ThemeMode.SYSTEM, "Ikuti sistem", Icons.Outlined.SettingsBrightness),
                        Triple(ThemeMode.LIGHT, "Terang", Icons.Outlined.LightMode),
                        Triple(ThemeMode.DARK, "Gelap", Icons.Outlined.DarkMode),
                    ).forEach { (mode, label, icon) ->
                        Row(
                            Modifier
                                .fillMaxWidth()
                                .selectable(
                                    selected = theme == mode,
                                    onClick = { scope.launch { repository.setThemeMode(mode) } },
                                    role = Role.RadioButton,
                                )
                                .padding(vertical = Spacing.sm),
                            verticalAlignment = Alignment.CenterVertically,
                        ) {
                            Icon(icon, contentDescription = null, tint = MaterialTheme.colorScheme.onSurfaceVariant)
                            Spacer(Modifier.width(Spacing.md))
                            Text(label, style = MaterialTheme.typography.bodyLarge, modifier = Modifier.weight(1f))
                            RadioButton(selected = theme == mode, onClick = null)
                        }
                    }
                }
            }

            SindenCard(Modifier.fillMaxWidth()) {
                SwitchRow(
                    title = "Sembunyikan saldo",
                    subtitle = "Samarkan nominal kas di beranda",
                    checked = hideBalance,
                    onChange = { scope.launch { repository.setHideBalance(it) } },
                )
                SwitchRow(
                    title = "Suara notifikasi",
                    subtitle = "Bunyikan nada saat ada warta dinas baru",
                    checked = sound,
                    onChange = {
                        sound = it
                        NotificationHelper.setNotificationSoundEnabled(context, it)
                    },
                )
            }

            SindenCard(Modifier.fillMaxWidth(), onClick = { showServer = true }) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Outlined.Dns, contentDescription = null, tint = MaterialTheme.colorScheme.onSurfaceVariant)
                    Spacer(Modifier.width(Spacing.md))
                    Column(Modifier.weight(1f)) {
                        Text("Alamat server", style = MaterialTheme.typography.bodyLarge)
                        Text(baseUrl, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                    TextButton(onClick = { showServer = true }) { Text("Ubah") }
                }
            }

            Text(
                "SISINDEN Android v${BuildConfig.VERSION_NAME} · Denintel Kodaeral V",
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.align(Alignment.CenterHorizontally).padding(vertical = Spacing.lg),
            )
        }
    }

    if (showServer) {
        ServerDialog(
            current = baseUrl,
            onSave = { input ->
                val normalized = SessionStore.normalizeBaseUrl(input)
                if (normalized == null) {
                    "Alamat server tidak valid. Gunakan alamat HTTPS."
                } else {
                    // Mengganti server mengakhiri sesi: token server lama tidak berlaku di server baru.
                    // Cabut token di server LAMA terlebih dahulu agar token tidak pernah terkirim ke server baru.
                    val changed = normalized != baseUrl
                    repository.appScopeLaunch {
                        if (changed) repository.logout()
                        repository.setBaseUrl(normalized)
                    }
                    null
                }
            },
            onDismiss = { showServer = false },
        )
    }
}

@Composable
private fun SwitchRow(title: String, subtitle: String, checked: Boolean, onChange: (Boolean) -> Unit) {
    Row(
        Modifier
            .fillMaxWidth()
            .selectable(selected = checked, onClick = { onChange(!checked) }, role = Role.Switch)
            .padding(vertical = Spacing.sm),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(Modifier.weight(1f)) {
            Text(title, style = MaterialTheme.typography.bodyLarge)
            Text(subtitle, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
        Switch(checked = checked, onCheckedChange = null)
    }
}
