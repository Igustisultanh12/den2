package id.my.sisinden.app.ui.chat

import android.content.Context
import android.net.Uri
import android.provider.OpenableColumns
import android.webkit.MimeTypeMap
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import id.my.sisinden.app.core.network.ApiError
import id.my.sisinden.app.core.network.ApiResult
import id.my.sisinden.app.core.notification.NotificationHelper
import id.my.sisinden.app.core.ui.components.LoadState
import id.my.sisinden.app.data.model.ChatMessageDto
import id.my.sisinden.app.data.model.ChatSyncResponse
import id.my.sisinden.app.data.model.ChatUserDto
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.data.repository.UploadImage
import id.my.sisinden.app.ui.common.UiMessages
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

/** Filter status utas (operator), sama dengan halaman Chat web. */
enum class ThreadFilter(val label: String, val query: String) {
    ALL("Semua", "all"),
    OPEN("Aktif", "OPEN"),
    CLOSED("Ditutup", "CLOSED"),
}

/**
 * Beranda Chat Dinas. Operator (admin/developer) melihat daftar utas;
 * personel langsung masuk ke utas miliknya (dibuat otomatis seperti di web).
 */
class ChatHomeViewModel(private val repository: SindenRepository) : ViewModel() {

    private val _state = MutableStateFlow<LoadState<ChatSyncResponse>>(LoadState.Loading)
    val state: StateFlow<LoadState<ChatSyncResponse>> = _state.asStateFlow()

    private val _filter = MutableStateFlow(ThreadFilter.ALL)
    val filter: StateFlow<ThreadFilter> = _filter.asStateFlow()

    private val _search = MutableStateFlow("")
    val search: StateFlow<String> = _search.asStateFlow()

    private val _userResults = MutableStateFlow<List<ChatUserDto>>(emptyList())
    val userResults: StateFlow<List<ChatUserDto>> = _userResults.asStateFlow()

    private val _busy = MutableStateFlow(false)
    val busy: StateFlow<Boolean> = _busy.asStateFlow()

    val messages = UiMessages()

    private var searchJob: Job? = null
    private var userSearchJob: Job? = null

    init {
        load()
    }

    fun load() {
        viewModelScope.launch {
            when (val res = repository.chatHome()) {
                is ApiResult.Success -> _state.value = LoadState.Ready(res.data)
                is ApiResult.Failure -> if (_state.value !is LoadState.Ready) _state.value = LoadState.Failed(res.error)
            }
        }
    }

    /** Polling daftar utas operator (dipanggil berkala oleh layar). */
    suspend fun refreshThreads() {
        val current = (_state.value as? LoadState.Ready)?.data ?: return
        if (current.isOperator != true) return
        val res = repository.chatSync(null, _filter.value.query, _search.value)
        if (res is ApiResult.Success) {
            _state.value = LoadState.Ready(current.copy(threads = res.data.threads, stats = res.data.stats))
        }
    }

    fun setFilter(value: ThreadFilter) {
        _filter.value = value
        viewModelScope.launch { refreshThreads() }
    }

    fun setSearch(value: String) {
        _search.value = value
        searchJob?.cancel()
        searchJob = viewModelScope.launch {
            delay(350)
            refreshThreads()
        }
    }

    fun searchUsers(query: String) {
        userSearchJob?.cancel()
        if (query.trim().length < 2) {
            _userResults.value = emptyList()
            return
        }
        userSearchJob = viewModelScope.launch {
            delay(300)
            val res = repository.chatSearchUsers(query)
            if (res is ApiResult.Success) _userResults.value = res.data.users.orEmpty()
        }
    }

    /** Buka utas aktif dengan personel (atau buat baru). */
    fun startChat(user: ChatUserDto, onOpened: (String) -> Unit) {
        val existing = user.existingThreadUuid
        if (!existing.isNullOrBlank()) {
            onOpened(existing)
            return
        }
        val id = user.id ?: return
        if (_busy.value) return
        _busy.value = true
        viewModelScope.launch {
            when (val res = repository.chatStart(id)) {
                is ApiResult.Success -> res.data.uuid?.let(onOpened) ?: messages.post("Utas tidak dapat dibuat.")
                is ApiResult.Failure -> messages.post(res.error.message)
            }
            _busy.value = false
        }
    }
}

