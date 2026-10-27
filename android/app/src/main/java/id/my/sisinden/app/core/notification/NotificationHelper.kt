package id.my.sisinden.app.core.notification

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.media.AudioAttributes
import android.media.RingtoneManager
import android.net.Uri
import android.os.Build
import androidx.core.app.NotificationCompat
import id.my.sisinden.app.MainActivity
import id.my.sisinden.app.R

/**
 * Notifikasi sistem (bilah status) bersuara untuk warta dinas dan Chat Dinas.
 *
 * Deduplikasi disimpan persisten (id terakhir yang sudah dibunyikan) sehingga
 * notifikasi yang sama tidak berbunyi dua kali walau dicek dari beberapa
 * tempat (beranda, sinkron latar depan, WorkManager di latar belakang).
 */
object NotificationHelper {

    const val CHANNEL_ID = "sinden_kedinasan_channel"
    const val CHANNEL_NAME = "Warta & Instruksi Kedinasan SINDEN"
    const val CHANNEL_DESC = "Pemberitahuan resmi dan instruksi komando kedinasan Denintel Koarmada 2"

    const val CHAT_CHANNEL_ID = "sinden_chat_channel"
    private const val CHAT_CHANNEL_NAME = "Chat Dinas"
    private const val CHAT_CHANNEL_DESC = "Pesan baru pada Chat Dinas Sinden 2"

    private const val PREFS_NAME = "sinden_notification_prefs"
    private const val PREF_LAST_NOTIFIED_ID = "last_notified_id"
    private const val PREF_IS_INITIALIZED = "is_initialized"
    private const val PREF_SOUND_ENABLED = "sound_enabled"
    private const val PREF_LAST_CHAT_ID = "last_chat_message_id"
    private const val PREF_CHAT_INITIALIZED = "chat_initialized"

    /** Id notifikasi sistem untuk Chat Dinas (satu notifikasi yang diperbarui). */
    private const val CHAT_NOTIFICATION_ID = 0x5C4A7

    /** Batas banner sekaligus agar bilah status tidak dibanjiri setelah lama offline. */
    private const val MAX_BANNERS = 4

    fun isNotificationSoundEnabled(context: Context): Boolean {
        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        return prefs.getBoolean(PREF_SOUND_ENABLED, true)
    }

    fun setNotificationSoundEnabled(context: Context, enabled: Boolean) {
        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        prefs.edit().putBoolean(PREF_SOUND_ENABLED, enabled).apply()
    }

