package id.my.sisinden.app.ui.tracking

import android.Manifest
import android.app.Activity
import android.content.Context
import android.content.ContextWrapper
import android.content.Intent
import android.net.Uri
import android.os.Build
import android.provider.Settings
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.safeDrawingPadding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.CheckCircle
import androidx.compose.material.icons.outlined.GppBad
import androidx.compose.material.icons.outlined.LocationOn
import androidx.compose.material.icons.outlined.RadioButtonUnchecked
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import id.my.sisinden.app.core.tracking.MockLocation
import kotlinx.coroutines.launch
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.core.app.ActivityCompat
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.LifecycleEventObserver
import androidx.lifecycle.compose.LocalLifecycleOwner
import id.my.sisinden.app.core.tracking.LocationAccess
import id.my.sisinden.app.core.tracking.LocationReadiness
import id.my.sisinden.app.core.tracking.LocationTrackingService
import id.my.sisinden.app.core.ui.components.IconBadge
import id.my.sisinden.app.core.ui.components.PrimaryButton
import id.my.sisinden.app.core.ui.components.SindenCard
import id.my.sisinden.app.core.ui.theme.Spacing

/**
 * Gerbang wajib lokasi: aplikasi utama baru tampil setelah izin lokasi presisi,
 * izin lokasi "sepanjang waktu", dan GPS perangkat aktif. Setelah terpenuhi,
 * pelacakan realtime dimulai (dihentikan saat logout oleh SindenRoot).
 *
 * Juga dipasang di layar login ([startTracking] = false): personel tidak dapat
 * login sebelum GPS aktif. Bila aplikasi lokasi palsu (Fake GPS) terdeteksi,
 * seluruh aplikasi tertutup layar peringatan.
 */
@Composable
fun LocationGate(startTracking: Boolean = true, content: @Composable () -> Unit) {
    val context = LocalContext.current
    var readiness by remember { mutableStateOf(LocationAccess.readiness(context)) }
    val recheck = { readiness = LocationAccess.readiness(context) }
    val mockDetected by MockLocation.detected.collectAsStateWithLifecycle()

    // Periksa ulang setiap kali pengguna kembali dari dialog izin / Pengaturan.
    val lifecycleOwner = LocalLifecycleOwner.current
    DisposableEffect(lifecycleOwner) {
        val observer = LifecycleEventObserver { _, event -> if (event == Lifecycle.Event.ON_RESUME) recheck() }
        lifecycleOwner.lifecycle.addObserver(observer)
        onDispose { lifecycleOwner.lifecycle.removeObserver(observer) }
    }

    LaunchedEffect(readiness.ready, startTracking) {
        if (readiness.ready && startTracking) LocationTrackingService.start(context.applicationContext)
    }

    when {
        mockDetected -> MockLocationScreen()
        readiness.ready -> content()
        else -> LocationRequiredScreen(readiness, onChanged = recheck)
    }
}

/** Layar blokir saat aplikasi lokasi palsu (Fake GPS / mock location) terdeteksi. */
@Composable
private fun MockLocationScreen() {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    var checking by remember { mutableStateOf(false) }
    var stillMock by remember { mutableStateOf(false) }

    Surface(Modifier.fillMaxSize(), color = MaterialTheme.colorScheme.background) {
        Column(
            Modifier
                .fillMaxSize()
                .safeDrawingPadding()
                .verticalScroll(rememberScrollState())
                .padding(Spacing.screen),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center,
        ) {
            IconBadge(Icons.Outlined.GppBad, MaterialTheme.colorScheme.error, size = 72.dp, iconSize = 36.dp)
            Spacer(Modifier.height(Spacing.lg))
            Text("Lokasi palsu terdeteksi", style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(Spacing.sm))
            Text(
                "Sinden 2 mendeteksi aplikasi lokasi palsu (Fake GPS / mock location) di perangkat ini. " +
                    "Demi keamanan sistem, aplikasi tidak dapat dipakai dan kejadian ini telah dilaporkan ke Admin.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                textAlign = TextAlign.Center,
            )
            Spacer(Modifier.height(Spacing.lg))
            SindenCard(Modifier.fillMaxWidth()) {
                Text("Cara memulihkan:", style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.Bold)
                Spacer(Modifier.height(Spacing.xs))
                Text(
                    "1. Hapus / matikan aplikasi Fake GPS.\n" +
                        "2. Buka Pengaturan → Opsi Pengembang → \"Pilih aplikasi lokasi tiruan\" → Tidak ada.\n" +
                        "3. Pastikan GPS aktif, lalu tekan Periksa ulang.",
                    style = MaterialTheme.typography.bodyMedium,
                )
            }
            if (stillMock) {
                Spacer(Modifier.height(Spacing.md))
                Hint("Lokasi palsu masih terdeteksi. Pastikan langkah di atas sudah dilakukan.")
            }
            Spacer(Modifier.height(Spacing.lg))
            PrimaryButton(
                "Periksa ulang",
                loading = checking,
                onClick = {
                    checking = true
                    scope.launch {
                        stillMock = MockLocation.recheck(context.applicationContext)
                        checking = false
                    }
                },
            )
            TextButton(onClick = {
                runCatching { context.startActivity(Intent(Settings.ACTION_APPLICATION_DEVELOPMENT_SETTINGS).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)) }
            }) { Text("Buka Opsi Pengembang") }
        }
    }
}

