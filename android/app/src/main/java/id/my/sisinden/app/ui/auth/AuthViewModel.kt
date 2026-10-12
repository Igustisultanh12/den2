package id.my.sisinden.app.ui.auth

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import id.my.sisinden.app.data.model.ConfigResponse
import id.my.sisinden.app.data.model.UserDto
import id.my.sisinden.app.data.repository.NetworkResult
import id.my.sisinden.app.data.repository.SindenRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

sealed interface LoginUiState {
    data object Idle : LoginUiState
    data object Loading : LoginUiState
    data class Success(val user: UserDto) : LoginUiState
    data class Error(val message: String) : LoginUiState
}

class AuthViewModel(private val repository: SindenRepository) : ViewModel() {

    private val _loginState = MutableStateFlow<LoginUiState>(LoginUiState.Idle)
    val loginState: StateFlow<LoginUiState> = _loginState.asStateFlow()

    private val _configState = MutableStateFlow<ConfigResponse?>(null)
    val configState: StateFlow<ConfigResponse?> = _configState.asStateFlow()

    val currentUser = repository.userFlow
    val currentToken = repository.tokenFlow

    init {
        fetchConfig()
    }

    fun fetchConfig() {
        viewModelScope.launch {
            when (val result = repository.getConfig()) {
                is NetworkResult.Success -> _configState.value = result.data
                is NetworkResult.Error -> {}
            }
        }
    }

    fun login(input: String, pass: String) {
        val trimmedInput = input.trim()
        if (trimmedInput.isEmpty() || pass.isEmpty()) {
            _loginState.value = LoginUiState.Error("NRP / Email dan kata sandi wajib diisi.")
            return
        }

        _loginState.value = LoginUiState.Loading
        viewModelScope.launch {
            when (val result = repository.login(trimmedInput, pass)) {
                is NetworkResult.Success -> {
                    val user = result.data.user
                    if (user != null) {
                        _loginState.value = LoginUiState.Success(user)
                    } else {
                        _loginState.value = LoginUiState.Error("Data profil tidak valid.")
                    }
                }
                is NetworkResult.Error -> {
                    _loginState.value = LoginUiState.Error(result.message)
                }
            }
        }
    }

    fun resetState() {
        _loginState.value = LoginUiState.Idle
    }

    fun logout() {
        viewModelScope.launch {
            repository.logout()
            _loginState.value = LoginUiState.Idle
        }
    }
}
