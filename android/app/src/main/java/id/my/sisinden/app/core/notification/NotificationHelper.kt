package id.my.sisinden.app.core.notification

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.media.AudioAttributes
import android.media.RingtoneManager
import android.os.Build
import androidx.core.app.NotificationCompat
import id.my.sisinden.app.MainActivity
import id.my.sisinden.app.R

object NotificationHelper {

    const val CHANNEL_ID = "sinden_kedinasan_channel"
    const val CHANNEL_NAME = "Warta & Instruksi Kedinasan SINDEN"
    const val CHANNEL_DESC = "Pemberitahuan resmi dan instruksi komando kedinasan Denintel Koarmada 2"

    private const val PREFS_NAME = "sinden_notification_prefs"
    private const val PREF_LAST_NOTIFIED_ID = "last_notified_id"
    private const val PREF_IS_INITIALIZED = "is_initialized"
    private const val PREF_SOUND_ENABLED = "sound_enabled"

    fun isNotificationSoundEnabled(context: Context): Boolean {
        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        return prefs.getBoolean(PREF_SOUND_ENABLED, true)
    }

    fun setNotificationSoundEnabled(context: Context, enabled: Boolean) {
        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        prefs.edit().putBoolean(PREF_SOUND_ENABLED, enabled).apply()
    }

    fun createNotificationChannel(context: Context) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val soundUri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION)
            val audioAttributes = AudioAttributes.Builder()
                .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                .setUsage(AudioAttributes.USAGE_NOTIFICATION_COMMUNICATION_INSTANT)
                .build()

            val importance = NotificationManager.IMPORTANCE_HIGH
            val channel = NotificationChannel(CHANNEL_ID, CHANNEL_NAME, importance).apply {
                description = CHANNEL_DESC
                enableLights(true)
                enableVibration(true)
                vibrationPattern = longArrayOf(0, 250, 150, 250)
                setSound(soundUri, audioAttributes)
            }

            val notificationManager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            notificationManager.createNotificationChannel(channel)
        }
    }

    fun processNewNotification(
        context: Context,
        notificationId: Int,
        title: String,
        message: String,
        isRead: Boolean
    ): Boolean {
        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        val initialized = prefs.getBoolean(PREF_IS_INITIALIZED, false)
        val lastNotified = prefs.getInt(PREF_LAST_NOTIFIED_ID, 0)

        // Sinkronisasi senyap pada peluncuran pertama: tidak membunyikan notifikasi lama
        if (!initialized) {
            prefs.edit()
                .putBoolean(PREF_IS_INITIALIZED, true)
                .putInt(PREF_LAST_NOTIFIED_ID, notificationId)
                .apply()
            return false
        }

        // Hanya berbunyi dan memunculkan banner jika notifikasi benar-benar baru dan belum dibaca
        if (notificationId > lastNotified && !isRead) {
            prefs.edit().putInt(PREF_LAST_NOTIFIED_ID, notificationId).apply()
            showNotification(context, notificationId, title, message)
            return true
        }

        return false
    }

    /** Warta dari push FCM: tampilkan bila belum pernah dibunyikan (dedup dengan pemeriksaan berkala). */
    fun showPushedNotification(context: Context, id: Int, title: String, message: String, link: String?) {
        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        val last = prefs.getInt(PREF_LAST_NOTIFIED_ID, 0)
        if (prefs.getBoolean(PREF_IS_INITIALIZED, false) && id in 1..last) return
        prefs.edit().putBoolean(PREF_IS_INITIALIZED, true).putInt(PREF_LAST_NOTIFIED_ID, maxOf(id, last)).apply()
        showNotification(context, id, title, message, deepLinkFor(link))
    }

    /** Pesan Chat Dinas dari push FCM (satu banner, diperbarui untuk pesan berikutnya). */
    fun showPushedChat(context: Context, sender: String, text: String) {
        showNotification(context, CHAT_NOTIFICATION_ID, sender, text, "notifications")
    }

    /**
     * Petakan tautan web notifikasi (kolom `link` AppNotification) ke rute
     * aplikasi. Hak akses tetap diperiksa NavHost saat tautan dibuka.
     */
    fun deepLinkFor(link: String?): String {
        val path = link.orEmpty().substringBefore('?').trim().lowercase()
        return when {
            path.startsWith("/signature-requests") || path.startsWith("/signature") -> "signature"
            path.startsWith("/sp-jaga") || path.startsWith("/duty") -> "duty"
            path.startsWith("/skhpp") -> "skhpp"
            path.startsWith("/attendance") || path.startsWith("/absensi") -> "attendance"
            path.startsWith("/letter") -> "letters"
            path.startsWith("/violation") -> "violations"
            path.startsWith("/mitra") -> "mitra"
            path.startsWith("/pantau-personel") -> "tracking"
            else -> "notifications"
        }
    }

    private const val CHAT_NOTIFICATION_ID = 0x0C4A7

    fun showNotification(
        context: Context,
        notificationId: Int,
        title: String,
        message: String,
        /** Rute aplikasi (deep link sisinden://app/<route>); null = buka aplikasi saja. */
        route: String? = null,
    ) {
        createNotificationChannel(context)

        val intent = (if (route != null) {
            Intent(Intent.ACTION_VIEW, android.net.Uri.parse("sisinden://app/$route"), context, MainActivity::class.java)
        } else {
            Intent(context, MainActivity::class.java)
        }).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
        }

        val pendingIntent = PendingIntent.getActivity(
            context,
            notificationId,
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val soundEnabled = isNotificationSoundEnabled(context)
        val soundUri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION)

        val builder = NotificationCompat.Builder(context, CHANNEL_ID)
            .setSmallIcon(R.drawable.ic_sinden_mark)
            .setContentTitle(title)
            .setContentText(message)
            .setStyle(NotificationCompat.BigTextStyle().bigText(message))
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setAutoCancel(true)
            .setContentIntent(pendingIntent)

        if (soundEnabled) {
            builder.setDefaults(NotificationCompat.DEFAULT_ALL)
                .setSound(soundUri)
                .setVibrate(longArrayOf(0, 250, 150, 250))
        } else {
            builder.setSilent(true)
        }

        val notificationManager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        notificationManager.notify(notificationId, builder.build())
    }
}
