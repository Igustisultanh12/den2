package id.my.sisinden.app.ui.letters

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import id.my.sisinden.app.data.model.CreateLetterLogRequest
import id.my.sisinden.app.data.model.LetterLogDto
import id.my.sisinden.app.data.repository.NetworkResult
import id.my.sisinden.app.data.repository.SindenRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

sealed interface LetterUiState {
    data object Loading : LetterUiState
    data class Success(val letters: List<LetterLogDto>) : LetterUiState
    data class Error(val message: String) : LetterUiState
}

class LetterViewModel(private val repository: SindenRepository) : ViewModel() {

    private val _uiState = MutableStateFlow<LetterUiState>(LetterUiState.Loading)
    val uiState: StateFlow<LetterUiState> = _uiState.asStateFlow()

    private val _activeFilter = MutableStateFlow<String?>(null) // null = Semua, masuk, keluar, disposisi
    val activeFilter: StateFlow<String?> = _activeFilter.asStateFlow()

    private val _searchQuery = MutableStateFlow("")
    val searchQuery: StateFlow<String> = _searchQuery.asStateFlow()

    private val _createState = MutableStateFlow<String?>(null)
    val createState: StateFlow<String?> = _createState.asStateFlow()

    init {
        loadLetters()
    }

    fun setFilter(type: String?) {
        _activeFilter.value = type
        loadLetters()
    }

    fun setSearchQuery(query: String) {
        _searchQuery.value = query
        loadLetters()
    }

    fun loadLetters() {
        _uiState.value = LetterUiState.Loading
        viewModelScope.launch {
            val type = _activeFilter.value
            val query = _searchQuery.value.ifEmpty { null }
            when (val res = repository.getLetterLogs(type, query)) {
                is NetworkResult.Success -> {
                    _uiState.value = LetterUiState.Success(res.data.data)
                }
                is NetworkResult.Error -> {
                    _uiState.value = LetterUiState.Error(res.message)
                }
            }
        }
    }

    fun createLetter(
        type: String,
        number: String,
        sender: String,
        recipient: String,
        subject: String,
        date: String,
        notes: String,
        onSuccess: () -> Unit
    ) {
        if (number.trim().isEmpty() || subject.trim().isEmpty()) {
            _createState.value = "Nomor surat dan perihal wajib diisi."
            return
        }

        viewModelScope.launch {
            val req = CreateLetterLogRequest(
                type = type,
                letterNumber = number,
                sender = sender,
                recipient = recipient,
                subject = subject,
                date = date,
                notes = notes
            )
            when (val res = repository.createLetterLog(req)) {
                is NetworkResult.Success -> {
                    _createState.value = null
                    loadLetters()
                    onSuccess()
                }
                is NetworkResult.Error -> {
                    _createState.value = res.message
                }
            }
        }
    }

    fun resetCreateState() {
        _createState.value = null
    }
}
