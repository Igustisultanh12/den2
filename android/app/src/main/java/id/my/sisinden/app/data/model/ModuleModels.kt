package id.my.sisinden.app.data.model

import com.google.gson.annotations.SerializedName

// ===================================================================== Kas
// Dipakai bersama oleh Buku Kas Pangkalan (/cash) dan Kas Unit Teknis (/technical-cash).

data class CashEntryDto(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("date") val date: String? = null,
    @SerializedName("description") val description: String? = null,
    @SerializedName("debit") val debit: Double = 0.0,
    @SerializedName("credit") val credit: Double = 0.0,
    @SerializedName("balance") val balance: Double = 0.0,
    @SerializedName("receipt_urls") val receiptUrls: List<String>? = null,
) {
    val isIncome: Boolean get() = debit > 0.0
    val amount: Double get() = if (debit > 0.0) debit else credit
}

data class CashLedgerResponse(
    @SerializedName("status") val status: String? = null,
    @SerializedName("balance") val balance: Double = 0.0,
    @SerializedName("total_debit") val totalDebit: Double = 0.0,
    @SerializedName("total_credit") val totalCredit: Double = 0.0,
    @SerializedName("data") val data: List<CashEntryDto>? = null,
)

data class CashEntryRequest(
    @SerializedName("date") val date: String,
    @SerializedName("description") val description: String,
    @SerializedName("type") val type: String, // "masuk" | "keluar"
    @SerializedName("amount") val amount: Double,
)

// ========================================================= Agenda Surat

data class LetterLogDto(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("sequence") val sequence: String? = null,
    @SerializedName("full_number") val fullNumber: String? = null,
    @SerializedName("subject") val subject: String? = null,
    @SerializedName("recipient") val recipient: String? = null,
    @SerializedName("date") val date: String? = null,
    @SerializedName("category_id") val categoryId: Int = 0,
    @SerializedName("category_name") val categoryName: String? = null,
    @SerializedName("sub_category_name") val subCategoryName: String? = null,
    @SerializedName("is_archived") val isArchived: Boolean = false,
)

data class LetterLogsResponse(
    @SerializedName("status") val status: String? = null,
    @SerializedName("data") val data: List<LetterLogDto>? = null,
)

data class CreateLetterLogRequest(
    @SerializedName("category_id") val categoryId: Int,
    @SerializedName("subject") val subject: String,
    @SerializedName("recipient") val recipient: String,
    @SerializedName("date") val date: String,
)

data class UpdateLetterLogRequest(
    @SerializedName("subject") val subject: String,
    @SerializedName("recipient") val recipient: String,
    @SerializedName("date") val date: String,
)

data class CategoryDto(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("name") val name: String? = null,
    @SerializedName("code") val code: String? = null,
)

data class CategoriesResponse(
    @SerializedName("status") val status: String? = null,
    @SerializedName("data") val data: List<CategoryDto>? = null,
)

// ================================================================= SKHPP

data class SkhppDto(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("nomor_skhpp") val nomorSkhpp: String? = null,
    @SerializedName("nama") val nama: String? = null,
    @SerializedName("pangkat_korps_nrp") val pangkatKorpsNrp: String? = null,
    @SerializedName("jabatan") val jabatan: String? = null,
    @SerializedName("peruntukan") val peruntukan: String? = null,
    @SerializedName("kategori") val kategori: String? = null,
    @SerializedName("status") val status: String? = null,
    @SerializedName("tanggal_surat") val tanggalSurat: String? = null,
    @SerializedName("created_by") val createdBy: String? = null,
) {
    val normalizedStatus: String get() = status?.lowercase() ?: "pending"

    val statusLabel: String
        get() = when (normalizedStatus) {
            "approved" -> "Disetujui"
            "rejected" -> "Dikembalikan"
            "draft" -> "Draf"
            else -> "Menunggu"
        }

    val kategoriLabel: String
        get() = when (kategori) {
            "perusahaan" -> "Mitra Kerja / Perusahaan"
            "sipil_dinas" -> "Sipil Dinas (PNS)"
            else -> "Militer"
        }
}

