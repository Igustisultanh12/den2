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
            "chat" -> {
                val thread = d["thread_uuid"]
                // Sedang membuka utas yang sama di layar → tidak perlu banner.
                if (CommsState.appForeground && thread != null && thread == CommsState.activeChatThread) return
                NotificationHelper.showPushedChat(
                    this,
                    messageId = d["id"]?.toLongOrNull() ?: System.currentTimeMillis(),
                    sender = d["title"].orEmpty().ifBlank { "Chat Dinas" },
                    text = d["body"].orEmpty().ifBlank { "Pesan baru" },
                )
            }
            "call" -> {
                val thread = d["thread_uuid"].orEmpty()
                val callId = d["call_id"].orEmpty()
                if (thread.isBlank() || CommsState.activeCallThread != null) return
                // Aplikasi sedang terbuka & tidak terkunci: layar panggilan dibuka oleh poller.
                if (CommsState.appForeground && CommsState.mainUiVisible) return
                if (callId.isNotBlank()) CommsState.claimCall(callId)
                CallNotifications.showIncoming(
                    this,
                    threadUuid = thread,
                    video = !d["call_type"].equals("audio", ignoreCase = true),
                    callerName = d["caller_name"].orEmpty().ifBlank { "Panggilan Dinas" },
                )
            }
            "call_cancel" -> CallNotifications.cancel(this)
            // Admin mengunggah APK baru di Pengaturan web.
            "app_update" -> AppUpdater.onServerPush(this, d["version"].orEmpty(), d["title"].orEmpty(), d["body"].orEmpty())
        }
    }
}
