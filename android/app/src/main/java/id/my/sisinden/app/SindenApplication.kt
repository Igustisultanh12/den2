package id.my.sisinden.app

import android.app.Application
import id.my.sisinden.app.core.data.SessionManager
import id.my.sisinden.app.core.data.SessionStore
import id.my.sisinden.app.core.network.ApiClient
import id.my.sisinden.app.core.notification.CommsState
import id.my.sisinden.app.core.notification.NotificationHelper
import id.my.sisinden.app.core.security.AppLockManager
import id.my.sisinden.app.core.security.AppLockStore
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
    lateinit var appLock: AppLockManager
        private set

    override fun onCreate() {
        super.onCreate()
        NotificationHelper.createNotificationChannel(this)
        // Firebase dari konfigurasi tersimpan, agar push tetap diterima saat aplikasi dibangunkan.
        id.my.sisinden.app.core.notification.PushManager.initFromCache(this)
        appLock = AppLockManager(AppLockStore(this)).apply {
            // Panggilan video yang sedang berlangsung tidak ikut terkunci karena "diam".
            keepAlive = { CommsState.activeCallThread != null }
        }
        sessionStore = SessionStore(this)
        sessionManager = SessionManager(sessionStore, appScope)
        val apiClient = ApiClient(sessionManager, sessionStore)
        repository = SindenRepository(this, apiClient, sessionStore, sessionManager, appScope, appLock)
    }
}
