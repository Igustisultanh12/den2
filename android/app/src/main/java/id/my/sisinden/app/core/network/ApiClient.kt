package id.my.sisinden.app.core.network

import id.my.sisinden.app.BuildConfig
import id.my.sisinden.app.core.data.SessionManager
import id.my.sisinden.app.core.data.SessionStore
import okhttp3.HttpUrl.Companion.toHttpUrlOrNull
import okhttp3.Interceptor
import okhttp3.OkHttpClient
import okhttp3.Response
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.util.concurrent.TimeUnit

/**
 * Pembuat klien HTTP. Token hanya dilampirkan ke host server SISINDEN yang
 * dikonfigurasi (tidak pernah ke host lain), dan tidak pernah dicatat ke log.
 */
class ApiClient(
    private val session: SessionManager,
    private val store: SessionStore,
) {
    @Volatile private var cachedBaseUrl: String? = null
    @Volatile private var cachedService: SindenApiService? = null

    private inner class AuthInterceptor : Interceptor {
        override fun intercept(chain: Interceptor.Chain): Response {
            val original = chain.request()
            val base = cachedBaseUrl?.toHttpUrlOrNull()
            val sameHost = base != null && original.url.host == base.host
            val token = session.token

            val builder = original.newBuilder()
                .header("Accept", "application/json")
                .header("User-Agent", "SISINDEN-Android/${BuildConfig.VERSION_NAME}")
            val attachToken = sameHost && !token.isNullOrBlank()
            if (attachToken) builder.header("Authorization", "Bearer $token")

            val response = chain.proceed(builder.build())

            if (attachToken) {
                when (response.code) {
                    401 -> session.onUnauthorized()
                    403 -> {
                        val peek = runCatching { response.peekBody(1024).string() }.getOrNull().orEmpty()
                        if (peek.contains("account_inactive")) session.onAccountDisabled()
                    }
                }
            }
            return response
        }
    }

    val httpClient: OkHttpClient by lazy {
        val builder = OkHttpClient.Builder()
            .addInterceptor(AuthInterceptor())
            .connectTimeout(15, TimeUnit.SECONDS)
            .readTimeout(45, TimeUnit.SECONDS) // PDF dihasilkan di server dan bisa memakan waktu
            .writeTimeout(60, TimeUnit.SECONDS) // unggah foto SKHPP
            .retryOnConnectionFailure(true)

        if (BuildConfig.DEBUG) {
            builder.addInterceptor(
                HttpLoggingInterceptor().apply {
                    level = HttpLoggingInterceptor.Level.BASIC
                    redactHeader("Authorization")
                    redactHeader("Cookie")
                    redactHeader("Set-Cookie")
                }
            )
        }
        builder.build()
    }

    /** Layanan Retrofit untuk URL server aktif; dibangun ulang jika URL berubah. */
    suspend fun service(): SindenApiService {
        val baseUrl = store.baseUrl().trimEnd('/') + "/"
        val existing = cachedService
        if (existing != null && cachedBaseUrl == baseUrl) return existing
        synchronized(this) {
            val again = cachedService
            if (again != null && cachedBaseUrl == baseUrl) return again
            val created = Retrofit.Builder()
                .baseUrl(baseUrl)
                .client(httpClient)
                .addConverterFactory(GsonConverterFactory.create())
                .build()
                .create(SindenApiService::class.java)
            cachedBaseUrl = baseUrl
            cachedService = created
            return created
        }
    }
}
