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
import id.my.sisinden.app.data.model.ChatHomeResponse
import id.my.sisinden.app.data.model.ChatMessageDto
import id.my.sisinden.app.data.model.ChatUserDto
import id.my.sisinden.app.data.model.ConversationDto
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

/**
 * Beranda Chat Dinas gaya WhatsApp: daftar percakapan milik pengguna (chat pribadi
 * + Operator Dinas) dan pencarian kontak untuk memulai chat dengan personel mana pun.
 */
class ChatHomeViewModel(private val repository: SindenRepository) : ViewModel() {

    private val _state = MutableStateFlow<LoadState<ChatHomeResponse>>(LoadState.Loading)
    val state: StateFlow<LoadState<ChatHomeResponse>> = _state.asStateFlow()

    private val _search = MutableStateFlow("")
    val search: StateFlow<String> = _search.asStateFlow()

    private val _contacts = MutableStateFlow<List<ChatUserDto>>(emptyList())
    val contacts: StateFlow<List<ChatUserDto>> = _contacts.asStateFlow()

    private val _contactsLoading = MutableStateFlow(false)
    val contactsLoading: StateFlow<Boolean> = _contactsLoading.asStateFlow()

    private val _busy = MutableStateFlow(false)
    val busy: StateFlow<Boolean> = _busy.asStateFlow()

    val messages = UiMessages()

    private var searchJob: Job? = null
    private var contactJob: Job? = null

    init {
        load()
    }

    fun load() {
        viewModelScope.launch { refresh(showErrors = true) }
    }

    /** Polling daftar percakapan (dipanggil berkala oleh layar). */
    suspend fun refresh(showErrors: Boolean = false) {
        when (val res = repository.chatHome(_search.value)) {
            is ApiResult.Success -> _state.value = LoadState.Ready(res.data)
            is ApiResult.Failure -> if (_state.value !is LoadState.Ready || showErrors) {
                if (_state.value !is LoadState.Ready) _state.value = LoadState.Failed(res.error)
            }
        }
    }

    fun setSearch(value: String) {
        _search.value = value
        searchJob?.cancel()
        searchJob = viewModelScope.launch {
            delay(350)
            refresh()
        }
    }

    /** Kontak: semua personel aktif; [query] kosong = tampilkan daftar awal. */
    fun searchContacts(query: String) {
        contactJob?.cancel()
        contactJob = viewModelScope.launch {
            delay(if (query.isBlank()) 0 else 300)
            _contactsLoading.value = true
            val res = repository.chatSearchUsers(query)
            if (res is ApiResult.Success) _contacts.value = res.data.users.orEmpty()
            _contactsLoading.value = false
        }
    }

    /** Buka percakapan; entri "Operator Dinas" tanpa uuid dibuatkan utasnya lebih dulu. */
    fun open(conv: ConversationDto, onOpened: (uuid: String, title: String) -> Unit) {
        val uuid = conv.uuid
        if (!uuid.isNullOrBlank()) {
            onOpened(uuid, conv.displayTitle)
            return
        }
        if (_busy.value) return
        _busy.value = true
        viewModelScope.launch {
            when (val res = repository.chatStartSupport()) {
                is ApiResult.Success -> res.data.uuid?.let { onOpened(it, conv.displayTitle) } ?: messages.post("Percakapan tidak dapat dibuat.")
                is ApiResult.Failure -> messages.post(res.error.message)
            }
            _busy.value = false
        }
    }

