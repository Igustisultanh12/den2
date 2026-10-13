package id.my.sisinden.app

import android.app.Application
import id.my.sisinden.app.core.data.SessionManager
import id.my.sisinden.app.core.data.SessionStore
import id.my.sisinden.app.core.network.ApiClient
import id.my.sisinden.app.core.notification.NotificationHelper
import id.my.sisinden.app.data.repository.SindenRepository
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob

/** Kontainer dependensi sederhana (tanpa framework DI, sesuai arsitektur awal). */
class SindenApplication : Application() {

    val appScope: CoroutineScope = CoroutineScope(SupervisorJob() + Dispatchers.Main.immediate)

    lateinit var sessionStore: SessionStore
        private set
    lateinit var sessionManager: SessionManager
        private set
    lateinit var repository: SindenRepository
        private set

    override fun onCreate() {
        super.onCreate()
        NotificationHelper.createNotificationChannel(this)
        sessionStore = SessionStore(this)
        sessionManager = SessionManager(sessionStore, appScope)
        val apiClient = ApiClient(sessionManager, sessionStore)
        repository = SindenRepository(this, apiClient, sessionStore, sessionManager, appScope)
    }
}
