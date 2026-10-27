package id.my.sisinden.app.ui.auth

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Badge
import androidx.compose.material.icons.outlined.Info
import androidx.compose.material.icons.outlined.Lock
import androidx.compose.material.icons.outlined.Shield
import androidx.compose.material.icons.outlined.Visibility
import androidx.compose.material.icons.outlined.VisibilityOff
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
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
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.compose.ui.res.stringResource
import id.my.sisinden.app.BuildConfig
import id.my.sisinden.app.R
import id.my.sisinden.app.core.data.LogoutReason
import id.my.sisinden.app.core.ui.components.Tone
import id.my.sisinden.app.core.ui.components.toneColors
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.ui.common.BrandBackdrop
import id.my.sisinden.app.ui.common.FloatingLogo
import id.my.sisinden.app.ui.common.Slate300
import id.my.sisinden.app.ui.common.Slate400
import id.my.sisinden.app.ui.common.Slate900
import id.my.sisinden.app.ui.common.rememberCardFloat
import java.time.Year

/** Oranye tombol "Masuk" di web (orange-600 → cukup kontras untuk teks putih tebal). */
private val SignInOrange = Color(0xFFEA580C)

/**
 * Halaman masuk dengan tampilan seperti login web Sinden 2: latar foto dari
 * pengaturan Admin + lapisan gelap, logo mengambang, dan kartu kaca gelap.
 * Alamat server tidak dapat diubah dari aplikasi.
 */
@Composable
fun LoginScreen(
    repository: SindenRepository,
    logoutReason: LogoutReason?,
    onOpenActivation: () -> Unit,
) {
    val vm: LoginViewModel = viewModel { LoginViewModel(repository) }
    val state by vm.state.collectAsStateWithLifecycle()
    val config by vm.config.collectAsStateWithLifecycle()
    val focus = LocalFocusManager.current
    var showPassword by rememberSaveable { mutableStateOf(false) }
    val appName = config?.appName?.takeIf { it.isNotBlank() } ?: "SINDEN 2"
    val cardLift = rememberCardFloat()
    val cardLiftPx = with(LocalDensity.current) { 6.dp.toPx() }

    BrandBackdrop(backgroundUrl = config?.loginBackground) {
        Column(
            Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .statusBarsPadding()
                .navigationBarsPadding()
                .imePadding()
                .padding(horizontal = Spacing.xxl),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Spacer(Modifier.height(Spacing.xl))
            FloatingLogo(size = 128.dp)
            Text(
                appName.uppercase(),
                style = MaterialTheme.typography.headlineMedium,
                color = Color.White,
                letterSpacing = 1.sp,
                textAlign = TextAlign.Center,
            )
            Spacer(Modifier.height(Spacing.xs))
            Text(
                "Sistem Informasi Detasemen Intelijen",
                style = MaterialTheme.typography.bodySmall,
                color = Slate300,
                textAlign = TextAlign.Center,
            )
            Spacer(Modifier.height(Spacing.xxl))

            // Kartu form (bg-slate-900/90, border-white/10, mengambang halus)
            Column(
                Modifier
                    .widthIn(max = 460.dp)
                    .fillMaxWidth()
                    .graphicsLayer { translationY = -cardLiftPx * cardLift }
                    .clip(RoundedCornerShape(24.dp))
                    .background(Slate900.copy(alpha = 0.90f))
                    .border(1.dp, Color.White.copy(alpha = 0.10f), RoundedCornerShape(24.dp))
                    .padding(Spacing.xxl),
                verticalArrangement = Arrangement.spacedBy(Spacing.md),
            ) {
                Column {
                    Text("Masuk Akun", style = MaterialTheme.typography.titleLarge, color = Color.White)
                    Spacer(Modifier.height(Spacing.xs))
                    Text(
                        "Gunakan akun internal Anda untuk mengakses Sinden 2.",
                        style = MaterialTheme.typography.bodySmall,
                        color = Slate300,
                    )
                }

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

                DarkField(
                    value = state.identifier,
                    onValueChange = vm::onIdentifier,
                    label = "NRP, Username, atau Email",
                    error = state.identifierError,
                    leading = { Icon(Icons.Outlined.Badge, contentDescription = null) },
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email, imeAction = ImeAction.Next),
                )
                DarkField(
                    value = state.password,
                    onValueChange = vm::onPassword,
                    label = "Kata sandi",
                    error = state.passwordError,
                    leading = { Icon(Icons.Outlined.Lock, contentDescription = null) },
                    trailing = {
                        IconButton(onClick = { showPassword = !showPassword }) {
                            Icon(
                                if (showPassword) Icons.Outlined.VisibilityOff else Icons.Outlined.Visibility,
                                contentDescription = if (showPassword) "Sembunyikan kata sandi" else "Tampilkan kata sandi",
                            )
                        }
                    },
                    visualTransformation = if (showPassword) VisualTransformation.None else PasswordVisualTransformation(),
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password, imeAction = ImeAction.Done),
                    keyboardActions = KeyboardActions(onDone = {
                        focus.clearFocus()
                        vm.submit()
                    }),
                )

                Button(
                    onClick = { focus.clearFocus(); vm.submit() },
                    enabled = !state.busy,
                    modifier = Modifier.fillMaxWidth().height(56.dp),
                    shape = RoundedCornerShape(16.dp),
                    colors = ButtonDefaults.buttonColors(
                        containerColor = SignInOrange,
                        contentColor = Color.White,
                        disabledContainerColor = SignInOrange.copy(alpha = 0.5f),
                        disabledContentColor = Color.White.copy(alpha = 0.8f),
                    ),
                ) {
                    Icon(Icons.Outlined.Shield, contentDescription = null, modifier = Modifier.size(20.dp))
                    Spacer(Modifier.width(Spacing.sm))
                    Text("Masuk", style = MaterialTheme.typography.labelLarge, fontWeight = FontWeight.Black)
                }

                TextButton(onClick = onOpenActivation, modifier = Modifier.align(Alignment.CenterHorizontally)) {
                    Text(
                        "Belum aktif? Aktivasi akun dengan token Admin",
                        style = MaterialTheme.typography.labelMedium,
                        color = Color(0xFFFDBA74),
                        textAlign = TextAlign.Center,
                    )
                }
            }

            Spacer(Modifier.height(Spacing.xxl))
            Text(
                "$appName · Versi ${BuildConfig.VERSION_NAME}",
                style = MaterialTheme.typography.labelSmall,
                color = Slate400,
                textAlign = TextAlign.Center,
            )
            Text(
                stringResource(R.string.app_copyright, Year.now().value),
                style = MaterialTheme.typography.labelSmall,
                color = Slate400,
                textAlign = TextAlign.Center,
                modifier = Modifier.padding(horizontal = Spacing.lg),
            )
            Spacer(Modifier.height(Spacing.lg))
        }

        SecurityCheckOverlay(
            step = state.step,
            labels = listOf("Memeriksa keamanan sistem", "Memeriksa sesi login", "Masuk ke beranda"),
            action = TurnstileAction.LOGIN,
            widgetKey = state.widgetKey,
            showWidget = state.showWidget,
            onToken = vm::onTurnstileToken,
            onWidgetError = vm::onTurnstileError,
            onCancel = vm::cancelCheck,
        )
    }
}

