package id.my.sisinden.app.ui.settings

import android.Manifest
import android.annotation.SuppressLint
import android.app.NotificationManager
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import android.os.PowerManager
import android.provider.Settings
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.CheckCircle
import androidx.compose.material.icons.outlined.ErrorOutline
import androidx.compose.material.icons.outlined.NotificationsActive
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import androidx.lifecycle.compose.LifecycleResumeEffect
import id.my.sisinden.app.core.network.ApiResult
import id.my.sisinden.app.core.notification.NotificationHelper
import id.my.sisinden.app.core.notification.PushManager
import id.my.sisinden.app.core.security.expectExternalActivity
import id.my.sisinden.app.core.ui.components.SindenCard
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.data.repository.SindenRepository
import kotlinx.coroutines.launch

private data class NotifStatus(
    val allowed: Boolean,
    val fullScreen: Boolean,
    val batteryFree: Boolean,
    val firebaseReady: Boolean,
)

private fun readStatus(context: Context): NotifStatus {
    val nm = context.getSystemService(NotificationManager::class.java)
    val fullScreen = if (Build.VERSION.SDK_INT >= 34) nm.canUseFullScreenIntent() else true
    val pm = context.getSystemService(PowerManager::class.java)
    return NotifStatus(
        allowed = NotificationManagerCompat.from(context).areNotificationsEnabled(),
        fullScreen = fullScreen,
        batteryFree = pm?.isIgnoringBatteryOptimizations(context.packageName) == true,
        firebaseReady = PushManager.isReady(context),
    )
}

/**
 * Kartu "Notifikasi": memeriksa semua syarat agar warta, chat, dan panggilan
 * muncul & berdering di bilah notifikasi walau aplikasi ditutup, plus tombol uji.
 */
@SuppressLint("BatteryLife")
@Composable
internal fun NotificationCard(repository: SindenRepository, onMessage: (String) -> Unit) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    var refresh by remember { mutableIntStateOf(0) }
    var status by remember { mutableStateOf(readStatus(context)) }
    var serverPush by remember { mutableStateOf<Boolean?>(null) }
    var testing by remember { mutableStateOf(false) }

    LifecycleResumeEffect(refresh) {
        status = readStatus(context)
        onPauseOrDispose { }
    }
    LaunchedEffect(Unit) {
        val res = repository.getConfig()
        if (res is ApiResult.Success) {
            serverPush = res.data.pushEnabled == true
            PushManager.configure(context, res.data.push)
            PushManager.register(context, repository)
            status = readStatus(context)
        }
    }

    val permission = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) { refresh++ }

    fun open(intent: Intent) {
        context.expectExternalActivity()
        try {
            context.startActivity(intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
        } catch (_: Exception) {
            context.startActivity(Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS, Uri.parse("package:${context.packageName}")).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
        }
    }

    SindenCard(Modifier.fillMaxWidth()) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Icon(Icons.Outlined.NotificationsActive, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
            Spacer(Modifier.width(Spacing.md))
            Text("Notifikasi & panggilan", style = MaterialTheme.typography.titleSmall)
        }
        Spacer(Modifier.height(Spacing.sm))
        Column(verticalArrangement = Arrangement.spacedBy(Spacing.xs)) {
            CheckRow(
                ok = status.allowed,
                title = "Izin notifikasi",
                detail = if (status.allowed) "Diizinkan" else "Ditolak — notifikasi tidak akan muncul",
                action = if (status.allowed) null else "Izinkan",
            ) {
                if (Build.VERSION.SDK_INT >= 33 &&
                    ContextCompat.checkSelfPermission(context, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED
                ) {
                    permission.launch(Manifest.permission.POST_NOTIFICATIONS)
                } else {
                    open(Intent(Settings.ACTION_APP_NOTIFICATION_SETTINGS).putExtra(Settings.EXTRA_APP_PACKAGE, context.packageName))
                }
            }
            CheckRow(
                ok = status.fullScreen,
                title = "Panggilan layar penuh",
                detail = if (status.fullScreen) "Panggilan masuk tampil di layar kunci" else "Belum diizinkan — panggilan hanya berupa notifikasi biasa",
                action = if (status.fullScreen) null else "Izinkan",
            ) {
                if (Build.VERSION.SDK_INT >= 34) {
                    open(Intent(Settings.ACTION_MANAGE_APP_USE_FULL_SCREEN_INTENT, Uri.parse("package:${context.packageName}")))
                }
            }
            CheckRow(
                ok = serverPush == true && status.firebaseReady,
                title = "Notifikasi push (aplikasi ditutup)",
                detail = when {
                    serverPush == null -> "Memeriksa server…"
                    serverPush == false -> "Belum aktif: Admin perlu mengatur Firebase di Pengaturan web"
                    !status.firebaseReady -> "Server aktif, HP belum terhubung — buka ulang aplikasi"
                    else -> "Aktif — warta, chat & panggilan tetap muncul walau aplikasi ditutup"
                },
                action = null,
            ) {}
            CheckRow(
                ok = status.batteryFree,
                title = "Tanpa batasan baterai",
                detail = if (status.batteryFree) "Aplikasi tidak dibatasi penghemat baterai" else "Disarankan agar notifikasi tidak tertunda",
                action = if (status.batteryFree) null else "Atur",
            ) {
                open(Intent(Settings.ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS, Uri.parse("package:${context.packageName}")))
            }
        }
        Spacer(Modifier.height(Spacing.sm))
        OutlinedButton(
            enabled = !testing,
            modifier = Modifier.fillMaxWidth(),
            onClick = {
                testing = true
                scope.launch {
                    when (val res = repository.pushTest()) {
                        is ApiResult.Success -> {
                            val body = res.data
                            if (body.status == "success") {
                                onMessage(body.message ?: "Notifikasi uji dikirim.")
                            } else {
                                // Push belum siap: uji notifikasi lokal agar bilah notifikasi tetap terverifikasi.
                                NotificationHelper.showNotification(context, 999_001, "Uji notifikasi Sinden 2 (lokal)", body.message ?: "Notifikasi lokal berfungsi.")
                                onMessage(body.message ?: "Push belum siap.")
                            }
                        }
                        is ApiResult.Failure -> onMessage(res.error.message)
                    }
                    testing = false
                }
            },
        ) { Text(if (testing) "Mengirim…" else "Kirim notifikasi uji") }
    }
}

@Composable
private fun CheckRow(ok: Boolean, title: String, detail: String, action: String?, onAction: () -> Unit) {
    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
        Icon(
            if (ok) Icons.Outlined.CheckCircle else Icons.Outlined.ErrorOutline,
            contentDescription = if (ok) "OK" else "Perlu tindakan",
            tint = if (ok) Color(0xFF16A34A) else MaterialTheme.colorScheme.error,
            modifier = Modifier.size(22.dp),
        )
        Spacer(Modifier.width(Spacing.sm))
        Column(Modifier.weight(1f)) {
            Text(title, style = MaterialTheme.typography.bodyMedium)
            Text(detail, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
        if (action != null) TextButton(onClick = onAction) { Text(action) }
    }
}
