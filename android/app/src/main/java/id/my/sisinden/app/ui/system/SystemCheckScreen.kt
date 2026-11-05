package id.my.sisinden.app.ui.system

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Refresh
import androidx.compose.material.icons.outlined.Speed
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LocalLifecycleOwner
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.repeatOnLifecycle
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import id.my.sisinden.app.core.network.ApiResult
import id.my.sisinden.app.core.ui.components.InfoRow
import id.my.sisinden.app.core.ui.components.LoadStateContent
import id.my.sisinden.app.core.ui.components.RefreshableBox
import id.my.sisinden.app.core.ui.components.SectionHeader
import id.my.sisinden.app.core.ui.components.SindenCard
import id.my.sisinden.app.core.ui.components.SindenScaffold
import id.my.sisinden.app.core.ui.components.StatusChip
import id.my.sisinden.app.core.ui.components.Tone
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.data.model.PhysicalDiskDto
import id.my.sisinden.app.data.model.SpeedtestResultDto
import id.my.sisinden.app.data.model.SystemMetricsDto
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.ui.common.CollectMessages
import id.my.sisinden.app.ui.common.LoadableViewModel
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class SystemCheckViewModel(private val repository: SindenRepository) : LoadableViewModel<SystemMetricsDto>() {
    init { load() }

    override suspend fun fetch(): ApiResult<SystemMetricsDto> = repository.systemCheck()

    private val _speedtest = MutableStateFlow<SpeedtestResultDto?>(null)
    val speedtest: StateFlow<SpeedtestResultDto?> = _speedtest.asStateFlow()

    private val _testing = MutableStateFlow(false)
    val testing: StateFlow<Boolean> = _testing.asStateFlow()

    fun runSpeedtest() {
        if (_testing.value) return
        _testing.value = true
        viewModelScope.launch {
            when (val res = repository.systemSpeedtest()) {
                is ApiResult.Success -> _speedtest.value = res.data
                is ApiResult.Failure -> messages.post(res.error.message)
            }
            _testing.value = false
        }
    }
}

private fun statusTone(status: String?): Tone = when (status) {
    "healthy" -> Tone.SUCCESS
    "warning" -> Tone.WARNING
    "critical" -> Tone.DANGER
    else -> Tone.NEUTRAL
}

private fun statusLabel(status: String?): String = when (status) {
    "healthy" -> "Sehat"
    "warning" -> "Waspada"
    "critical" -> "Kritis"
    else -> "Belum ada data"
}

private fun percentTone(p: Double): Tone = when {
    p > 85 -> Tone.DANGER
    p > 70 -> Tone.WARNING
    else -> Tone.SUCCESS
}

private fun fmt(p: Double): String = if (p % 1.0 == 0.0) p.toInt().toString() else "%.1f".format(p)

