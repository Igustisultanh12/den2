package id.my.sisinden.app.ui.auth

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import id.my.sisinden.app.core.data.SessionStore
import id.my.sisinden.app.core.network.ApiResult
import id.my.sisinden.app.data.repository.SindenRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class LoginUiState(
    val identifier: String = "",
    val password: String = "",
    val identifierError: String? = null,
    val passwordError: String? = null,
    val submitting: Boolean = false,
    val errorMessage: String? = null,
)

class LoginViewModel(private val repository: SindenRepository) : ViewModel() {
    private val _state = MutableStateFlow(LoginUiState())
    val state: StateFlow<LoginUiState> = _state.asStateFlow()

    val baseUrl: StateFlow<String> = repository.baseUrlFlow
        .stateIn(viewModelScope, SharingStarted.WhileSubscribed(5_000), SessionStore.DEFAULT_BASE_URL)

    fun onIdentifier(v: String) = _state.update { it.copy(identifier = v, identifierError = null, errorMessage = null) }
    fun onPassword(v: String) = _state.update { it.copy(password = v, passwordError = null, errorMessage = null) }

    fun submit() {
        val s = _state.value
        if (s.submitting) return // cegah permintaan ganda
        val idErr = if (s.identifier.isBlank()) "NRP / Email wajib diisi" else null
        val pwErr = if (s.password.isEmpty()) "Kata sandi wajib diisi" else null
        if (idErr != null || pwErr != null) {
            _state.update { it.copy(identifierError = idErr, passwordError = pwErr) }
            return
        }
        _state.update { it.copy(submitting = true, errorMessage = null) }
        viewModelScope.launch {
            when (val res = repository.login(s.identifier, s.password)) {
                // Sukses: SessionManager mengganti layar ke aplikasi utama secara otomatis.
                is ApiResult.Success -> _state.update { it.copy(submitting = false, password = "") }
                is ApiResult.Failure -> _state.update { it.copy(submitting = false, errorMessage = res.error.message) }
            }
        }
    }

    /** @return pesan error, atau null bila tersimpan. */
    fun saveBaseUrl(input: String): String? {
        val normalized = SessionStore.normalizeBaseUrl(input)
            ?: return "Alamat server tidak valid. Gunakan alamat HTTPS, mis. https://sisinden.my.id"
        viewModelScope.launch { repository.setBaseUrl(normalized) }
        return null
    }
}

data class ActivationUiState(
    val nrp: String = "",
    val token: String = "",
    val password: String = "",
    val confirm: String = "",
    val errors: Map<String, String> = emptyMap(),
    val submitting: Boolean = false,
    val errorMessage: String? = null,
    val successMessage: String? = null,
)

class ActivationViewModel(private val repository: SindenRepository) : ViewModel() {
    private val _state = MutableStateFlow(ActivationUiState())
    val state: StateFlow<ActivationUiState> = _state.asStateFlow()

    fun update(transform: (ActivationUiState) -> ActivationUiState) =
        _state.update { transform(it).copy(errorMessage = null) }

    fun submit() {
        val s = _state.value
        if (s.submitting) return
        val errors = buildMap {
            if (s.nrp.isBlank()) put("nrp", "NRP / Email wajib diisi")
            if (s.token.isBlank()) put("token", "Token aktivasi dari Admin wajib diisi")
            if (s.password.length < 8) put("password", "Minimal 8 karakter")
            if (s.confirm != s.password) put("confirm", "Konfirmasi kata sandi tidak sama")
        }
        if (errors.isNotEmpty()) {
            _state.update { it.copy(errors = errors) }
            return
        }
        _state.update { it.copy(submitting = true, errors = emptyMap(), errorMessage = null) }
        viewModelScope.launch {
            when (val res = repository.activate(s.nrp, s.token, s.password)) {
                is ApiResult.Success -> _state.update {
                    it.copy(submitting = false, successMessage = res.data.message ?: "Aktivasi berhasil. Silakan masuk.")
                }
                is ApiResult.Failure -> _state.update { it.copy(submitting = false, errorMessage = res.error.message) }
            }
        }
    }
}
