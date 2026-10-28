package id.my.sisinden.app.data.model

import com.google.gson.annotations.SerializedName

// ======================================================== Chat Dinas
// Bentuk data mengikuti App\Support\ChatConversations di server (web & aplikasi identik).
// Dua jenis percakapan: "direct" (chat pribadi dua personel) dan "support" (Operator Dinas).

data class ChatUserDto(
    @SerializedName("id") val id: Int? = null,
    @SerializedName("name") val name: String? = null,
    @SerializedName("pangkat") val pangkat: String? = null,
    @SerializedName("nrp") val nrp: String? = null,
    @SerializedName("jabatan") val jabatan: String? = null,
    @SerializedName("role") val role: String? = null,
    @SerializedName("avatar") val avatar: String? = null,
    @SerializedName("is_online") val isOnline: Boolean = false,
) {
    val displayName: String get() = name?.takeIf { it.isNotBlank() } ?: "Personel"
    val fullName: String get() = listOfNotNull(pangkat?.takeIf { it.isNotBlank() }, displayName).joinToString(" ")
}

data class ConversationLatestDto(
    @SerializedName("sender_id") val senderId: Int? = null,
    @SerializedName("sender_name") val senderName: String? = null,
    @SerializedName("preview") val preview: String? = null,
    @SerializedName("created_at") val createdAt: String? = null,
)

/** Satu entri daftar percakapan. [uuid] null = entri "Operator Dinas" yang belum pernah dipakai. */
data class ConversationDto(
    @SerializedName("uuid") val uuid: String? = null,
    /** direct | support */
    @SerializedName("kind") val kind: String? = null,
    /** user | peer | operator — peran Anda dalam percakapan. */
    @SerializedName("role") val role: String? = null,
    @SerializedName("title") val title: String? = null,
    @SerializedName("subtitle") val subtitle: String? = null,
    @SerializedName("avatar") val avatar: String? = null,
    @SerializedName("partner") val partner: ChatUserDto? = null,
    @SerializedName("status") val status: String? = null,
    @SerializedName("unread") val unread: Int = 0,
    @SerializedName("is_online") val isOnline: Boolean = false,
    @SerializedName("is_typing") val isTyping: Boolean = false,
    @SerializedName("last_message_at") val lastMessageAt: String? = null,
    @SerializedName("latest_message") val latestMessage: ConversationLatestDto? = null,
) {
    val isOpen: Boolean get() = !status.equals("CLOSED", ignoreCase = true)
    val isSupport: Boolean get() = kind == "support"
    val displayTitle: String get() = title?.takeIf { it.isNotBlank() } ?: if (isSupport) "Operator Dinas" else "Personel"
}

data class ChatAttachmentDto(
    @SerializedName("original_name") val originalName: String? = null,
    @SerializedName("file_path") val filePath: String? = null,
    @SerializedName("url") val url: String? = null,
    @SerializedName("mime_type") val mimeType: String? = null,
    @SerializedName("size") val size: Long = 0,
    @SerializedName("is_image") val isImage: Boolean = false,
    @SerializedName("is_audio") val isAudio: Boolean = false,
    @SerializedName("is_voice") val isVoice: Boolean = false,
    @SerializedName("duration_ms") val durationMs: Long? = null,
) {
    /**
     * URL publik HTTPS lampiran di server Sinden 2. Dibangun dari `file_path`
     * (disk public) agar tidak bergantung pada APP_URL server.
     */
    fun publicUrl(baseUrl: String): String? {
        val path = filePath?.trim()?.trimStart('/')
        if (!path.isNullOrEmpty() && !path.contains("..")) return "${baseUrl.trimEnd('/')}/storage/$path"
        val raw = url?.trim().orEmpty()
        return when {
            raw.startsWith("https://") -> raw
            raw.startsWith("/") -> baseUrl.trimEnd('/') + raw
            else -> null
        }
    }
}

data class ChatMessageDto(
    @SerializedName("id") val id: Long = 0,
    @SerializedName("thread_id") val threadId: Int = 0,
    /** USER, ADMIN, atau SYSTEM (catatan panggilan). */
    @SerializedName("sender_type") val senderType: String? = null,
    @SerializedName("sender_id") val senderId: Int? = null,
    @SerializedName("sender_name") val senderName: String? = null,
    @SerializedName("message") val message: String? = null,
    @SerializedName("attachments") val attachments: List<ChatAttachmentDto>? = null,
    @SerializedName("is_read") val isRead: Boolean = false,
    @SerializedName("created_at") val createdAt: String? = null,
) {
    val isSystem: Boolean get() = senderType.equals("SYSTEM", ignoreCase = true)
}

/** Respons GET /api/mobile/chat. */
data class ChatHomeResponse(
    @SerializedName("is_operator") val isOperator: Boolean = false,
    @SerializedName("conversations") val conversations: List<ConversationDto>? = null,
    @SerializedName("total_unread") val totalUnread: Int = 0,
    @SerializedName("incoming_call") val incomingCall: CallDataDto? = null,
)

