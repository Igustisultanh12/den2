package id.my.sisinden.app.core.network

import id.my.sisinden.app.data.model.*
import okhttp3.MultipartBody
import okhttp3.RequestBody
import okhttp3.ResponseBody
import retrofit2.Response
import retrofit2.http.*

/**
 * Kontrak REST /api/mobile/… (lihat routes/mobile_api.php di repo yang sama).
 * Path relatif (tanpa "/" di depan) agar base URL dengan sub-path tetap valid.
 */
interface SindenApiService {

    // ------------------------------------------------------ Publik
    @GET("api/mobile/config")
    suspend fun getConfig(): Response<ConfigResponse>

    @POST("api/mobile/login")
    suspend fun login(@Body request: LoginRequest): Response<LoginResponse>

    @POST("api/mobile/activate")
    suspend fun activate(@Body request: ActivateRequest): Response<SimpleResponse>

    // ------------------------------------------------------ Sesi
    @GET("api/mobile/me")
    suspend fun me(): Response<MeResponse>

    @POST("api/mobile/logout")
    suspend fun logout(): Response<SimpleResponse>

    @GET("api/mobile/dashboard")
    suspend fun dashboard(): Response<DashboardResponse>

    // ------------------------------------------------------ Daftar Jaga
    @GET("api/mobile/duty-roster")
    suspend fun dutyRoster(
        @Query("sp_id") spId: Int? = null,
        @Query("bulan") bulan: Int? = null,
        @Query("tahun") tahun: Int? = null,
    ): Response<DutyRosterResponse>

    // ------------------------------------------------------ Presensi
    @GET("api/mobile/attendances")
    suspend fun attendances(): Response<AttendanceResponse>

    @POST("api/mobile/attendances")
    suspend fun recordAttendance(@Body request: CreateAttendanceRequest): Response<AttendanceActionResponse>

    // ------------------------------------------------------ Pelacakan Lokasi
    @POST("api/mobile/location")
    suspend fun reportLocation(@Body request: LocationBatchRequest): Response<LocationReportResponse>

    @GET("api/mobile/tracking")
    suspend fun tracking(): Response<TrackingResponse>

    // ------------------------------------------------------ Notifikasi
    @GET("api/mobile/notifications")
    suspend fun notifications(): Response<NotificationResponse>

    @POST("api/mobile/notifications/{id}/read")
    suspend fun markNotificationRead(@Path("id") id: Int): Response<SimpleResponse>

    @POST("api/mobile/notifications/read-all")
    suspend fun markAllNotificationsRead(): Response<SimpleResponse>

    // ------------------------------------------------------ Kas Pangkalan
    @GET("api/mobile/cash")
    suspend fun cash(): Response<CashLedgerResponse>

    @POST("api/mobile/cash")
    suspend fun createCash(@Body request: CashEntryRequest): Response<SimpleResponse>

    @PUT("api/mobile/cash/{id}")
    suspend fun updateCash(@Path("id") id: Int, @Body request: CashEntryRequest): Response<SimpleResponse>

    @DELETE("api/mobile/cash/{id}")
    suspend fun deleteCash(@Path("id") id: Int): Response<SimpleResponse>

    // ------------------------------------------------------ Kas Unit Teknis
    @GET("api/mobile/technical-cash")
    suspend fun technicalCash(): Response<CashLedgerResponse>

    @POST("api/mobile/technical-cash")
    suspend fun createTechnicalCash(@Body request: CashEntryRequest): Response<SimpleResponse>

    @PUT("api/mobile/technical-cash/{id}")
    suspend fun updateTechnicalCash(@Path("id") id: Int, @Body request: CashEntryRequest): Response<SimpleResponse>

    @DELETE("api/mobile/technical-cash/{id}")
    suspend fun deleteTechnicalCash(@Path("id") id: Int): Response<SimpleResponse>

    // ------------------------------------------------------ Agenda Surat
    @GET("api/mobile/letter-logs")
    suspend fun letterLogs(
        @Query("search") search: String? = null,
        @Query("category_id") categoryId: Int? = null,
    ): Response<LetterLogsResponse>

    @POST("api/mobile/letter-logs")
    suspend fun createLetterLog(@Body request: CreateLetterLogRequest): Response<SimpleResponse>

    @PUT("api/mobile/letter-logs/{id}")
    suspend fun updateLetterLog(@Path("id") id: Int, @Body request: UpdateLetterLogRequest): Response<SimpleResponse>

    @DELETE("api/mobile/letter-logs/{id}")
    suspend fun deleteLetterLog(@Path("id") id: Int): Response<SimpleResponse>

