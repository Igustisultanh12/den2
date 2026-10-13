package id.my.sisinden.app.ui.common

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import id.my.sisinden.app.core.network.ApiResult
import id.my.sisinden.app.core.ui.components.LoadState
import id.my.sisinden.app.data.model.SimpleResponse
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

/**
 * Pola dasar layar modul: muat data, pull-to-refresh, dan aksi tulis
 * (tambah/ubah/hapus) dengan pencegahan permintaan ganda serta umpan balik.
 */
abstract class LoadableViewModel<T> : ViewModel() {

    private val _state = MutableStateFlow<LoadState<T>>(LoadState.Loading)
    val state: StateFlow<LoadState<T>> = _state.asStateFlow()

    private val _refreshing = MutableStateFlow(false)
    val refreshing: StateFlow<Boolean> = _refreshing.asStateFlow()

    /** true selama aksi tulis berjalan (tombol simpan menampilkan loading). */
    private val _busy = MutableStateFlow(false)
    val busy: StateFlow<Boolean> = _busy.asStateFlow()

    val messages = UiMessages()

    private var loadJob: Job? = null

    protected abstract suspend fun fetch(): ApiResult<T>

    fun load(pullToRefresh: Boolean = false) {
        loadJob?.cancel()
        if (pullToRefresh) _refreshing.value = true
        else if (_state.value !is LoadState.Ready) _state.value = LoadState.Loading
        loadJob = viewModelScope.launch {
            when (val res = fetch()) {
                is ApiResult.Success -> _state.value = LoadState.Ready(res.data)
                is ApiResult.Failure -> {
                    if (_state.value is LoadState.Ready) messages.post(res.error.message)
                    else _state.value = LoadState.Failed(res.error)
                }
            }
            _refreshing.value = false
        }
    }

    /** Muat ulang dari awal (mis. ganti filter) dengan skeleton. */
    fun reload() {
        _state.value = LoadState.Loading
        load()
    }

    /**
     * Jalankan aksi tulis. [onSuccess] dipanggil (mis. menutup form) lalu data dimuat ulang.
     * Pesan sukses dari server diutamakan.
     */
    protected fun action(
        successMessage: String,
        onSuccess: () -> Unit = {},
        block: suspend () -> ApiResult<SimpleResponse>,
    ) {
        if (_busy.value) return
        _busy.value = true
        viewModelScope.launch {
            when (val res = block()) {
                is ApiResult.Success -> {
                    onSuccess()
                    messages.post(res.data.message?.takeIf { it.isNotBlank() } ?: successMessage)
                    load()
                }
                is ApiResult.Failure -> messages.post(res.error.message)
            }
            _busy.value = false
        }
    }
}
