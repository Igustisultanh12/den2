package id.my.sisinden.app.ui.settings

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowRight
import androidx.compose.material.icons.outlined.Password
import androidx.compose.material.icons.outlined.Timer
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.style.TextAlign
import id.my.sisinden.app.core.security.AppLockManager
import id.my.sisinden.app.core.security.Biometric
import id.my.sisinden.app.core.security.PinRules
import id.my.sisinden.app.core.ui.components.FormSheet
import id.my.sisinden.app.core.ui.components.SindenCard
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.ui.lock.PinPad
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

/** Kartu "Keamanan": login biometrik, ubah PIN, info kunci otomatis 5 menit. */
@Composable
internal fun SecurityCard(appLock: AppLockManager, userId: Int, onMessage: (String) -> Unit) {
    val context = LocalContext.current
    val bioAvailable = remember { Biometric.isAvailable(context) }
    var bio by remember { mutableStateOf(appLock.store.biometricEnabled) }
    var changing by remember { mutableStateOf(false) }

    SindenCard(Modifier.fillMaxWidth()) {
        Text("Keamanan", style = MaterialTheme.typography.titleSmall)
        Spacer(Modifier.height(Spacing.sm))
        SwitchRow(
            title = "Login biometrik",
            subtitle = if (bioAvailable) "Buka aplikasi dengan sidik jari / Face ID" else "Belum tersedia: daftarkan sidik jari/wajah di pengaturan HP (Android 9+)",
            checked = bio && bioAvailable,
            onChange = { on ->
                if (!bioAvailable) {
                    onMessage("Daftarkan sidik jari atau wajah di pengaturan HP terlebih dahulu.")
                } else if (!on) {
                    bio = false
                    appLock.store.biometricEnabled = false
                } else {
                    Biometric.authenticate(
                        context, "Aktifkan login biometrik", "Verifikasi sidik jari atau wajah Anda",
                        onSuccess = {
                            bio = true
                            appLock.store.biometricEnabled = true
                            onMessage("Login biometrik diaktifkan.")
                        },
                        onError = onMessage,
                    )
                }
            },
        )
        Row(
            Modifier.fillMaxWidth().clickable { changing = true }.padding(vertical = Spacing.md),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Icon(Icons.Outlined.Password, contentDescription = null, tint = MaterialTheme.colorScheme.onSurfaceVariant)
            Spacer(Modifier.width(Spacing.md))
            Column(Modifier.weight(1f)) {
                Text("Ubah PIN", style = MaterialTheme.typography.bodyLarge)
                Text("PIN 6 angka untuk membuka aplikasi", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
            Icon(Icons.AutoMirrored.Filled.KeyboardArrowRight, contentDescription = null)
        }
        Row(Modifier.padding(vertical = Spacing.xs), verticalAlignment = Alignment.CenterVertically) {
            Icon(Icons.Outlined.Timer, contentDescription = null, tint = MaterialTheme.colorScheme.onSurfaceVariant)
            Spacer(Modifier.width(Spacing.md))
            Text(
                "Aplikasi terkunci otomatis setelah 5 menit tidak digunakan. Screenshot & rekam layar diblokir.",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }

    if (changing) {
        ChangePinSheet(
            appLock = appLock,
            userId = userId,
            onDismiss = { changing = false },
            onChanged = {
                changing = false
                onMessage("PIN berhasil diubah.")
            },
        )
    }
}

private enum class ChangeStep(val title: String) { OLD("Masukkan PIN lama"), NEW("Masukkan PIN baru"), CONFIRM("Ulangi PIN baru") }

@Composable
private fun ChangePinSheet(appLock: AppLockManager, userId: Int, onDismiss: () -> Unit, onChanged: () -> Unit) {
    var step by remember { mutableStateOf(ChangeStep.OLD) }
    var pin by remember { mutableStateOf("") }
    var fresh by remember { mutableStateOf("") }
    var error by remember { mutableStateOf<String?>(null) }
    var shake by remember { mutableIntStateOf(0) }
    var busy by remember { mutableStateOf(false) }

    LaunchedEffect(pin) {
        if (pin.length < PinRules.LENGTH) return@LaunchedEffect
        val entered = pin
        when (step) {
            ChangeStep.OLD -> {
                busy = true
                val ok = withContext(Dispatchers.Default) { appLock.store.matches(entered) }
                busy = false
                if (ok) { step = ChangeStep.NEW; error = null } else { error = "PIN lama salah."; shake++ }
            }
            ChangeStep.NEW -> {
                val weak = PinRules.weakness(entered)
                if (weak != null) { error = weak; shake++ } else { fresh = entered; step = ChangeStep.CONFIRM; error = null }
            }
            ChangeStep.CONFIRM -> {
                if (entered != fresh) {
                    error = "PIN tidak sama. Masukkan PIN baru lagi."; shake++; step = ChangeStep.NEW
                } else {
                    busy = true
                    withContext(Dispatchers.Default) { appLock.store.setPin(userId, entered) }
                    busy = false
                    onChanged()
                }
            }
        }
        pin = ""
    }

    FormSheet(title = "Ubah PIN", onDismiss = onDismiss) {
        Text(step.title, style = MaterialTheme.typography.titleMedium, modifier = Modifier.align(Alignment.CenterHorizontally))
        Text(
            error.orEmpty(),
            color = MaterialTheme.colorScheme.error,
            style = MaterialTheme.typography.bodySmall,
            textAlign = TextAlign.Center,
            modifier = Modifier.fillMaxWidth().padding(vertical = Spacing.sm),
        )
        PinPad(
            pin = pin,
            onPinChange = { pin = it; if (it.isNotEmpty()) error = null },
            enabled = !busy,
            shake = shake,
            dark = false,
            modifier = Modifier.align(Alignment.CenterHorizontally),
        )
    }
}
