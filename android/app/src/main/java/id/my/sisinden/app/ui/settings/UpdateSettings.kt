package id.my.sisinden.app.ui.settings

import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.SystemUpdate
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.Icon
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import id.my.sisinden.app.BuildConfig
import id.my.sisinden.app.SindenApplication
import id.my.sisinden.app.core.ui.components.SindenCard
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.core.update.AppUpdater
import id.my.sisinden.app.core.update.ReleaseInfo
import id.my.sisinden.app.core.update.UpdateState
import kotlinx.coroutines.launch

private fun sizeLabel(bytes: Long): String =
    if (bytes >= 1_048_576) "%.1f MB".format(bytes / 1_048_576.0) else if (bytes > 0) "${bytes / 1024} KB" else ""

/** Versi yang dapat diunduh dari status saat ini (tersedia / gagal / menunggu izin). */
private fun pendingInfo(state: UpdateState): ReleaseInfo? = when (state) {
    is UpdateState.Available -> state.info
    is UpdateState.NeedsInstallPermission -> state.info
    is UpdateState.Failed -> state.info
    else -> null
}

/** Kartu "Pembaruan aplikasi": versi terpasang, cek manual, unduh & pasang dari server SISINDEN. */
@Composable
internal fun UpdateCard() {
    val context = LocalContext.current
    val app = context.applicationContext as SindenApplication
    val state by AppUpdater.state.collectAsStateWithLifecycle()
    var auto by remember { mutableStateOf(AppUpdater.isAutoEnabled(context)) }
    val busy = state is UpdateState.Checking || state is UpdateState.Downloading || state is UpdateState.Installing
    val pending = pendingInfo(state)

    // Dibuka dari pemberitahuan pembaruan → langsung periksa.
    LaunchedEffect(Unit) {
        if (state is UpdateState.Idle || state is UpdateState.UpToDate) app.appScope.launch { AppUpdater.check(context.applicationContext) }
    }

    SindenCard(Modifier.fillMaxWidth()) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Icon(Icons.Outlined.SystemUpdate, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
            Spacer(Modifier.width(Spacing.md))
            Text("Pembaruan aplikasi", style = MaterialTheme.typography.titleSmall, modifier = Modifier.weight(1f))
            Text("v${BuildConfig.VERSION_NAME}", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
        Spacer(Modifier.height(Spacing.sm))
        val status = when (val s = state) {
            UpdateState.Idle -> "Pembaruan disediakan Admin melalui Pengaturan web SISINDEN."
            UpdateState.Checking -> "Memeriksa pembaruan…"
            is UpdateState.UpToDate -> "Aplikasi sudah versi terbaru (v${s.version})."
            is UpdateState.Available -> "Versi ${s.info.version} tersedia" + sizeLabel(s.info.apkSize).let { if (it.isEmpty()) "." else " ($it)." }
            is UpdateState.Downloading -> "Mengunduh v${s.version}… ${s.percent}%"
            is UpdateState.Installing -> "Memasang v${s.version}… Jika diminta, ketuk \"Perbarui\"."
            is UpdateState.NeedsInstallPermission -> "Versi ${s.info.version} sudah diunduh. Izinkan \"Instal aplikasi tidak dikenal\" untuk SISINDEN, lalu ketuk \"Pasang sekarang\"."
            is UpdateState.Failed -> s.message
        }
        Text(
            status,
            style = MaterialTheme.typography.bodySmall,
            color = if (state is UpdateState.Failed) MaterialTheme.colorScheme.error else MaterialTheme.colorScheme.onSurfaceVariant,
        )
        val notes = (state as? UpdateState.Available)?.info?.notes.orEmpty()
        if (notes.isNotBlank()) {
            Spacer(Modifier.height(Spacing.xs))
            Text(notes, style = MaterialTheme.typography.bodySmall)
        }
        (state as? UpdateState.Downloading)?.let { d ->
            Spacer(Modifier.height(Spacing.sm))
            LinearProgressIndicator(progress = { d.percent / 100f }, modifier = Modifier.fillMaxWidth())
        }
        Spacer(Modifier.height(Spacing.sm))
        if (state is UpdateState.NeedsInstallPermission) {
            OutlinedButton(onClick = {
                context.startActivity(AppUpdater.installPermissionIntent(context))
            }, modifier = Modifier.fillMaxWidth()) { Text("Izinkan pemasangan") }
        }
        if (pending != null && !busy) {
            Button(
                // Berjalan di cakupan aplikasi: unduhan tetap lanjut walau layar Pengaturan ditutup.
                onClick = { app.appScope.launch { AppUpdater.downloadAndInstall(context.applicationContext, pending) } },
                modifier = Modifier.fillMaxWidth(),
            ) { Text(if (state is UpdateState.NeedsInstallPermission) "Pasang sekarang" else "Unduh & pasang v${pending.version}") }
        }
        SwitchRow(
            title = "Perbarui otomatis",
            subtitle = "Saat Admin mengunggah versi baru, unduh & pasang otomatis di latar belakang (hanya lewat Wi-Fi)",
            checked = auto,
            onChange = {
                auto = it
                AppUpdater.setAutoEnabled(context, it)
            },
        )
        OutlinedButton(
            onClick = { app.appScope.launch { AppUpdater.check(context.applicationContext) } },
            enabled = !busy,
            modifier = Modifier.fillMaxWidth(),
        ) { Text(if (busy) "Sedang diproses…" else "Periksa pembaruan sekarang") }
    }
}

/**
 * Dialog pemberitahuan pembaruan di layar utama: muncul saat server punya
 * versi baru (sekali per versi bila dipilih "Nanti"), bisa langsung diunduh.
 */
@Composable
fun UpdatePromptHost() {
    val context = LocalContext.current
    val app = context.applicationContext as SindenApplication
    val state by AppUpdater.state.collectAsStateWithLifecycle()
    /** Versi yang sedang diunduh dari dialog ini (dialog tetap tampil untuk progres). */
    var followVersion by rememberSaveable { mutableStateOf<String?>(null) }
    var hidden by rememberSaveable { mutableStateOf(false) }

    val s = state
    val info = pendingInfo(s)
    val following = followVersion != null && (s is UpdateState.Downloading || s is UpdateState.Installing || s is UpdateState.NeedsInstallPermission || s is UpdateState.Failed)
    val prompt = s is UpdateState.Available && !AppUpdater.isPromptDismissed(context, s.info.version)
    if (hidden || (!prompt && !following)) return

    val dismiss = {
        if (s is UpdateState.Available) AppUpdater.dismissPrompt(context, s.info.version)
        if (following) hidden = true
        followVersion = null
    }
    AlertDialog(
        onDismissRequest = dismiss,
        icon = { Icon(Icons.Outlined.SystemUpdate, contentDescription = null) },
        title = {
            Text(
                when (s) {
                    is UpdateState.Downloading -> "Mengunduh pembaruan"
                    is UpdateState.Installing -> "Memasang pembaruan"
                    is UpdateState.Failed -> "Pembaruan gagal"
                    else -> "Pembaruan SISINDEN tersedia"
                },
            )
        },
        text = {
            Column(Modifier.heightIn(max = 320.dp).verticalScroll(rememberScrollState())) {
                when (s) {
                    is UpdateState.Available -> {
                        Text("Versi ${s.info.version} siap diunduh" + sizeLabel(s.info.apkSize).let { if (it.isEmpty()) "." else " ($it)." } + " Versi terpasang: ${BuildConfig.VERSION_NAME}.")
                        if (s.info.notes.isNotBlank()) {
                            Spacer(Modifier.height(Spacing.sm))
                            Text(s.info.notes, style = MaterialTheme.typography.bodySmall)
                        }
                    }
                    is UpdateState.Downloading -> {
                        Text("v${s.version} — ${s.percent}%")
                        Spacer(Modifier.height(Spacing.sm))
                        LinearProgressIndicator(progress = { s.percent / 100f }, modifier = Modifier.fillMaxWidth())
                    }
                    is UpdateState.Installing -> Text("Jika Android meminta konfirmasi, ketuk \"Perbarui\". Aplikasi akan dimulai ulang.")
                    is UpdateState.NeedsInstallPermission -> Text("Izinkan \"Instal aplikasi tidak dikenal\" untuk SISINDEN, lalu kembali dan ketuk \"Pasang sekarang\".")
                    is UpdateState.Failed -> Text(s.message, color = MaterialTheme.colorScheme.error)
                    else -> Unit
                }
            }
        },
        confirmButton = {
            when (s) {
                is UpdateState.NeedsInstallPermission -> Row {
                    TextButton(onClick = {
                                context.startActivity(AppUpdater.installPermissionIntent(context))
                    }) { Text("Izinkan") }
                    TextButton(onClick = { app.appScope.launch { AppUpdater.downloadAndInstall(context.applicationContext, s.info) } }) { Text("Pasang sekarang") }
                }
                is UpdateState.Available, is UpdateState.Failed -> if (info != null) {
                    TextButton(onClick = {
                        followVersion = info.version
                        hidden = false
                        app.appScope.launch { AppUpdater.downloadAndInstall(context.applicationContext, info) }
                    }) { Text(if (s is UpdateState.Failed) "Coba lagi" else "Unduh & pasang") }
                }
                else -> Unit
            }
        },
        dismissButton = {
            TextButton(onClick = dismiss) {
                Text(if (s is UpdateState.Downloading || s is UpdateState.Installing) "Sembunyikan" else if (s is UpdateState.Available) "Nanti" else "Tutup")
            }
        },
    )
}
