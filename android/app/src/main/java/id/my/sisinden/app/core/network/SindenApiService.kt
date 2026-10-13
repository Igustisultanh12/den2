package id.my.sisinden.app.core.network

import id.my.sisinden.app.data.model.*
import retrofit2.Response
import retrofit2.http.*

interface SindenApiService {

    // 1. Konfigurasi Sistem
    @GET("/api/mobile/config")
    suspend fun getConfig(): Response<ConfigResponse>

    // 2. Autentikasi & Aktivasi
    @POST("/api/mobile/login")
    suspend fun login(@Body request: LoginRequest): Response<LoginResponse>

    @POST("/api/mobile/activate")
    suspend fun activateAccount(@Body request: ActivateRequest): Response<SimpleResponse>

    // 3. Agenda & Log Surat
    @GET("/api/mobile/letter-logs")
    suspend fun getLetterLogs(
        @Query("type") type: String? = null,
        @Query("search") search: String? = null
    ): Response<LetterLogsResponse>

    @POST("/api/mobile/letter-logs")
    suspend fun createLetterLog(@Body request: CreateLetterLogRequest): Response<SimpleResponse>

    // 4. SKHPP (Surat Keterangan Hasil Penelitian Personel)
    @GET("/api/mobile/skhpp")
    suspend fun getSkhpp(
        @Query("status") status: String? = null,
        @Query("search") search: String? = null
    ): Response<SkhppResponse>

    @POST("/api/mobile/skhpp")
    suspend fun createSkhpp(@Body request: CreateSkhppRequest): Response<SimpleResponse>

    @POST("/api/mobile/skhpp/{id}/verify")
    suspend fun verifySkhpp(@Path("id") id: Int): Response<SimpleResponse>

    // 5. Kas Denintel
    @GET("/api/mobile/cash")
    suspend fun getCashSummary(): Response<CashResponse>

    @POST("/api/mobile/cash")
    suspend fun createCashTransaction(@Body request: CreateCashRequest): Response<SimpleResponse>

    @PUT("/api/mobile/cash/{id}")
    suspend fun updateCashTransaction(@Path("id") id: Int, @Body request: CreateCashRequest): Response<SimpleResponse>

    @DELETE("/api/mobile/cash/{id}")
    suspend fun deleteCashTransaction(@Path("id") id: Int): Response<SimpleResponse>

    // 6. Kas Taktis Operasi
    @GET("/api/mobile/technical-cash")
    suspend fun getTechnicalCash(): Response<TechnicalCashResponse>

    @POST("/api/mobile/technical-cash")
    suspend fun createTechnicalCash(@Body request: CreateTechnicalCashRequest): Response<SimpleResponse>

    @PUT("/api/mobile/technical-cash/{id}")
    suspend fun updateTechnicalCash(@Path("id") id: Int, @Body request: CreateTechnicalCashRequest): Response<SimpleResponse>

    @DELETE("/api/mobile/technical-cash/{id}")
    suspend fun deleteTechnicalCash(@Path("id") id: Int): Response<SimpleResponse>

    // 7. Mitra Intelijen
    @GET("/api/mobile/mitra")
    suspend fun getMitra(@Query("search") search: String? = null): Response<MitraResponse>

    @POST("/api/mobile/mitra")
    suspend fun createMitra(@Body request: CreateMitraRequest): Response<SimpleResponse>

    @PUT("/api/mobile/mitra/{id}")
    suspend fun updateMitra(@Path("id") id: Int, @Body request: CreateMitraRequest): Response<SimpleResponse>

    @DELETE("/api/mobile/mitra/{id}")
    suspend fun deleteMitra(@Path("id") id: Int): Response<SimpleResponse>

    @POST("/api/mobile/mitra/{id}/toggle-payment")
    suspend fun toggleMitraPayment(
        @Path("id") id: Int,
        @Body body: Map<String, String>
    ): Response<SimpleResponse>

    // 8. Tanda Tangan Elektronik Komandan
    @GET("/api/mobile/signature-requests")
    suspend fun getSignatureRequests(): Response<SignatureResponse>

    @POST("/api/mobile/signature-requests/{id}/sign")
    suspend fun signDocument(@Path("id") id: Int): Response<SimpleResponse>

    // 9. Pelanggaran Personel
    @GET("/api/mobile/violations")
    suspend fun getViolations(@Query("search") search: String? = null): Response<ViolationResponse>

    @POST("/api/mobile/violations")
    suspend fun createViolation(@Body request: CreateViolationRequest): Response<SimpleResponse>

    // 10. Direktori Personel
    @GET("/api/mobile/users")
    suspend fun getPersonnel(): Response<PersonnelResponse>

    // 11. Notifikasi Resmi
    @GET("/api/mobile/notifications")
    suspend fun getNotifications(): Response<NotificationResponse>
}
