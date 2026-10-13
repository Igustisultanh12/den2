package id.my.sisinden.app.ui.cash

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import id.my.sisinden.app.data.model.*
import id.my.sisinden.app.data.repository.NetworkResult
import id.my.sisinden.app.data.repository.SindenRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

sealed interface CashUiState {
    data object Loading : CashUiState
    data class Success(val data: CashResponse) : CashUiState
    data class Error(val message: String) : CashUiState
}

sealed interface TechnicalCashUiState {
    data object Loading : TechnicalCashUiState
    data class Success(val data: TechnicalCashResponse) : TechnicalCashUiState
    data class Error(val message: String) : TechnicalCashUiState
}

class CashViewModel(private val repository: SindenRepository) : ViewModel() {

    private val _cashState = MutableStateFlow<CashUiState>(CashUiState.Loading)
    val cashState: StateFlow<CashUiState> = _cashState.asStateFlow()

    private val _technicalCashState = MutableStateFlow<TechnicalCashUiState>(TechnicalCashUiState.Loading)
    val technicalCashState: StateFlow<TechnicalCashUiState> = _technicalCashState.asStateFlow()

    init {
        loadCash()
        loadTechnicalCash()
    }

    fun loadCash() {
        _cashState.value = CashUiState.Loading
        viewModelScope.launch {
            when (val res = repository.getCashSummary()) {
                is NetworkResult.Success -> _cashState.value = CashUiState.Success(res.data)
                is NetworkResult.Error -> _cashState.value = CashUiState.Error(res.message)
            }
        }
    }

    fun loadTechnicalCash() {
        _technicalCashState.value = TechnicalCashUiState.Loading
        viewModelScope.launch {
            when (val res = repository.getTechnicalCash()) {
                is NetworkResult.Success -> _technicalCashState.value = TechnicalCashUiState.Success(res.data)
                is NetworkResult.Error -> _technicalCashState.value = TechnicalCashUiState.Error(res.message)
            }
        }
    }

    fun addCashTransaction(type: String, amount: Double, category: String, desc: String, onSuccess: () -> Unit) {
        viewModelScope.launch {
            val req = CreateCashRequest(type = type, amount = amount, category = category, description = desc)
            when (repository.createCashTransaction(req)) {
                is NetworkResult.Success -> {
                    loadCash()
                    onSuccess()
                }
                is NetworkResult.Error -> {}
            }
        }
    }

    fun editCashTransaction(id: Int, type: String, amount: Double, category: String, desc: String, onSuccess: () -> Unit) {
        viewModelScope.launch {
            val req = CreateCashRequest(type = type, amount = amount, category = category, description = desc)
            when (repository.updateCashTransaction(id, req)) {
                is NetworkResult.Success -> {
                    loadCash()
                    onSuccess()
                }
                is NetworkResult.Error -> {}
            }
        }
    }

    fun deleteCashTransaction(id: Int, onSuccess: () -> Unit) {
        viewModelScope.launch {
            when (repository.deleteCashTransaction(id)) {
                is NetworkResult.Success -> {
                    loadCash()
                    onSuccess()
                }
                is NetworkResult.Error -> {}
            }
        }
    }

    fun addTechnicalCashTransaction(type: String, amount: Double, desc: String, onSuccess: () -> Unit) {
        viewModelScope.launch {
            val req = CreateTechnicalCashRequest(type = type, amount = amount, description = desc)
            when (repository.createTechnicalCash(req)) {
                is NetworkResult.Success -> {
                    loadTechnicalCash()
                    onSuccess()
                }
                is NetworkResult.Error -> {}
            }
        }
    }

    fun editTechnicalCashTransaction(id: Int, type: String, amount: Double, desc: String, onSuccess: () -> Unit) {
        viewModelScope.launch {
            val req = CreateTechnicalCashRequest(type = type, amount = amount, description = desc)
            when (repository.updateTechnicalCash(id, req)) {
                is NetworkResult.Success -> {
                    loadTechnicalCash()
                    onSuccess()
                }
                is NetworkResult.Error -> {}
            }
        }
    }

    fun deleteTechnicalCashTransaction(id: Int, onSuccess: () -> Unit) {
        viewModelScope.launch {
            when (repository.deleteTechnicalCash(id)) {
                is NetworkResult.Success -> {
                    loadTechnicalCash()
                    onSuccess()
                }
                is NetworkResult.Error -> {}
            }
        }
    }
}