@Composable
private fun LocationRequiredScreen(readiness: LocationReadiness, onChanged: () -> Unit) {
    val context = LocalContext.current
    var preciseAsked by remember { mutableStateOf(false) }

    val preciseLauncher = rememberLauncherForActivityResult(ActivityResultContracts.RequestMultiplePermissions()) {
        preciseAsked = true
        onChanged()
    }
    val backgroundLauncher = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) { onChanged() }

    // Bila izin pernah ditolak permanen, dialog sistem tidak muncul lagi: arahkan ke Pengaturan.
    val preciseBlocked = preciseAsked && !readiness.preciseGranted &&
        context.findActivity()?.let { !ActivityCompat.shouldShowRequestPermissionRationale(it, Manifest.permission.ACCESS_FINE_LOCATION) } == true

    Surface(Modifier.fillMaxSize(), color = MaterialTheme.colorScheme.background) {
        Column(
            Modifier
                .fillMaxSize()
                .safeDrawingPadding()
                .verticalScroll(rememberScrollState())
                .padding(Spacing.screen),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center,
        ) {
            IconBadge(Icons.Outlined.LocationOn, MaterialTheme.colorScheme.primary, size = 72.dp, iconSize = 36.dp)
            Spacer(Modifier.height(Spacing.lg))
            Text("Akses lokasi wajib", style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(Spacing.sm))
            Text(
                "Sinden 2 memakai GPS untuk presensi dan pemantauan posisi personel secara realtime oleh Admin & Komandan " +
                    "selama Anda login. Lokasi dikirim berkala ke server satuan dan ditandai dengan notifikasi permanen.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                textAlign = TextAlign.Center,
            )
            Spacer(Modifier.height(Spacing.xl))

            SindenCard(Modifier.fillMaxWidth()) {
                Step("Izin lokasi presisi", readiness.preciseGranted)
                Step(
                    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) "Izinkan lokasi \"Sepanjang waktu\"" else "Izinkan lokasi di latar belakang",
                    readiness.backgroundGranted,
                )
                Step("GPS / Lokasi perangkat aktif", readiness.locationEnabled)
            }
            Spacer(Modifier.height(Spacing.lg))

            when {
                !readiness.preciseGranted && preciseBlocked -> {
                    Hint("Izin lokasi ditolak. Buka Pengaturan → Izin → Lokasi, lalu pilih \"Izinkan sepanjang waktu\" dan aktifkan \"Gunakan lokasi akurat\".")
                    PrimaryButton("Buka pengaturan aplikasi", onClick = { context.openAppSettings() })
                }
                !readiness.preciseGranted -> PrimaryButton(
                    "Izinkan akses lokasi",
                    onClick = {
                        preciseLauncher.launch(arrayOf(Manifest.permission.ACCESS_FINE_LOCATION, Manifest.permission.ACCESS_COARSE_LOCATION))
                    },
                )
                !readiness.backgroundGranted -> {
                    Hint("Pada layar berikutnya pilih \"Izinkan sepanjang waktu\" agar posisi tetap terkirim saat aplikasi di latar belakang.")
                    PrimaryButton(
                        "Izinkan sepanjang waktu",
                        onClick = {
                            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                                backgroundLauncher.launch(Manifest.permission.ACCESS_BACKGROUND_LOCATION)
                            }
                        },
                    )
                    TextButton(onClick = { context.openAppSettings() }) { Text("Buka pengaturan aplikasi") }
                }
                !readiness.locationEnabled -> PrimaryButton(
                    "Aktifkan GPS",
                    onClick = { context.startActivity(Intent(Settings.ACTION_LOCATION_SOURCE_SETTINGS)) },
                )
            }
        }
    }
}

@Composable
private fun Step(label: String, done: Boolean) {
    Row(Modifier.padding(vertical = Spacing.xs), verticalAlignment = Alignment.CenterVertically) {
        Icon(
            if (done) Icons.Outlined.CheckCircle else Icons.Outlined.RadioButtonUnchecked,
            contentDescription = if (done) "Terpenuhi" else "Belum",
            tint = if (done) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.outline,
            modifier = Modifier.size(22.dp),
        )
        Spacer(Modifier.width(Spacing.md))
        Text(label, style = MaterialTheme.typography.bodyLarge, fontWeight = if (done) FontWeight.Normal else FontWeight.SemiBold)
    }
}

@Composable
private fun Hint(text: String) {
    Text(
        text,
        style = MaterialTheme.typography.bodySmall,
        color = MaterialTheme.colorScheme.onSurfaceVariant,
        textAlign = TextAlign.Center,
        modifier = Modifier.padding(bottom = Spacing.md),
    )
}

private fun Context.openAppSettings() {
    startActivity(
        Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS, Uri.fromParts("package", packageName, null))
            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
    )
}

private tailrec fun Context.findActivity(): Activity? = when (this) {
    is Activity -> this
    is ContextWrapper -> baseContext.findActivity()
    else -> null
}