    @GET("api/mobile/categories")
    suspend fun categories(): Response<CategoriesResponse>

    // ------------------------------------------------------ SKHPP
    @GET("api/mobile/skhpp")
    suspend fun skhpp(
        @Query("status") status: String? = null,
        @Query("search") search: String? = null,
    ): Response<SkhppResponse>

    @Multipart
    @POST("api/mobile/skhpp")
    suspend fun createSkhpp(
        @PartMap fields: Map<String, @JvmSuppressWildcards RequestBody>,
        @Part photos: List<MultipartBody.Part>,
    ): Response<SimpleResponse>

    @FormUrlEncoded
    @POST("api/mobile/skhpp/{id}/verify")
    suspend fun approveSkhpp(@Path("id") id: Int, @Field("priority") priority: String): Response<SimpleResponse>

    @FormUrlEncoded
    @POST("api/mobile/skhpp/{id}/reject")
    suspend fun rejectSkhpp(@Path("id") id: Int, @Field("catatan_revisi") note: String): Response<SimpleResponse>

    @DELETE("api/mobile/skhpp/{id}")
    suspend fun deleteSkhpp(@Path("id") id: Int): Response<SimpleResponse>

    // ------------------------------------------------------ Tanda Tangan
    @GET("api/mobile/signature-requests")
    suspend fun signatureRequests(): Response<SignatureListResponse>

    @POST("api/mobile/signature-requests/{id}/sign")
    suspend fun approveSignature(@Path("id") id: Int, @Body body: SignatureDecisionRequest): Response<SimpleResponse>

    @POST("api/mobile/signature-requests/{id}/reject")
    suspend fun rejectSignature(@Path("id") id: Int, @Body body: SignatureDecisionRequest): Response<SimpleResponse>

    // ------------------------------------------------------ Rekening Komandan
    @GET("api/mobile/commander-account")
    suspend fun commanderAccount(@Query("month") month: String? = null): Response<CommanderAccountResponse>

    @POST("api/mobile/commander-account")
    suspend fun createCommanderTx(@Body request: CommanderTxRequest): Response<SimpleResponse>

    @DELETE("api/mobile/commander-account/{id}")
    suspend fun deleteCommanderTx(@Path("id") id: Int): Response<SimpleResponse>

    // ------------------------------------------------------ Mitra
    @GET("api/mobile/mitra")
    suspend fun mitra(@Query("tahun") tahun: Int): Response<MitraResponse>

    @POST("api/mobile/mitra")
    suspend fun createMitra(@Body request: MitraRequest): Response<SimpleResponse>

    @PUT("api/mobile/mitra/{id}")
    suspend fun updateMitra(@Path("id") id: Int, @Body request: MitraRequest): Response<SimpleResponse>

    @DELETE("api/mobile/mitra/{id}")
    suspend fun deleteMitra(@Path("id") id: Int): Response<SimpleResponse>

    @POST("api/mobile/mitra/{id}/toggle-payment")
    suspend fun toggleMitraPayment(@Path("id") id: Int, @Body request: TogglePaymentRequest): Response<SimpleResponse>

    // ------------------------------------------------------ Pelanggaran
    @GET("api/mobile/violations")
    suspend fun violations(
        @Query("search") search: String? = null,
        @Query("status") status: String? = null,
    ): Response<ViolationsResponse>

    @POST("api/mobile/violations")
    suspend fun createViolation(@Body request: CreateViolationRequest): Response<SimpleResponse>

    @POST("api/mobile/violations/{id}/progress")
    suspend fun addViolationProgress(@Path("id") id: Int, @Body request: ViolationProgressRequest): Response<SimpleResponse>

    @DELETE("api/mobile/violations/{id}")
    suspend fun deleteViolation(@Path("id") id: Int): Response<SimpleResponse>

    // ------------------------------------------------------ Pengguna (Admin)
    @GET("api/mobile/users")
    suspend fun users(): Response<UsersResponse>

    @POST("api/mobile/users")
    suspend fun createUser(@Body request: CreateUserRequest): Response<SimpleResponse>

    @PUT("api/mobile/users/{id}")
    suspend fun updateUserStatus(@Path("id") id: Int, @Body request: UpdateUserStatusRequest): Response<SimpleResponse>

    @DELETE("api/mobile/users/{id}")
    suspend fun deleteUser(@Path("id") id: Int): Response<SimpleResponse>

    // ------------------------------------------------------ PDF (stream)
    @Streaming
    @GET
    suspend fun downloadFile(@Url url: String): Response<ResponseBody>
}