/** Berkas lampiran yang dipilih (belum terkirim). */
class PendingAttachment(val upload: UploadImage, val sizeLabel: String)

data class ChatThreadUi(
    val loading: Boolean = true,
    val error: ApiError? = null,
    val messages: List<ChatMessageDto> = emptyList(),
    val remoteTyping: Boolean = false,
    val open: Boolean = true,
    val sending: Boolean = false,
)

/**
 * Satu utas Chat Dinas: polling 3 detik (sama dengan web), kirim pesan teks
 * dan lampiran, indikator mengetik, buka/tutup & hapus utas (operator).
 */
class ChatThreadViewModel(
    private val repository: SindenRepository,
    private val appContext: Context,
    private val uuid: String,
) : ViewModel() {

    private val _ui = MutableStateFlow(ChatThreadUi())
    val ui: StateFlow<ChatThreadUi> = _ui.asStateFlow()

    private val _attachments = MutableStateFlow<List<PendingAttachment>>(emptyList())
    val attachments: StateFlow<List<PendingAttachment>> = _attachments.asStateFlow()

    val messages = UiMessages()

    private var lastTypingPing = 0L

    init {
        viewModelScope.launch {
            when (val res = repository.chatMessages(uuid)) {
                is ApiResult.Success -> {
                    _ui.update { it.copy(loading = false, error = null, messages = res.data.messages.orEmpty()) }
                    markSeen()
                }
                is ApiResult.Failure -> _ui.update { it.copy(loading = false, error = res.error) }
            }
            poll()
        }
    }

    /** Sinkron pesan, status utas, dan indikator mengetik. */
    suspend fun poll() {
        val res = repository.chatSync(uuid)
        if (res !is ApiResult.Success) return
        val data = res.data
        val operator = data.threads != null
        val list = (if (operator) data.activeMessages else data.messages) ?: return
        val status = if (operator) data.threads?.firstOrNull { it.uuid == uuid }?.status else data.thread?.status
        _ui.update {
            it.copy(
                loading = false,
                error = null,
                messages = list,
                remoteTyping = data.isTyping,
                open = status?.let { s -> !s.equals("CLOSED", ignoreCase = true) } ?: it.open,
            )
        }
        markSeen()
    }

    private fun markSeen() {
        val maxId = _ui.value.messages.maxOfOrNull { it.id } ?: return
        NotificationHelper.markChatSeen(appContext, maxId)
    }

    /** Kirim sinyal "sedang mengetik" paling sering tiap 3 detik. */
    fun onTyping() {
        val now = System.currentTimeMillis()
        if (now - lastTypingPing < 3_000) return
        lastTypingPing = now
        viewModelScope.launch { repository.chatTyping(uuid) }
    }

    fun send(text: String, onSent: () -> Unit) {
        val files = _attachments.value
        if (text.isBlank() && files.isEmpty()) return
        if (_ui.value.sending) return
        _ui.update { it.copy(sending = true) }
        viewModelScope.launch {
            when (val res = repository.chatSend(uuid, text, files.map { it.upload })) {
                is ApiResult.Success -> {
                    val sent = res.data.message
                    _attachments.value = emptyList()
                    if (sent != null) _ui.update { s -> s.copy(messages = (s.messages + sent).distinctBy { it.id }) }
                    onSent()
                    poll()
                }
                is ApiResult.Failure -> messages.post(res.error.message)
            }
            _ui.update { it.copy(sending = false) }
        }
    }

    fun addAttachments(uris: List<Uri>) {
        viewModelScope.launch {
            val picked = withContext(Dispatchers.IO) { uris.mapNotNull { readAttachment(it) } }
            val combined = (_attachments.value + picked).take(MAX_FILES)
            val total = combined.sumOf { it.upload.bytes.size.toLong() }
            when {
                picked.size < uris.size -> messages.post("Sebagian berkas tidak dapat dibaca atau formatnya tidak didukung.")
                total > MAX_TOTAL_BYTES -> {
                    messages.post("Total lampiran melebihi 20 MB.")
                    return@launch
                }
            }
            if (total <= MAX_TOTAL_BYTES) _attachments.value = combined
        }
    }

    fun removeAttachment(item: PendingAttachment) {
        _attachments.update { list -> list.filterNot { it === item } }
    }

    fun toggleStatus() {
        viewModelScope.launch {
            when (val res = repository.chatToggleStatus(uuid)) {
                is ApiResult.Success -> {
                    _ui.update { it.copy(open = !res.data.status.equals("CLOSED", ignoreCase = true)) }
                    res.data.message?.let(messages::post)
                }
                is ApiResult.Failure -> messages.post(res.error.message)
            }
        }
    }

    fun delete(onDeleted: () -> Unit) {
        viewModelScope.launch {
            when (val res = repository.chatDelete(uuid)) {
                is ApiResult.Success -> onDeleted()
                is ApiResult.Failure -> messages.post(res.error.message)
            }
        }
    }

    private fun readAttachment(uri: Uri): PendingAttachment? = runCatching {
        val resolver = appContext.contentResolver
        val mime = resolver.getType(uri) ?: "application/octet-stream"
        var name: String? = null
        var size = -1L
        resolver.query(uri, arrayOf(OpenableColumns.DISPLAY_NAME, OpenableColumns.SIZE), null, null, null)?.use { c ->
            if (c.moveToFirst()) {
                name = c.getString(0)
                if (!c.isNull(1)) size = c.getLong(1)
            }
        }
        if (size > MAX_TOTAL_BYTES) return null
        val fileName = attachmentName(name, mime) ?: return null
        val bytes = resolver.openInputStream(uri)?.use { input ->
            val out = java.io.ByteArrayOutputStream()
            val buf = ByteArray(16 * 1024)
            var total = 0L
            while (true) {
                val n = input.read(buf)
                if (n < 0) break
                total += n
                if (total > MAX_TOTAL_BYTES) return null
                out.write(buf, 0, n)
            }
            out.toByteArray()
        } ?: return null
        PendingAttachment(UploadImage(fileName, mime, bytes), sizeLabel(bytes.size.toLong()))
    }.getOrNull()

    companion object {
        const val MAX_TOTAL_BYTES = 20L * 1024 * 1024
        const val MAX_FILES = 5

        /** Ekstensi yang diterima server (LiveChatController@sendMessage). */
        val ALLOWED_EXT = setOf("jpg", "jpeg", "png", "webp", "pdf", "doc", "docx", "xls", "xlsx", "txt", "zip")

        val PICKER_MIME = arrayOf(
            "image/jpeg", "image/png", "image/webp", "application/pdf",
            "application/msword", "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
            "application/vnd.ms-excel", "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            "text/plain", "application/zip",
        )

        /**
         * Nama berkas aman dengan ekstensi yang diizinkan (server memeriksa
         * ekstensi nama asli). Null bila formatnya tidak didukung.
         */
        fun attachmentName(displayName: String?, mime: String): String? {
            val raw = displayName?.substringAfterLast('/')?.trim().orEmpty()
            var ext = raw.substringAfterLast('.', "").lowercase()
            var base = if (ext.isNotEmpty()) raw.dropLast(ext.length + 1) else raw
            if (ext !in ALLOWED_EXT) {
                val fromMime = MimeTypeMap.getSingleton().getExtensionFromMimeType(mime)?.lowercase()
                    ?: when (mime) {
                        "image/jpeg" -> "jpg"
                        "application/pdf" -> "pdf"
                        else -> null
                    }
                if (fromMime == null || fromMime !in ALLOWED_EXT) return null
                if (ext.isNotEmpty()) base = raw
                ext = fromMime
            }
            val safeBase = base.replace(Regex("[^A-Za-z0-9_.-]+"), "_").trim('_', '.').take(60).ifEmpty { "lampiran" }
            return "$safeBase.$ext"
        }

        fun sizeLabel(bytes: Long): String = when {
            bytes >= 1024 * 1024 -> "%.1f MB".format(bytes / 1024.0 / 1024.0)
            bytes >= 1024 -> "${bytes / 1024} KB"
            else -> "$bytes B"
        }
    }
}
