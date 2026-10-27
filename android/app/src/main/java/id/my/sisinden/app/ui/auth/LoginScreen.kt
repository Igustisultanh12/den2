package id.my.sisinden.app.ui.auth

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Badge
import androidx.compose.material.icons.outlined.Dns
import androidx.compose.material.icons.outlined.Info
import androidx.compose.material.icons.outlined.Lock
import androidx.compose.material.icons.outlined.Visibility
import androidx.compose.material.icons.outlined.VisibilityOff
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import id.my.sisinden.app.BuildConfig
import id.my.sisinden.app.R
import id.my.sisinden.app.core.data.LogoutReason
import id.my.sisinden.app.core.ui.components.PrimaryButton
import id.my.sisinden.app.core.ui.components.SindenTextField
import id.my.sisinden.app.core.ui.components.Tone
import id.my.sisinden.app.core.ui.components.toneColors
import id.my.sisinden.app.core.ui.theme.Navy900
import id.my.sisinden.app.core.ui.theme.Ocean600
import id.my.sisinden.app.core.ui.theme.Ocean700
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.data.repository.SindenRepository

@Composable
fun LoginScreen(
    repository: SindenRepository,
    logoutReason: LogoutReason?,
    onOpenActivation: () -> Unit,
) {
    val vm: LoginViewModel = viewModel { LoginViewModel(repository) }
    val state by vm.state.collectAsStateWithLifecycle()
    val baseUrl by vm.baseUrl.collectAsStateWithLifecycle()
    val focus = LocalFocusManager.current
    var showPassword by rememberSaveable { mutableStateOf(false) }
    var showServerDialog by rememberSaveable { mutableStateOf(false) }

    Box(Modifier.fillMaxSize().background(MaterialTheme.colorScheme.background)) {
        // Latar hero (satu gradien lembut, tidak berlebihan)
        Box(
            Modifier
                .fillMaxWidth()
                .height(300.dp)
                .background(Brush.linearGradient(listOf(Navy900, Ocean700, Ocean600)))
        )

        Column(
            Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .statusBarsPadding()
                .navigationBarsPadding()
                .imePadding()
                .padding(horizontal = Spacing.screen),
        ) {
            Spacer(Modifier.height(40.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(
                    Modifier.size(52.dp).clip(RoundedCornerShape(16.dp)).background(Color.White.copy(alpha = 0.16f)),
                    contentAlignment = Alignment.Center,
                ) {
                    Image(painterResource(R.drawable.ic_sinden_mark), contentDescription = null, modifier = Modifier.size(36.dp))
                }
                Spacer(Modifier.width(Spacing.md))
                Column {
                    Text("SISINDEN", color = Color.White, style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
                    Text("Denintel Koarmada 2", color = Color.White.copy(alpha = 0.75f), style = MaterialTheme.typography.bodySmall)
                }
            }
            Spacer(Modifier.height(28.dp))
            Text("Selamat datang 👋", color = Color.White, style = MaterialTheme.typography.headlineMedium)
            Spacer(Modifier.height(Spacing.xs))
            Text(
                "Masuk untuk melihat jadwal jaga, presensi, dan tugas kedinasan Anda.",
                color = Color.White.copy(alpha = 0.8f),
                style = MaterialTheme.typography.bodyMedium,
            )
            Spacer(Modifier.height(28.dp))

            Surface(
                shape = MaterialTheme.shapes.large,
                color = MaterialTheme.colorScheme.surface,
                shadowElevation = 6.dp,
                modifier = Modifier.fillMaxWidth(),
            ) {
                Column(Modifier.padding(Spacing.xl), verticalArrangement = Arrangement.spacedBy(Spacing.sm)) {
                    if (logoutReason == LogoutReason.EXPIRED || logoutReason == LogoutReason.DISABLED) {
                        Banner(
                            text = if (logoutReason == LogoutReason.EXPIRED) "Sesi Anda telah berakhir. Silakan masuk kembali."
                            else "Akun Anda dinonaktifkan oleh Admin.",
                            tone = Tone.WARNING,
                        )
                    }
                    AnimatedVisibility(visible = state.errorMessage != null) {
                        Banner(text = state.errorMessage.orEmpty(), tone = Tone.DANGER)
                    }

                    SindenTextField(
                        value = state.identifier,
                        onValueChange = vm::onIdentifier,
                        label = "NRP atau Email",
                        error = state.identifierError,
                        leadingIcon = { Icon(Icons.Outlined.Badge, contentDescription = null) },
                        keyboardType = KeyboardType.Email,
                    )
                    SindenTextField(
                        value = state.password,
                        onValueChange = vm::onPassword,
                        label = "Kata sandi",
                        error = state.passwordError,
                        leadingIcon = { Icon(Icons.Outlined.Lock, contentDescription = null) },
                        trailingIcon = {
                            IconButton(onClick = { showPassword = !showPassword }) {
                                Icon(
                                    if (showPassword) Icons.Outlined.VisibilityOff else Icons.Outlined.Visibility,
                                    contentDescription = if (showPassword) "Sembunyikan kata sandi" else "Tampilkan kata sandi",
                                )
                            }
                        },
                        keyboardType = KeyboardType.Password,
                        imeAction = ImeAction.Done,
                        visualTransformation = if (showPassword) VisualTransformation.None else PasswordVisualTransformation(),
                        keyboardActions = KeyboardActions(onDone = {
                            focus.clearFocus()
                            vm.submit()
                        }),
                    )
                    Spacer(Modifier.height(Spacing.xs))
                    PrimaryButton(text = "Masuk", onClick = { focus.clearFocus(); vm.submit() }, loading = state.submitting)
                    TextButton(onClick = onOpenActivation, modifier = Modifier.align(Alignment.CenterHorizontally)) {
                        Text("Belum aktif? Aktivasi akun dengan token Admin")
                    }
                }
            }

            Spacer(Modifier.height(Spacing.lg))
            TextButton(onClick = { showServerDialog = true }, modifier = Modifier.align(Alignment.CenterHorizontally)) {
                Icon(Icons.Outlined.Dns, contentDescription = null, modifier = Modifier.size(18.dp))
                Spacer(Modifier.width(Spacing.sm))
                Text(baseUrl.removePrefix("https://"), style = MaterialTheme.typography.labelMedium)
            }
            Text(
                "Versi ${BuildConfig.VERSION_NAME}",
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.align(Alignment.CenterHorizontally).offset(y = (-4).dp),
            )
            Spacer(Modifier.height(Spacing.xxl))
        }
    }

    if (showServerDialog) {
        ServerDialog(
            current = baseUrl,
            onSave = vm::saveBaseUrl,
            onDismiss = { showServerDialog = false },
        )
    }
}

@Composable
internal fun Banner(text: String, tone: Tone) {
    val c = toneColors(tone)
    Row(
        Modifier.fillMaxWidth().clip(MaterialTheme.shapes.small).background(c.bg).padding(Spacing.md),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(Icons.Outlined.Info, contentDescription = null, tint = c.fg, modifier = Modifier.size(18.dp))
        Spacer(Modifier.width(Spacing.sm))
        Text(text, color = c.fg, style = MaterialTheme.typography.bodySmall)
    }
}

/** Dialog alamat server (mis. mirror internal). Hanya HTTPS. */
@Composable
internal fun ServerDialog(current: String, onSave: (String) -> String?, onDismiss: () -> Unit) {
    var value by rememberSaveable { mutableStateOf(current) }
    var error by rememberSaveable { mutableStateOf<String?>(null) }
    AlertDialog(
        onDismissRequest = onDismiss,
        icon = { Icon(Icons.Outlined.Dns, contentDescription = null) },
        title = { Text("Alamat server") },
        text = {
            Column {
                Text(
                    "Ubah hanya atas arahan Admin. Data login akan dikirim ke alamat ini.",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                Spacer(Modifier.height(Spacing.md))
                SindenTextField(
                    value = value,
                    onValueChange = { value = it; error = null },
                    label = "URL server",
                    error = error,
                    keyboardType = KeyboardType.Uri,
                    imeAction = ImeAction.Done,
                )
            }
        },
        confirmButton = {
            TextButton(onClick = {
                val err = onSave(value)
                if (err == null) onDismiss() else error = err
            }) { Text("Simpan") }
        },
        dismissButton = {
            TextButton(onClick = { value = "https://sisinden.my.id"; error = null }) { Text("Bawaan") }
        },
    )
}
