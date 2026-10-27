package id.my.sisinden.app.data.model

import com.google.gson.annotations.SerializedName

// ======================================================== Chat Dinas
// Bentuk data mengikuti LiveChatController milik web (lihat
// app/Http/Controllers/Chat/LiveChatController.php) agar web & aplikasi identik.

data class ChatUserDto(
    @SerializedName("id") val id: Int? = null,
    @SerializedName("name") val name: String? = null,
    @SerializedName("pangkat") val pangkat: String? = null,
    @SerializedName("nrp") val nrp: String? = null,
    @SerializedName("role") val role: String? = null,
    @SerializedName("existing_thread_uuid") val existingThreadUuid: String? = null,
) {
    val displayName: String get() = name?.takeIf { it.isNotBlank() } ?: "Personel"
    val fullName: String get() = listOfNotNull(pangkat?.takeIf { it.isNotBlank() }, displayName).joinToString(" ")
}

data class ChatLatestMessageDto(
    @SerializedName("sender_name") val senderName: String? = null,
    @SerializedName("sender_type") val senderType: String? = null,
    @SerializedName("message") val message: String? = null,
    @SerializedName("has_attachments") val hasAttachments: Boolean = false,
    @SerializedName("created_at") val createdAt: String? = null,
)

data class ChatThreadDto(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("uuid") val uuid: String = "",
    @SerializedName("subject") val subject: String? = null,
    @SerializedName("status") val status: String? = null,
    /** Operator: teks relatif ("5 menit yang lalu"); personel: waktu ISO. */
    @SerializedName("last_message_at") val lastMessageAt: String? = null,
    @SerializedName("unread_admin") val unreadAdmin: Int = 0,
    @SerializedName("unread_user") val unreadUser: Int = 0,
    @SerializedName("is_online") val isOnline: Boolean = false,
    @SerializedName("user") val user: ChatUserDto? = null,
    @SerializedName("latest_message") val latestMessage: ChatLatestMessageDto? = null,
) {
    val isOpen: Boolean get() = !status.equals("CLOSED", ignoreCase = true)
}

data class ChatAttachmentDto(
    @SerializedName("original_name") val originalName: String? = null,
    @SerializedName("file_path") val filePath: String? = null,
    @SerializedName("url") val url: String? = null,
    @SerializedName("mime_type") val mimeType: String? = null,
    @SerializedName("size") val size: Long = 0,
    @SerializedName("is_image") val isImage: Boolean = false,
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

data class ChatStatsDto(
    @SerializedName("total_open") val totalOpen: Int = 0,
    @SerializedName("total_unread") val totalUnread: Int = 0,
    @SerializedName("total_threads") val totalThreads: Int = 0,
)

/**
 * Respons /api/mobile/chat dan /api/mobile/chat/sync.
 * Operator mengisi [threads]/[activeMessages]; personel mengisi [thread]/[messages].
 */
data class ChatSyncResponse(
    @SerializedName("is_operator") val isOperator: Boolean? = null,
    @SerializedName("threads") val threads: List<ChatThreadDto>? = null,
    @SerializedName("active_messages") val activeMessages: List<ChatMessageDto>? = null,
    @SerializedName("stats") val stats: ChatStatsDto? = null,
    @SerializedName("thread") val thread: ChatThreadDto? = null,
    @SerializedName("messages") val messages: List<ChatMessageDto>? = null,
    @SerializedName("is_typing") val isTyping: Boolean = false,
    @SerializedName("call") val call: CallDataDto? = null,
    @SerializedName("incoming_call") val incomingCall: CallDataDto? = null,
)

data class ChatMessagesResponse(
    @SerializedName("messages") val messages: List<ChatMessageDto>? = null,
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

data class ChatStartRequest(@SerializedName("user_id") val userId: Int)

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
