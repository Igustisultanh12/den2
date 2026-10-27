package id.my.sisinden.app.ui.dashboard

import android.content.Context
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import id.my.sisinden.app.core.network.ApiResult
import id.my.sisinden.app.core.notification.DutyReminders
import id.my.sisinden.app.core.notification.NotificationSync
import id.my.sisinden.app.core.security.Perm
import id.my.sisinden.app.core.ui.components.LoadState
import id.my.sisinden.app.core.util.Fmt
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
                is ApiResult.Success -> {
                    _state.value = LoadState.Ready(res.data)
                    syncDutyReminders(res.data)
                }
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

    /**
     * Jadwalkan pengingat H-1 untuk tanggal jaga pribadi (bulan berjalan &
     * berikutnya, sesuai data `duty.my_dates` dari server).
     */
    private fun syncDutyReminders(data: DashboardResponse) {
        val duty = data.duty ?: return
        if (!repository.session.permissions.has(Perm.DUTY_VIEW)) return
        val roleByDate = buildMap {
            (listOfNotNull(duty.today) + duty.upcoming.orEmpty()).forEach { day ->
                val mine = day.safeEntries.firstOrNull { it.isMe } ?: return@forEach
                Fmt.parseDate(day.date)?.let { put(it, mine.role) }
            }
        }
        val dates = duty.myDates.orEmpty().mapNotNull { Fmt.parseDate(it) }
        DutyReminders.sync(appContext, dates.associateWith { roleByDate[it] })
    }

    /** Bunyikan notifikasi sistem untuk warta & chat baru (deduplikasi persisten). */
    private suspend fun checkNewNotification() {
        NotificationSync.run(appContext, repository)
    }
}
