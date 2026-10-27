package id.my.sisinden.app.ui.duty

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import id.my.sisinden.app.core.network.ApiError
import id.my.sisinden.app.core.network.ApiResult
import id.my.sisinden.app.core.ui.components.LoadState
import id.my.sisinden.app.data.model.DutyRosterResponse
import id.my.sisinden.app.data.model.PendingSpJagaDto
import id.my.sisinden.app.ui.common.UiMessages
import id.my.sisinden.app.data.repository.SindenRepository
import kotlinx.coroutines.Job
import kotlinx.coroutines.async
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import java.time.YearMonth

/** Jadwal jaga satu bulan (SP Jaga periode tsb) beserta tanggal milik pengguna. */
data class MonthDuty(
    val month: YearMonth,
    val roster: DutyRosterResponse?,
    val error: ApiError?,
) {
    val mine: List<MyDuty> get() = myDutiesOf(roster)
}

/**
 * Daftar Jaga pribadi: bulan berjalan + bulan sebelumnya. Hanya tanggal jaga
 * milik pengguna yang ditampilkan; jadwal lengkap tersedia sebagai PDF resmi.
 */
class DutyRosterViewModel(
    private val repository: SindenRepository,
    /** Pasops (duty_roster.sign): memuat antrean SP Jaga yang menunggu TTE. */
    private val canSign: Boolean = false,
) : ViewModel() {

    private val _state = MutableStateFlow<LoadState<List<MonthDuty>>>(LoadState.Loading)
    val state: StateFlow<LoadState<List<MonthDuty>>> = _state.asStateFlow()

    private val _refreshing = MutableStateFlow(false)
    val refreshing: StateFlow<Boolean> = _refreshing.asStateFlow()

    private val _pending = MutableStateFlow<List<PendingSpJagaDto>>(emptyList())
    val pending: StateFlow<List<PendingSpJagaDto>> = _pending.asStateFlow()

    private val _signing = MutableStateFlow(false)
    val signing: StateFlow<Boolean> = _signing.asStateFlow()

    val messages = UiMessages()

    private var job: Job? = null

    init {
        load()
    }

    fun load(pullToRefresh: Boolean = false) {
        job?.cancel()
        if (pullToRefresh) _refreshing.value = true
        else if (_state.value !is LoadState.Ready) _state.value = LoadState.Loading
        val current = YearMonth.now()
        val months = listOf(current, current.minusMonths(1))
        job = viewModelScope.launch {
            val pendingJob = if (canSign) async { repository.pendingSpJaga() } else null
            val results = months
                .map { ym -> ym to async { repository.dutyRoster(bulan = ym.monthValue, tahun = ym.year) } }
                .map { (ym, deferred) ->
                    when (val res = deferred.await()) {
                        is ApiResult.Success -> MonthDuty(ym, res.data, null)
                        is ApiResult.Failure -> MonthDuty(ym, null, res.error)
                    }
                }
            val allFailed = results.all { it.error != null }
            if (allFailed && (!pullToRefresh || _state.value !is LoadState.Ready)) {
                _state.value = LoadState.Failed(results.first().error!!)
            } else if (!allFailed) {
                _state.value = LoadState.Ready(results)
            }
            pendingJob?.await()?.let { res ->
                if (res is ApiResult.Success) _pending.value = res.data.data.orEmpty()
            }
            _refreshing.value = false
        }
    }

    /** Tandatangani (TTE) SP Jaga — sama dengan tombol TTE Pasops di web. */
    fun approve(id: Int, onDone: () -> Unit) {
        if (_signing.value) return
        _signing.value = true
        viewModelScope.launch {
            when (val res = repository.approveSpJaga(id)) {
                is ApiResult.Success -> {
                    onDone()
                    messages.post(res.data.message?.takeIf { it.isNotBlank() } ?: "SP Jaga berhasil disahkan.")
                    _pending.value = _pending.value.filterNot { it.id == id }
                    load()
                }
                is ApiResult.Failure -> messages.post(res.error.message)
            }
            _signing.value = false
        }
    }
}
