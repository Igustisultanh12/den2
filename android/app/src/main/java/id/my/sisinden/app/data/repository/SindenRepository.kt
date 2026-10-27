package id.my.sisinden.app.data.repository

import android.content.Context
import android.os.Build
import id.my.sisinden.app.core.data.LogoutReason
import id.my.sisinden.app.core.data.SessionManager
import id.my.sisinden.app.core.data.SessionStore
import id.my.sisinden.app.core.network.ApiClient
import id.my.sisinden.app.core.network.ApiError
import id.my.sisinden.app.core.network.ApiErrors
import id.my.sisinden.app.core.network.ApiResult
import id.my.sisinden.app.core.network.ErrorKind
import id.my.sisinden.app.core.network.SindenApiService
import id.my.sisinden.app.core.network.safeApiCall
import id.my.sisinden.app.data.model.*
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.launch
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.MultipartBody
import okhttp3.RequestBody
import okhttp3.RequestBody.Companion.toRequestBody
import java.io.File

/** Berkas foto terpilih untuk unggahan multipart. */
class UploadImage(val fileName: String, val mimeType: String, val bytes: ByteArray)

/**
 * Satu-satunya pintu akses data jaringan. ViewModel tidak memanggil Retrofit
 * langsung sehingga penanganan sesi & error seragam di seluruh aplikasi.
 */
