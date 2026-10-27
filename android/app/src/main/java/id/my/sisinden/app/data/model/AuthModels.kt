package id.my.sisinden.app.data.model

import com.google.gson.annotations.SerializedName
import id.my.sisinden.app.core.security.Perm
import id.my.sisinden.app.core.security.PermissionSet

data class LoginRequest(
    @SerializedName("username") val username: String,
    @SerializedName("password") val password: String,
    @SerializedName("device_name") val deviceName: String,
)

data class ActivateRequest(
    @SerializedName("nrp") val nrp: String,
    @SerializedName("token") val token: String,
    @SerializedName("password") val password: String,
)

data class LoginResponse(
    @SerializedName("status") val status: String? = null,
    @SerializedName("token") val token: String? = null,
    @SerializedName("message") val message: String? = null,
    @SerializedName("user") val user: UserProfile? = null,
)

data class MeResponse(
    @SerializedName("status") val status: String? = null,
    @SerializedName("user") val user: UserProfile? = null,
    @SerializedName("server_time") val serverTime: String? = null,
)

/**
 * Profil + permission resmi dari server.
 * Field `can_access_*` hanya dipakai jika server lama belum mengirim `permissions`.
 */
data class UserProfile(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("name") val name: String? = null,
    @SerializedName("email") val email: String? = null,
    @SerializedName("nrp") val nrp: String? = null,
    @SerializedName("role") val role: String? = null,
    @SerializedName("pangkat") val pangkat: String? = null,
    @SerializedName("korps") val korps: String? = null,
    @SerializedName("jabatan") val jabatan: String? = null,
    @SerializedName("phone") val phone: String? = null,
    @SerializedName("avatar_url") val avatarUrl: String? = null,
    @SerializedName("is_active") val isActive: Boolean = true,
    @SerializedName("permissions") val permissions: List<String>? = null,
    @SerializedName("can_access_agenda") val legacyAgenda: Boolean? = null,
    @SerializedName("can_access_letters") val legacyLetters: Boolean? = null,
    @SerializedName("can_access_skhpp") val legacySkhpp: Boolean? = null,
    @SerializedName("can_access_signature") val legacySignature: Boolean? = null,
    @SerializedName("can_access_cash") val legacyCash: Boolean? = null,
    @SerializedName("can_access_commander") val legacyCommander: Boolean? = null,
    @SerializedName("can_access_mitra") val legacyMitra: Boolean? = null,
    @SerializedName("can_access_technical_cash") val legacyTechnicalCash: Boolean? = null,
    @SerializedName("can_access_violations") val legacyViolations: Boolean? = null,
    @SerializedName("can_access_users") val legacyUsers: Boolean? = null,
) {
    val displayName: String get() = name?.takeIf { it.isNotBlank() } ?: "Personel"

    val firstName: String
        get() = displayName.split(" ", ",").firstOrNull { it.isNotBlank() } ?: displayName

    val initials: String
        get() = displayName.split(" ").filter { it.isNotBlank() && it.first().isLetter() }
            .take(2).joinToString("") { it.first().uppercase() }.ifEmpty { "P" }

    /** Contoh: "Kapten Laut (P) · Dan Unit 1" */
    val subtitle: String
        get() = listOfNotNull(
            pangkat?.takeIf { it.isNotBlank() },
            jabatan?.takeIf { it.isNotBlank() },
        ).joinToString(" · ").ifEmpty { roleLabel }

    val roleLabel: String
        get() = when (role?.lowercase()?.replace(Regex("[\\s_-]+"), "")) {
            "admin" -> "Administrator"
            "komandan" -> "Komandan"
            "wadan" -> "Wakil Komandan"
            "pasops" -> "Pasops"
            "staf" -> "Staf"
            "anggotasintel" -> "Anggota Sintel"
            "danunitteknis" -> "Dan Unit Teknis"
            "kaurmintel" -> "Kaurmintel"
            "paurset" -> "Paurset"
            null, "" -> "Personel"
            else -> role?.replaceFirstChar { it.uppercase() } ?: "Personel"
        }

    /** Permission resmi server; fallback kompatibilitas untuk server versi lama. */
    fun permissionSet(): PermissionSet {
        permissions?.let { return PermissionSet(it.toSet()) }
        val p = mutableSetOf(
            Perm.DASHBOARD_VIEW, Perm.PROFILE_VIEW, Perm.NOTIFICATIONS_VIEW,
            Perm.ATTENDANCE_VIEW, Perm.ATTENDANCE_CREATE,
        )
        if (legacyAgenda == true) p += listOf(Perm.LETTER_LOGS_VIEW, Perm.LETTER_LOGS_CREATE)
        if (legacySkhpp == true) p += listOf(Perm.SKHPP_VIEW)
        if (legacySignature == true) p += Perm.SIGNATURE_VIEW
        if (legacyCash == true) p += listOf(Perm.CASH_VIEW, Perm.CASH_MANAGE)
        if (legacyCommander == true) p += Perm.COMMANDER_VIEW
        if (legacyMitra == true) p += listOf(Perm.MITRA_VIEW, Perm.MITRA_MANAGE)
        if (legacyTechnicalCash == true) p += listOf(Perm.TECHNICAL_CASH_VIEW, Perm.TECHNICAL_CASH_MANAGE)
        if (legacyViolations == true) p += Perm.VIOLATIONS_VIEW
        if (legacyUsers == true) p += Perm.USERS_VIEW
        return PermissionSet(p)
    }
}

data class ConfigResponse(
    @SerializedName("agency_name") val agencyName: String? = null,
    @SerializedName("agency_logo") val agencyLogo: String? = null,
    @SerializedName("api_status") val apiStatus: String? = null,
    @SerializedName("min_version") val minVersion: String? = null,
    @SerializedName("server_time") val serverTime: String? = null,
    /** Konfigurasi klien Firebase (publik) — null bila admin belum mengatur. */
    @SerializedName("push") val push: PushConfigDto? = null,
    @SerializedName("push_enabled") val pushEnabled: Boolean? = null,
)

data class PushConfigDto(
    @SerializedName("api_key") val apiKey: String? = null,
    @SerializedName("app_id") val appId: String? = null,
    @SerializedName("project_id") val projectId: String? = null,
    @SerializedName("sender_id") val senderId: String? = null,
) {
    val isComplete: Boolean
        get() = !apiKey.isNullOrBlank() && !appId.isNullOrBlank() && !projectId.isNullOrBlank() && !senderId.isNullOrBlank()
}

data class PushRegisterRequest(
    @SerializedName("token") val token: String,
    @SerializedName("app_version") val appVersion: String?,
    @SerializedName("device_name") val deviceName: String?,
)

data class PushTestResponse(
    @SerializedName("status") val status: String? = null,
    @SerializedName("code") val code: String? = null,
    @SerializedName("message") val message: String? = null,
)

data class LogoutRequest(@SerializedName("push_token") val pushToken: String? = null)

data class SimpleResponse(
    @SerializedName("status") val status: String? = null,
    @SerializedName("message") val message: String? = null,
)
