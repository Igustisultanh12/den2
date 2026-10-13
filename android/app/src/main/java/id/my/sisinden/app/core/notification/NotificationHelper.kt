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
    const val CHANNEL_DESC = "Pemberitahuan resmi dan instruksi komando kedinasan Denintel Kodaeral V"

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

    fun showNotification(
        context: Context,
        notificationId: Int,
        title: String,
        message: String
    ) {
        createNotificationChannel(context)

        val intent = Intent(context, MainActivity::class.java).apply {
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
            .setSmallIcon(R.mipmap.ic_launcher)
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
