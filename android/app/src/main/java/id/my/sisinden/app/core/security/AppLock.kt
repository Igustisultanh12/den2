package id.my.sisinden.app.core.security

import android.content.Context
import android.content.SharedPreferences
import android.util.Base64
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import java.security.MessageDigest
import java.security.SecureRandom
import javax.crypto.SecretKeyFactory
import javax.crypto.spec.PBEKeySpec

/**
 * PIN 6 digit seperti M-Banking. PIN tidak pernah disimpan apa adanya: yang
 * disimpan hanya hash PBKDF2-HMAC-SHA256 dengan salt acak per perangkat, di
 * penyimpanan privat aplikasi (dikecualikan dari backup).
 */
object PinRules {
    const val LENGTH = 6

    /** Null bila PIN layak; selain itu pesan alasan penolakan. */
    fun weakness(pin: String): String? {
        if (pin.length != LENGTH || !pin.all { it.isDigit() }) return "PIN harus $LENGTH angka."
        if (pin.toSet().size == 1) return "PIN tidak boleh angka yang sama semua."
        val steps = pin.zipWithNext { a, b -> b - a }.toSet()
        if (steps == setOf(1) || steps == setOf(-1)) return "PIN tidak boleh angka berurutan."
        return null
    }

    internal fun hash(pin: String, salt: ByteArray, iterations: Int = ITERATIONS): ByteArray {
        val spec = PBEKeySpec(pin.toCharArray(), salt, iterations, 256)
        return try {
            SecretKeyFactory.getInstance("PBKDF2WithHmacSHA256").generateSecret(spec).encoded
        } finally {
            spec.clearPassword()
        }
    }

    internal fun newSalt(): ByteArray = ByteArray(16).also { SecureRandom().nextBytes(it) }

    internal const val ITERATIONS = 120_000
}

/** Hasil pencocokan PIN. */
sealed interface PinResult {
    data object Ok : PinResult
    data class Wrong(val remaining: Int) : PinResult
    /** Terlalu banyak salah: sesi diakhiri, wajib masuk ulang dengan kata sandi. */
    data object Locked : PinResult
}

/** Kontrak penyimpanan PIN (memudahkan pengujian unit). */
interface LockStore {
    fun hasPin(userId: Int): Boolean
    fun setPin(userId: Int, pin: String)
    fun matches(pin: String): Boolean
    var failedAttempts: Int
    var biometricEnabled: Boolean
    fun clear()
}

/** Penyimpanan PIN & preferensi biometrik (terikat ke id pengguna). */
class AppLockStore(context: Context) : LockStore {
    private val prefs: SharedPreferences =
        context.applicationContext.getSharedPreferences("sinden_app_lock", Context.MODE_PRIVATE)

    override fun hasPin(userId: Int): Boolean =
        prefs.getInt(KEY_USER, Int.MIN_VALUE) == userId && prefs.contains(KEY_HASH) && prefs.contains(KEY_SALT)

    override fun setPin(userId: Int, pin: String) {
        val salt = PinRules.newSalt()
        prefs.edit()
            .putInt(KEY_USER, userId)
            .putString(KEY_SALT, b64(salt))
            .putString(KEY_HASH, b64(PinRules.hash(pin, salt)))
            .putInt(KEY_FAILED, 0)
            .apply()
    }

    override fun matches(pin: String): Boolean {
        val salt = prefs.getString(KEY_SALT, null)?.let(::unb64) ?: return false
        val expected = prefs.getString(KEY_HASH, null)?.let(::unb64) ?: return false
        return MessageDigest.isEqual(expected, PinRules.hash(pin, salt))
    }

    override var failedAttempts: Int
        get() = prefs.getInt(KEY_FAILED, 0)
        set(value) { prefs.edit().putInt(KEY_FAILED, value).apply() }

    override var biometricEnabled: Boolean
        get() = prefs.getBoolean(KEY_BIO, false)
        set(value) { prefs.edit().putBoolean(KEY_BIO, value).apply() }

