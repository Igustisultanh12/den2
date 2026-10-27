package id.my.sisinden.app.ui.auth

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import id.my.sisinden.app.core.ui.components.PrimaryButton
import id.my.sisinden.app.core.ui.components.SindenScaffold
import id.my.sisinden.app.core.ui.components.SindenTextField
import id.my.sisinden.app.core.ui.components.Tone
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.data.repository.SindenRepository

@Composable
fun ActivationScreen(
    repository: SindenRepository,
    onBack: () -> Unit,
    onActivated: () -> Unit,
) {
    val vm: ActivationViewModel = viewModel { ActivationViewModel(repository) }
    val s by vm.state.collectAsStateWithLifecycle()

    SindenScaffold(title = "Aktivasi Akun", subtitle = "Gunakan token dari Admin Sinden 2", onBack = onBack) { padding ->
        Column(
            Modifier
                .fillMaxSize()
                .padding(padding)
                .verticalScroll(rememberScrollState())
                .navigationBarsPadding()
                .imePadding()
                .padding(horizontal = Spacing.screen),
            verticalArrangement = Arrangement.spacedBy(Spacing.sm),
        ) {
            Text(
                "Token aktivasi diberikan oleh Admin saat akun Anda didaftarkan. Tanpa token, kata sandi tidak dapat diatur demi keamanan akun.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            Spacer(Modifier.height(Spacing.sm))
            if (s.successMessage != null) {
                Banner(s.successMessage.orEmpty(), Tone.SUCCESS)
                PrimaryButton(text = "Kembali ke halaman masuk", onClick = onActivated)
                return@Column
            }
            if (s.errorMessage != null) Banner(s.errorMessage.orEmpty(), Tone.DANGER)

            SindenTextField(s.nrp, { v -> vm.update { it.copy(nrp = v) } }, "NRP atau Email", error = s.errors["nrp"])
            SindenTextField(s.token, { v -> vm.update { it.copy(token = v) } }, "Token aktivasi", error = s.errors["token"])
            SindenTextField(
                s.password, { v -> vm.update { it.copy(password = v) } }, "Kata sandi baru",
                error = s.errors["password"], supporting = "Minimal 8 karakter",
                keyboardType = KeyboardType.Password, visualTransformation = PasswordVisualTransformation(),
            )
            SindenTextField(
                s.confirm, { v -> vm.update { it.copy(confirm = v) } }, "Ulangi kata sandi",
                error = s.errors["confirm"], keyboardType = KeyboardType.Password, imeAction = ImeAction.Done,
                visualTransformation = PasswordVisualTransformation(),
            )
            Spacer(Modifier.height(Spacing.sm))
            PrimaryButton(text = "Aktifkan akun", onClick = vm::submit, loading = s.submitting)
            Spacer(Modifier.height(Spacing.xxl))
        }
    }
}
