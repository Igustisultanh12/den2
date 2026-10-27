package id.my.sisinden.app.core.notification

import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import id.my.sisinden.app.SindenApplication
import id.my.sisinden.app.core.data.SessionState
import id.my.sisinden.app.core.update.AppUpdater

/**
 * Penerima pesan push FCM. Server mengirim pesan data berprioritas tinggi
 * sehingga aplikasi sendiri yang menampilkan notifikasi (kanal, suara, tautan)
 * — tetap berjalan walau aplikasi sedang ditutup.
 */
class SindenMessagingService : FirebaseMessagingService() {

    override fun onNewToken(token: String) {
        PushManager.saveToken(this, token)
        val app = applicationContext as? SindenApplication ?: return
        app.repository.appScopeLaunch {
            if (app.sessionManager.state.value is SessionState.Restoring) app.sessionManager.restore()
            if (app.sessionManager.state.value is SessionState.LoggedIn) app.repository.registerPushToken(token)
        }
    }

    override fun onMessageReceived(message: RemoteMessage) {
        val d = message.data
        when (d["type"]) {
            "notification" -> NotificationHelper.showPushedNotification(
                this,
                id = d["id"]?.toIntOrNull() ?: (System.currentTimeMillis() / 1000).toInt(),
                title = d["title"].orEmpty().ifBlank { "Warta Dinas Sinden 2" },
                message = d["body"].orEmpty().ifBlank { "Terdapat pemberitahuan kedinasan baru." },
                link = d["link"],
            )
            "chat" -> NotificationHelper.showPushedChat(
                this,
                sender = d["title"].orEmpty().ifBlank { "Chat Dinas" },
                text = d["body"].orEmpty().ifBlank { "Pesan baru" },
            )
            // Admin mengunggah APK baru di Pengaturan web.
            "app_update" -> AppUpdater.onServerPush(this, d["version"].orEmpty(), d["title"].orEmpty(), d["body"].orEmpty())
        }
    }
}
