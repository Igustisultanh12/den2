package id.my.sisinden.app.data.model

import com.google.gson.annotations.SerializedName
import java.util.Calendar

// --- MODUL AUTENTIKASI ---
data class LoginRequest(
    @SerializedName("username") val username: String,
    @SerializedName("password") val password: String
)

data class ActivateRequest(
    @SerializedName("nrp") val nrp: String,
    @SerializedName("name") val name: String,
    @SerializedName("password") val password: String
)

data class LoginResponse(
    @SerializedName("status") val status: String,
    @SerializedName("token") val token: String? = null,
    @SerializedName("message") val message: String? = null,
    @SerializedName("user") val user: UserDto? = null
)

data class UserDto(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("name") val name: String = "",
    @SerializedName("email") val email: String = "",
    @SerializedName("username") val username: String = "",
    @SerializedName("nrp") val nrp: String = "",
    @SerializedName("role") val role: String = "user",
    @SerializedName("pangkat") val pangkat: String = "Prajurit",
    @SerializedName("korps") val korps: String = "",
    @SerializedName("jabatan") val jabatan: String = "",
    @SerializedName("is_active") val isActive: Boolean = true,
    @SerializedName("can_access_agenda") val canAccessAgenda: Boolean = true,
    @SerializedName("can_access_letters") val canAccessLetters: Boolean = true,
    @SerializedName("can_access_skhpp") val canAccessSkhpp: Boolean = true,
    @SerializedName("can_access_categories") val canAccessCategories: Boolean = true,
    @SerializedName("can_access_signature") val canAccessSignature: Boolean = true,
    @SerializedName("can_access_cash") val canAccessCash: Boolean = false,
    @SerializedName("can_access_commander") val canAccessCommander: Boolean = false,
    @SerializedName("can_access_mitra") val canAccessMitra: Boolean = false,
    @SerializedName("can_access_technical_cash") val canAccessTechnicalCash: Boolean = false,
    @SerializedName("can_access_violations") val canAccessViolations: Boolean = true,
    @SerializedName("can_access_activities") val canAccessActivities: Boolean = true,
    @SerializedName("can_access_users") val canAccessUsers: Boolean = false,
    @SerializedName("can_access_settings") val canAccessSettings: Boolean = false
) {
    val isAdminOrOfficer: Boolean
        get() = role.lowercase() in listOf("admin", "superadmin", "komandan", "danden", "bendahara", "keuangan", "officer")

    val isDanUnitTeknis: Boolean
        get() = role.lowercase().replace(" ", "").contains("danunit") || role.lowercase().contains("teknis")

    val canOpenCash: Boolean
        get() = isAdminOrOfficer || canAccessCash

    val canOpenTechnicalCash: Boolean
        get() = isAdminOrOfficer || isDanUnitTeknis || canAccessTechnicalCash

    val canOpenMitra: Boolean
        get() = isAdminOrOfficer || canAccessMitra
}

// --- MODUL KONFIGURASI SERVER ---
data class ConfigResponse(
    @SerializedName("status") val status: String = "success",
    @SerializedName("app_name") val appName: String? = null,
    @SerializedName("agency_name") val agencyName: String? = null,
    @SerializedName("agency_logo") val agencyLogo: String? = null,
    @SerializedName("login_background") val loginBackground: String? = null,
    @SerializedName("api_base_url") val apiBaseUrl: String? = null,
    @SerializedName("apk_download_url") val apkDownloadUrl: String? = null,
    @SerializedName("api_status") val apiStatus: String? = null,
    @SerializedName("min_version") val minVersion: String? = null,
    @SerializedName("mobile_theme_mode") val mobileThemeMode: String? = "system",
    @SerializedName("mobile_primary_color") val mobilePrimaryColor: String? = "#1E3A8A",
    @SerializedName("mobile_accent_color") val mobileAccentColor: String? = "#0284C7",
    @SerializedName("mobile_card_radius") val mobileCardRadius: Int? = 20,
    @SerializedName("mobile_show_balance_card") val mobileShowBalanceCard: Boolean? = true,
    @SerializedName("mobile_show_summary_trio") val mobileShowSummaryTrio: Boolean? = true,
    @SerializedName("mobile_show_quick_actions") val mobileShowQuickActions: Boolean? = true,
    @SerializedName("server_time") val serverTime: String? = null
)

// --- MODUL AGENDA & SURAT ---
data class LetterLogDto(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("type") val type: String = "masuk", // masuk, keluar, disposisi
    @SerializedName("letter_number") val letterNumber: String = "",
    @SerializedName("sender") val sender: String = "",
    @SerializedName("recipient") val recipient: String = "",
    @SerializedName("subject") val subject: String = "",
    @SerializedName("date") val date: String = "",
    @SerializedName("status") val status: String = "diproses",
    @SerializedName("notes") val notes: String = ""
)

