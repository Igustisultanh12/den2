package id.my.sisinden.app.ui.dashboard

import android.content.Context
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import id.my.sisinden.app.core.network.ApiResult
import id.my.sisinden.app.core.notification.NotificationHelper
import id.my.sisinden.app.core.security.Perm
import id.my.sisinden.app.core.ui.components.LoadState
import id.my.sisinden.app.data.model.DashboardResponse
import id.my.sisinden.app.data.repository.SindenRepository
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.launch

class DashboardViewModel(
    private val repository: SindenRepository,
    private val appContext: Context,
) : ViewModel() {

    private val _state = MutableStateFlow<LoadState<DashboardResponse>>(LoadState.Loading)
    val state: StateFlow<LoadState<DashboardResponse>> = _state.asStateFlow()

    private val _refreshing = MutableStateFlow(false)
    val refreshing: StateFlow<Boolean> = _refreshing.asStateFlow()

    val hideBalance: StateFlow<Boolean> = repository.hideBalanceFlow
        .stateIn(viewModelScope, SharingStarted.WhileSubscribed(5_000), false)

    private var job: Job? = null

    init {
        load()
    }

    fun load(pullToRefresh: Boolean = false) {
        if (job?.isActive == true) return // hindari permintaan ganda
        if (pullToRefresh) _refreshing.value = true
        else if (_state.value !is LoadState.Ready) _state.value = LoadState.Loading

        job = viewModelScope.launch {
            when (val res = repository.dashboard()) {
                is ApiResult.Success -> _state.value = LoadState.Ready(res.data)
                is ApiResult.Failure -> {
                    // Pertahankan data lama saat refresh gagal; tampilkan error hanya bila belum ada data.
                    if (_state.value !is LoadState.Ready) _state.value = LoadState.Failed(res.error)
                }
            }
            _refreshing.value = false
            checkNewNotification()
        }
    }

    fun toggleHideBalance() {
        viewModelScope.launch { repository.setHideBalance(!hideBalance.value) }
    }

    /** Bunyikan notifikasi sistem untuk warta baru (deduplikasi persisten di NotificationHelper). */
    private suspend fun checkNewNotification() {
        if (!repository.session.permissions.has(Perm.NOTIFICATIONS_VIEW)) return
        val res = repository.notifications()
        if (res is ApiResult.Success) {
            val latest = res.data.notifications.orEmpty().firstOrNull() ?: return
            NotificationHelper.processNewNotification(
                context = appContext,
                notificationId = latest.id,
                title = latest.title?.ifBlank { null } ?: "Warta Dinas Sinden 2",
                message = latest.message?.ifBlank { null } ?: "Terdapat pemberitahuan kedinasan baru.",
                isRead = latest.isRead,
            )
        }
    }
}
