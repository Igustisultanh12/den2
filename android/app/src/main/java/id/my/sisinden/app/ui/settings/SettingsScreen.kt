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
import androidx.compose.material.icons.outlined.FormatSize
import androidx.compose.material.icons.outlined.LightMode
import androidx.compose.material.icons.outlined.SettingsBrightness
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.RadioButton
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.semantics.Role
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import id.my.sisinden.app.BuildConfig
import id.my.sisinden.app.core.notification.NotificationHelper
import id.my.sisinden.app.core.ui.components.SindenCard
import id.my.sisinden.app.core.ui.components.SindenScaffold
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.core.ui.theme.TextScale
import id.my.sisinden.app.core.ui.theme.ThemeMode
import id.my.sisinden.app.data.repository.SindenRepository
import kotlinx.coroutines.launch

@Composable
fun SettingsScreen(repository: SindenRepository, onBack: () -> Unit) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val theme by repository.themeModeFlow.collectAsStateWithLifecycle(initialValue = ThemeMode.SYSTEM)
    val hideBalance by repository.hideBalanceFlow.collectAsStateWithLifecycle(initialValue = false)
    val textScale by repository.textScaleFlow.collectAsStateWithLifecycle(initialValue = TextScale.NORMAL)
    var sound by rememberSaveable { mutableStateOf(NotificationHelper.isNotificationSoundEnabled(context)) }
    val snackbar = remember { SnackbarHostState() }
    val userId = repository.session.user?.id ?: 0

    SindenScaffold(title = "Pengaturan", onBack = onBack, snackbarHostState = snackbar) { padding ->
        Column(
            Modifier.fillMaxSize().padding(padding).verticalScroll(rememberScrollState()).padding(horizontal = Spacing.screen),
            verticalArrangement = Arrangement.spacedBy(Spacing.md),
        ) {
            NotificationCard(repository = repository, onMessage = { msg -> scope.launch { snackbar.showSnackbar(msg) } })

            UpdateCard()

            SecurityCard(
                appLock = repository.appLock,
                userId = userId,
                onMessage = { msg -> scope.launch { snackbar.showSnackbar(msg) } },
            )

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
                Text("Ukuran teks", style = MaterialTheme.typography.titleSmall)
                Text(
                    "Perbesar huruf di seluruh aplikasi agar lebih mudah dibaca.",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                Spacer(Modifier.height(Spacing.sm))
                Column(Modifier.selectableGroup()) {
                    TextScale.entries.forEach { scale ->
                        Row(
                            Modifier
                                .fillMaxWidth()
                                .selectable(
                                    selected = textScale == scale,
                                    onClick = { scope.launch { repository.setTextScale(scale) } },
                                    role = Role.RadioButton,
                                )
                                .padding(vertical = Spacing.sm),
                            verticalAlignment = Alignment.CenterVertically,
                        ) {
                            Icon(Icons.Outlined.FormatSize, contentDescription = null, tint = MaterialTheme.colorScheme.onSurfaceVariant)
                            Spacer(Modifier.width(Spacing.md))
                            Text(
                                scale.label,
                                style = MaterialTheme.typography.bodyLarge,
                                fontSize = MaterialTheme.typography.bodyLarge.fontSize * (scale.factor / textScale.factor),
                                modifier = Modifier.weight(1f),
                            )
                            RadioButton(selected = textScale == scale, onClick = null)
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

            Text(
                "Sinden 2 Android v${BuildConfig.VERSION_NAME} · Denintel Koarmada 2",
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.align(Alignment.CenterHorizontally).padding(top = Spacing.lg),
            )
            Text(
                androidx.compose.ui.res.stringResource(id.my.sisinden.app.R.string.app_copyright, java.time.Year.now().value),
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                textAlign = androidx.compose.ui.text.style.TextAlign.Center,
                modifier = Modifier.align(Alignment.CenterHorizontally).padding(start = Spacing.lg, end = Spacing.lg, bottom = Spacing.lg),
            )
        }
    }
}

@Composable
internal fun SwitchRow(title: String, subtitle: String, checked: Boolean, onChange: (Boolean) -> Unit) {
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