/** Cek Sistem & Server: CPU, RAM, disk fisik (terpakai/free/kesehatan SMART), server & database. */
@Composable
fun SystemCheckScreen(repository: SindenRepository, onBack: () -> Unit) {
    val vm: SystemCheckViewModel = viewModel { SystemCheckViewModel(repository) }
    val state by vm.state.collectAsStateWithLifecycle()
    val refreshing by vm.refreshing.collectAsStateWithLifecycle()
    val speed by vm.speedtest.collectAsStateWithLifecycle()
    val testing by vm.testing.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }
    CollectMessages(vm.messages, snackbar)

    // Perbarui otomatis tiap 15 detik selama layar tampil.
    val lifecycle = LocalLifecycleOwner.current.lifecycle
    LaunchedEffect(lifecycle) {
        lifecycle.repeatOnLifecycle(Lifecycle.State.RESUMED) {
            while (true) {
                delay(15_000)
                vm.load(silent = true)
            }
        }
    }

    SindenScaffold(
        title = "Cek Sistem & Server",
        subtitle = "Kondisi server SISINDEN",
        onBack = onBack,
        snackbarHostState = snackbar,
        actions = {
            IconButton(onClick = { vm.load(pullToRefresh = true) }) { Icon(Icons.Outlined.Refresh, contentDescription = "Muat ulang") }
        },
    ) { padding ->
        Box(Modifier.padding(padding)) {
            RefreshableBox(refreshing, { vm.load(pullToRefresh = true) }) {
                LoadStateContent(state, onRetry = { vm.load() }) { m ->
                    LazyColumn(
                        contentPadding = PaddingValues(start = Spacing.screen, end = Spacing.screen, bottom = 32.dp),
                        verticalArrangement = Arrangement.spacedBy(Spacing.md),
                    ) {
                        item { ResourceCards(m) }

                        val disks = m.disk?.physicalDisks.orEmpty()
                        if (disks.isNotEmpty()) {
                            item {
                                SectionHeader(
                                    "Disk Fisik & Kesehatan",
                                    subtitle = m.disk?.smartReport?.let { r ->
                                        when {
                                            !r.available -> "Data SMART belum dipasang di server"
                                            r.stale -> "Data SMART usang — cek cron di server"
                                            else -> "Data SMART: ${r.generatedAt?.replace('T', ' ')?.take(16).orEmpty()}"
                                        }
                                    },
                                )
                            }
                            items(disks, key = { it.name ?: it.device.orEmpty() }) { DiskCard(it) }
                        }

                        val parts = m.disk?.partitions.orEmpty()
                        if (parts.isNotEmpty()) {
                            item { SectionHeader("Partisi") }
                            item {
                                SindenCard {
                                    parts.forEachIndexed { i, p ->
                                        if (i > 0) Spacer(Modifier.height(Spacing.sm))
                                        Row(verticalAlignment = Alignment.CenterVertically) {
                                            Column(Modifier.weight(1f)) {
                                                Text(p.mount.orEmpty(), style = MaterialTheme.typography.labelLarge, fontWeight = FontWeight.Bold)
                                                Text(
                                                    "${p.filesystem.orEmpty()} · ${p.usedHuman} / ${p.totalHuman} · sisa ${p.freeHuman}",
                                                    style = MaterialTheme.typography.bodySmall,
                                                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                                                )
                                            }
                                            StatusChip("${fmt(p.usagePercent)}%", percentTone(p.usagePercent))
                                        }
                                    }
                                }
                            }
                        }

                        item { SectionHeader("Server & Database") }
                        item {
                            SindenCard {
                                val s = m.server
                                InfoRow("Hostname", s?.hostname)
                                InfoRow("IP", s?.ipAddress)
                                InfoRow("OS", s?.os)
                                InfoRow("Kernel", s?.kernel)
                                InfoRow("Web server", s?.serverSoftware)
                                InfoRow("PHP", s?.phpVersion)
                                InfoRow("Uptime", s?.uptimeHuman)
                                InfoRow("Waktu server", s?.serverTime)
                                val d = m.database
                                InfoRow("Database", listOfNotNull(d?.driver, d?.version).joinToString(" · ").ifBlank { null })
                                InfoRow("Ukuran DB", d?.let { "${it.sizeHuman ?: "-"} · ${it.totalTables} tabel" })
                            }
                        }

                        item { SectionHeader("Kecepatan Internet Server") }
                        item {
                            SindenCard {
                                speed?.let { r ->
                                    InfoRow("Download", r.downloadMbps?.let { "${fmt(it)} Mbps" })
                                    InfoRow("Upload", r.uploadMbps?.let { "${fmt(it)} Mbps" } ?: "Tidak terukur")
                                    InfoRow("Ping", r.ping?.let { "${fmt(it)} ms" })
                                    InfoRow("ISP / Server", listOfNotNull(r.isp, r.server).joinToString(" · ").ifBlank { null })
                                    Spacer(Modifier.height(Spacing.sm))
                                }
                                OutlinedButton(onClick = vm::runSpeedtest, enabled = !testing, modifier = Modifier.fillMaxWidth()) {
                                    if (testing) {
                                        CircularProgressIndicator(Modifier.width(18.dp).height(18.dp), strokeWidth = 2.dp)
                                        Spacer(Modifier.width(Spacing.sm))
                                        Text("Menguji… (10–30 detik)")
                                    } else {
                                        Icon(Icons.Outlined.Speed, contentDescription = null)
                                        Spacer(Modifier.width(Spacing.sm))
                                        Text("Mulai Speedtest")
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun ResourceCards(m: SystemMetricsDto) {
    Column(verticalArrangement = Arrangement.spacedBy(Spacing.sm)) {
        Row(horizontalArrangement = Arrangement.spacedBy(Spacing.sm)) {
            MetricCard(
                "CPU", m.cpu?.usagePercent ?: 0.0,
                "${m.cpu?.cores ?: 0} core · load ${m.cpu?.load1 ?: 0.0}",
                Modifier.weight(1f),
            )
            MetricCard(
                "RAM", m.ram?.usagePercent ?: 0.0,
                "${m.ram?.usedHuman ?: "-"} / ${m.ram?.totalHuman ?: "-"}",
                Modifier.weight(1f),
            )
        }
        MetricCard(
            "Total Penyimpanan", m.disk?.usagePercent ?: 0.0,
            "Terpakai ${m.disk?.usedHuman ?: "-"} / ${m.disk?.totalHuman ?: "-"} · Free ${m.disk?.freeHuman ?: "-"} · App ${m.disk?.appStorageUsage ?: "-"}",
            Modifier.fillMaxWidth(),
        )
    }
}

@Composable
private fun MetricCard(title: String, percent: Double, detail: String, modifier: Modifier = Modifier) {
    SindenCard(modifier = modifier, contentPadding = Spacing.md) {
        Text(title, style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
        Text("${fmt(percent)}%", style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Black)
        Spacer(Modifier.height(Spacing.xs))
        UsageBar(percent)
        Spacer(Modifier.height(Spacing.xs))
        Text(detail, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
    }
}

@Composable
private fun UsageBar(percent: Double) {
    val color = when (percentTone(percent)) {
        Tone.DANGER -> Color(0xFFE11D48)
        Tone.WARNING -> Color(0xFFF59E0B)
        else -> Color(0xFF059669)
    }
    Box(
        Modifier.fillMaxWidth().height(8.dp).clip(RoundedCornerShape(50)).background(MaterialTheme.colorScheme.surfaceVariant),
    ) {
        Box(
            Modifier.fillMaxHeight().fillMaxWidth((percent / 100.0).coerceIn(0.0, 1.0).toFloat())
                .clip(RoundedCornerShape(50)).background(color),
        )
    }
}

@Composable
private fun DiskCard(d: PhysicalDiskDto) {
    val smart = d.smart
    SindenCard(contentPadding = Spacing.md) {
        Row(verticalAlignment = Alignment.Top) {
            Column(Modifier.weight(1f)) {
                Text(
                    "${d.device.orEmpty()} · ${d.kind.orEmpty()}",
                    style = MaterialTheme.typography.titleSmall,
                    fontWeight = FontWeight.Bold,
                )
                Text(
                    "${if (d.isSystem) "Disk Sistem" else "Disk Data"} · ${d.model.orEmpty()} · ${d.sizeHuman.orEmpty()}",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                d.mounts?.takeIf { it.isNotEmpty() }?.let {
                    Text(it.joinToString(" · "), style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
            }
            Column(horizontalAlignment = Alignment.End) {
                Text("Kesehatan", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                Text(
                    smart?.healthPercent?.let { "$it%" } ?: "—",
                    style = MaterialTheme.typography.headlineSmall,
                    fontWeight = FontWeight.Black,
                )
                StatusChip(statusLabel(smart?.status), statusTone(smart?.status))
            }
        }
        Spacer(Modifier.height(Spacing.sm))
        Text("Terpakai ${fmt(d.usagePercent)}%", style = MaterialTheme.typography.labelLarge)
        Spacer(Modifier.height(Spacing.xs))
        UsageBar(d.usagePercent)
        Spacer(Modifier.height(Spacing.xs))
        InfoRow("Terpakai", d.usedHuman)
        InfoRow("Free", d.freeHuman)
        if (d.mounts.isNullOrEmpty()) {
            Text("Disk belum di-mount.", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.error)
        } else d.unmountedHuman?.let { InfoRow("Tidak ter-mount", it) }

        if (smart?.available == true) {
            InfoRow("Status SMART", when (smart.smartPassed) { true -> "PASSED"; false -> "FAILED"; null -> "-" })
            InfoRow("Suhu", smart.temperature?.let { "$it°C" })
            InfoRow("Umur nyala", smart.powerOnHours?.let { h -> "${"%,d".format(h / 24)} hari (${"%,d".format(h)} jam)" })
            val issues = smart.issues.orEmpty()
            if (issues.isEmpty()) {
                Text("Tidak ada sektor rusak atau error yang tercatat.", style = MaterialTheme.typography.bodySmall, color = Color(0xFF059669))
            } else {
                issues.forEach { Text("• $it", style = MaterialTheme.typography.bodySmall, color = Color(0xFFB45309)) }
            }
        } else if (smart != null) {
            Text("SMART tidak terbaca: ${smart.message.orEmpty()}", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
    }
}