class SindenRepository(
    private val appContext: Context,
    private val api: ApiClient,
    private val store: SessionStore,
    val session: SessionManager,
    private val appScope: CoroutineScope,
) {
    /** Jalankan pekerjaan yang harus selesai walau layar pemanggil ditutup (mis. logout). */
    fun appScopeLaunch(block: suspend () -> Unit) {
        appScope.launch { block() }
    }

    private suspend fun svc(): SindenApiService = api.service()

    private suspend fun <T> call(block: suspend SindenApiService.() -> retrofit2.Response<T>): ApiResult<T> =
        safeApiCall { svc().block() }

    // ------------------------------------------------------------- Sesi

    suspend fun login(username: String, password: String): ApiResult<UserProfile> {
        val device = "SISINDEN Android (${Build.MANUFACTURER} ${Build.MODEL})".take(100)
        return when (val res = call { login(LoginRequest(username.trim(), password, device)) }) {
            is ApiResult.Success -> {
                val token = res.data.token
                val user = res.data.user
                if (res.data.status == "success" && !token.isNullOrBlank() && user != null) {
                    session.startSession(token, user)
                    ApiResult.Success(user)
                } else {
                    ApiResult.Failure(ApiError(ErrorKind.SERVER, res.data.message ?: "Login gagal diproses server."))
                }
            }
            is ApiResult.Failure -> res
        }
    }

    suspend fun activate(nrp: String, token: String, password: String): ApiResult<SimpleResponse> =
        call { activate(ActivateRequest(nrp.trim(), token.trim(), password)) }

    /** Segarkan profil + permission dari server. */
    suspend fun refreshSession(): ApiResult<UserProfile> = when (val res = call { me() }) {
        is ApiResult.Success -> {
            val user = res.data.user
            if (user != null) {
                session.updateUser(user)
                ApiResult.Success(user)
            } else ApiResult.Failure(ApiError(ErrorKind.SERVER, "Profil tidak tersedia."))
        }
        is ApiResult.Failure -> res
    }

    suspend fun logout() {
        // Cabut token di server (best-effort), lalu hapus sesi lokal apa pun hasilnya.
        if (session.token != null) call { logout() }
        session.end(LogoutReason.USER)
    }

    suspend fun getConfig(): ApiResult<ConfigResponse> = call { getConfig() }

    // ------------------------------------------------------- Dashboard

    suspend fun dashboard(): ApiResult<DashboardResponse> {
        val res = call { dashboard() }
        if (res is ApiResult.Success) res.data.user?.let { session.updateUser(it) }
        return res
    }

    suspend fun dutyRoster(spId: Int? = null, bulan: Int? = null, tahun: Int? = null) =
        call { dutyRoster(spId, bulan, tahun) }

    // ------------------------------------------------------- Presensi

    suspend fun attendances() = call { attendances() }

    /** Lokasi GPS & foto senyum wajib (ditegakkan juga oleh server). */
    suspend fun recordAttendance(
        status: String,
        lat: Double,
        lng: Double,
        accuracy: Float?,
        notes: String?,
        photoDataUrl: String,
        smileScore: Int?,
    ) = call {
        recordAttendance(
            CreateAttendanceRequest(
                status = status,
                latitude = lat,
                longitude = lng,
                accuracy = accuracy,
                deviceModel = deviceModel,
                notes = notes?.takeIf { it.isNotBlank() },
                photo = photoDataUrl,
                smileScore = smileScore,
            )
        )
    }

    // ------------------------------------------------ Pelacakan Lokasi

    suspend fun reportLocation(points: List<LocationPoint>) = call {
        reportLocation(LocationBatchRequest(points = points, deviceModel = deviceModel))
    }

    suspend fun tracking() = call { tracking() }

    private val deviceModel: String
        get() = "${Build.MANUFACTURER} ${Build.MODEL} (Android ${Build.VERSION.RELEASE})".take(150)

    // ----------------------------------------------------- Notifikasi

    suspend fun notifications() = call { notifications() }
    suspend fun markNotificationRead(id: Int) = call { markNotificationRead(id) }
    suspend fun markAllNotificationsRead() = call { markAllNotificationsRead() }

    // ------------------------------------------------------------ Kas

    suspend fun cashLedger(kind: CashKind) = call { if (kind == CashKind.BASE) cash() else technicalCash() }

    suspend fun saveCash(kind: CashKind, id: Int?, request: CashEntryRequest) = call {
        when {
            kind == CashKind.BASE && id == null -> createCash(request)
            kind == CashKind.BASE -> updateCash(id!!, request)
            id == null -> createTechnicalCash(request)
            else -> updateTechnicalCash(id, request)
        }
    }

    suspend fun deleteCash(kind: CashKind, id: Int) = call {
        if (kind == CashKind.BASE) deleteCash(id) else deleteTechnicalCash(id)
    }

    // --------------------------------------------------- Agenda Surat

    suspend fun letterLogs(search: String?, categoryId: Int?) =
        call { letterLogs(search?.takeIf { it.isNotBlank() }, categoryId) }

    suspend fun categories() = call { categories() }
    suspend fun createLetterLog(request: CreateLetterLogRequest) = call { createLetterLog(request) }
    suspend fun updateLetterLog(id: Int, request: UpdateLetterLogRequest) = call { updateLetterLog(id, request) }
    suspend fun deleteLetterLog(id: Int) = call { deleteLetterLog(id) }

    // ----------------------------------------------------------- SKHPP

    suspend fun skhpp(status: String?, search: String?) =
        call { skhpp(status, search?.takeIf { it.isNotBlank() }) }

    suspend fun createSkhpp(
        fields: Map<String, String>,
        members: List<SkhppMemberInput>,
        photo1: UploadImage,
        photo2: UploadImage?,
    ): ApiResult<SimpleResponse> {
        val text = "text/plain".toMediaType()
        val parts = LinkedHashMap<String, RequestBody>()
        fields.forEach { (k, v) -> parts[k] = v.toRequestBody(text) }
        parts["has_pengikut"] = (if (members.isNotEmpty()) "1" else "0").toRequestBody(text)
        members.forEachIndexed { i, m ->
            parts["members[$i][nama]"] = m.nama.toRequestBody(text)
            parts["members[$i][pangkat_nrp_nik]"] = m.pangkatNrpNik.toRequestBody(text)
            parts["members[$i][jabatan]"] = m.jabatan.toRequestBody(text)
        }
        val files = buildList {
            add(MultipartBody.Part.createFormData("foto_1", photo1.fileName, photo1.bytes.toRequestBody(photo1.mimeType.toMediaType())))
            if (photo2 != null) {
                add(MultipartBody.Part.createFormData("foto_2", photo2.fileName, photo2.bytes.toRequestBody(photo2.mimeType.toMediaType())))
            }
        }
        return call { createSkhpp(parts, files) }
    }

    suspend fun approveSkhpp(id: Int, priority: String) = call { approveSkhpp(id, priority) }
    suspend fun rejectSkhpp(id: Int, note: String) = call { rejectSkhpp(id, note) }
    suspend fun deleteSkhpp(id: Int) = call { deleteSkhpp(id) }

    // ---------------------------------------------------- Tanda Tangan

    suspend fun signatureRequests() = call { signatureRequests() }
    suspend fun approveSignature(id: Int) = call { approveSignature(id, SignatureDecisionRequest()) }
    suspend fun rejectSignature(id: Int, note: String) = call { rejectSignature(id, SignatureDecisionRequest(note)) }

    // ----------------------------------------------- Rekening Komandan

    suspend fun commanderAccount(month: String?) = call { commanderAccount(month) }
    suspend fun createCommanderTx(request: CommanderTxRequest) = call { createCommanderTx(request) }
    suspend fun deleteCommanderTx(id: Int) = call { deleteCommanderTx(id) }

    // ----------------------------------------------------------- Mitra

    suspend fun mitra(year: Int) = call { mitra(year) }
    suspend fun saveMitra(id: Int?, request: MitraRequest) = call {
        if (id == null) createMitra(request) else updateMitra(id, request)
    }
    suspend fun deleteMitra(id: Int) = call { deleteMitra(id) }
    suspend fun toggleMitraPayment(id: Int, year: Int, month: Int) = call { toggleMitraPayment(id, TogglePaymentRequest(year, month)) }

    // ----------------------------------------------------- Pelanggaran

    suspend fun violations(search: String?, status: String?) =
        call { violations(search?.takeIf { it.isNotBlank() }, status) }
    suspend fun createViolation(request: CreateViolationRequest) = call { createViolation(request) }
    suspend fun addViolationProgress(id: Int, note: String, status: String?) =
        call { addViolationProgress(id, ViolationProgressRequest(note, status)) }
    suspend fun deleteViolation(id: Int) = call { deleteViolation(id) }

    // -------------------------------------------------- Pengguna (Admin)

    suspend fun users() = call { users() }
    suspend fun createUser(request: CreateUserRequest) = call { createUser(request) }
    suspend fun setUserActive(id: Int, active: Boolean) = call { updateUserStatus(id, UpdateUserStatusRequest(active)) }
    suspend fun deleteUser(id: Int) = call { deleteUser(id) }

    // ------------------------------------------------------------- PDF

    /**
     * Unduh PDF resmi dari server ke folder cache privat dan validasi isinya
     * (status HTTP, Content-Type, tanda tangan berkas "%PDF", ukuran > 0).
     */
    suspend fun downloadPdf(apiPath: String, fileName: String): ApiResult<File> = withContext(Dispatchers.IO) {
        if (!apiPath.startsWith("api/mobile/pdf/") || apiPath.contains("..")) {
            return@withContext ApiResult.Failure(ApiError(ErrorKind.FORBIDDEN, "Alamat dokumen tidak diizinkan."))
        }
        val dir = File(appContext.cacheDir, "pdf").apply { mkdirs() }
        val target = File(dir, sanitizeFileName(fileName))
        val temp = File(dir, target.name + ".part")
        try {
            val response = svc().downloadFile(apiPath)
            if (!response.isSuccessful) {
                val raw = try { response.errorBody()?.string() } catch (_: Exception) { null }
                return@withContext ApiResult.Failure(ApiErrors.fromHttp(response.code(), raw))
            }
            val body = response.body()
                ?: return@withContext ApiResult.Failure(ApiError(ErrorKind.SERVER, "Server tidak mengirim berkas."))
            val type = body.contentType()?.toString().orEmpty()
            if (!type.contains("pdf", ignoreCase = true)) {
                body.close()
                return@withContext ApiResult.Failure(ApiError(ErrorKind.SERVER, "Server tidak mengembalikan dokumen PDF."))
            }
            body.byteStream().use { input -> temp.outputStream().use { output -> input.copyTo(output) } }

            val header = ByteArray(4)
            val read = temp.inputStream().use { it.read(header) }
            if (temp.length() == 0L || read < 4 || String(header, Charsets.US_ASCII) != "%PDF") {
                temp.delete()
                return@withContext ApiResult.Failure(ApiError(ErrorKind.SERVER, "Berkas PDF kosong atau rusak."))
            }
            if (target.exists()) target.delete()
            if (!temp.renameTo(target)) {
                temp.copyTo(target, overwrite = true)
                temp.delete()
            }
            ApiResult.Success(target)
        } catch (c: CancellationException) {
            temp.delete()
            throw c
        } catch (t: Throwable) {
            temp.delete()
            ApiResult.Failure(ApiErrors.fromThrowable(t))
        }
    }

    private fun sanitizeFileName(name: String): String {
        val cleaned = name.replace(Regex("[^A-Za-z0-9._-]+"), "_").trim('_').take(80)
        val base = cleaned.ifEmpty { "dokumen" }
        return if (base.endsWith(".pdf", ignoreCase = true)) base else "$base.pdf"
    }

    // ------------------------------------------------------- Preferensi

    val themeModeFlow = store.themeModeFlow
    val baseUrlFlow = store.baseUrlFlow
    val hideBalanceFlow = store.hideBalanceFlow
    suspend fun setThemeMode(mode: id.my.sisinden.app.core.ui.theme.ThemeMode) = store.setThemeMode(mode)
    suspend fun setHideBalance(hide: Boolean) = store.setHideBalance(hide)
    suspend fun setBaseUrl(url: String) = store.setBaseUrl(url)
}

enum class CashKind(val title: String, val subtitle: String) {
    BASE("Buku Kas Pangkalan", "Penerimaan & pengeluaran kas Denintel"),
    TECHNICAL("Kas Unit Teknis", "Dana operasional Dan Unit Teknis"),
}