data class SkhppResponse(
    @SerializedName("status") val status: String? = null,
    @SerializedName("data") val data: List<SkhppDto>? = null,
)

/** Anggota pengikut SKHPP (opsional). */
data class SkhppMemberInput(
    val nama: String,
    val pangkatNrpNik: String,
    val jabatan: String,
)

// ===================================================== Rekening Komandan

data class CommanderTxDto(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("tanggal") val tanggal: String? = null,
    @SerializedName("jenis") val jenis: String? = null,
    @SerializedName("jumlah") val jumlah: Double = 0.0,
    @SerializedName("keterangan") val keterangan: String? = null,
    @SerializedName("petugas_input") val petugasInput: String? = null,
) {
    val isIncome: Boolean get() = jenis.equals("MASUK", ignoreCase = true)
    val dateOnly: String? get() = tanggal?.take(10)
}

data class CommanderAccountResponse(
    @SerializedName("status") val status: String? = null,
    @SerializedName("total_masuk") val totalMasuk: Double = 0.0,
    @SerializedName("total_keluar") val totalKeluar: Double = 0.0,
    @SerializedName("saldo_akhir") val saldoAkhir: Double = 0.0,
    @SerializedName("can_manage") val canManage: Boolean = false,
    @SerializedName("data") val data: List<CommanderTxDto>? = null,
)

data class CommanderTxRequest(
    @SerializedName("tanggal") val tanggal: String,
    @SerializedName("jenis") val jenis: String,
    @SerializedName("jumlah") val jumlah: Double,
    @SerializedName("keterangan") val keterangan: String,
)

// ================================================================= Mitra

data class MitraDto(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("nama") val nama: String? = null,
    @SerializedName("pt") val pt: String? = null,
    @SerializedName("no_tlp") val noTlp: String? = null,
    @SerializedName("keterangan") val keterangan: String? = null,
    @SerializedName("sort_order") val sortOrder: Int = 0,
    @SerializedName("payment_matrix") val paymentMatrix: Map<String, Boolean>? = null,
) {
    fun isPaid(month: Int): Boolean = paymentMatrix?.get(month.toString()) == true
    val paidCount: Int get() = (1..12).count { isPaid(it) }
}

data class MitraResponse(
    @SerializedName("status") val status: String? = null,
    @SerializedName("tahun") val tahun: Int = 0,
    @SerializedName("data") val data: List<MitraDto>? = null,
)

data class MitraRequest(
    @SerializedName("nama") val nama: String,
    @SerializedName("pt") val pt: String?,
    @SerializedName("no_tlp") val noTlp: String?,
    @SerializedName("keterangan") val keterangan: String?,
)

data class TogglePaymentRequest(
    @SerializedName("tahun") val tahun: Int,
    @SerializedName("bulan") val bulan: Int,
)

// ========================================================= Tanda Tangan

data class SignatureRequestDto(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("subject") val subject: String? = null,
    @SerializedName("letter_number") val letterNumber: String? = null,
    @SerializedName("person_name") val personName: String? = null,
    @SerializedName("requester_name") val requesterName: String? = null,
    @SerializedName("peruntukan") val peruntukan: String? = null,
    @SerializedName("status") val status: String? = null,
    @SerializedName("note") val note: String? = null,
    @SerializedName("verification_code") val verificationCode: String? = null,
    @SerializedName("created_at") val createdAt: String? = null,
) {
    val normalizedStatus: String get() = status?.lowercase() ?: "pending"
    val isPending: Boolean get() = normalizedStatus !in setOf("approved", "signed", "selesai", "rejected", "ditolak")
}

data class SignatureListResponse(
    @SerializedName("status") val status: String? = null,
    @SerializedName("can_approve") val canApprove: Boolean = false,
    @SerializedName("requests") val requests: List<SignatureRequestDto>? = null,
    /** SKHPP yang menunggu TTE Komandan (pengaju hanya melihat miliknya). */
    @SerializedName("skhpp_requests") val skhppRequests: List<SkhppQueueDto>? = null,
)

