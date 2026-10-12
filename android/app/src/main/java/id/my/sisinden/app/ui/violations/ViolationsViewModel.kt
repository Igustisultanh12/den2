package id.my.sisinden.app.ui.violations

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import id.my.sisinden.app.data.model.CreateViolationRequest
import id.my.sisinden.app.data.model.ViolationDto
import id.my.sisinden.app.data.repository.NetworkResult
import id.my.sisinden.app.data.repository.SindenRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

sealed interface ViolationUiState {
    data object Loading : ViolationUiState
    data class Success(val list: List<ViolationDto>) : ViolationUiState
    data class Error(val message: String) : ViolationUiState
}

class ViolationsViewModel(private val repository: SindenRepository) : ViewModel() {

    private val _uiState = MutableStateFlow<ViolationUiState>(ViolationUiState.Loading)
    val uiState: StateFlow<ViolationUiState> = _uiState.asStateFlow()

    private val _searchQuery = MutableStateFlow("")
    val searchQuery: StateFlow<String> = _searchQuery.asStateFlow()

    init {
        loadViolations()
    }

    fun setSearchQuery(query: String) {
        _searchQuery.value = query
        loadViolations()
    }

    fun loadViolations() {
        _uiState.value = ViolationUiState.Loading
        viewModelScope.launch {
            val q = _searchQuery.value.ifEmpty { null }
            when (val res = repository.getViolations(q)) {
                is NetworkResult.Success -> _uiState.value = ViolationUiState.Success(res.data.data)
                is NetworkResult.Error -> _uiState.value = ViolationUiState.Error(res.message)
            }
        }
    }

    fun createViolation(userId: Int, type: String, desc: String, date: String, onSuccess: () -> Unit) {
        viewModelScope.launch {
            val req = CreateViolationRequest(userId, type, desc, date)
            when (repository.createViolation(req)) {
                is NetworkResult.Success -> {
                    loadViolations()
                    onSuccess()
                }
                is NetworkResult.Error -> {}
            }
        }
    }
}
