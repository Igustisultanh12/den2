package id.my.sisinden.app.data.model

import com.google.gson.annotations.SerializedName

// --- MODUL AUTENTIKASI ---
data class LoginRequest(
    @SerializedName("username") val username: String,
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
)

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
    @SerializedName("type") val type: String = "masuk", // masuk, keluar
    @SerializedName("amount") val amount: Double = 0.0,
    @SerializedName("category") val category: String = "",
    @SerializedName("description") val description: String = "",
    @SerializedName("date") val date: String = ""
)

data class CashResponse(
    @SerializedName("status") val status: String = "success",
    @SerializedName("total_balance") val totalBalance: Double = 0.0,
    @SerializedName("total_income") val totalIncome: Double = 0.0,
    @SerializedName("total_expense") val totalExpense: Double = 0.0,
    @SerializedName("transactions") val transactions: List<CashTransactionDto> = emptyList()
)

data class CreateCashRequest(
    @SerializedName("type") val type: String,
    @SerializedName("amount") val amount: Double,
    @SerializedName("category") val category: String,
    @SerializedName("description") val description: String
)

// --- MODUL KAS TAKTIS OPERASI ---
data class TechnicalCashTransactionDto(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("type") val type: String = "keluar",
    @SerializedName("amount") val amount: Double = 0.0,
    @SerializedName("description") val description: String = "",
    @SerializedName("date") val date: String = ""
)

data class TechnicalCashResponse(
    @SerializedName("status") val status: String = "success",
    @SerializedName("balance") val balance: Double = 0.0,
    @SerializedName("transactions") val transactions: List<TechnicalCashTransactionDto> = emptyList()
)

data class CreateTechnicalCashRequest(
    @SerializedName("type") val type: String,
    @SerializedName("amount") val amount: Double,
    @SerializedName("description") val description: String
)

// --- MODUL MITRA INTELIJEN ---
data class MitraDto(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("name") val name: String = "",
    @SerializedName("code_name") val codeName: String = "",
    @SerializedName("phone") val phone: String = "",
    @SerializedName("sector") val sector: String = "",
    @SerializedName("payment_status") val paymentStatus: String = "belum_dibayar",
    @SerializedName("last_contact") val lastContact: String = ""
)

data class MitraResponse(
    @SerializedName("status") val status: String = "success",
    @SerializedName("data") val data: List<MitraDto> = emptyList()
)

data class CreateMitraRequest(
    @SerializedName("name") val name: String,
    @SerializedName("phone") val phone: String,
    @SerializedName("code_name") val codeName: String,
    @SerializedName("target_area") val targetArea: String
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
