package id.my.sisinden.app.ui.signature

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import id.my.sisinden.app.data.model.SignatureRequestDto
import id.my.sisinden.app.data.repository.NetworkResult
import id.my.sisinden.app.data.repository.SindenRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

sealed interface SignatureUiState {
    data object Loading : SignatureUiState
    data class Success(val list: List<SignatureRequestDto>) : SignatureUiState
    data class Error(val message: String) : SignatureUiState
}

class SignatureViewModel(private val repository: SindenRepository) : ViewModel() {

    private val _uiState = MutableStateFlow<SignatureUiState>(SignatureUiState.Loading)
    val uiState: StateFlow<SignatureUiState> = _uiState.asStateFlow()

    init {
        loadRequests()
    }

    fun loadRequests() {
        _uiState.value = SignatureUiState.Loading
        viewModelScope.launch {
            when (val res = repository.getSignatureRequests()) {
                is NetworkResult.Success -> _uiState.value = SignatureUiState.Success(res.data.requests)
                is NetworkResult.Error -> _uiState.value = SignatureUiState.Error(res.message)
            }
        }
    }

    fun signDocument(id: Int) {
        viewModelScope.launch {
            when (repository.signDocument(id)) {
                is NetworkResult.Success -> loadRequests()
                is NetworkResult.Error -> {}
            }
        }
    }
}