/** Field teks bergaya web (bg-white/10, border-white/10, teks putih). */
@Composable
private fun DarkField(
    value: String,
    onValueChange: (String) -> Unit,
    label: String,
    error: String?,
    leading: @Composable () -> Unit,
    keyboardOptions: KeyboardOptions,
    trailing: (@Composable () -> Unit)? = null,
    visualTransformation: VisualTransformation = VisualTransformation.None,
    keyboardActions: KeyboardActions = KeyboardActions.Default,
) {
    OutlinedTextField(
        value = value,
        onValueChange = onValueChange,
        modifier = Modifier.fillMaxWidth(),
        label = { Text(label) },
        isError = error != null,
        supportingText = error?.let { { Text(it) } },
        singleLine = true,
        leadingIcon = leading,
        trailingIcon = trailing,
        visualTransformation = visualTransformation,
        keyboardOptions = keyboardOptions,
        keyboardActions = keyboardActions,
        shape = RoundedCornerShape(14.dp),
        textStyle = MaterialTheme.typography.bodyLarge,
        colors = OutlinedTextFieldDefaults.colors(
            focusedTextColor = Color.White,
            unfocusedTextColor = Color.White,
            focusedContainerColor = Color.White.copy(alpha = 0.10f),
            unfocusedContainerColor = Color.White.copy(alpha = 0.08f),
            errorContainerColor = Color.White.copy(alpha = 0.08f),
            focusedBorderColor = Color.White.copy(alpha = 0.85f),
            unfocusedBorderColor = Color.White.copy(alpha = 0.14f),
            focusedLabelColor = Color.White,
            unfocusedLabelColor = Slate300,
            focusedLeadingIconColor = Color.White,
            unfocusedLeadingIconColor = Slate300,
            focusedTrailingIconColor = Color.White,
            unfocusedTrailingIconColor = Slate300,
            cursorColor = Color(0xFFFB923C),
            errorTextColor = Color.White,
            errorLabelColor = Color(0xFFFCA5A5),
            errorBorderColor = Color(0xFFF87171),
            errorSupportingTextColor = Color(0xFFFCA5A5),
            errorLeadingIconColor = Color(0xFFFCA5A5),
        ),
    )
}

@Composable
internal fun Banner(text: String, tone: Tone) {
    val c = toneColors(tone)
    Row(
        Modifier.fillMaxWidth().clip(MaterialTheme.shapes.small).background(c.bg).padding(Spacing.md),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(Icons.Outlined.Info, contentDescription = null, tint = c.fg, modifier = Modifier.size(20.dp))
        Spacer(Modifier.width(Spacing.sm))
        Text(text, color = c.fg, style = MaterialTheme.typography.bodySmall)
    }
}
