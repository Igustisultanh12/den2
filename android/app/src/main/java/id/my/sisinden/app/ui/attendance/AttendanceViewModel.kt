package id.my.sisinden.app.ui.attendance

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import id.my.sisinden.app.data.model.AttendanceDto
import id.my.sisinden.app.data.model.CreateAttendanceRequest
import id.my.sisinden.app.data.repository.NetworkResult
import id.my.sisinden.app.data.repository.SindenRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

sealed interface AttendanceUiState {
    data object Loading : AttendanceUiState
    data class Success(
        val todayAttendance: AttendanceDto?,
        val attendances: List<AttendanceDto>,
        val totalAttendedToday: Int,
        val totalActivePersonnel: Int
    ) : AttendanceUiState
    data class Error(val message: String) : AttendanceUiState
}

sealed interface AttendanceActionUiState {
    data object Idle : AttendanceActionUiState
    data object Loading : AttendanceActionUiState
    data class Success(val message: String) : AttendanceActionUiState
    data class Error(val message: String) : AttendanceActionUiState
}

class AttendanceViewModel(
    private val repository: SindenRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow<AttendanceUiState>(AttendanceUiState.Loading)
    val uiState: StateFlow<AttendanceUiState> = _uiState.asStateFlow()

    private val _actionState = MutableStateFlow<AttendanceActionUiState>(AttendanceActionUiState.Idle)
    val actionState: StateFlow<AttendanceActionUiState> = _actionState.asStateFlow()

    init {
        loadAttendances()
    }

    fun loadAttendances() {
        viewModelScope.launch {
            _uiState.value = AttendanceUiState.Loading
            when (val result = repository.getAttendances()) {
                is NetworkResult.Success -> {
                    _uiState.value = AttendanceUiState.Success(
                        todayAttendance = result.data.todayAttendance,
                        attendances = result.data.attendances,
                        totalAttendedToday = result.data.totalAttendedToday,
                        totalActivePersonnel = result.data.totalActivePersonnel
                    )
                }
                is NetworkResult.Error -> {
                    _uiState.value = AttendanceUiState.Error(result.message)
                }
            }
        }
    }

    fun submitAttendance(
        status: String = "hadir",
        latitude: Double? = null,
        longitude: Double? = null,
        locationName: String? = null,
        deviceModel: String? = null,
        notes: String? = null,
        photo: String? = null,
        onSuccess: (() -> Unit)? = null
    ) {
        viewModelScope.launch {
            _actionState.value = AttendanceActionUiState.Loading
            val request = CreateAttendanceRequest(
                status = status,
                latitude = latitude,
                longitude = longitude,
                locationName = locationName,
                deviceModel = deviceModel,
                notes = notes,
                photo = photo
            )

            when (val result = repository.recordAttendance(request)) {
                is NetworkResult.Success -> {
                    val msg = result.data.message ?: "Presensi berhasil dicatat."
                    _actionState.value = AttendanceActionUiState.Success(msg)
                    loadAttendances()
                    onSuccess?.invoke()
                }
                is NetworkResult.Error -> {
                    _actionState.value = AttendanceActionUiState.Error(result.message)
                }
            }
        }
    }

    fun resetActionState() {
        _actionState.value = AttendanceActionUiState.Idle
    }
}