data class LetterLogsResponse(
    @SerializedName("status") val status: String = "success",
    @SerializedName("data") val data: List<LetterLogDto> = emptyList()
)

data class CreateLetterLogRequest(
    @SerializedName("type") val type: String,
    @SerializedName("letter_number") val letterNumber: String,
    @SerializedName("sender") val sender: String,
    @SerializedName("recipient") val recipient: String,
    @SerializedName("subject") val subject: String,
    @SerializedName("date") val date: String,
    @SerializedName("notes") val notes: String = ""
)

// --- MODUL SKHPP ---
data class SkhppDto(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("reference_number") val referenceNumber: String? = null,
    @SerializedName("name") val name: String = "",
    @SerializedName("nrp") val nrp: String = "",
    @SerializedName("pangkat") val pangkat: String = "",
    @SerializedName("jabatan") val jabatan: String = "",
    @SerializedName("purpose") val purpose: String = "",
    @SerializedName("status") val status: String = "pending", // pending, verified, rejected
    @SerializedName("created_at") val createdAt: String? = null
)

data class SkhppResponse(
    @SerializedName("status") val status: String = "success",
    @SerializedName("data") val data: List<SkhppDto> = emptyList()
)

data class CreateSkhppRequest(
    @SerializedName("name") val name: String,
    @SerializedName("nrp") val nrp: String,
    @SerializedName("pangkat") val pangkat: String,
    @SerializedName("keperluan") val keperluan: String
)

// --- MODUL KAS DENINTEL ---
data class CashTransactionDto(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("type") val type: String? = null, // masuk, keluar
    @SerializedName(value = "amount", alternate = ["nominal"]) val amount: Double = 0.0,
    @SerializedName("debit") val debit: Double = 0.0,
    @SerializedName("credit") val credit: Double = 0.0,
    @SerializedName("balance") val balance: Double = 0.0,
    @SerializedName("category") val category: String = "Operasional",
    @SerializedName("description") val description: String = "",
    @SerializedName("date") val date: String = "",
    @SerializedName("receipt_urls") val receiptUrls: List<String> = emptyList()
) {
    val isIncome: Boolean
        get() = debit > 0 || (type?.lowercase() == "masuk")

    val effectiveAmount: Double
        get() = if (debit > 0) debit else if (credit > 0) credit else amount
}

data class CashResponse(
    @SerializedName("status") val status: String = "success",
    @SerializedName(value = "balance", alternate = ["totalSaldo", "total_balance"]) val totalBalance: Double = 0.0,
    @SerializedName(value = "total_debit", alternate = ["total_income", "debit"]) val totalIncome: Double = 0.0,
    @SerializedName(value = "total_credit", alternate = ["total_expense", "credit"]) val totalExpense: Double = 0.0,
    @SerializedName(value = "cashes", alternate = ["transactions", "data"]) val transactions: List<CashTransactionDto> = emptyList()
)

data class CreateCashRequest(
    @SerializedName("type") val type: String,
    @SerializedName("amount") val amount: Double,
    @SerializedName("category") val category: String = "Operasional",
    @SerializedName("description") val description: String,
    @SerializedName("date") val date: String? = null,
    @SerializedName("debit") val debit: Double = if (type.lowercase() == "masuk" || type.lowercase() == "debit") amount else 0.0,
    @SerializedName("credit") val credit: Double = if (type.lowercase() == "keluar" || type.lowercase() == "kredit") amount else 0.0
)

// --- MODUL KAS TAKTIS OPERASI ---
data class TechnicalCashTransactionDto(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("type") val type: String? = null,
    @SerializedName("amount") val amount: Double = 0.0,
    @SerializedName("debit") val debit: Double = 0.0,
    @SerializedName("credit") val credit: Double = 0.0,
    @SerializedName("balance") val balance: Double = 0.0,
    @SerializedName("description") val description: String = "",
    @SerializedName("date") val date: String = ""
) {
    val isIncome: Boolean
        get() = debit > 0 || (type?.lowercase() == "masuk")

    val effectiveAmount: Double
        get() = if (debit > 0) debit else if (credit > 0) credit else amount
}

data class TechnicalCashResponse(
    @SerializedName("status") val status: String = "success",
    @SerializedName(value = "balance", alternate = ["totalSaldo", "total_balance"]) val balance: Double = 0.0,
    @SerializedName(value = "total_debit", alternate = ["total_income"]) val totalDebit: Double = 0.0,
    @SerializedName(value = "total_credit", alternate = ["total_expense"]) val totalCredit: Double = 0.0,
    @SerializedName(value = "cashes", alternate = ["transactions", "data"]) val transactions: List<TechnicalCashTransactionDto> = emptyList()
)

