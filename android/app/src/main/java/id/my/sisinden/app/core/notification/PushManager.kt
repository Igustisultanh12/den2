package id.my.sisinden.app.core.notification

import android.content.Context
import android.util.Log
import com.google.firebase.FirebaseApp
import com.google.firebase.FirebaseOptions
import com.google.firebase.messaging.FirebaseMessaging
import id.my.sisinden.app.data.model.PushConfigDto
import id.my.sisinden.app.data.repository.SindenRepository

/**
 * Notifikasi push (Firebase Cloud Messaging) — seperti WhatsApp, pesan &
 * panggilan tetap muncul walau aplikasi ditutup.
 *
 * Konfigurasi klien Firebase dikirim server (/api/mobile/config) setelah Admin
 * mengunggah google-services.json di Pengaturan web, lalu disimpan di HP agar
 * Firebase dapat diinisialisasi saat aplikasi dibangunkan oleh pesan push.
 */
object PushManager {
    private const val TAG = "SindenPush"
    private const val PREFS = "sinden_push"
    private const val K_API = "api_key"
    private const val K_APP = "app_id"
    private const val K_PROJECT = "project_id"
    private const val K_SENDER = "sender_id"
    private const val K_TOKEN = "last_token"

    private fun prefs(context: Context) = context.applicationContext.getSharedPreferences(PREFS, Context.MODE_PRIVATE)

    fun isReady(context: Context): Boolean = FirebaseApp.getApps(context).isNotEmpty()

    /** Dipanggil di Application.onCreate: pakai konfigurasi tersimpan. */
    fun initFromCache(context: Context) {
        val p = prefs(context)
        val cfg = PushConfigDto(p.getString(K_API, null), p.getString(K_APP, null), p.getString(K_PROJECT, null), p.getString(K_SENDER, null))
        if (cfg.isComplete) init(context, cfg)
    }

    /** Simpan konfigurasi dari server dan inisialisasi Firebase bila belum. */
    fun configure(context: Context, cfg: PushConfigDto?): Boolean {
        if (cfg == null || !cfg.isComplete) return isReady(context)
        prefs(context).edit()
            .putString(K_API, cfg.apiKey)
            .putString(K_APP, cfg.appId)
            .putString(K_PROJECT, cfg.projectId)
            .putString(K_SENDER, cfg.senderId)
            .apply()
        return init(context, cfg)
    }

    private fun init(context: Context, cfg: PushConfigDto): Boolean {
        if (isReady(context)) return true
        return try {
            val options = FirebaseOptions.Builder()
                .setApiKey(cfg.apiKey!!)
                .setApplicationId(cfg.appId!!)
                .setProjectId(cfg.projectId!!)
                .setGcmSenderId(cfg.senderId!!)
                .build()
            FirebaseApp.initializeApp(context.applicationContext, options)
            true
        } catch (e: Exception) {
            Log.w(TAG, "Firebase tidak dapat diinisialisasi: ${e.javaClass.simpleName}")
            false
        }
    }

    fun lastToken(context: Context): String? = prefs(context).getString(K_TOKEN, null)

    internal fun saveToken(context: Context, token: String) {
        prefs(context).edit().putString(K_TOKEN, token).apply()
    }

    /** Ambil token FCM lalu daftarkan ke akun yang sedang masuk. */
    fun register(context: Context, repository: SindenRepository) {
        if (!isReady(context) || repository.session.token == null) return
        try {
            FirebaseMessaging.getInstance().token.addOnSuccessListener { token ->
                if (!token.isNullOrBlank()) {
                    saveToken(context, token)
                    repository.appScopeLaunch { repository.registerPushToken(token) }
                }
            }
        } catch (e: Exception) {
            Log.w(TAG, "Token FCM tidak tersedia: ${e.javaClass.simpleName}")
        }
    }
}
