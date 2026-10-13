package id.my.sisinden.app.data.model

import com.google.gson.annotations.SerializedName

/** Ringkasan satu Surat Perintah (SP) Jaga. */
data class SpJagaSummary(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("nomor_sprin") val nomorSprin: String? = null,
    @SerializedName("bulan") val bulan: Int = 0,
    @SerializedName("tahun") val tahun: Int = 0,
    @SerializedName("periode_label") val periodeLabel: String? = null,
    @SerializedName("status") val status: String? = null,
    @SerializedName("ttd_type") val ttdType: String? = null,
    @SerializedName("tmt_mulai") val tmtMulai: String? = null,
    @SerializedName("tmt_selesai") val tmtSelesai: String? = null,
    @SerializedName("tanggal_surat") val tanggalSurat: String? = null,
    @SerializedName("total_personel") val totalPersonel: Int = 0,
    @SerializedName("perwira_tertua") val perwiraTertua: String? = null,
    @SerializedName("penandatangan") val penandatangan: String? = null,
    @SerializedName("approved_at") val approvedAt: String? = null,
) {
    val isPublished: Boolean get() = status == "published"

    val statusLabel: String
        get() = when (status) {
            "published" -> "Terbit"
            "signed" -> "Ditandatangani"
            "pending_signature" -> "Menunggu TTE"
            "draft" -> "Draf"
            else -> status ?: "-"
        }
}

data class DutyEntry(
    @SerializedName("name") val name: String? = null,
    @SerializedName("pangkat_korps") val pangkatKorps: String? = null,
    @SerializedName("nrp") val nrp: String? = null,
    @SerializedName("role") val role: String? = null,
    @SerializedName("role_code") val roleCode: String? = null,
    @SerializedName("divisi_no") val divisiNo: Int? = null,
    @SerializedName("is_me") val isMe: Boolean = false,
)

data class DutyDay(
    @SerializedName("date") val date: String = "",
    @SerializedName("day_of_week") val dayOfWeek: Int = 0,
    @SerializedName("is_today") val isToday: Boolean = false,
    @SerializedName("has_me") val hasMe: Boolean = false,
    @SerializedName("sp_id") val spId: Int? = null,
    @SerializedName("entries") val entries: List<DutyEntry>? = null,
) {
    val safeEntries: List<DutyEntry> get() = entries.orEmpty()
}

data class DutyRosterResponse(
    @SerializedName("status") val status: String? = null,
    @SerializedName("sp") val sp: SpJagaSummary? = null,
    @SerializedName("days") val days: List<DutyDay>? = null,
    @SerializedName("my_dates") val myDates: List<String>? = null,
    @SerializedName("my_roles") val myRoles: List<String>? = null,
    @SerializedName("available") val available: List<SpJagaSummary>? = null,
)

data class DashboardDuty(
    @SerializedName("sources") val sources: List<SpJagaSummary>? = null,
    @SerializedName("today") val today: DutyDay? = null,
    @SerializedName("upcoming") val upcoming: List<DutyDay>? = null,
    @SerializedName("my_next_date") val myNextDate: String? = null,
    @SerializedName("my_dates") val myDates: List<String>? = null,
)
