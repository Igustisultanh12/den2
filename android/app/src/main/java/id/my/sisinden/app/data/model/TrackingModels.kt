package id.my.sisinden.app.data.model

import com.google.gson.annotations.SerializedName

/** Satu titik lokasi yang dikirim aplikasi ke POST /api/mobile/location. */
data class LocationPoint(
    @SerializedName("latitude") val latitude: Double,
    @SerializedName("longitude") val longitude: Double,
    @SerializedName("accuracy") val accuracy: Float? = null,
    @SerializedName("speed") val speed: Float? = null,
    @SerializedName("heading") val heading: Float? = null,
    @SerializedName("battery") val battery: Int? = null,
    @SerializedName("is_charging") val isCharging: Boolean? = null,
    @SerializedName("provider") val provider: String? = null,
    /** ISO-8601 waktu perangkat saat titik diambil. */
    @SerializedName("recorded_at") val recordedAt: String,
)

data class LocationBatchRequest(
    @SerializedName("points") val points: List<LocationPoint>,
    @SerializedName("device_model") val deviceModel: String? = null,
)

data class LocationReportResponse(
    @SerializedName("status") val status: String? = null,
    @SerializedName("accepted") val accepted: Int = 0,
    @SerializedName("recorded_at") val recordedAt: String? = null,
)

/** Posisi terakhir seorang personel (GET /api/mobile/tracking). */
data class TrackedPersonnelDto(
    @SerializedName("user_id") val userId: Int = 0,
    @SerializedName("name") val name: String? = null,
    @SerializedName("pangkat") val pangkat: String? = null,
    @SerializedName("nrp") val nrp: String? = null,
    @SerializedName("jabatan") val jabatan: String? = null,
    @SerializedName("role") val role: String? = null,
    @SerializedName("latitude") val latitude: Double = 0.0,
    @SerializedName("longitude") val longitude: Double = 0.0,
    @SerializedName("accuracy") val accuracy: Float? = null,
    @SerializedName("speed") val speed: Float? = null,
    @SerializedName("heading") val heading: Float? = null,
    @SerializedName("battery") val battery: Int? = null,
    @SerializedName("is_charging") val isCharging: Boolean? = null,
    @SerializedName("device") val device: String? = null,
    @SerializedName("recorded_at") val recordedAt: String? = null,
    @SerializedName("age_seconds") val ageSeconds: Long? = null,
    @SerializedName("is_online") val isOnline: Boolean = false,
) {
    val displayName: String get() = listOfNotNull(pangkat?.takeIf { it.isNotBlank() }, name).joinToString(" ")
}

/** Satu titik jejak (GET /api/mobile/tracking/{id}/history). */
data class TrackPointDto(
    @SerializedName("latitude") val latitude: Double = 0.0,
    @SerializedName("longitude") val longitude: Double = 0.0,
    @SerializedName("recorded_at") val recordedAt: String? = null,
)

data class TrackHistoryResponse(
    @SerializedName("status") val status: String? = null,
    @SerializedName("points") val points: List<TrackPointDto>? = null,
)

data class TrackingResponse(
    @SerializedName("status") val status: String? = null,
    @SerializedName("server_time") val serverTime: String? = null,
    @SerializedName("total_online") val totalOnline: Int = 0,
    @SerializedName("personnel") val personnel: List<TrackedPersonnelDto>? = null,
)
