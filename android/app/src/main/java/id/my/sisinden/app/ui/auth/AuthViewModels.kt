package id.my.sisinden.app.ui.auth

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import id.my.sisinden.app.core.network.ApiResult
import id.my.sisinden.app.core.network.ErrorKind
import id.my.sisinden.app.data.model.ConfigResponse
import id.my.sisinden.app.data.repository.SindenRepository
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import kotlinx.coroutines.withTimeoutOrNull

/** Durasi minimum tiap tahap animasi agar terbaca (ms). */
private const val MIN_STEP_MS = 700L
private const val FINISH_MS = 900L
private const val TOKEN_SHOW_MS = 600L

/** Pesan ramah untuk kode error widget Turnstile. */
internal fun turnstileErrorMessage(code: String): String = when {
    code == "network" -> "Pemeriksaan keamanan tidak dapat dimuat. Periksa koneksi internet Anda lalu coba lagi."
    code.startsWith("110") -> "Konfigurasi pemeriksaan keamanan bermasalah. Hubungi Admin Sinden 2."
    code.startsWith("300") || code.startsWith("600") -> "Pemeriksaan keamanan gagal. Pastikan tanggal & jam ponsel benar, lalu coba lagi."
    else -> "Pemeriksaan keamanan gagal${if (code.isNotBlank()) " (kode $code)" else ""}. Silakan coba lagi."
}

data class LoginUiState(
    val identifier: String = "",
    val password: String = "",
    val identifierError: String? = null,
    val passwordError: String? = null,
    val errorMessage: String? = null,
    val step: AuthStep = AuthStep.IDLE,
    /** Naik setiap kali widget Turnstile perlu dimuat ulang (token sekali pakai). */
    val widgetKey: Int = 0,
    /** Widget Turnstile ditampilkan hanya bila server mewajibkannya (turnstile_enabled). */
    val showWidget: Boolean = false,
) {
    val busy: Boolean get() = step != AuthStep.IDLE
}

class LoginViewModel(private val repository: SindenRepository) : ViewModel() {
    private val _state = MutableStateFlow(LoginUiState())
    val state: StateFlow<LoginUiState> = _state.asStateFlow()

    /** Konfigurasi publik server (latar login, nama instansi, status Turnstile). */
    private val _config = MutableStateFlow<ConfigResponse?>(null)
    val config: StateFlow<ConfigResponse?> = _config.asStateFlow()

    init {
        viewModelScope.launch {
            val res = repository.getConfig()
            if (res is ApiResult.Success) _config.value = res.data
        }
    }

    private var pending: Job? = null

    fun onIdentifier(v: String) = _state.update { it.copy(identifier = v, identifierError = null, errorMessage = null) }
    fun onPassword(v: String) = _state.update { it.copy(password = v, passwordError = null, errorMessage = null) }

    /** Tombol "Masuk": validasi lalu mulai tahap 1 (pemeriksaan keamanan). */
    fun submit() {
        val s = _state.value
        if (s.busy) return // cegah permintaan ganda
        val idErr = if (s.identifier.isBlank()) "NRP / Email wajib diisi" else null
        val pwErr = if (s.password.isEmpty()) "Kata sandi wajib diisi" else null
        if (idErr != null || pwErr != null) {
            _state.update { it.copy(identifierError = idErr, passwordError = pwErr) }
            return
        }
        _state.update { it.copy(step = AuthStep.SECURITY, errorMessage = null, showWidget = false) }
        pending = viewModelScope.launch {
            // Selalu ambil status Turnstile terbaru (Admin bisa mengaktifkannya kapan saja).
            val fresh = withTimeoutOrNull(4_000) { repository.getConfig() }
            if (fresh is ApiResult.Success) _config.value = fresh.data
            if (_state.value.step != AuthStep.SECURITY) return@launch
            startSecurityStep()
        }
    }