/** Percakapan terbuka: GET /chat/{uuid}/messages dan bagian `active` dari /chat/sync. */
data class ChatDetailDto(
    @SerializedName("thread") val thread: ConversationDto? = null,
    @SerializedName("messages") val messages: List<ChatMessageDto>? = null,
    @SerializedName("is_typing") val isTyping: Boolean = false,
    @SerializedName("call") val call: CallDataDto? = null,
)

/** Respons GET /api/mobile/chat/sync?uuid=&after=. */
data class ChatSyncResponse(
    @SerializedName("conversations") val conversations: List<ConversationDto>? = null,
    @SerializedName("total_unread") val totalUnread: Int = 0,
    @SerializedName("active") val active: ChatDetailDto? = null,
    @SerializedName("incoming_call") val incomingCall: CallDataDto? = null,
)

data class ChatSendResponse(
    @SerializedName("success") val success: Boolean = false,
    @SerializedName("message") val message: ChatMessageDto? = null,
)

data class ChatUnreadResponse(
    @SerializedName("unread") val unread: Int = 0,
    @SerializedName("latest") val latest: ChatMessageDto? = null,
    @SerializedName("thread_uuid") val threadUuid: String? = null,
)

data class ChatUsersResponse(
    @SerializedName("users") val users: List<ChatUserDto>? = null,
)

/** [userId] → chat pribadi dengan personel; [support] true → utas Operator Dinas. */
data class ChatStartRequest(
    @SerializedName("user_id") val userId: Int? = null,
    @SerializedName("support") val support: Boolean? = null,
)

data class ChatStartResponse(
    @SerializedName("success") val success: Boolean = false,
    @SerializedName("uuid") val uuid: String? = null,
)

data class ChatStatusResponse(
    @SerializedName("success") val success: Boolean = false,
    @SerializedName("status") val status: String? = null,
    @SerializedName("message") val message: String? = null,
)

// ======================================================== Panggilan (Vicon)

data class CallPartyDto(
    /** USER atau ADMIN. */
    @SerializedName("type") val type: String? = null,
    @SerializedName("id") val id: Int? = null,
    @SerializedName("name") val name: String? = null,
    @SerializedName("pangkat") val pangkat: String? = null,
)

data class CallDataDto(
    @SerializedName("call_id") val callId: String? = null,
    @SerializedName("thread_uuid") val threadUuid: String? = null,
    /** RINGING, ACCEPTED, CONNECTING, CONNECTED, REJECTED, ENDED. */
    @SerializedName("status") val status: String? = null,
    @SerializedName("caller") val caller: CallPartyDto? = null,
    @SerializedName("callee") val callee: CallPartyDto? = null,
    @SerializedName("call_type") val callType: String? = null,
    @SerializedName("created_at") val createdAt: Long? = null,
    @SerializedName("started_at") val startedAt: Long? = null,
) {
    val isVideo: Boolean get() = !callType.equals("audio", ignoreCase = true)
    val normalizedStatus: String get() = status?.uppercase() ?: ""
}

data class CallResponse(
    @SerializedName("success") val success: Boolean = false,
    @SerializedName("call") val call: CallDataDto? = null,
)

data class InitiateCallRequest(@SerializedName("call_type") val callType: String)

data class CallSignalRequest(
    @SerializedName("action") val action: String,
    @SerializedName("status") val status: String,
)

data class EndCallRequest(
    /** ended, rejected, canceled, missed — sama dengan web. */
    @SerializedName("reason") val reason: String,
    @SerializedName("duration_seconds") val durationSeconds: Int = 0,
)

data class AgoraTokenResponse(
    @SerializedName("appId") val appId: String? = null,
    @SerializedName("channel") val channel: String? = null,
    @SerializedName("token") val token: String? = null,
    @SerializedName("uid") val uid: Long = 0,
)

// ======================================================== TTE SP Jaga

data class PendingSpJagaDto(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("nomor_sprin") val nomorSprin: String? = null,
    @SerializedName("bulan") val bulan: Int = 0,
    @SerializedName("tahun") val tahun: Int = 0,
    @SerializedName("status") val status: String? = null,
    @SerializedName("total_personel") val totalPersonel: Int = 0,
    @SerializedName("tanggal_surat") val tanggalSurat: String? = null,
)

data class PendingSpJagaResponse(
    @SerializedName("status") val status: String? = null,
    @SerializedName("data") val data: List<PendingSpJagaDto>? = null,
)

// ======================================================== Antrean SKHPP (TTE)

data class SkhppQueueDto(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("nama") val nama: String? = null,
    @SerializedName("nomor_skhpp") val nomorSkhpp: String? = null,
    @SerializedName("peruntukan") val peruntukan: String? = null,
    @SerializedName("status") val status: String? = null,
    @SerializedName("created_at") val createdAt: String? = null,
)
