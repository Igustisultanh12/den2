package id.my.sisinden.app.ui.skhpp

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import id.my.sisinden.app.data.model.CreateSkhppRequest
import id.my.sisinden.app.data.model.SkhppDto
import id.my.sisinden.app.data.repository.NetworkResult
import id.my.sisinden.app.data.repository.SindenRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

sealed interface SkhppUiState {
    data object Loading : SkhppUiState
    data class Success(val list: List<SkhppDto>) : SkhppUiState
    data class Error(val message: String) : SkhppUiState
}

class SkhppViewModel(private val repository: SindenRepository) : ViewModel() {

    private val _uiState = MutableStateFlow<SkhppUiState>(SkhppUiState.Loading)
    val uiState: StateFlow<SkhppUiState> = _uiState.asStateFlow()

    private val _statusFilter = MutableStateFlow<String?>(null)
    val statusFilter: StateFlow<String?> = _statusFilter.asStateFlow()

    private val _searchQuery = MutableStateFlow("")
    val searchQuery: StateFlow<String> = _searchQuery.asStateFlow()

    val currentUser = repository.userFlow

    init {
        loadSkhpp()
    }

    fun setFilter(status: String?) {
        _statusFilter.value = status
        loadSkhpp()
    }

    fun setSearchQuery(query: String) {
        _searchQuery.value = query
        loadSkhpp()
    }

    fun loadSkhpp() {
        _uiState.value = SkhppUiState.Loading
        viewModelScope.launch {
            val status = _statusFilter.value
            val query = _searchQuery.value.ifEmpty { null }
            when (val res = repository.getSkhpp(status, query)) {
                is NetworkResult.Success -> {
                    _uiState.value = SkhppUiState.Success(res.data.data)
                }
                is NetworkResult.Error -> {
                    _uiState.value = SkhppUiState.Error(res.message)
                }
            }
        }
    }

    fun createSkhpp(name: String, nrp: String, pangkat: String, purpose: String, onSuccess: () -> Unit) {
        viewModelScope.launch {
            val req = CreateSkhppRequest(name, nrp, pangkat, purpose)
            when (repository.createSkhpp(req)) {
                is NetworkResult.Success -> {
                    loadSkhpp()
                    onSuccess()
                }
                is NetworkResult.Error -> {}
            }
        }
    }

    fun verifySkhpp(id: Int) {
        viewModelScope.launch {
            when (repository.verifySkhpp(id)) {
                is NetworkResult.Success -> {
                    loadSkhpp()
                }
                is NetworkResult.Error -> {}
            }
        }
    }
}