data class SignatureDecisionRequest(
    @SerializedName("note") val note: String? = null,
)

// ====================================================== Pelanggaran

data class ViolationDto(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("name") val name: String? = null,
    @SerializedName("nrp") val nrp: String? = null,
    @SerializedName("rank") val rank: String? = null,
    @SerializedName("position") val position: String? = null,
    @SerializedName("unit") val unit: String? = null,
    @SerializedName("case_description") val caseDescription: String? = null,
    @SerializedName("incident_date") val incidentDate: String? = null,
    @SerializedName("case_development") val caseDevelopment: String? = null,
    @SerializedName("status") val status: String? = null,
) {
    /** Riwayat perkembangan kasus, satu entri per baris "[dd/mm/yyyy hh:mm] catatan". */
    val history: List<String>
        get() = caseDevelopment.orEmpty().lines().map { it.trim() }.filter { it.isNotEmpty() }
}

data class ViolationStats(
    @SerializedName("total") val total: Int = 0,
    @SerializedName("proses") val proses: Int = 0,
    @SerializedName("selesai") val selesai: Int = 0,
)

data class ViolationsResponse(
    @SerializedName("status") val status: String? = null,
    @SerializedName("stats") val stats: ViolationStats? = null,
    @SerializedName("data") val data: List<ViolationDto>? = null,
)

data class CreateViolationRequest(
    @SerializedName("nama") val nama: String,
    @SerializedName("nrp") val nrp: String,
    @SerializedName("pangkat") val pangkat: String,
    @SerializedName("jabatan") val jabatan: String,
    @SerializedName("satuan") val satuan: String,
    @SerializedName("kasus") val kasus: String,
    @SerializedName("tmt") val tmt: String,
    @SerializedName("perkembangan_kasus") val perkembanganKasus: String,
    @SerializedName("status") val status: String,
)

data class ViolationProgressRequest(
    @SerializedName("note") val note: String,
    @SerializedName("status") val status: String?,
)

object ViolationStatus {
    val all = listOf("PROSES", "SIDANG", "SELESAI", "BANDING", "DINAS_KEMBALI", "PDTH")
    fun label(code: String?): String = when (code) {
        "PROSES" -> "Proses"
        "SIDANG" -> "Sidang"
        "SELESAI" -> "Selesai"
        "BANDING" -> "Banding"
        "DINAS_KEMBALI" -> "Dinas Kembali"
        "PDTH" -> "PDTH"
        else -> code ?: "-"
    }
}

// ======================================================== Pengguna (Admin)

data class ManagedUserDto(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("name") val name: String? = null,
    @SerializedName("email") val email: String? = null,
    @SerializedName("nrp") val nrp: String? = null,
    @SerializedName("pangkat") val pangkat: String? = null,
    @SerializedName("role") val role: String? = null,
    @SerializedName("jabatan") val jabatan: String? = null,
    @SerializedName("phone") val phone: String? = null,
    @SerializedName("is_active") val isActive: Boolean = false,
)

data class UsersResponse(
    @SerializedName("status") val status: String? = null,
    @SerializedName("data") val data: List<ManagedUserDto>? = null,
)

data class CreateUserRequest(
    @SerializedName("name") val name: String,
    @SerializedName("email") val email: String,
    @SerializedName("nrp") val nrp: String,
    @SerializedName("pangkat") val pangkat: String?,
    @SerializedName("role") val role: String,
    @SerializedName("password") val password: String,
)

data class UpdateUserStatusRequest(
    @SerializedName("is_active") val isActive: Boolean,
)

object KnownRoles {
    /** Sama dengan MobileAccess::KNOWN_ROLES di server. */
    val all = listOf(
        "personel", "staf", "anggotasintel", "danunitteknis", "kaurmintel", "paurset",
        "pasops", "wadan", "komandan", "admin", "danunit1", "user",
    )
}
