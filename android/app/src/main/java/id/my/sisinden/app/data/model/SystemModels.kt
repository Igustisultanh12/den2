package id.my.sisinden.app.data.model

import com.google.gson.annotations.SerializedName

// ======================================================== Cek Sistem & Server
// Bentuk data mengikuti App\Services\SystemCheckService::getAllMetrics() (sama dengan web).

data class SystemMetricsDto(
    @SerializedName("cpu") val cpu: SystemCpuDto? = null,
    @SerializedName("ram") val ram: SystemRamDto? = null,
    @SerializedName("disk") val disk: SystemDiskDto? = null,
    @SerializedName("server") val server: SystemServerDto? = null,
    @SerializedName("database") val database: SystemDatabaseDto? = null,
    @SerializedName("speedtest_cli_available") val speedtestCliAvailable: Boolean = false,
    @SerializedName("timestamp") val timestamp: String? = null,
)

data class SystemCpuDto(
    @SerializedName("model") val model: String? = null,
    @SerializedName("cores") val cores: Int = 0,
    @SerializedName("usage_percent") val usagePercent: Double = 0.0,
    @SerializedName("load_avg_1m") val load1: Double = 0.0,
    @SerializedName("load_avg_5m") val load5: Double = 0.0,
    @SerializedName("load_avg_15m") val load15: Double = 0.0,
    @SerializedName("status") val status: String? = null,
)

data class SystemRamDto(
    @SerializedName("total_human") val totalHuman: String? = null,
    @SerializedName("used_human") val usedHuman: String? = null,
    @SerializedName("available_human") val availableHuman: String? = null,
    @SerializedName("usage_percent") val usagePercent: Double = 0.0,
    @SerializedName("swap_used_human") val swapUsedHuman: String? = null,
    @SerializedName("swap_total_human") val swapTotalHuman: String? = null,
    @SerializedName("status") val status: String? = null,
)

data class SystemDiskDto(
    @SerializedName("physical_disks") val physicalDisks: List<PhysicalDiskDto>? = null,
    @SerializedName("smart_report") val smartReport: SmartReportDto? = null,
    @SerializedName("partitions") val partitions: List<DiskPartitionDto>? = null,
    @SerializedName("total_human") val totalHuman: String? = null,
    @SerializedName("used_human") val usedHuman: String? = null,
    @SerializedName("free_human") val freeHuman: String? = null,
    @SerializedName("usage_percent") val usagePercent: Double = 0.0,
    @SerializedName("app_storage_usage") val appStorageUsage: String? = null,
    @SerializedName("overall_health") val overallHealth: String? = null,
)

data class SmartReportDto(
    @SerializedName("available") val available: Boolean = false,
    @SerializedName("generated_at") val generatedAt: String? = null,
    @SerializedName("stale") val stale: Boolean = false,
)

data class PhysicalDiskDto(
    @SerializedName("name") val name: String? = null,
    @SerializedName("device") val device: String? = null,
    @SerializedName("model") val model: String? = null,
    @SerializedName("kind") val kind: String? = null,
    @SerializedName("is_system") val isSystem: Boolean = false,
    @SerializedName("size_human") val sizeHuman: String? = null,
    @SerializedName("mounts") val mounts: List<String>? = null,
    @SerializedName("total_human") val totalHuman: String? = null,
    @SerializedName("used_human") val usedHuman: String? = null,
    @SerializedName("free_human") val freeHuman: String? = null,
    @SerializedName("usage_percent") val usagePercent: Double = 0.0,
    @SerializedName("unmounted_human") val unmountedHuman: String? = null,
    @SerializedName("space_status") val spaceStatus: String? = null,
    @SerializedName("smart") val smart: DiskSmartDto? = null,
)

data class DiskSmartDto(
    @SerializedName("available") val available: Boolean = false,
    @SerializedName("health_percent") val healthPercent: Int? = null,
    @SerializedName("status") val status: String? = null,
    @SerializedName("smart_passed") val smartPassed: Boolean? = null,
    @SerializedName("temperature") val temperature: Int? = null,
    @SerializedName("power_on_hours") val powerOnHours: Int? = null,
    @SerializedName("issues") val issues: List<String>? = null,
    @SerializedName("message") val message: String? = null,
)

data class DiskPartitionDto(
    @SerializedName("filesystem") val filesystem: String? = null,
    @SerializedName("mount") val mount: String? = null,
    @SerializedName("used_human") val usedHuman: String? = null,
    @SerializedName("total_human") val totalHuman: String? = null,
    @SerializedName("free_human") val freeHuman: String? = null,
    @SerializedName("usage_percent") val usagePercent: Double = 0.0,
    @SerializedName("health_status") val healthStatus: String? = null,
)

data class SystemServerDto(
    @SerializedName("hostname") val hostname: String? = null,
    @SerializedName("ip_address") val ipAddress: String? = null,
    @SerializedName("os") val os: String? = null,
    @SerializedName("kernel") val kernel: String? = null,
    @SerializedName("server_software") val serverSoftware: String? = null,
    @SerializedName("php_version") val phpVersion: String? = null,
    @SerializedName("uptime_human") val uptimeHuman: String? = null,
    @SerializedName("server_time") val serverTime: String? = null,
)

data class SystemDatabaseDto(
    @SerializedName("driver") val driver: String? = null,
    @SerializedName("version") val version: String? = null,
    @SerializedName("database_name") val databaseName: String? = null,
    @SerializedName("size_human") val sizeHuman: String? = null,
    @SerializedName("total_tables") val totalTables: Int = 0,
    @SerializedName("status") val status: String? = null,
)

data class SpeedtestResultDto(
    @SerializedName("status") val status: String? = null,
    @SerializedName("message") val message: String? = null,
    @SerializedName("download_mbps") val downloadMbps: Double? = null,
    @SerializedName("upload_mbps") val uploadMbps: Double? = null,
    @SerializedName("ping") val ping: Double? = null,
    @SerializedName("jitter") val jitter: Double? = null,
    @SerializedName("isp") val isp: String? = null,
    @SerializedName("server") val server: String? = null,
)
