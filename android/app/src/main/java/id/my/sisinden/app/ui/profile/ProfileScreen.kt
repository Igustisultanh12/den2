package id.my.sisinden.app.ui.profile

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ExitToApp
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowRight
import androidx.compose.material.icons.outlined.Notifications
import androidx.compose.material.icons.outlined.Settings
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import id.my.sisinden.app.BuildConfig
import id.my.sisinden.app.core.data.SessionState
import id.my.sisinden.app.core.security.Perm
import id.my.sisinden.app.core.ui.components.ConfirmDialog
import id.my.sisinden.app.core.ui.components.IconBadge
import id.my.sisinden.app.core.ui.components.InfoRow
import id.my.sisinden.app.core.ui.components.InitialsAvatar
import id.my.sisinden.app.core.ui.components.ListRowCard
import id.my.sisinden.app.core.ui.components.SindenCard
import id.my.sisinden.app.core.ui.components.SindenScaffold
import id.my.sisinden.app.core.ui.components.StatusChip
import id.my.sisinden.app.core.ui.components.Tone
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.ui.navigation.Feature

@OptIn(ExperimentalLayoutApi::class)
@Composable
fun ProfileScreen(
    repository: SindenRepository,
    session: SessionState.LoggedIn,
    onOpenSettings: () -> Unit,
    onOpenNotifications: () -> Unit,
) {
    val user = session.user
    var confirmLogout by rememberSaveable { mutableStateOf(false) }
    var loggingOut by rememberSaveable { mutableStateOf(false) }

    SindenScaffold(title = "Profil") { padding ->
        Column(
            Modifier
                .fillMaxSize()
                .padding(padding)
                .verticalScroll(rememberScrollState())
                .padding(horizontal = Spacing.screen),
            verticalArrangement = Arrangement.spacedBy(Spacing.md),
        ) {
            SindenCard(Modifier.fillMaxWidth()) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    InitialsAvatar(user.initials, size = 64.dp, highlight = true)
                    Spacer(Modifier.width(Spacing.lg))
                    Column(Modifier.weight(1f)) {
                        Text(user.displayName, style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
                        Text(user.subtitle, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        Spacer(Modifier.height(Spacing.xs))
                        StatusChip(user.roleLabel, Tone.INFO)
                    }
                }
                Spacer(Modifier.height(Spacing.lg))
                InfoRow("NRP", user.nrp)
                InfoRow("Pangkat", user.pangkat)
                InfoRow("Korps", user.korps)
                InfoRow("Jabatan", user.jabatan)
                InfoRow("Email", user.email)
                InfoRow("Telepon", user.phone)
            }

            SindenCard(Modifier.fillMaxWidth()) {
                Text("Hak akses Anda", style = MaterialTheme.typography.titleSmall)
                Text(
                    "Diatur oleh Admin Sinden 2 dan diperbarui setiap kali aplikasi tersinkron dengan server.",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                Spacer(Modifier.height(Spacing.md))
                FlowRow(horizontalArrangement = Arrangement.spacedBy(Spacing.sm), verticalArrangement = Arrangement.spacedBy(Spacing.sm)) {
                    Feature.permitted(session.permissions).forEach { StatusChip(it.title, Tone.NEUTRAL) }
                }
                if (session.isStale) {
                    Spacer(Modifier.height(Spacing.sm))
                    Text(
                        "Belum tersinkron dengan server — menampilkan data tersimpan.",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.error,
                    )
                }
            }

            if (session.permissions.has(Perm.NOTIFICATIONS_VIEW)) {
                ListRowCard(
                    title = "Notifikasi",
                    subtitle = "Warta & instruksi kedinasan",
                    leading = { IconBadge(Icons.Outlined.Notifications, MaterialTheme.colorScheme.primary) },
                    trailing = { Icon(Icons.AutoMirrored.Filled.KeyboardArrowRight, contentDescription = null) },
                    onClick = onOpenNotifications,
                )
            }
            ListRowCard(
                title = "Pengaturan",
                subtitle = "Tema, server, notifikasi",
                leading = { IconBadge(Icons.Outlined.Settings, MaterialTheme.colorScheme.primary) },
                trailing = { Icon(Icons.AutoMirrored.Filled.KeyboardArrowRight, contentDescription = null) },
                onClick = onOpenSettings,
            )
            ListRowCard(
                title = "Keluar",
                subtitle = "Akhiri sesi di perangkat ini",
                leading = { IconBadge(Icons.AutoMirrored.Filled.ExitToApp, MaterialTheme.colorScheme.error) },
                onClick = { confirmLogout = true },
            )
            Text(
                "Sinden 2 Android v${BuildConfig.VERSION_NAME} (${BuildConfig.VERSION_CODE})",
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.align(Alignment.CenterHorizontally).padding(vertical = Spacing.lg),
            )
        }
    }

    if (confirmLogout) {
        ConfirmDialog(
            title = "Keluar dari Sinden 2?",
            message = "Token sesi di perangkat ini akan dicabut. Anda perlu masuk kembali untuk mengakses data.",
            confirmLabel = "Keluar",
            destructive = true,
            loading = loggingOut,
            onDismiss = { confirmLogout = false },
            onConfirm = {
                loggingOut = true
                repository.appScopeLaunch { repository.logout() }
            },
        )
    }
}
