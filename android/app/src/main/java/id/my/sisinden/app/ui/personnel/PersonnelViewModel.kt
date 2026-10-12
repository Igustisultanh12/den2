package id.my.sisinden.app.ui.personnel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import id.my.sisinden.app.data.model.PersonnelDto
import id.my.sisinden.app.data.repository.NetworkResult
import id.my.sisinden.app.data.repository.SindenRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

sealed interface PersonnelUiState {
    data object Loading : PersonnelUiState
    data class Success(val list: List<PersonnelDto>) : PersonnelUiState
    data class Error(val message: String) : PersonnelUiState
}

class PersonnelViewModel(private val repository: SindenRepository) : ViewModel() {

    private val _uiState = MutableStateFlow<PersonnelUiState>(PersonnelUiState.Loading)
    val uiState: StateFlow<PersonnelUiState> = _uiState.asStateFlow()

    private val _searchQuery = MutableStateFlow("")
    val searchQuery: StateFlow<String> = _searchQuery.asStateFlow()

    init {
        loadPersonnel()
    }

    fun setSearchQuery(query: String) {
        _searchQuery.value = query
    }

    fun loadPersonnel() {
        _uiState.value = PersonnelUiState.Loading
        viewModelScope.launch {
            when (val res = repository.getPersonnel()) {
                is NetworkResult.Success -> _uiState.value = PersonnelUiState.Success(res.data.data)
                is NetworkResult.Error -> _uiState.value = PersonnelUiState.Error(res.message)
            }
        }
    }
}