    private fun startSecurityStep() {
        val useTurnstile = _config.value?.turnstileEnabled == true
        _state.update { it.copy(step = AuthStep.SECURITY, widgetKey = it.widgetKey + 1, showWidget = useTurnstile) }
        if (!useTurnstile) {
            // Turnstile sedang nonaktif di server: tahap keamanan hanya animasi singkat.
            pending = viewModelScope.launch {
                delay(MIN_STEP_MS)
                if (_state.value.step == AuthStep.SECURITY) authenticate(null)
            }
        }
    }

    fun onTurnstileToken(token: String) {
        if (_state.value.step != AuthStep.SECURITY) return
        // Biarkan tanda "Berhasil" Cloudflare terlihat sejenak sebelum lanjut.
        pending = viewModelScope.launch {
            delay(TOKEN_SHOW_MS)
            if (_state.value.step == AuthStep.SECURITY) authenticate(token)
        }
    }

    fun onTurnstileError(code: String) {
        if (_state.value.step != AuthStep.SECURITY) return
        when {
            code == "expired" -> _state.update { it.copy(widgetKey = it.widgetKey + 1) }
            // Server belum mewajibkan Turnstile → lanjutkan tanpa token.
            _config.value?.turnstileEnabled != true -> authenticate(null)
            else -> _state.update {
                it.copy(step = AuthStep.IDLE, widgetKey = it.widgetKey + 1, errorMessage = turnstileErrorMessage(code))
            }
        }
    }

    fun cancelCheck() {
        if (_state.value.step != AuthStep.SECURITY) return
        pending?.cancel()
        _state.update { it.copy(step = AuthStep.IDLE, widgetKey = it.widgetKey + 1) }
    }

    /** Tahap 2 (cek sesi login di server) dan tahap 3 (masuk ke beranda). */
    private fun authenticate(token: String?) {
        val s = _state.value
        _state.update { it.copy(step = AuthStep.SESSION) }
        viewModelScope.launch {
            val started = System.currentTimeMillis()
            val res = repository.login(s.identifier, s.password, token) {
                val elapsed = System.currentTimeMillis() - started
                if (elapsed < MIN_STEP_MS) delay(MIN_STEP_MS - elapsed)
                _state.update { it.copy(step = AuthStep.FINISHING) }
                delay(FINISH_MS)
            }
            when (res) {
                // Sukses: SessionManager mengganti layar ke beranda secara otomatis.
                is ApiResult.Success -> _state.update { it.copy(password = "") }
                is ApiResult.Failure -> {
                    val elapsed = System.currentTimeMillis() - started
                    if (elapsed < MIN_STEP_MS) delay(MIN_STEP_MS - elapsed)
                    if (token == null && res.error.kind == ErrorKind.VALIDATION && _config.value?.turnstileEnabled != true &&
                        res.error.message.contains("keamanan", ignoreCase = true)
                    ) {
                        // Server ternyata mewajibkan Turnstile (konfigurasi lama di HP) → tampilkan widget.
                        _config.value = _config.value?.copy(turnstileEnabled = true) ?: ConfigResponse(turnstileEnabled = true)
                        startSecurityStep()
                        return@launch
                    }
                    _state.update {
                        it.copy(step = AuthStep.IDLE, widgetKey = it.widgetKey + 1, errorMessage = res.error.message)
                    }
                }
            }
        }
    }
}

data class ActivationUiState(
    val nrp: String = "",
    val token: String = "",
    val password: String = "",
    val confirm: String = "",
    val consent: Boolean = false,
    val errors: Map<String, String> = emptyMap(),
    val errorMessage: String? = null,
    val successMessage: String? = null,
    val step: AuthStep = AuthStep.IDLE,
    val widgetKey: Int = 0,
    val showWidget: Boolean = false,
) {
    val busy: Boolean get() = step != AuthStep.IDLE
}

