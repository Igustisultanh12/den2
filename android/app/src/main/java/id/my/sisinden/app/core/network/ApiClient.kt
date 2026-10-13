package id.my.sisinden.app.core.network

import id.my.sisinden.app.core.data.DataStoreManager
import kotlinx.coroutines.runBlocking
import okhttp3.Interceptor
import okhttp3.OkHttpClient
import okhttp3.Response
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.util.concurrent.TimeUnit

class ApiClient(private val dataStoreManager: DataStoreManager) {

    private class AuthInterceptor(private val dataStoreManager: DataStoreManager) : Interceptor {
        override fun intercept(chain: Interceptor.Chain): Response {
            val token = runBlocking { dataStoreManager.getToken() }
            val user = runBlocking { dataStoreManager.getUser() }
            val original = chain.request()
            val requestBuilder = original.newBuilder()
                .header("Accept", "application/json")
                .header("User-Agent", "SISINDEN-Android/1.0 (Kedinasan Android Native)")

            if (!token.isNullOrEmpty()) {
                requestBuilder.header("Authorization", "Bearer $token")
            }

            if (user != null) {
                requestBuilder.header("X-User-Id", user.id.toString())
                if (!user.nrp.isNullOrEmpty()) {
                    requestBuilder.header("X-User-Nrp", user.nrp)
                }
                if (!user.email.isNullOrEmpty()) {
                    requestBuilder.header("X-User-Email", user.email)
                }
            }

            return chain.proceed(requestBuilder.build())
        }
    }

    private val okHttpClient: OkHttpClient by lazy {
        val logging = HttpLoggingInterceptor().apply {
            level = HttpLoggingInterceptor.Level.BODY
        }

        OkHttpClient.Builder()
            .addInterceptor(AuthInterceptor(dataStoreManager))
            .addInterceptor(logging)
            .connectTimeout(15, TimeUnit.SECONDS)
            .readTimeout(20, TimeUnit.SECONDS)
            .writeTimeout(20, TimeUnit.SECONDS)
            .build()
    }

    fun getService(): SindenApiService {
        val baseUrl = runBlocking { dataStoreManager.getBaseUrl() }
        val sanitizedBaseUrl = if (baseUrl.endsWith("/")) baseUrl else "$baseUrl/"

        return Retrofit.Builder()
            .baseUrl(sanitizedBaseUrl)
            .client(okHttpClient)
            .addConverterFactory(GsonConverterFactory.create())
            .build()
            .create(SindenApiService::class.java)
    }
}