data class CreateTechnicalCashRequest(
    @SerializedName("type") val type: String = "keluar",
    @SerializedName("amount") val amount: Double,
    @SerializedName("description") val description: String,
    @SerializedName("date") val date: String? = null,
    @SerializedName("debit") val debit: Double = if (type.lowercase() == "masuk" || type.lowercase() == "debit") amount else 0.0,
    @SerializedName("credit") val credit: Double = if (type.lowercase() == "keluar" || type.lowercase() == "kredit") amount else 0.0
)

// --- MODUL MITRA INTELIJEN ---
data class MitraDto(
    @SerializedName("id") val id: Int = 0,
    @SerializedName(value = "nama", alternate = ["name", "code_name"]) val nama: String = "",
    @SerializedName(value = "pt", alternate = ["sector", "perusahaan"]) val pt: String = "",
    @SerializedName(value = "no_tlp", alternate = ["phone", "telepon"]) val noTlp: String = "",
    @SerializedName("sort_order") val sortOrder: Int = 0,
    @SerializedName("keterangan") val keterangan: String? = null,
    @SerializedName("payment_matrix") val paymentMatrix: Map<String, Boolean>? = null,
    @SerializedName(value = "payment_status", alternate = ["status"]) val paymentStatus: String? = null
) {
    val name: String get() = nama.ifEmpty { "Mitra Sandi" }
    val phone: String get() = noTlp.ifEmpty { "-" }
    val sector: String get() = pt.ifEmpty { "Umum" }
    val isPaidThisMonth: Boolean
        get() {
            if (paymentStatus == "sudah_dibayar" || paymentStatus == "lunas") return true
            val currentMonth = (Calendar.getInstance().get(Calendar.MONTH) + 1).toString()
            return paymentMatrix?.get(currentMonth) == true
        }
}

data class MitraResponse(
    @SerializedName("status") val status: String = "success",
    @SerializedName("data") val data: List<MitraDto> = emptyList()
)

data class CreateMitraRequest(
    @SerializedName("nama") val name: String,
    @SerializedName("no_tlp") val phone: String,
    @SerializedName("pt") val sector: String,
    @SerializedName("keterangan") val notes: String = ""
)

// --- MODUL TANDA TANGAN ELEKTRONIK ---
data class SignatureRequestDto(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("document_title") val documentTitle: String = "",
    @SerializedName("document_number") val documentNumber: String = "",
    @SerializedName("requester_name") val requesterName: String = "",
    @SerializedName("status") val status: String = "menunggu",
    @SerializedName("created_at") val createdAt: String = ""
)

data class SignatureResponse(
    @SerializedName("status") val status: String = "success",
    @SerializedName("requests") val requests: List<SignatureRequestDto> = emptyList()
)

// --- MODUL PELANGGARAN PERSONEL ---
data class ViolationDto(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("user_name") val userName: String = "",
    @SerializedName("user_nrp") val userNrp: String = "",
    @SerializedName("violation_type") val violationType: String = "",
    @SerializedName("description") val description: String = "",
    @SerializedName("date") val date: String = "",
    @SerializedName("status") val status: String = "diproses"
)

data class ViolationResponse(
    @SerializedName("status") val status: String = "success",
    @SerializedName("data") val data: List<ViolationDto> = emptyList()
)

data class CreateViolationRequest(
    @SerializedName("user_id") val userId: Int,
    @SerializedName("violation_type") val violationType: String,
    @SerializedName("description") val description: String,
    @SerializedName("date") val date: String
)

// --- MODUL PERSONEL ---
data class PersonnelDto(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("name") val name: String = "",
    @SerializedName("nrp") val nrp: String = "",
    @SerializedName("pangkat") val pangkat: String = "",
    @SerializedName("korps") val korps: String = "",
    @SerializedName("jabatan") val jabatan: String = "",
    @SerializedName("phone") val phone: String? = null,
    @SerializedName("role") val role: String = "user"
)

data class PersonnelResponse(
    @SerializedName("status") val status: String = "success",
    @SerializedName("data") val data: List<PersonnelDto> = emptyList()
)

// --- MODUL NOTIFIKASI ---
data class NotificationDto(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("title") val title: String = "",
    @SerializedName("message") val message: String = "",
    @SerializedName("type") val type: String = "info",
    @SerializedName("created_at") val createdAt: String = "",
    @SerializedName("is_read") val isRead: Boolean = false
)

data class NotificationResponse(
    @SerializedName("status") val status: String = "success",
    @SerializedName("notifications") val notifications: List<NotificationDto> = emptyList()
)

// --- RESPONS UMUM ---
data class SimpleResponse(
    @SerializedName("status") val status: String = "success",
    @SerializedName("message") val message: String? = null
)
