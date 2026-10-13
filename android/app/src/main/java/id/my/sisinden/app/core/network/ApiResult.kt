package id.my.sisinden.app.core.network

import com.google.gson.JsonParser
import kotlinx.coroutines.CancellationException
import retrofit2.Response
import java.io.IOException
import java.net.SocketTimeoutException
import java.net.UnknownHostException
import javax.net.ssl.SSLException

/** Jenis kegagalan yang dibedakan di UI (pesan & tindakan berbeda). */
enum class ErrorKind { NETWORK, TIMEOUT, UNAUTHORIZED, FORBIDDEN, NOT_FOUND, VALIDATION, RATE_LIMITED, SERVER, UNKNOWN }

data class ApiError(
    val kind: ErrorKind,
    val message: String,
    val code: Int? = null,
)

sealed interface ApiResult<out T> {
    data class Success<T>(val data: T) : ApiResult<T>
    data class Failure(val error: ApiError) : ApiResult<Nothing>
}

inline fun <T, R> ApiResult<T>.map(transform: (T) -> R): ApiResult<R> = when (this) {
    is ApiResult.Success -> ApiResult.Success(transform(data))
    is ApiResult.Failure -> this
}

val ApiResult<*>.errorOrNull: ApiError? get() = (this as? ApiResult.Failure)?.error

object ApiErrors {

    fun fromHttp(code: Int, rawBody: String?): ApiError {
        val serverMessage = rawBody?.let { extractMessage(it) }
        val kind = when (code) {
            401 -> ErrorKind.UNAUTHORIZED
            403 -> ErrorKind.FORBIDDEN
            404 -> ErrorKind.NOT_FOUND
            419 -> ErrorKind.UNAUTHORIZED
            422 -> ErrorKind.VALIDATION
            429 -> ErrorKind.RATE_LIMITED
            in 500..599 -> ErrorKind.SERVER
            else -> ErrorKind.UNKNOWN
        }
        val fallback = when (kind) {
            ErrorKind.UNAUTHORIZED -> "Sesi Anda telah berakhir. Silakan masuk kembali."
            ErrorKind.FORBIDDEN -> "Anda tidak memiliki otoritas untuk tindakan ini."
            ErrorKind.NOT_FOUND -> "Data atau layanan tidak ditemukan di server."
            ErrorKind.VALIDATION -> "Data yang dikirim belum valid."
            ErrorKind.RATE_LIMITED -> "Terlalu banyak percobaan. Tunggu sebentar lalu coba lagi."
            ErrorKind.SERVER -> "Server sedang mengalami kendala ($code). Coba beberapa saat lagi."
            else -> "Permintaan gagal diproses ($code)."
        }
        return ApiError(kind, serverMessage?.takeIf { it.isNotBlank() } ?: fallback, code)
    }

    fun fromThrowable(t: Throwable): ApiError = when (t) {
        is SocketTimeoutException -> ApiError(ErrorKind.TIMEOUT, "Koneksi ke server terlalu lama. Periksa jaringan Anda lalu coba lagi.")
        is UnknownHostException -> ApiError(ErrorKind.NETWORK, "Tidak ada koneksi internet atau alamat server tidak dikenal.")
        is SSLException -> ApiError(ErrorKind.NETWORK, "Koneksi aman (HTTPS) ke server gagal diverifikasi.")
        is IOException -> ApiError(ErrorKind.NETWORK, "Gagal terhubung ke server. Periksa koneksi Anda.")
        is com.google.gson.JsonParseException, is IllegalStateException ->
            ApiError(ErrorKind.SERVER, "Format respons server tidak sesuai. Pastikan server SISINDEN sudah diperbarui.")
        else -> ApiError(ErrorKind.UNKNOWN, "Terjadi kesalahan tak terduga.")
    }

    private fun extractMessage(body: String): String? = try {
        val json = JsonParser.parseString(body)
        if (json.isJsonObject) {
            val obj = json.asJsonObject
            when {
                obj.has("message") && obj.get("message").isJsonPrimitive -> obj.get("message").asString
                obj.has("error") && obj.get("error").isJsonPrimitive -> obj.get("error").asString
                else -> null
            }
        } else null
    } catch (_: Exception) {
        null
    }
}

/**
 * Jalankan panggilan Retrofit dengan penanganan error yang seragam.
 * Pembatalan coroutine (CancellationException) tidak ditelan.
 */
suspend fun <T> safeApiCall(call: suspend () -> Response<T>): ApiResult<T> {
    return try {
        val response = call()
        if (response.isSuccessful) {
            val body = response.body()
            if (body != null) ApiResult.Success(body)
            else ApiResult.Failure(ApiError(ErrorKind.SERVER, "Respons server kosong.", response.code()))
        } else {
            val raw = try { response.errorBody()?.string() } catch (_: Exception) { null }
            ApiResult.Failure(ApiErrors.fromHttp(response.code(), raw))
        }
    } catch (c: CancellationException) {
        throw c
    } catch (t: Throwable) {
        ApiResult.Failure(ApiErrors.fromThrowable(t))
    }
}
