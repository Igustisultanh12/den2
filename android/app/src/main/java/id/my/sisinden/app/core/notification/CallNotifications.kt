package id.my.sisinden.app.core.notification

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.media.AudioAttributes
import android.media.RingtoneManager
import android.net.Uri
import android.os.Build
import androidx.core.app.NotificationCompat
import id.my.sisinden.app.MainActivity
import id.my.sisinden.app.R
import id.my.sisinden.app.SindenApplication
import id.my.sisinden.app.core.data.SessionState

/**
 * Notifikasi panggilan masuk layar penuh (seperti WhatsApp): berdering dengan
 * nada dering HP, tampil di layar kunci, tombol Jawab / Tolak.
 */
object CallNotifications {
    const val CHANNEL_ID = "sinden_call_channel"
    private const val NOTIFICATION_ID = 0x5CA11
    const val ACTION_REJECT = "id.my.sisinden.app.action.REJECT_CALL"
    const val EXTRA_THREAD = "thread_uuid"

    private fun ensureChannel(context: Context) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
        val manager = context.getSystemService(NotificationManager::class.java)
        if (manager.getNotificationChannel(CHANNEL_ID) != null) return
        val ringtone = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_RINGTONE)
        val channel = NotificationChannel(CHANNEL_ID, "Panggilan Dinas", NotificationManager.IMPORTANCE_HIGH).apply {
            description = "Panggilan video/suara masuk dari Chat Dinas"
            enableVibration(true)
            vibrationPattern = longArrayOf(0, 800, 600, 800, 600)
            lockscreenVisibility = Notification.VISIBILITY_PUBLIC
            setSound(
                ringtone,
                AudioAttributes.Builder()
                    .setUsage(AudioAttributes.USAGE_NOTIFICATION_RINGTONE)
                    .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                    .build(),
            )
        }
        manager.createNotificationChannel(channel)
    }

    fun callDeepLink(threadUuid: String, video: Boolean, peer: String): Uri =
        Uri.parse("sinden2://app/call/${Uri.encode(threadUuid)}?video=$video&incoming=true&peer=${Uri.encode(peer)}")

    fun showIncoming(context: Context, threadUuid: String, video: Boolean, callerName: String) {
        ensureChannel(context)
        val open = Intent(Intent.ACTION_VIEW, callDeepLink(threadUuid, video, callerName), context, MainActivity::class.java)
            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP)
        val openPi = PendingIntent.getActivity(context, NOTIFICATION_ID, open, PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE)
        val reject = Intent(context, CallActionReceiver::class.java).setAction(ACTION_REJECT).putExtra(EXTRA_THREAD, threadUuid)
        val rejectPi = PendingIntent.getBroadcast(context, NOTIFICATION_ID + 1, reject, PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE)

        val notification = NotificationCompat.Builder(context, CALL_CHANNEL)
            .setSmallIcon(R.drawable.ic_stat_sinden)
            .setContentTitle(callerName)
            .setContentText(if (video) "Panggilan video dinas masuk" else "Panggilan suara dinas masuk")
            .setCategory(NotificationCompat.CATEGORY_CALL)
            .setPriority(NotificationCompat.PRIORITY_MAX)
            .setVisibility(NotificationCompat.VISIBILITY_PUBLIC)
            .setOngoing(true)
            .setAutoCancel(true)
            .setTimeoutAfter(45_000)
            .setSound(RingtoneManager.getDefaultUri(RingtoneManager.TYPE_RINGTONE))
            .setVibrate(longArrayOf(0, 800, 600, 800, 600))
            .setContentIntent(openPi)
            .setFullScreenIntent(openPi, true)
            .addAction(0, "Tolak", rejectPi)
            .addAction(0, "Jawab", openPi)
            .build()
        // Ulangi nada dering sampai dijawab/ditolak/kedaluwarsa.
        notification.flags = notification.flags or Notification.FLAG_INSISTENT
        try {
            context.getSystemService(NotificationManager::class.java).notify(NOTIFICATION_ID, notification)
        } catch (_: SecurityException) {
            // Izin notifikasi dicabut pengguna.
        }
    }

    fun cancel(context: Context) {
        context.getSystemService(NotificationManager::class.java).cancel(NOTIFICATION_ID)
    }

    private const val CALL_CHANNEL = CHANNEL_ID
}

/** Tombol "Tolak" pada notifikasi panggilan. */
class CallActionReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action != CallNotifications.ACTION_REJECT) return
        CallNotifications.cancel(context)
        val uuid = intent.getStringExtra(CallNotifications.EXTRA_THREAD) ?: return
        val app = context.applicationContext as? SindenApplication ?: return
        val pending = goAsync()
        app.repository.appScopeLaunch {
            try {
                if (app.sessionManager.state.value is SessionState.Restoring) app.sessionManager.restore()
                if (app.sessionManager.state.value is SessionState.LoggedIn) app.repository.endCall(uuid, "rejected")
            } finally {
                pending.finish()
            }
        }
    }
}
