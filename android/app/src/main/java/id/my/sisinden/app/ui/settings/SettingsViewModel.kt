package id.my.sisinden.app.ui.settings

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import id.my.sisinden.app.data.model.ConfigResponse
import id.my.sisinden.app.data.model.UserDto
import id.my.sisinden.app.data.repository.NetworkResult
import id.my.sisinden.app.data.repository.SindenRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.launch

data class SettingsUiState(
    val agencyConfig: ConfigResponse? = null,
    val isLoadingConfig: Boolean = false,
    val saveUrlSuccess: Boolean = false,
    val errorMessage: String? = null
)

class SettingsViewModel(private val repository: SindenRepository) : ViewModel() {

    val currentUser: StateFlow<UserDto?> = repository.userFlow
        .stateIn(viewModelScope, SharingStarted.WhileSubscribed(5000), null)

    val currentBaseUrl: StateFlow<String> = repository.baseUrlFlow
        .stateIn(viewModelScope, SharingStarted.WhileSubscribed(5000), "https://sisinden.my.id")

    val isDarkMode: StateFlow<Boolean?> = repository.isDarkModeFlow
        .stateIn(viewModelScope, SharingStarted.WhileSubscribed(5000), null)

    private val _uiState = MutableStateFlow(SettingsUiState())
    val uiState: StateFlow<SettingsUiState> = _uiState.asStateFlow()

    init {
        loadAgencyConfig()
    }

    fun loadAgencyConfig() {
        _uiState.value = _uiState.value.copy(isLoadingConfig = true, errorMessage = null)
        viewModelScope.launch {
            when (val res = repository.getConfig()) {
                is NetworkResult.Success -> {
                    _uiState.value = _uiState.value.copy(
                        agencyConfig = res.data,
                        isLoadingConfig = false
                    )
                }
                is NetworkResult.Error -> {
                    _uiState.value = _uiState.value.copy(
                        isLoadingConfig = false,
                        errorMessage = res.message
                    )
                }
            }
        }
    }

    fun updateBaseUrl(newUrl: String) {
        viewModelScope.launch {
            repository.setBaseUrl(newUrl)
            _uiState.value = _uiState.value.copy(saveUrlSuccess = true)
        }
    }

    fun setDarkMode(mode: Boolean?) {
        viewModelScope.launch {
            repository.setDarkMode(mode)
        }
    }

    fun toggleDarkMode(enabled: Boolean) {
        setDarkMode(enabled)
    }

    fun logout(onLoggedOut: () -> Unit) {
        viewModelScope.launch {
            repository.logout()
            onLoggedOut()
        }
    }

    fun clearFeedback() {
        _uiState.value = _uiState.value.copy(saveUrlSuccess = false, errorMessage = null)
    }
}
