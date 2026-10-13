package id.my.sisinden.app.data.repository

import id.my.sisinden.app.core.data.DataStoreManager
import id.my.sisinden.app.core.network.ApiClient
import id.my.sisinden.app.data.model.*
import kotlinx.coroutines.flow.Flow
import org.json.JSONObject

sealed class NetworkResult<out T> {
    data class Success<out T>(val data: T) : NetworkResult<T>()
    data class Error(val message: String, val code: Int? = null) : NetworkResult<Nothing>()
}

class SindenRepository(
    private val dataStoreManager: DataStoreManager,
    private val apiClient: ApiClient
) {

    val userFlow: Flow<UserDto?> = dataStoreManager.userFlow
    val tokenFlow: Flow<String?> = dataStoreManager.tokenFlow
    val baseUrlFlow: Flow<String> = dataStoreManager.baseUrlFlow
    val isDarkModeFlow: Flow<Boolean?> = dataStoreManager.isDarkModeFlow

    suspend fun setBaseUrl(url: String) = dataStoreManager.setBaseUrl(url)
    suspend fun setDarkMode(mode: Boolean?) = dataStoreManager.setDarkMode(mode)

    private fun <T> handleResponse(response: retrofit2.Response<T>): NetworkResult<T> {
        return if (response.isSuccessful) {
            val body = response.body()
            if (body != null) {
                NetworkResult.Success(body)
            } else {
                NetworkResult.Error("Respons kosong dari server.")
            }
        } else {
            val errorBody = response.errorBody()?.string()
            var errorMessage = "Gagal memproses data (${response.code()})"
            if (!errorBody.isNullOrEmpty()) {
                try {
                    val json = JSONObject(errorBody)
                    if (json.has("message")) {
                        errorMessage = json.getString("message")
                    }
                } catch (_: Exception) {}
            }
            NetworkResult.Error(errorMessage, response.code())
        }
    }

    suspend fun login(username: String, password: String): NetworkResult<LoginResponse> {
        return try {
            val response = apiClient.getService().login(LoginRequest(username, password))
            val result = handleResponse(response)
            if (result is NetworkResult.Success) {
                val data = result.data
                if (data.status == "success" && data.token != null && data.user != null) {
                    dataStoreManager.saveAuth(data.token, data.user)
                }
            }
            result
        } catch (e: Exception) {
            NetworkResult.Error(e.localizedMessage ?: "Gagal terhubung ke server.")
        }
    }

    suspend fun activateAccount(request: ActivateRequest): NetworkResult<SimpleResponse> {
        return try {
            val response = apiClient.getService().activateAccount(request)
            handleResponse(response)
        } catch (e: Exception) {
            NetworkResult.Error(e.localizedMessage ?: "Gagal memproses aktivasi akun.")
        }
    }

    suspend fun logout() {
        dataStoreManager.clearAuth()
    }

    suspend fun getConfig(): NetworkResult<ConfigResponse> {
        return try {
            val response = apiClient.getService().getConfig()
            handleResponse(response)
        } catch (e: Exception) {
            NetworkResult.Error(e.localizedMessage ?: "Gagal mengambil konfigurasi sistem.")
        }
    }

    suspend fun getLetterLogs(type: String? = null, search: String? = null): NetworkResult<LetterLogsResponse> {
        return try {
            val response = apiClient.getService().getLetterLogs(type, search)
            handleResponse(response)
        } catch (e: Exception) {
            NetworkResult.Error(e.localizedMessage ?: "Gagal memuat agenda surat.")
        }
    }

    suspend fun createLetterLog(request: CreateLetterLogRequest): NetworkResult<SimpleResponse> {
        return try {
            val response = apiClient.getService().createLetterLog(request)
            handleResponse(response)
        } catch (e: Exception) {
            NetworkResult.Error(e.localizedMessage ?: "Gagal menyimpan agenda surat.")
        }
    }

    suspend fun getSkhpp(status: String? = null, search: String? = null): NetworkResult<SkhppResponse> {
        return try {
            val response = apiClient.getService().getSkhpp(status, search)
            handleResponse(response)
        } catch (e: Exception) {
            NetworkResult.Error(e.localizedMessage ?: "Gagal memuat daftar SKHPP.")
        }
    }

    suspend fun createSkhpp(request: CreateSkhppRequest): NetworkResult<SimpleResponse> {
        return try {
            val response = apiClient.getService().createSkhpp(request)
            handleResponse(response)
        } catch (e: Exception) {
            NetworkResult.Error(e.localizedMessage ?: "Gagal mengajukan SKHPP.")
        }
    }

    suspend fun verifySkhpp(id: Int): NetworkResult<SimpleResponse> {
        return try {
            val response = apiClient.getService().verifySkhpp(id)
            handleResponse(response)
        } catch (e: Exception) {
            NetworkResult.Error(e.localizedMessage ?: "Gagal memverifikasi SKHPP.")
        }
    }

    suspend fun getCashSummary(): NetworkResult<CashResponse> {
        return try {
            val response = apiClient.getService().getCashSummary()
            handleResponse(response)
        } catch (e: Exception) {
            NetworkResult.Error(e.localizedMessage ?: "Gagal memuat data kas Denintel.")
        }
    }

    suspend fun createCashTransaction(request: CreateCashRequest): NetworkResult<SimpleResponse> {
        return try {
            val response = apiClient.getService().createCashTransaction(request)
            handleResponse(response)
        } catch (e: Exception) {
            NetworkResult.Error(e.localizedMessage ?: "Gagal mencatat transaksi kas.")
        }
    }

    suspend fun updateCashTransaction(id: Int, request: CreateCashRequest): NetworkResult<SimpleResponse> {
        return try {
            val response = apiClient.getService().updateCashTransaction(id, request)
            handleResponse(response)
        } catch (e: Exception) {
            NetworkResult.Error(e.localizedMessage ?: "Gagal memperbarui transaksi kas.")
        }
    }

    suspend fun deleteCashTransaction(id: Int): NetworkResult<SimpleResponse> {
        return try {
            val response = apiClient.getService().deleteCashTransaction(id)
            handleResponse(response)
        } catch (e: Exception) {
            NetworkResult.Error(e.localizedMessage ?: "Gagal menghapus transaksi kas.")
        }
    }

    suspend fun getTechnicalCash(): NetworkResult<TechnicalCashResponse> {
        return try {
            val response = apiClient.getService().getTechnicalCash()
            handleResponse(response)
        } catch (e: Exception) {
            NetworkResult.Error(e.localizedMessage ?: "Gagal memuat kas taktis operasi.")
        }
    }

    suspend fun createTechnicalCash(request: CreateTechnicalCashRequest): NetworkResult<SimpleResponse> {
        return try {
            val response = apiClient.getService().createTechnicalCash(request)
            handleResponse(response)
        } catch (e: Exception) {
            NetworkResult.Error(e.localizedMessage ?: "Gagal mencatat kas taktis operasi.")
        }
    }

    suspend fun updateTechnicalCash(id: Int, request: CreateTechnicalCashRequest): NetworkResult<SimpleResponse> {
        return try {
            val response = apiClient.getService().updateTechnicalCash(id, request)
            handleResponse(response)
        } catch (e: Exception) {
            NetworkResult.Error(e.localizedMessage ?: "Gagal memperbarui kas taktis operasi.")
        }
    }

    suspend fun deleteTechnicalCash(id: Int): NetworkResult<SimpleResponse> {
        return try {
            val response = apiClient.getService().deleteTechnicalCash(id)
            handleResponse(response)
        } catch (e: Exception) {
            NetworkResult.Error(e.localizedMessage ?: "Gagal menghapus kas taktis operasi.")
        }
    }

    suspend fun getMitra(search: String? = null): NetworkResult<MitraResponse> {
        return try {
            val response = apiClient.getService().getMitra(search)
            handleResponse(response)
        } catch (e: Exception) {
            NetworkResult.Error(e.localizedMessage ?: "Gagal memuat mitra intelijen.")
        }
    }

    suspend fun createMitra(request: CreateMitraRequest): NetworkResult<SimpleResponse> {
        return try {
            val response = apiClient.getService().createMitra(request)
            handleResponse(response)
        } catch (e: Exception) {
            NetworkResult.Error(e.localizedMessage ?: "Gagal mendaftarkan mitra intelijen.")
        }
    }

    suspend fun updateMitra(id: Int, request: CreateMitraRequest): NetworkResult<SimpleResponse> {
        return try {
            val response = apiClient.getService().updateMitra(id, request)
            handleResponse(response)
        } catch (e: Exception) {
            NetworkResult.Error(e.localizedMessage ?: "Gagal memperbarui data mitra.")
        }
    }

    suspend fun deleteMitra(id: Int): NetworkResult<SimpleResponse> {
        return try {
            val response = apiClient.getService().deleteMitra(id)
            handleResponse(response)
        } catch (e: Exception) {
            NetworkResult.Error(e.localizedMessage ?: "Gagal menghapus mitra.")
        }
    }

    suspend fun toggleMitraPayment(id: Int, status: String, year: Int? = null, month: Int? = null): NetworkResult<SimpleResponse> {
        return try {
            val map = mutableMapOf("status_payment" to status)
            if (year != null) map["tahun"] = year.toString()
            if (month != null) map["bulan"] = month.toString()
            val response = apiClient.getService().toggleMitraPayment(id, map)
            handleResponse(response)
        } catch (e: Exception) {
            NetworkResult.Error(e.localizedMessage ?: "Gagal memperbarui status pembayaran mitra.")
        }
    }

    suspend fun getSignatureRequests(): NetworkResult<SignatureResponse> {
        return try {
            val response = apiClient.getService().getSignatureRequests()
            handleResponse(response)
        } catch (e: Exception) {
            NetworkResult.Error(e.localizedMessage ?: "Gagal memuat permohonan tanda tangan.")
        }
    }

    suspend fun signDocument(id: Int): NetworkResult<SimpleResponse> {
        return try {
            val response = apiClient.getService().signDocument(id)
            handleResponse(response)
        } catch (e: Exception) {
            NetworkResult.Error(e.localizedMessage ?: "Gagal menandatangani dokumen.")
        }
    }

    suspend fun getViolations(search: String? = null): NetworkResult<ViolationResponse> {
        return try {
            val response = apiClient.getService().getViolations(search)
            handleResponse(response)
        } catch (e: Exception) {
            NetworkResult.Error(e.localizedMessage ?: "Gagal memuat data pelanggaran.")
        }
    }

    suspend fun createViolation(request: CreateViolationRequest): NetworkResult<SimpleResponse> {
        return try {
            val response = apiClient.getService().createViolation(request)
            handleResponse(response)
        } catch (e: Exception) {
            NetworkResult.Error(e.localizedMessage ?: "Gagal mencatat pelanggaran.")
        }
    }

    suspend fun getPersonnel(): NetworkResult<PersonnelResponse> {
        return try {
            val response = apiClient.getService().getPersonnel()
            handleResponse(response)
        } catch (e: Exception) {
            NetworkResult.Error(e.localizedMessage ?: "Gagal memuat direktori personel.")
        }
    }

    suspend fun getNotifications(): NetworkResult<NotificationResponse> {
        return try {
            val response = apiClient.getService().getNotifications()
            handleResponse(response)
        } catch (e: Exception) {
            NetworkResult.Error(e.localizedMessage ?: "Gagal memuat notifikasi kedinasan.")
        }
    }

    // 12. Presensi Kehadiran Personel (Absensi)
    suspend fun getAttendances(): NetworkResult<AttendanceResponse> {
        return try {
            val user = dataStoreManager.getUser()
            val response = apiClient.getService().getAttendances(user?.id)
            handleResponse(response)
        } catch (e: Exception) {
            NetworkResult.Error(e.localizedMessage ?: "Gagal memuat data presensi kehadiran.")
        }
    }

    suspend fun recordAttendance(request: CreateAttendanceRequest): NetworkResult<AttendanceActionResponse> {
        return try {
            val user = dataStoreManager.getUser()
            val requestWithUser = if (request.userId == null && user != null) {
                request.copy(userId = user.id)
            } else {
                request
            }
            val response = apiClient.getService().recordAttendance(requestWithUser)
            handleResponse(response)
        } catch (e: Exception) {
            NetworkResult.Error(e.localizedMessage ?: "Gagal mencatat presensi kehadiran.")
        }
    }
}
