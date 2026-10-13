package id.my.sisinden.app.ui.attendance

import android.annotation.SuppressLint
import android.content.Context
import android.location.Location
import android.location.LocationManager
import android.os.Build
import android.os.CancellationSignal
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import id.my.sisinden.app.core.network.ApiResult
import id.my.sisinden.app.core.ui.components.LoadState
import id.my.sisinden.app.data.model.AttendanceResponse
import id.my.sisinden.app.data.model.AttendanceStatus
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.ui.common.UiMessages
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlinx.coroutines.withTimeoutOrNull
import kotlin.coroutines.resume

/** Koordinat nyata dari perangkat (tidak ada koordinat bawaan/dummy). */
data class GeoPoint(val latitude: Double, val longitude: Double, val accuracyMeters: Float?)

data class AttendanceForm(
    val status: AttendanceStatus = AttendanceStatus.HADIR,
    val notes: String = "",
    val location: GeoPoint? = null,
    val locating: Boolean = false,
    val locationError: String? = null,
    val submitting: Boolean = false,
)

class AttendanceViewModel(
    private val repository: SindenRepository,
    private val appContext: Context,
) : ViewModel() {

    private val _state = MutableStateFlow<LoadState<AttendanceResponse>>(LoadState.Loading)
    val state: StateFlow<LoadState<AttendanceResponse>> = _state.asStateFlow()

    private val _form = MutableStateFlow(AttendanceForm())
    val form: StateFlow<AttendanceForm> = _form.asStateFlow()

    private val _refreshing = MutableStateFlow(false)
    val refreshing: StateFlow<Boolean> = _refreshing.asStateFlow()

    val messages = UiMessages()

    init {
        load()
    }

    fun load(pullToRefresh: Boolean = false) {
        if (pullToRefresh) _refreshing.value = true else if (_state.value !is LoadState.Ready) _state.value = LoadState.Loading
        viewModelScope.launch {
            when (val res = repository.attendances()) {
                is ApiResult.Success -> _state.value = LoadState.Ready(res.data)
                is ApiResult.Failure -> {
                    if (_state.value is LoadState.Ready) {
                        messages.post(res.error.message)
                    } else {
                        _state.value = LoadState.Failed(res.error)
                    }
                }
            }
            _refreshing.value = false
        }
    }

    fun setStatus(status: AttendanceStatus) = _form.update { it.copy(status = status) }
    fun setNotes(notes: String) = _form.update { it.copy(notes = notes.take(1000)) }
    fun clearLocation() = _form.update { it.copy(location = null, locationError = null) }

    /** Dipanggil setelah izin lokasi diberikan pengguna. */
    @SuppressLint("MissingPermission")
    fun fetchLocation() {
        if (_form.value.locating) return
        _form.update { it.copy(locating = true, locationError = null) }
        viewModelScope.launch {
            val point = runCatching { currentLocation() }.getOrNull()
            _form.update {
                if (point != null) it.copy(locating = false, location = point)
                else it.copy(locating = false, locationError = "Lokasi belum didapat. Pastikan GPS aktif lalu coba lagi, atau kirim tanpa lokasi.")
            }
        }
    }

    @SuppressLint("MissingPermission")
    private suspend fun currentLocation(): GeoPoint? {
        val lm = appContext.getSystemService(Context.LOCATION_SERVICE) as? LocationManager ?: return null
        val providers = listOf(LocationManager.GPS_PROVIDER, LocationManager.NETWORK_PROVIDER)
            .filter { runCatching { lm.isProviderEnabled(it) }.getOrDefault(false) }
        if (providers.isEmpty()) return null

        // 1) Coba lokasi segar (Android 11+), batas 12 detik.
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            for (provider in providers) {
                val fresh = withTimeoutOrNull(12_000) {
                    suspendCancellableCoroutine<Location?> { cont ->
                        val signal = CancellationSignal()
                        cont.invokeOnCancellation { signal.cancel() }
                        lm.getCurrentLocation(provider, signal, appContext.mainExecutor) { loc -> cont.resume(loc) }
                    }
                }
                if (fresh != null) return fresh.toGeoPoint()
            }
        }
        // 2) Lokasi terakhir yang diketahui (maks. 10 menit) sebagai cadangan.
        val recent = providers.mapNotNull { runCatching { lm.getLastKnownLocation(it) }.getOrNull() }
            .filter { System.currentTimeMillis() - it.time < 10 * 60 * 1000 }
            .maxByOrNull { it.time }
        return recent?.toGeoPoint()
    }

    private fun Location.toGeoPoint() = GeoPoint(latitude, longitude, if (hasAccuracy()) accuracy else null)

    fun submit() {
        val f = _form.value
        if (f.submitting) return
        _form.update { it.copy(submitting = true) }
        viewModelScope.launch {
            val res = repository.recordAttendance(
                status = f.status.code,
                lat = f.location?.latitude,
                lng = f.location?.longitude,
                notes = f.notes,
            )
            _form.update { it.copy(submitting = false) }
            when (res) {
                is ApiResult.Success -> {
                    messages.post(res.data.message ?: "Presensi tercatat.")
                    _form.update { it.copy(notes = "") }
                    load()
                }
                is ApiResult.Failure -> messages.post(res.error.message)
            }
        }
    }
}
