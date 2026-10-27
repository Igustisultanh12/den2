package id.my.sisinden.app.core.security

/**
 * Kunci permission resmi yang dikirim backend (App\Support\MobileAccess).
 *
 * Aplikasi TIDAK menghitung hak akses sendiri. Daftar ini hanya nama konstan
 * agar tidak salah ketik; nilainya (diizinkan/tidak) selalu berasal dari server
 * dan backend tetap menolak setiap operasi tanpa izin.
 */
object Perm {
    const val DASHBOARD_VIEW = "dashboard.view"
    const val PROFILE_VIEW = "profile.view"
    const val NOTIFICATIONS_VIEW = "notifications.view"

    const val CHAT_VIEW = "chat.view"
    const val CHAT_MANAGE = "chat.manage"

    const val ATTENDANCE_VIEW = "attendance.view"
    const val ATTENDANCE_CREATE = "attendance.create"

    const val LOCATION_REPORT = "location.report"
    const val TRACKING_VIEW = "tracking.view"

    const val DUTY_VIEW = "duty_roster.view"
    const val DUTY_PDF = "duty_roster.pdf"
    const val DUTY_SIGN = "duty_roster.sign"

    const val LETTER_LOGS_VIEW = "letter_logs.view"
    const val LETTER_LOGS_CREATE = "letter_logs.create"
    const val LETTER_LOGS_UPDATE = "letter_logs.update"
    const val LETTER_LOGS_DELETE = "letter_logs.delete"
    const val LETTER_LOGS_PDF = "letter_logs.pdf"

    const val SKHPP_VIEW = "skhpp.view"
    const val SKHPP_CREATE = "skhpp.create"
    const val SKHPP_APPROVE = "skhpp.approve"
    const val SKHPP_DELETE = "skhpp.delete"
    const val SKHPP_PDF = "skhpp.pdf"

    const val SIGNATURE_VIEW = "signature.view"
    const val SIGNATURE_APPROVE = "signature.approve"

    const val CASH_VIEW = "cash.view"
    const val CASH_MANAGE = "cash.manage"
    const val CASH_PDF = "cash.pdf"

    const val COMMANDER_VIEW = "commander_account.view"
    const val COMMANDER_MANAGE = "commander_account.manage"
    const val COMMANDER_PDF = "commander_account.pdf"


    const val MITRA_VIEW = "mitra.view"
    const val MITRA_MANAGE = "mitra.manage"

    const val TECHNICAL_CASH_VIEW = "technical_cash.view"
    const val TECHNICAL_CASH_MANAGE = "technical_cash.manage"

    const val VIOLATIONS_VIEW = "violations.view"
    const val VIOLATIONS_MANAGE = "violations.manage"

    const val CATEGORIES_VIEW = "categories.view"

    const val USERS_VIEW = "users.view"
    const val USERS_MANAGE = "users.manage"
}

/** Kumpulan permission milik sesi aktif. */
data class PermissionSet(val granted: Set<String>) {
    fun has(permission: String): Boolean = permission in granted
    fun hasAny(vararg permissions: String): Boolean = permissions.any { it in granted }

    companion object {
        val EMPTY = PermissionSet(emptySet())
    }
}
