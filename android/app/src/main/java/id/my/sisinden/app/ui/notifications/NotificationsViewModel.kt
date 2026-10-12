package id.my.sisinden.app.ui.notifications

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import id.my.sisinden.app.data.model.NotificationDto
import id.my.sisinden.app.data.repository.NetworkResult
import id.my.sisinden.app.data.repository.SindenRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

sealed interface NotificationUiState {
    data object Loading : NotificationUiState
    data class Success(val list: List<NotificationDto>) : NotificationUiState
    data class Error(val message: String) : NotificationUiState
}

class NotificationsViewModel(private val repository: SindenRepository) : ViewModel() {

    private val _uiState = MutableStateFlow<NotificationUiState>(NotificationUiState.Loading)
    val uiState: StateFlow<NotificationUiState> = _uiState.asStateFlow()

    init {
        loadNotifications()
    }

    fun loadNotifications() {
        _uiState.value = NotificationUiState.Loading
        viewModelScope.launch {
            when (val res = repository.getNotifications()) {
                is NetworkResult.Success -> _uiState.value = NotificationUiState.Success(res.data.notifications)
                is NetworkResult.Error -> _uiState.value = NotificationUiState.Error(res.message)
            }
        }
    }
}
