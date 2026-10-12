package id.my.sisinden.app

import android.app.Application
import id.my.sisinden.app.core.data.DataStoreManager
import id.my.sisinden.app.core.network.ApiClient
import id.my.sisinden.app.data.repository.SindenRepository

class SindenApplication : Application() {

    lateinit var dataStoreManager: DataStoreManager
        private set

    lateinit var apiClient: ApiClient
        private set

    lateinit var repository: SindenRepository
        private set

    override fun onCreate() {
        super.onCreate()
        id.my.sisinden.app.core.notification.NotificationHelper.createNotificationChannel(this)
        dataStoreManager = DataStoreManager(this)
        apiClient = ApiClient(dataStoreManager)
        repository = SindenRepository(dataStoreManager, apiClient)
    }
}
