package id.my.sisinden.app.ui.mitra

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import id.my.sisinden.app.data.model.CreateMitraRequest
import id.my.sisinden.app.data.model.MitraDto
import id.my.sisinden.app.data.repository.NetworkResult
import id.my.sisinden.app.data.repository.SindenRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

sealed interface MitraUiState {
    data object Loading : MitraUiState
    data class Success(val list: List<MitraDto>) : MitraUiState
    data class Error(val message: String) : MitraUiState
}

class MitraViewModel(private val repository: SindenRepository) : ViewModel() {

    private val _uiState = MutableStateFlow<MitraUiState>(MitraUiState.Loading)
    val uiState: StateFlow<MitraUiState> = _uiState.asStateFlow()

    private val _searchQuery = MutableStateFlow("")
    val searchQuery: StateFlow<String> = _searchQuery.asStateFlow()

    init {
        loadMitra()
    }

    fun setSearchQuery(query: String) {
        _searchQuery.value = query
        loadMitra()
    }

    fun loadMitra() {
        _uiState.value = MitraUiState.Loading
        viewModelScope.launch {
            val q = _searchQuery.value.ifEmpty { null }
            when (val res = repository.getMitra(q)) {
                is NetworkResult.Success -> _uiState.value = MitraUiState.Success(res.data.data)
                is NetworkResult.Error -> _uiState.value = MitraUiState.Error(res.message)
            }
        }
    }

    fun togglePayment(id: Int, currentStatus: String) {
        viewModelScope.launch {
            val newStatus = if (currentStatus == "sudah_dibayar") "belum_dibayar" else "sudah_dibayar"
            when (repository.toggleMitraPayment(id, newStatus)) {
                is NetworkResult.Success -> loadMitra()
                is NetworkResult.Error -> {}
            }
        }
    }

    fun togglePaymentMonth(id: Int, year: Int, month: Int) {
        viewModelScope.launch {
            when (repository.toggleMitraPayment(id = id, status = "toggle", year = year, month = month)) {
                is NetworkResult.Success -> loadMitra()
                is NetworkResult.Error -> {}
            }
        }
    }

    fun createMitra(name: String, phone: String, sector: String, notes: String, onSuccess: () -> Unit) {
        viewModelScope.launch {
            val req = CreateMitraRequest(name = name, phone = phone, sector = sector, notes = notes)
            when (repository.createMitra(req)) {
                is NetworkResult.Success -> {
                    loadMitra()
                    onSuccess()
                }
                is NetworkResult.Error -> {}
            }
        }
    }

    fun updateMitra(id: Int, name: String, phone: String, sector: String, notes: String, onSuccess: () -> Unit) {
        viewModelScope.launch {
            val req = CreateMitraRequest(name = name, phone = phone, sector = sector, notes = notes)
            when (repository.updateMitra(id, req)) {
                is NetworkResult.Success -> {
                    loadMitra()
                    onSuccess()
                }
                is NetworkResult.Error -> {}
            }
        }
    }

    fun deleteMitra(id: Int, onSuccess: () -> Unit) {
        viewModelScope.launch {
            when (repository.deleteMitra(id)) {
                is NetworkResult.Success -> {
                    loadMitra()
                    onSuccess()
                }
                is NetworkResult.Error -> {}
            }
        }
    }
}