    /** Mulai (atau lanjutkan) chat pribadi dengan personel. */
    fun startChat(user: ChatUserDto, onOpened: (uuid: String, title: String) -> Unit) {
        val id = user.id ?: return
        if (_busy.value) return
        _busy.value = true
        viewModelScope.launch {
            when (val res = repository.chatStart(id)) {
                is ApiResult.Success -> res.data.uuid?.let { onOpened(it, user.fullName) } ?: messages.post("Percakapan tidak dapat dibuat.")
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
    val thread: ConversationDto? = null,
    val messages: List<ChatMessageDto> = emptyList(),
    val remoteTyping: Boolean = false,
    val open: Boolean = true,
    val sending: Boolean = false,
) {
    val isOperator: Boolean get() = thread?.role == "operator"
    /** Chat pribadi dapat dihapus pesertanya; utas layanan hanya oleh operator. */
    val canDelete: Boolean get() = thread?.isSupport == false || isOperator
}

/**
 * Satu percakapan: polling tiap 2,5 detik (hanya pesan baru), kirim teks/lampiran/pesan
 * suara, indikator mengetik, buka/tutup utas layanan (operator) & hapus percakapan.
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
                    val d = res.data
                    _ui.update {
                        it.copy(
                            loading = false, error = null, thread = d.thread,
                            messages = d.messages.orEmpty(), remoteTyping = d.isTyping,
                            open = d.thread?.isOpen ?: true,
                        )
                    }
                    markSeen()
                }
                is ApiResult.Failure -> _ui.update { it.copy(loading = false, error = res.error) }
            }
        }
    }

    /** Sinkron pesan baru, status utas, dan indikator mengetik. */
    suspend fun poll() {
        val after = _ui.value.messages.lastOrNull()?.id
        val res = repository.chatSync(uuid, after)
        if (res !is ApiResult.Success) return
        val active = res.data.active ?: return
        val fresh = active.messages.orEmpty()
        _ui.update { s ->
            s.copy(
                loading = false,
                error = null,
                thread = active.thread ?: s.thread,
                messages = if (fresh.isEmpty()) s.messages else (s.messages + fresh).distinctBy { it.id },
                remoteTyping = active.isTyping,
                open = active.thread?.isOpen ?: s.open,
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

    private fun appendSent(sent: ChatMessageDto?) {
        if (sent != null) _ui.update { s -> s.copy(messages = (s.messages + sent).distinctBy { it.id }) }
    }

    fun send(text: String, onSent: () -> Unit) {
        val files = _attachments.value
        if (text.isBlank() && files.isEmpty()) return
        if (_ui.value.sending) return
        _ui.update { it.copy(sending = true) }
        viewModelScope.launch {
            when (val res = repository.chatSend(uuid, text, files.map { it.upload })) {
                is ApiResult.Success -> {
                    _attachments.value = emptyList()
                    appendSent(res.data.message)
                    onSent()
                }
                is ApiResult.Failure -> messages.post(res.error.message)
            }
            _ui.update { it.copy(sending = false) }
        }
    }

    /** Kirim rekaman pesan suara lalu hapus berkas sementara. */
    fun sendVoice(clip: VoiceClip) {
        viewModelScope.launch {
            _ui.update { it.copy(sending = true) }
            val bytes = withContext(Dispatchers.IO) { runCatching { clip.file.readBytes() }.getOrNull() }
            clip.file.delete()
            if (bytes == null || bytes.isEmpty()) {
                messages.post("Rekaman pesan suara tidak dapat dibaca.")
            } else when (val res = repository.chatSendVoice(uuid, UploadImage("pesan-suara.m4a", "audio/mp4", bytes), clip.durationMs)) {
                is ApiResult.Success -> appendSent(res.data.message)
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

        /** Ekstensi yang diterima server (ChatConversations::send). */
        val ALLOWED_EXT = setOf("jpg", "jpeg", "png", "webp", "pdf", "doc", "docx", "xls", "xlsx", "txt", "zip", "m4a", "mp3", "aac", "ogg", "wav")

        val PICKER_MIME = arrayOf(
            "image/jpeg", "image/png", "image/webp", "application/pdf",
            "application/msword", "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
            "application/vnd.ms-excel", "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            "text/plain", "application/zip", "audio/*",
        )

        /**
         * Nama berkas aman dengan ekstensi yang diizinkan (server memeriksa
         * isi berkas). Null bila formatnya tidak didukung.
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
