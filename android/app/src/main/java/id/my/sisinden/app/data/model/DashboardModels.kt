package id.my.sisinden.app.data.model

import com.google.gson.annotations.SerializedName

data class DashboardResponse(
    @SerializedName("status") val status: String? = null,
    @SerializedName("server_time") val serverTime: String? = null,
    @SerializedName("user") val user: UserProfile? = null,
    @SerializedName("summary") val summary: DashboardSummary? = null,
    @SerializedName("duty") val duty: DashboardDuty? = null,
)

/** Field modul bernilai null bila pengguna tidak berhak (server tidak mengirimnya). */
data class DashboardSummary(
    @SerializedName("attendance") val attendance: AttendanceSummary? = null,
    @SerializedName("notifications_unread") val notificationsUnread: Int = 0,
    @SerializedName("letter_logs_total") val letterLogsTotal: Int? = null,
    @SerializedName("letter_logs_this_month") val letterLogsThisMonth: Int? = null,
    @SerializedName("skhpp_pending") val skhppPending: Int? = null,
    @SerializedName("signature_pending") val signaturePending: Int? = null,
    @SerializedName("cash_balance") val cashBalance: Double? = null,
    @SerializedName("technical_cash_balance") val technicalCashBalance: Double? = null,
)

data class AttendanceSummary(
    @SerializedName("today") val today: AttendanceDto? = null,
    @SerializedName("total_attended_today") val totalAttendedToday: Int = 0,
    @SerializedName("total_active_personnel") val totalActivePersonnel: Int = 0,
)

// ---------------------------------------------------------------- Presensi

data class AttendanceDto(
    @SerializedName("id") val id: Long = 0,
    @SerializedName("attendance_date") val attendanceDate: String? = null,
    @SerializedName("time_in") val timeIn: String? = null,
    @SerializedName("status") val status: String? = null,
    @SerializedName("location_name") val locationName: String? = null,
    @SerializedName("latitude") val latitude: Double? = null,
    @SerializedName("longitude") val longitude: Double? = null,
    @SerializedName("device") val device: String? = null,
    @SerializedName("notes") val notes: String? = null,
    @SerializedName("photo_url") val photoUrl: String? = null,
    @SerializedName("smile_score") val smileScore: Int? = null,
) {
    /** "2026-10-13T00:00:00.000000Z" → "2026-10-13" */
    val dateOnly: String? get() = attendanceDate?.take(10)

    val timeShort: String? get() = timeIn?.take(5)

    val statusLabel: String get() = AttendanceStatus.labelOf(status)
}

enum class AttendanceStatus(val code: String, val label: String) {
    HADIR("hadir", "Hadir Kantor"),
    DINAS_LUAR("dinas_luar", "Dinas Luar"),
    PIKET("piket", "Piket Jaga"),
    IZIN("izin", "Izin Dinas");

    companion object {
        fun labelOf(code: String?): String = entries.firstOrNull { it.code == code }?.label ?: (code ?: "-")
    }
}

data class AttendanceResponse(
    @SerializedName("status") val status: String? = null,
    @SerializedName("today_attendance") val todayAttendance: AttendanceDto? = null,
    @SerializedName("attendances") val attendances: List<AttendanceDto>? = null,
    @SerializedName("total_attended_today") val totalAttendedToday: Int = 0,
    @SerializedName("total_active_personnel") val totalActivePersonnel: Int = 0,
)

data class CreateAttendanceRequest(
    @SerializedName("status") val status: String,
    @SerializedName("latitude") val latitude: Double,
    @SerializedName("longitude") val longitude: Double,
    @SerializedName("accuracy") val accuracy: Float? = null,
    @SerializedName("device_model") val deviceModel: String? = null,
    @SerializedName("notes") val notes: String? = null,
    /** data:image/jpeg;base64,… hasil foto verifikasi senyum. */
    @SerializedName("photo") val photo: String,
    @SerializedName("smile_score") val smileScore: Int? = null,
)

data class AttendanceActionResponse(
    @SerializedName("status") val status: String? = null,
    @SerializedName("message") val message: String? = null,
    @SerializedName("attendance") val attendance: AttendanceDto? = null,
)

// ------------------------------------------------------------- Notifikasi

data class NotificationDto(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("title") val title: String? = null,
    @SerializedName("message") val message: String? = null,
    @SerializedName("type") val type: String? = null,
    /** Tautan web asal (mis. /signature-requests?open_id=1) — dipetakan ke rute aplikasi. */
    @SerializedName("link") val link: String? = null,
    @SerializedName("is_read") val isRead: Boolean = false,
    @SerializedName("created_at") val createdAt: String? = null,
)

data class NotificationResponse(
    @SerializedName("status") val status: String? = null,
    @SerializedName("notifications") val notifications: List<NotificationDto>? = null,
    @SerializedName("unreadCount") val unreadCount: Int = 0,
)
