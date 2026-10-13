package id.my.sisinden.app.ui.dashboard

import android.content.Context
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import id.my.sisinden.app.core.notification.NotificationHelper
import id.my.sisinden.app.data.model.*
import id.my.sisinden.app.data.repository.NetworkResult
import id.my.sisinden.app.data.repository.SindenRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

data class DashboardStats(
    val cashBalance: Double = 0.0,
    val totalIncome: Double = 0.0,
    val totalExpense: Double = 0.0,
    val technicalCashBalance: Double = 0.0,
    val totalLetters: Int = 0,
    val pendingSkhpp: Int = 0,
    val totalNotifications: Int = 0,
    val unreadNotifCount: Int = 0,
    val totalTransactions: Int = 0
)

sealed interface DashboardUiState {
    data object Loading : DashboardUiState
    data class Success(
        val stats: DashboardStats,
        val recentLetters: List<LetterLogDto>,
        val recentTransactions: List<CashTransactionDto>
    ) : DashboardUiState
    data class Error(val message: String) : DashboardUiState
}

class DashboardViewModel(
    private val repository: SindenRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow<DashboardUiState>(DashboardUiState.Loading)
    val uiState: StateFlow<DashboardUiState> = _uiState.asStateFlow()

    private val _isPrivacyHidden = MutableStateFlow(false)
    val isPrivacyHidden: StateFlow<Boolean> = _isPrivacyHidden.asStateFlow()

    private val _configState = MutableStateFlow<ConfigResponse?>(null)
    val configState: StateFlow<ConfigResponse?> = _configState.asStateFlow()

    val currentUser = repository.userFlow
    val isDarkMode = repository.isDarkModeFlow

    private var lastNotifiedNotifId = 0

    init {
        loadConfig()
        loadDashboardData()
    }

    fun toggleDarkMode(current: Boolean?) {
        viewModelScope.launch {
            repository.setDarkMode(if (current == true) false else true)
        }
    }

    fun logout(onSuccess: () -> Unit) {
        viewModelScope.launch {
            repository.logout()
            onSuccess()
        }
    }

    fun togglePrivacy() {
        _isPrivacyHidden.value = !_isPrivacyHidden.value
    }

    fun loadConfig() {
        viewModelScope.launch {
            when (val res = repository.getConfig()) {
                is NetworkResult.Success -> _configState.value = res.data
                else -> {}
            }
        }
    }

    fun loadDashboardData(context: Context? = null) {
        _uiState.value = DashboardUiState.Loading
        viewModelScope.launch {
            var balance = 0.0
            var income = 0.0
            var expense = 0.0
            var techBalance = 0.0
            var letterCount = 0
            var skhppPending = 0
            var notifCount = 0
            var unreadCount = 0
            var txCount = 0
            var lettersList: List<LetterLogDto> = emptyList()
            var txList: List<CashTransactionDto> = emptyList()

            // 1. Kas Denintel
            when (val res = repository.getCashSummary()) {
                is NetworkResult.Success -> {
                    balance = res.data.totalBalance
                    income = res.data.totalIncome
                    expense = res.data.totalExpense
                    txCount = res.data.transactions.size
                    txList = res.data.transactions.take(5)
                }
                else -> {}
            }

            // 2. Kas Taktis Operasi
            when (val res = repository.getTechnicalCash()) {
                is NetworkResult.Success -> {
                    techBalance = res.data.balance
                }
                else -> {}
            }

            // 3. Surat
            when (val res = repository.getLetterLogs()) {
                is NetworkResult.Success -> {
                    letterCount = res.data.data.size
                    lettersList = res.data.data.take(5)
                }
                else -> {}
            }

            // 4. SKHPP
            when (val res = repository.getSkhpp()) {
                is NetworkResult.Success -> {
                    skhppPending = res.data.data.count { it.status.lowercase() == "pending" }
                }
                else -> {}
            }

            // 5. Notifikasi Dinas
            when (val res = repository.getNotifications()) {
                is NetworkResult.Success -> {
                    val notifs = res.data.notifications
                    notifCount = notifs.size
                    unreadCount = notifs.count { !it.isRead }

                    // Bunyikan notifikasi sistem bila ada pemberitahuan baru dengan deduplikasi persisten
                    if (context != null && notifs.isNotEmpty()) {
                        val latest = notifs.first()
                        NotificationHelper.processNewNotification(
                            context = context,
                            notificationId = latest.id,
                            title = latest.title.ifEmpty { "Warta Dinas SINDEN" },
                            message = latest.message.ifEmpty { "Terdapat instruksi kedinasan baru." },
                            isRead = latest.isRead
                        )
                    }
                }
                else -> {}
            }

            _uiState.value = DashboardUiState.Success(
                stats = DashboardStats(
                    cashBalance = balance,
                    totalIncome = income,
                    totalExpense = expense,
                    technicalCashBalance = techBalance,
                    totalLetters = letterCount,
                    pendingSkhpp = skhppPending,
                    totalNotifications = notifCount,
                    unreadNotifCount = unreadCount,
                    totalTransactions = txCount
                ),
                recentLetters = lettersList,
                recentTransactions = txList
            )
        }
    }
}
