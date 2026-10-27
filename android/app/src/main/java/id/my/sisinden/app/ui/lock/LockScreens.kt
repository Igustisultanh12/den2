package id.my.sisinden.app.ui.lock

import androidx.activity.compose.BackHandler
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.spring
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.Backspace
import androidx.compose.material.icons.outlined.Fingerprint
import androidx.compose.material.icons.outlined.Lock
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.IntOffset
import androidx.compose.ui.unit.dp
import id.my.sisinden.app.core.security.AppLockManager
import id.my.sisinden.app.core.security.Biometric
import id.my.sisinden.app.core.security.PinResult
import id.my.sisinden.app.core.security.PinRules
import id.my.sisinden.app.core.ui.components.ConfirmDialog
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.data.model.UserProfile
import id.my.sisinden.app.ui.common.BrandBackdrop
import id.my.sisinden.app.ui.common.FloatingLogo
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import kotlin.math.roundToInt

private val Accent = Color(0xFFF97316)

/** Titik PIN + papan angka besar (ramah lansia), dipakai di semua layar PIN. */
@Composable
fun PinPad(
    pin: String,
    onPinChange: (String) -> Unit,
    modifier: Modifier = Modifier,
    enabled: Boolean = true,
    shake: Int = 0,
    biometricIcon: ImageVector? = null,
    onBiometric: () -> Unit = {},
    dark: Boolean = true,
) {
    val fg = if (dark) Color.White else MaterialTheme.colorScheme.onSurface
    val offset = remember { Animatable(0f) }
    LaunchedEffect(shake) {
        if (shake > 0) {
            for (x in listOf(-18f, 16f, -12f, 8f, -4f, 0f)) offset.animateTo(x, spring(stiffness = Spring.StiffnessHigh))
        }
    }
    Column(modifier, horizontalAlignment = Alignment.CenterHorizontally) {
        Row(
            Modifier.offset { IntOffset(offset.value.roundToInt(), 0) },
            horizontalArrangement = Arrangement.spacedBy(18.dp),
        ) {
            repeat(PinRules.LENGTH) { i ->
                val filled = i < pin.length
                Box(
                    Modifier
                        .size(18.dp)
                        .clip(CircleShape)
                        .background(if (filled) fg else Color.Transparent)
                        .border(2.dp, fg.copy(alpha = if (filled) 1f else 0.6f), CircleShape),
                )
            }
        }
        Spacer(Modifier.height(Spacing.xxl))
        val rows = listOf(listOf("1", "2", "3"), listOf("4", "5", "6"), listOf("7", "8", "9"), listOf("bio", "0", "del"))
        Column(verticalArrangement = Arrangement.spacedBy(Spacing.md)) {
            rows.forEach { row ->
                Row(horizontalArrangement = Arrangement.spacedBy(28.dp)) {
                    row.forEach { key ->
                        when (key) {
                            "bio" -> KeyButton(fg, enabled && biometricIcon != null, visible = biometricIcon != null, label = "Biometrik", onClick = onBiometric) {
                                if (biometricIcon != null) Icon(biometricIcon, contentDescription = "Masuk dengan biometrik", tint = fg, modifier = Modifier.size(30.dp))
                            }
                            "del" -> KeyButton(fg, enabled && pin.isNotEmpty(), label = "Hapus", onClick = { onPinChange(pin.dropLast(1)) }) {
                                Icon(Icons.AutoMirrored.Outlined.Backspace, contentDescription = "Hapus angka", tint = fg, modifier = Modifier.size(26.dp))
                            }
                            else -> KeyButton(fg, enabled, filled = true, label = key, onClick = {
                                if (pin.length < PinRules.LENGTH) onPinChange(pin + key)
                            }) {
                                Text(key, color = fg, style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.SemiBold)
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun KeyButton(
    fg: Color,
    enabled: Boolean,
    label: String,
    visible: Boolean = true,
    filled: Boolean = false,
    onClick: () -> Unit,
    content: @Composable () -> Unit,
) {
    Box(
        Modifier
            .size(76.dp)
            .clip(CircleShape)
            .background(if (filled && visible) fg.copy(alpha = 0.10f) else Color.Transparent)
            .clickable(enabled = enabled && visible, onClickLabel = label, onClick = onClick),
        contentAlignment = Alignment.Center,
    ) { if (visible) content() }
}

@Composable
private fun LockFrame(content: @Composable ColumnScope.() -> Unit) {
    BrandBackdrop(backgroundUrl = null) {
        Column(
            Modifier
                .fillMaxSize()
                .statusBarsPadding()
                .navigationBarsPadding()
                .padding(horizontal = Spacing.screen, vertical = Spacing.lg),
            horizontalAlignment = Alignment.CenterHorizontally,
            content = content,
        )
    }
}

private enum class SetupStep { ENTER, CONFIRM, BIOMETRIC }

/**
 * Pembuatan PIN setelah login pertama di perangkat ini (seperti M-Banking):
 * buat PIN → ulangi PIN → (opsional) aktifkan biometrik.
 */
@Composable
fun PinSetupScreen(
    user: UserProfile,
    appLock: AppLockManager,
    onDone: () -> Unit,
    onCancelLogout: () -> Unit,
) {
    val context = LocalContext.current
    val bioAvailable = remember { Biometric.isAvailable(context) }
    var step by remember { mutableStateOf(SetupStep.ENTER) }
    var first by remember { mutableStateOf("") }
    var pin by remember { mutableStateOf("") }
    var error by remember { mutableStateOf<String?>(null) }
    var shake by remember { mutableIntStateOf(0) }
    var saving by remember { mutableStateOf(false) }
    var bioOn by remember { mutableStateOf(bioAvailable) }
    var confirmCancel by remember { mutableStateOf(false) }

    fun finish(enableBio: Boolean) {
        appLock.store.biometricEnabled = enableBio
        appLock.unlock()
        onDone()
    }

    BackHandler { if (step == SetupStep.CONFIRM) { step = SetupStep.ENTER; pin = ""; first = "" } else confirmCancel = true }

    LaunchedEffect(pin) {
        if (pin.length < PinRules.LENGTH) return@LaunchedEffect
        when (step) {
            SetupStep.ENTER -> {
                val weak = PinRules.weakness(pin)
                if (weak != null) {
                    error = weak; shake++; pin = ""
                } else {
                    first = pin; pin = ""; error = null; step = SetupStep.CONFIRM
                }
            }
            SetupStep.CONFIRM -> {
                if (pin != first) {
                    error = "PIN tidak sama. Silakan buat ulang."; shake++
                    pin = ""; first = ""; step = SetupStep.ENTER
                } else {
                    saving = true
                    val chosen = pin
                    withContext(Dispatchers.Default) { appLock.store.setPin(user.id, chosen) }
                    saving = false
                    if (bioAvailable) step = SetupStep.BIOMETRIC else finish(false)
                }
            }
            SetupStep.BIOMETRIC -> Unit
        }
    }

    LockFrame {
        Spacer(Modifier.height(Spacing.xl))
        FloatingLogo(size = 84.dp)
        Spacer(Modifier.height(Spacing.lg))
        if (step == SetupStep.BIOMETRIC) {
            Icon(Icons.Outlined.Fingerprint, contentDescription = null, tint = Color.White, modifier = Modifier.size(72.dp))
            Spacer(Modifier.height(Spacing.lg))
            Text("Aktifkan login biometrik?", color = Color.White, style = MaterialTheme.typography.headlineSmall, textAlign = TextAlign.Center)
            Spacer(Modifier.height(Spacing.sm))
            Text(
                "Buka aplikasi dengan sidik jari atau wajah yang terdaftar di HP ini. PIN tetap bisa dipakai kapan saja.",
                color = Color.White.copy(alpha = 0.85f),
                style = MaterialTheme.typography.bodyMedium,
                textAlign = TextAlign.Center,
                modifier = Modifier.widthIn(max = 360.dp),
            )
            Spacer(Modifier.height(Spacing.xl))
            Row(
                Modifier.widthIn(max = 360.dp).fillMaxWidth().clip(MaterialTheme.shapes.medium)
                    .background(Color.White.copy(alpha = 0.1f)).clickable { bioOn = !bioOn }.padding(Spacing.lg),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text("Sidik jari / Face ID", color = Color.White, style = MaterialTheme.typography.titleMedium, modifier = Modifier.weight(1f))
                Switch(checked = bioOn, onCheckedChange = { bioOn = it })
            }
            Spacer(Modifier.weight(1f))
            PrimaryLockButton("Lanjutkan") {
                if (!bioOn) finish(false)
                else Biometric.authenticate(
                    context, "Aktifkan login biometrik", "Verifikasi sidik jari atau wajah Anda",
                    onSuccess = { finish(true) },
                    onError = { msg -> error = msg },
                )
            }
            error?.let { Text(it, color = Color(0xFFFCA5A5), style = MaterialTheme.typography.bodySmall, textAlign = TextAlign.Center, modifier = Modifier.padding(top = Spacing.sm)) }
            TextButton(onClick = { finish(false) }) { Text("Nanti saja", color = Color.White) }
        } else {
            Text(
                if (step == SetupStep.ENTER) "Buat PIN Sinden 2" else "Ulangi PIN",
                color = Color.White,
                style = MaterialTheme.typography.headlineSmall,
            )
            Spacer(Modifier.height(Spacing.xs))
            Text(
                if (step == SetupStep.ENTER) "6 angka untuk membuka aplikasi. Hindari tanggal lahir atau angka berurutan."
                else "Masukkan sekali lagi PIN yang sama.",
                color = Color.White.copy(alpha = 0.85f),
                style = MaterialTheme.typography.bodyMedium,
                textAlign = TextAlign.Center,
                modifier = Modifier.widthIn(max = 340.dp),
            )
            Spacer(Modifier.height(Spacing.sm))
            Text(error.orEmpty(), color = Color(0xFFFCA5A5), style = MaterialTheme.typography.bodySmall, textAlign = TextAlign.Center)
            Spacer(Modifier.weight(1f))
            if (saving) CircularProgressIndicator(color = Color.White)
            PinPad(pin = pin, onPinChange = { pin = it; if (it.isNotEmpty()) error = null }, enabled = !saving, shake = shake)
            Spacer(Modifier.height(Spacing.lg))
        }
    }

    if (confirmCancel) {
        ConfirmDialog(
            title = "Batalkan pembuatan PIN?",
            message = "PIN wajib dibuat untuk memakai aplikasi. Jika dibatalkan, Anda akan keluar dan harus masuk lagi.",
            confirmLabel = "Keluar",
            destructive = true,
            onDismiss = { confirmCancel = false },
            onConfirm = { confirmCancel = false; onCancelLogout() },
        )
    }
}

/**
 * Layar kunci: muncul saat aplikasi dibuka atau setelah 5 menit tidak aktif.
 * Buka dengan PIN atau biometrik; 5 kali salah → keluar & wajib login ulang.
 */
@Composable
fun LockScreen(
    user: UserProfile,
    appLock: AppLockManager,
    onForgotPin: () -> Unit,
    onTooManyAttempts: () -> Unit,
) {
    val context = LocalContext.current
    val bioAvailable = remember { appLock.store.biometricEnabled && Biometric.isAvailable(context) }
    var pin by remember { mutableStateOf("") }
    var error by remember { mutableStateOf<String?>(null) }
    var shake by remember { mutableIntStateOf(0) }
    var checking by remember { mutableStateOf(false) }
    var confirmForgot by remember { mutableStateOf(false) }

    fun promptBiometric() {
        Biometric.authenticate(
            context, "Buka Sinden 2", "Gunakan sidik jari atau wajah Anda",
            onSuccess = { appLock.unlock() },
            onError = { msg -> error = msg },
        )
    }

    // Tampilkan dialog biometrik otomatis sekali saat layar kunci muncul.
    LaunchedEffect(Unit) { if (bioAvailable) promptBiometric() }

    LaunchedEffect(pin) {
        if (pin.length < PinRules.LENGTH || checking) return@LaunchedEffect
        checking = true
        val entered = pin
        val result = withContext(Dispatchers.Default) { appLock.verifyPin(entered) }
        checking = false
        when (result) {
            PinResult.Ok -> Unit
            is PinResult.Wrong -> {
                error = "PIN salah. Sisa ${result.remaining} kali percobaan."
                shake++
                pin = ""
            }
            PinResult.Locked -> onTooManyAttempts()
        }
    }

    LockFrame {
        Spacer(Modifier.height(Spacing.xl))
        FloatingLogo(size = 84.dp)
        Spacer(Modifier.height(Spacing.lg))
        Text("Halo, ${user.firstName}", color = Color.White, style = MaterialTheme.typography.headlineSmall)
        Spacer(Modifier.height(Spacing.xs))
        Row(verticalAlignment = Alignment.CenterVertically) {
            Icon(Icons.Outlined.Lock, contentDescription = null, tint = Color.White.copy(alpha = 0.85f), modifier = Modifier.size(16.dp))
            Spacer(Modifier.size(6.dp))
            Text("Masukkan PIN untuk melanjutkan", color = Color.White.copy(alpha = 0.85f), style = MaterialTheme.typography.bodyMedium)
        }
        Spacer(Modifier.height(Spacing.sm))
        Text(error.orEmpty(), color = Color(0xFFFCA5A5), style = MaterialTheme.typography.bodySmall, textAlign = TextAlign.Center)
        Spacer(Modifier.weight(1f))
        PinPad(
            pin = pin,
            onPinChange = { pin = it; if (it.isNotEmpty()) error = null },
            enabled = !checking,
            shake = shake,
            biometricIcon = if (bioAvailable) Icons.Outlined.Fingerprint else null,
            onBiometric = { promptBiometric() },
        )
        Spacer(Modifier.height(Spacing.md))
        TextButton(onClick = { confirmForgot = true }) {
            Text("Lupa PIN? Masuk dengan kata sandi", color = Accent, fontWeight = FontWeight.SemiBold)
        }
    }

    if (confirmForgot) {
        ConfirmDialog(
            title = "Lupa PIN?",
            message = "Anda akan keluar dan masuk kembali dengan NRP & kata sandi, lalu membuat PIN baru.",
            confirmLabel = "Lanjutkan",
            onDismiss = { confirmForgot = false },
            onConfirm = { confirmForgot = false; onForgotPin() },
        )
    }
}

@Composable
private fun PrimaryLockButton(text: String, onClick: () -> Unit) {
    Box(
        Modifier
            .widthIn(max = 360.dp)
            .fillMaxWidth()
            .height(56.dp)
            .clip(MaterialTheme.shapes.medium)
            .background(Accent)
            .clickable(onClick = onClick),
        contentAlignment = Alignment.Center,
    ) {
        Text(text, color = Color.White, style = MaterialTheme.typography.labelLarge, fontWeight = FontWeight.Bold)
    }
}