    fun createNotificationChannel(context: Context) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
        val soundUri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION)
        val audioAttributes = AudioAttributes.Builder()
            .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
            .setUsage(AudioAttributes.USAGE_NOTIFICATION_COMMUNICATION_INSTANT)
            .build()
        val manager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager

        val official = NotificationChannel(CHANNEL_ID, CHANNEL_NAME, NotificationManager.IMPORTANCE_HIGH).apply {
            description = CHANNEL_DESC
            enableLights(true)
            enableVibration(true)
            vibrationPattern = longArrayOf(0, 250, 150, 250)
            setSound(soundUri, audioAttributes)
        }
        val chat = NotificationChannel(CHAT_CHANNEL_ID, CHAT_CHANNEL_NAME, NotificationManager.IMPORTANCE_HIGH).apply {
            description = CHAT_CHANNEL_DESC
            enableLights(true)
            enableVibration(true)
            vibrationPattern = longArrayOf(0, 180, 120, 180)
            setSound(soundUri, audioAttributes)
        }
        manager.createNotificationChannel(official)
        manager.createNotificationChannel(chat)
    }

    /** Item warta minimal yang dibutuhkan untuk banner. */
    data class Item(val id: Int, val title: String, val message: String, val link: String?, val isRead: Boolean)

    /**
     * Bunyikan SEMUA warta baru yang belum dibaca sejak pengecekan terakhir
     * (bukan hanya yang terbaru). Peluncuran pertama hanya menyelaraskan
     * penanda tanpa membunyikan warta lama.
     *
     * @return jumlah banner yang dimunculkan.
     */
    fun processNotifications(context: Context, items: List<Item>): Int {
        if (items.isEmpty()) return 0
        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        val maxId = items.maxOf { it.id }
        if (!prefs.getBoolean(PREF_IS_INITIALIZED, false)) {
            prefs.edit().putBoolean(PREF_IS_INITIALIZED, true).putInt(PREF_LAST_NOTIFIED_ID, maxId).apply()
            return 0
        }
        val last = prefs.getInt(PREF_LAST_NOTIFIED_ID, 0)
        val fresh = newUnread(items, last)
        if (maxId > last) prefs.edit().putInt(PREF_LAST_NOTIFIED_ID, maxId).apply()
        fresh.take(MAX_BANNERS).forEach { showNotification(context, it.id, it.title, it.message, deepLinkFor(it.link)) }
        return fresh.size.coerceAtMost(MAX_BANNERS)
    }

    /** Warta baru (id > penanda) yang belum dibaca, terbaru lebih dulu. */
    internal fun newUnread(items: List<Item>, lastNotifiedId: Int): List<Item> =
        items.filter { it.id > lastNotifiedId && !it.isRead }.sortedByDescending { it.id }

    /**
     * Banner Chat Dinas untuk pesan masuk terbaru. [messageId] dipakai untuk
     * deduplikasi; panggil hanya bila pengguna tidak sedang membuka utas tsb.
     */
    fun processChat(context: Context, unread: Int, messageId: Long, sender: String, text: String) {
        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        if (!prefs.getBoolean(PREF_CHAT_INITIALIZED, false)) {
            prefs.edit().putBoolean(PREF_CHAT_INITIALIZED, true).putLong(PREF_LAST_CHAT_ID, messageId).apply()
            return
        }
        if (unread <= 0 || messageId <= prefs.getLong(PREF_LAST_CHAT_ID, 0L)) return
        prefs.edit().putLong(PREF_LAST_CHAT_ID, messageId).apply()
        val title = if (unread > 1) "$sender · $unread pesan baru" else sender
        // Personel hanya punya satu utas; operator membuka daftar utas.
        showNotification(context, CHAT_NOTIFICATION_ID, title, text, "chat", CHAT_CHANNEL_ID)
    }

    /** Warta dari push FCM: tampilkan bila belum pernah dibunyikan (dedup dengan polling). */
    fun showPushedNotification(context: Context, id: Int, title: String, message: String, link: String?) {
        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        val last = prefs.getInt(PREF_LAST_NOTIFIED_ID, 0)
        if (prefs.getBoolean(PREF_IS_INITIALIZED, false) && id in 1..last) return
        prefs.edit().putBoolean(PREF_IS_INITIALIZED, true).putInt(PREF_LAST_NOTIFIED_ID, maxOf(id, last)).apply()
        showNotification(context, id, title, message, deepLinkFor(link))
    }

    /** Pesan Chat Dinas dari push FCM (dedup dengan polling). */
    fun showPushedChat(context: Context, messageId: Long, sender: String, text: String) {
        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        if (messageId <= prefs.getLong(PREF_LAST_CHAT_ID, 0L)) return
        prefs.edit().putBoolean(PREF_CHAT_INITIALIZED, true).putLong(PREF_LAST_CHAT_ID, messageId).apply()
        showNotification(context, CHAT_NOTIFICATION_ID, sender, text, "chat", CHAT_CHANNEL_ID)
    }

    /** Tandai pesan chat sudah terlihat (mis. saat utas dibuka) agar tidak dibunyikan lagi. */
    fun markChatSeen(context: Context, messageId: Long) {
        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        if (messageId > prefs.getLong(PREF_LAST_CHAT_ID, 0L)) {
            prefs.edit().putBoolean(PREF_CHAT_INITIALIZED, true).putLong(PREF_LAST_CHAT_ID, messageId).apply()
        }
        val manager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        manager.cancel(CHAT_NOTIFICATION_ID)
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
            path.startsWith("/live-chat") || path.startsWith("/chat") -> "chat"
            path.startsWith("/attendance") || path.startsWith("/absensi") -> "attendance"
            path.startsWith("/letter") -> "letters"
            path.startsWith("/violation") -> "violations"
            path.startsWith("/mitra") -> "mitra"
            else -> "notifications"
        }
    }

    /** Kompatibilitas: bunyikan satu warta bila baru. */
    fun processNewNotification(
        context: Context,
        notificationId: Int,
        title: String,
        message: String,
        isRead: Boolean,
    ): Boolean = processNotifications(context, listOf(Item(notificationId, title, message, null, isRead))) > 0

    fun showNotification(
        context: Context,
        notificationId: Int,
        title: String,
        message: String,
        deepLink: String = "notifications",
        channelId: String = CHANNEL_ID,
    ) {
        createNotificationChannel(context)

        val intent = Intent(Intent.ACTION_VIEW, Uri.parse("sinden2://app/$deepLink"), context, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
        }
        val pendingIntent = PendingIntent.getActivity(
            context,
            notificationId,
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
        )

        val soundEnabled = isNotificationSoundEnabled(context)
        val soundUri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION)

        val builder = NotificationCompat.Builder(context, channelId)
            .setSmallIcon(R.drawable.ic_stat_sinden)
            .setContentTitle(title)
            .setContentText(message)
            .setStyle(NotificationCompat.BigTextStyle().bigText(message))
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setCategory(if (channelId == CHAT_CHANNEL_ID) NotificationCompat.CATEGORY_MESSAGE else NotificationCompat.CATEGORY_REMINDER)
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
        try {
            notificationManager.notify(notificationId, builder.build())
        } catch (_: SecurityException) {
            // Izin POST_NOTIFICATIONS dicabut pengguna: abaikan tanpa menghentikan aplikasi.
        }
    }
}
