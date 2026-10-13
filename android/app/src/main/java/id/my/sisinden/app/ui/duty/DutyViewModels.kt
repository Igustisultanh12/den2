package id.my.sisinden.app.ui.duty

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import id.my.sisinden.app.core.network.ApiResult
import id.my.sisinden.app.core.ui.components.LoadState
import id.my.sisinden.app.data.model.DutyRosterResponse
import id.my.sisinden.app.data.repository.SindenRepository
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import java.time.LocalDate
import java.time.YearMonth

enum class DutyFilter(val label: String) { ALL("Semua"), MINE("Jadwal saya"), UPCOMING("Mendatang") }

class DutyRosterViewModel(private val repository: SindenRepository) : ViewModel() {

    private val _state = MutableStateFlow<LoadState<DutyRosterResponse>>(LoadState.Loading)
    val state: StateFlow<LoadState<DutyRosterResponse>> = _state.asStateFlow()

    private val _refreshing = MutableStateFlow(false)
    val refreshing: StateFlow<Boolean> = _refreshing.asStateFlow()

    /** Periode yang sedang ditampilkan (default bulan berjalan). */
    private val _period = MutableStateFlow(YearMonth.now())
    val period: StateFlow<YearMonth> = _period.asStateFlow()

    /** Bila pengguna memilih SP tertentu dari daftar. */
    private var selectedSpId: Int? = null

    val filter = MutableStateFlow(DutyFilter.ALL)
    val query = MutableStateFlow("")

    private var job: Job? = null

    init {
        load()
    }

    fun load(pullToRefresh: Boolean = false) {
        job?.cancel()
        if (pullToRefresh) _refreshing.value = true else _state.value = LoadState.Loading
        val ym = _period.value
        val sp = selectedSpId
        job = viewModelScope.launch {
            val res = if (sp != null) repository.dutyRoster(spId = sp)
            else repository.dutyRoster(bulan = ym.monthValue, tahun = ym.year)
            when (res) {
                is ApiResult.Success -> _state.value = LoadState.Ready(res.data)
                is ApiResult.Failure -> if (!pullToRefresh || _state.value !is LoadState.Ready) {
                    _state.value = LoadState.Failed(res.error)
                }
            }
            _refreshing.value = false
        }
    }

    fun shiftMonth(delta: Long) {
        selectedSpId = null
        _period.value = _period.value.plusMonths(delta)
        load()
    }

    fun selectSp(spId: Int, bulan: Int, tahun: Int) {
        selectedSpId = spId
        if (bulan in 1..12 && tahun > 0) _period.value = YearMonth.of(tahun, bulan)
        load()
    }

    fun resetToCurrent() {
        selectedSpId = null
        _period.value = YearMonth.now()
        load()
    }

    /** Tanggal hari ini sebagai string ISO, untuk filter "Mendatang". */
    fun todayIso(): String = LocalDate.now().toString()
}

class DutyDayViewModel(
    private val repository: SindenRepository,
    private val spId: Int?,
    private val date: String,
) : ViewModel() {
    private val _state = MutableStateFlow<LoadState<DutyRosterResponse>>(LoadState.Loading)
    val state: StateFlow<LoadState<DutyRosterResponse>> = _state.asStateFlow()

    init {
        load()
    }

    fun load() {
        _state.value = LoadState.Loading
        viewModelScope.launch {
            val d = runCatching { LocalDate.parse(date) }.getOrNull()
            val res = when {
                spId != null -> repository.dutyRoster(spId = spId)
                d != null -> repository.dutyRoster(bulan = d.monthValue, tahun = d.year)
                else -> repository.dutyRoster()
            }
            _state.value = when (res) {
                is ApiResult.Success -> LoadState.Ready(res.data)
                is ApiResult.Failure -> LoadState.Failed(res.error)
            }
        }
    }
}