class ActivationViewModel(private val repository: SindenRepository) : ViewModel() {
    private val _state = MutableStateFlow(ActivationUiState())
    val state: StateFlow<ActivationUiState> = _state.asStateFlow()

    private var turnstileEnforced: Boolean? = null
    private var pending: Job? = null

    init {
        viewModelScope.launch {
            val res = repository.getConfig()
            if (res is ApiResult.Success) turnstileEnforced = res.data.turnstileEnabled
        }
    }

    fun update(transform: (ActivationUiState) -> ActivationUiState) =
        _state.update { transform(it).copy(errorMessage = null) }

    fun submit() {
        val s = _state.value
        if (s.busy) return
        val errors = buildMap {
            if (s.nrp.isBlank()) put("nrp", "NRP / Email wajib diisi")
            if (s.token.isBlank()) put("token", "Token aktivasi dari Admin wajib diisi")
            if (s.password.length < 8) put("password", "Minimal 8 karakter")
            if (s.confirm != s.password) put("confirm", "Konfirmasi kata sandi tidak sama")
            if (!s.consent) put("consent", "Anda harus menyetujui ketentuan untuk mengaktifkan akun")
        }
        if (errors.isNotEmpty()) {
            _state.update { it.copy(errors = errors) }
            return
        }
        _state.update { it.copy(step = AuthStep.SECURITY, errors = emptyMap(), errorMessage = null, showWidget = false) }
        pending = viewModelScope.launch {
            val fresh = withTimeoutOrNull(4_000) { repository.getConfig() }
            if (fresh is ApiResult.Success) turnstileEnforced = fresh.data.turnstileEnabled
            if (_state.value.step == AuthStep.SECURITY) startActivationCheck()
        }
    }

    private fun startActivationCheck() {
        val useTurnstile = turnstileEnforced == true
        _state.update { it.copy(widgetKey = it.widgetKey + 1, showWidget = useTurnstile) }
        if (!useTurnstile) {
            pending = viewModelScope.launch {
                delay(MIN_STEP_MS)
                if (_state.value.step == AuthStep.SECURITY) activate(null)
            }
        }
    }

    fun onTurnstileToken(token: String) {
        if (_state.value.step != AuthStep.SECURITY) return
        activate(token)
    }

    fun onTurnstileError(code: String) {
        if (_state.value.step != AuthStep.SECURITY) return
        when {
            code == "expired" -> _state.update { it.copy(widgetKey = it.widgetKey + 1) }
            turnstileEnforced != true -> activate(null)
            else -> _state.update {
                it.copy(step = AuthStep.IDLE, widgetKey = it.widgetKey + 1, errorMessage = turnstileErrorMessage(code))
            }
        }
    }

    fun cancelCheck() {
        if (_state.value.step != AuthStep.SECURITY) return
        pending?.cancel()
        _state.update { it.copy(step = AuthStep.IDLE, widgetKey = it.widgetKey + 1) }
    }

    private fun activate(turnstileToken: String?) {
        val s = _state.value
        _state.update { it.copy(step = AuthStep.SESSION) }
        viewModelScope.launch {
            val started = System.currentTimeMillis()
            val res = repository.activate(s.nrp, s.token, s.password, turnstileToken)
            val elapsed = System.currentTimeMillis() - started
            if (elapsed < MIN_STEP_MS) delay(MIN_STEP_MS - elapsed)
            when (res) {
                is ApiResult.Success -> {
                    _state.update { it.copy(step = AuthStep.FINISHING) }
                    delay(FINISH_MS)
                    _state.update {
                        it.copy(step = AuthStep.IDLE, successMessage = res.data.message ?: "Aktivasi berhasil. Silakan masuk.")
                    }
                }
                is ApiResult.Failure -> _state.update {
                    it.copy(step = AuthStep.IDLE, widgetKey = it.widgetKey + 1, errorMessage = res.error.message)
                }
            }
        }
    }
}