    /** Hapus PIN & biometrik (lupa PIN / terlalu banyak salah / ganti akun). */
    override fun clear() {
        prefs.edit().clear().apply()
    }

    private fun b64(bytes: ByteArray) = Base64.encodeToString(bytes, Base64.NO_WRAP)
    private fun unb64(s: String): ByteArray? = runCatching { Base64.decode(s, Base64.NO_WRAP) }.getOrNull()

    private companion object {
        const val KEY_USER = "pin_user_id"
        const val KEY_SALT = "pin_salt"
        const val KEY_HASH = "pin_hash"
        const val KEY_FAILED = "pin_failed"
        const val KEY_BIO = "biometric_enabled"
    }
}

/**
 * Kunci aplikasi bergaya M-Banking:
 *  - setiap kali pengguna keluar dari aplikasi (Home, ganti aplikasi, layar
 *    mati) lalu kembali, aplikasi terkunci dan wajib PIN / biometrik;
 *  - selama aplikasi terbuka, terkunci setelah [TIMEOUT_MS] tanpa sentuhan;
 *  - saat dibuka dari awal, aplikasi selalu terkunci.
 * Pengecualian: membuka pemilih berkas / berbagi PDF dari dalam aplikasi
 * ([expectExternal]) dan panggilan video yang sedang berlangsung.
 */
class AppLockManager(
    val store: LockStore,
    private val now: () -> Long = System::currentTimeMillis,
) {
    private val _locked = MutableStateFlow(true)
    val locked: StateFlow<Boolean> = _locked.asStateFlow()

    @Volatile private var lastInteraction = now()
    @Volatile private var backgroundAt: Long? = null

    /** true selama ada kegiatan yang tidak boleh terputus (mis. panggilan video). */
    @Volatile var keepAlive: () -> Boolean = { false }

    fun touch() {
        lastInteraction = now()
    }

    fun onBackground() {
        backgroundAt = now()
    }

    @Volatile private var externalUntil = 0L

    /**
     * Dipanggil sebelum aplikasi sendiri membuka layar sistem (pemilih berkas,
     * simpan/bagikan PDF, membuka lampiran) agar kembalinya tidak dianggap keluar.
     */
    fun expectExternal() {
        externalUntil = now() + EXTERNAL_GRACE_MS
    }

    fun onForeground() {
        val since = backgroundAt
        backgroundAt = null
        val t = now()
        val away = if (since == null) 0L else t - since
        val internalTrip = t <= externalUntil && away < TIMEOUT_MS
        externalUntil = 0L
        if (since != null && !internalTrip && !keepAlive()) lock() else touch()
    }

    /** Dipanggil berkala selama aplikasi terbuka. */
    fun checkIdle() {
        if (keepAlive()) {
            touch()
            return
        }
        if (!_locked.value && now() - lastInteraction >= TIMEOUT_MS) lock()
    }

    fun lock() {
        _locked.value = true
    }

    /** Buka kunci setelah PIN/biometrik benar atau login dengan kata sandi. */
    fun unlock() {
        store.failedAttempts = 0
        touch()
        _locked.value = false
    }

    fun verifyPin(pin: String): PinResult {
        if (store.matches(pin)) {
            unlock()
            return PinResult.Ok
        }
        val failed = store.failedAttempts + 1
        store.failedAttempts = failed
        return if (failed >= MAX_ATTEMPTS) PinResult.Locked else PinResult.Wrong(MAX_ATTEMPTS - failed)
    }

    companion object {
        const val TIMEOUT_MS = 5 * 60_000L
        const val MAX_ATTEMPTS = 5
        /** Batas waktu kembali dari pemilih berkas/berbagi tanpa diminta PIN. */
        const val EXTERNAL_GRACE_MS = 3 * 60_000L
    }
}

/** Tandai bahwa layar berikutnya dibuka oleh aplikasi sendiri (lihat [AppLockManager.expectExternal]). */
fun android.content.Context.expectExternalActivity() {
    (applicationContext as? id.my.sisinden.app.SindenApplication)?.appLock?.expectExternal()
}
