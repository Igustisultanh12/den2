package id.my.sisinden.app.ui.dashboard

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import id.my.sisinden.app.data.model.*
import id.my.sisinden.app.data.repository.NetworkResult
import id.my.sisinden.app.data.repository.SindenRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

data class DashboardStats(
    val cashBalance: Double = 0.0,
    val totalLetters: Int = 0,
    val pendingSkhpp: Int = 0,
    val totalNotifications: Int = 0
)

sealed interface DashboardUiState {
    data object Loading : DashboardUiState
    data class Success(
        val stats: DashboardStats,
        val recentLetters: List<LetterLogDto>
    ) : DashboardUiState
    data class Error(val message: String) : DashboardUiState
}

class DashboardViewModel(private val repository: SindenRepository) : ViewModel() {

    private val _uiState = MutableStateFlow<DashboardUiState>(DashboardUiState.Loading)
    val uiState: StateFlow<DashboardUiState> = _uiState.asStateFlow()

    val currentUser = repository.userFlow

    init {
        loadDashboardData()
    }

    fun loadDashboardData() {
        _uiState.value = DashboardUiState.Loading
        viewModelScope.launch {
            var balance = 0.0
            var letterCount = 0
            var skhppPending = 0
            var notifCount = 0
            var lettersList: List<LetterLogDto> = emptyList()

            // 1. Kas
            when (val res = repository.getCashSummary()) {
                is NetworkResult.Success -> balance = res.data.totalBalance
                else -> {}
            }

            // 2. Surat
            when (val res = repository.getLetterLogs()) {
                is NetworkResult.Success -> {
                    letterCount = res.data.data.size
                    lettersList = res.data.data.take(5)
                }
                else -> {}
            }

            // 3. SKHPP
            when (val res = repository.getSkhpp()) {
                is NetworkResult.Success -> {
                    skhppPending = res.data.data.count { it.status.lowercase() == "pending" }
                }
                else -> {}
            }

            // 4. Notifikasi
            when (val res = repository.getNotifications()) {
                is NetworkResult.Success -> {
                    notifCount = res.data.notifications.size
                }
                else -> {}
            }

            _uiState.value = DashboardUiState.Success(
                stats = DashboardStats(
                    cashBalance = balance,
                    totalLetters = letterCount,
                    pendingSkhpp = skhppPending,
                    totalNotifications = notifCount
                ),
                recentLetters = lettersList
            )
        }
    }
}
