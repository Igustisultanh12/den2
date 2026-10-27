package id.my.sisinden.app.core.notification

import android.content.Context
import androidx.work.Constraints
import androidx.work.CoroutineWorker
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.NetworkType
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import androidx.work.WorkerParameters
import id.my.sisinden.app.SindenApplication
import id.my.sisinden.app.core.data.SessionState
import id.my.sisinden.app.core.network.ApiResult
import id.my.sisinden.app.core.security.Perm
import id.my.sisinden.app.data.repository.SindenRepository
import java.util.concurrent.TimeUnit

/**
 * Layar/utas yang sedang terbuka di aplikasi, agar notifikasi & dering
 * tidak muncul untuk percakapan yang sedang dilihat pengguna.
 */
object CommsState {
    /** UUID utas Chat Dinas yang sedang terbuka (null = tidak ada). */
    @Volatile var activeChatThread: String? = null

    /** true bila layar Chat Dinas (daftar/utas) sedang tampil. */
    @Volatile var chatScreenVisible: Boolean = false

    /** Aktivitas utama sedang tampil di layar. */
    @Volatile var appForeground: Boolean = false

    /** Navigasi utama tampil & tidak terkunci (poller panggilan aktif). */
    @Volatile var mainUiVisible: Boolean = false

    /** UUID utas yang sedang dalam panggilan (null = tidak ada panggilan). */
    @Volatile var activeCallThread: String? = null

    private val handledCalls = LinkedHashSet<String>()

    /** Tandai panggilan masuk sudah ditangani; true bila baru pertama kali. */
    @Synchronized
    fun claimCall(callId: String): Boolean {
        if (callId in handledCalls) return false
        handledCalls += callId
        while (handledCalls.size > 20) handledCalls.remove(handledCalls.first())
        return true
    }
}

/**
 * Sinkronisasi notifikasi bersuara di bilah status.
 *
 * Dipanggil dari dua tempat:
 *  - latar depan: setiap ±30 detik selama aplikasi terbuka;
 *  - latar belakang: [NotificationSyncWorker] (WorkManager, minimal 15 menit,
 *    jadwal dapat ditunda sistem saat mode hemat daya/Doze).
 *
 * Server Sinden 2 belum memakai push (FCM), sehingga notifikasi di latar
 * belakang tidak instan.
 */
object NotificationSync {

    suspend fun run(context: Context, repository: SindenRepository) {
        val perms = repository.session.permissions
        if (perms.has(Perm.NOTIFICATIONS_VIEW)) {
            val res = repository.notifications()
            if (res is ApiResult.Success) {
                val items = res.data.notifications.orEmpty().map {
                    NotificationHelper.Item(
                        id = it.id,
                        title = it.title?.ifBlank { null } ?: "Warta Dinas Sinden 2",
                        message = it.message?.ifBlank { null } ?: "Terdapat pemberitahuan kedinasan baru.",
                        link = it.link,
                        isRead = it.isRead,
                    )
                }
                NotificationHelper.processNotifications(context, items)
            }
        }
        if (perms.has(Perm.CHAT_VIEW) && !CommsState.chatScreenVisible) {
            val res = repository.chatUnread()
            if (res is ApiResult.Success) {
                val latest = res.data.latest
                if (latest != null && !latest.isSystem && res.data.threadUuid != CommsState.activeChatThread) {
                    val text = latest.message?.takeIf { it.isNotBlank() } ?: "Mengirim lampiran"
                    NotificationHelper.processChat(
                        context = context,
                        unread = res.data.unread,
                        messageId = latest.id,
                        sender = latest.senderName?.ifBlank { null } ?: "Chat Dinas",
                        text = text,
                    )
                }
            }
        }
    }

    private const val WORK_NAME = "sinden_notification_sync"

    /** Jadwalkan sinkron latar belakang (idempoten). */
    fun schedule(context: Context) {
        val request = PeriodicWorkRequestBuilder<NotificationSyncWorker>(15, TimeUnit.MINUTES)
            .setConstraints(Constraints.Builder().setRequiredNetworkType(NetworkType.CONNECTED).build())
            .build()
        WorkManager.getInstance(context).enqueueUniquePeriodicWork(WORK_NAME, ExistingPeriodicWorkPolicy.KEEP, request)
    }

    fun cancel(context: Context) {
        WorkManager.getInstance(context).cancelUniqueWork(WORK_NAME)
    }
}

class NotificationSyncWorker(context: Context, params: WorkerParameters) : CoroutineWorker(context, params) {
    override suspend fun doWork(): Result {
        val app = applicationContext as? SindenApplication ?: return Result.success()
        val session = app.sessionManager
        if (session.state.value is SessionState.Restoring) session.restore()
        if (session.state.value !is SessionState.LoggedIn) return Result.success()
        return try {
            NotificationSync.run(applicationContext, app.repository)
            Result.success()
        } catch (_: Exception) {
            Result.retry()
        }
    }
}
