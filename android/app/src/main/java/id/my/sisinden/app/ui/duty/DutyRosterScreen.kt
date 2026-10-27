package id.my.sisinden.app.ui.duty

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Draw
import androidx.compose.material.icons.outlined.EventAvailable
import androidx.compose.material.icons.outlined.EventBusy
import androidx.compose.material.icons.outlined.FileDownload
import androidx.compose.material.icons.outlined.PictureAsPdf
import androidx.compose.material3.Button
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import id.my.sisinden.app.core.security.Perm
import id.my.sisinden.app.core.security.PermissionSet
import id.my.sisinden.app.core.ui.components.ConfirmDialog
import id.my.sisinden.app.core.ui.components.ErrorState
import id.my.sisinden.app.core.ui.components.Tone
import id.my.sisinden.app.core.ui.components.toneAccent
import id.my.sisinden.app.core.ui.components.IconBadge
import id.my.sisinden.app.core.ui.components.LoadStateContent
import id.my.sisinden.app.core.ui.components.RefreshableBox
import id.my.sisinden.app.core.ui.components.SindenCard
import id.my.sisinden.app.core.ui.components.SindenScaffold
import id.my.sisinden.app.core.ui.components.SoftDivider
import id.my.sisinden.app.core.ui.theme.SindenTheme
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.core.util.Fmt
import id.my.sisinden.app.data.model.PendingSpJagaDto
import id.my.sisinden.app.data.model.SpJagaSummary
import id.my.sisinden.app.ui.common.CollectMessages
import id.my.sisinden.app.data.repository.SindenRepository
import java.time.LocalDate
import java.time.YearMonth

/**
 * Daftar Jaga pribadi dalam bentuk kartu per bulan (bulan ini & bulan lalu).
 * Personel hanya melihat tanggal jaganya sendiri; SP Jaga lengkap tetap dapat
 * dipratinjau dan diunduh sebagai PDF resmi dari server.
 */
@Composable
fun DutyRosterScreen(
    repository: SindenRepository,
    permissions: PermissionSet,
    onOpenPdf: (String, String, String) -> Unit,
    onDownloadPdf: (String, String, String) -> Unit,
) {
    val canSign = permissions.has(Perm.DUTY_SIGN)
    val vm: DutyRosterViewModel = viewModel { DutyRosterViewModel(repository, canSign) }
    val state by vm.state.collectAsStateWithLifecycle()
    val refreshing by vm.refreshing.collectAsStateWithLifecycle()
    val pending by vm.pending.collectAsStateWithLifecycle()
    val signing by vm.signing.collectAsStateWithLifecycle()
    val canPdf = permissions.has(Perm.DUTY_PDF)
    val snackbar = remember { SnackbarHostState() }
    CollectMessages(vm.messages, snackbar)
    var confirmSign by remember { mutableStateOf<PendingSpJagaDto?>(null) }

    SindenScaffold(title = "Daftar Jaga", subtitle = "Jadwal jaga Anda", snackbarHostState = snackbar) { padding ->
        RefreshableBox(
            isRefreshing = refreshing,
            onRefresh = { vm.load(pullToRefresh = true) },
            modifier = Modifier.padding(padding),
        ) {
            LoadStateContent(state = state, onRetry = { vm.load() }) { months ->
                val today = LocalDate.now()
                val next = months.flatMap { it.mine }.filter { !it.date.isBefore(today) }.minByOrNull { it.date }
                LazyColumn(
                    Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(start = Spacing.screen, end = Spacing.screen, top = Spacing.lg, bottom = Spacing.xxxl),
                    verticalArrangement = Arrangement.spacedBy(Spacing.lg),
                ) {
                    if (canSign && pending.isNotEmpty()) {
                        items(pending, key = { "pending-${it.id}" }) { sp ->
                            PendingSignCard(
                                sp = sp,
                                onPreview = {
                                    onOpenPdf("api/mobile/pdf/sp-jaga/${sp.id}", "Draf SP Jaga ${Fmt.monthYear(YearMonth.of(sp.tahun, sp.bulan.coerceIn(1, 12)))}", "Draf_SP_Jaga_${sp.id}.pdf")
                                },
                                onSign = { confirmSign = sp },
                            )
                        }
                    }
                    item(key = "next") { NextDutyCard(next) }
                    items(months, key = { it.month.toString() }) { month ->
                        MonthDutyCard(
                            month = month,
                            isCurrent = month == months.first(),
                            canPdf = canPdf,
                            onRetry = { vm.load() },
                            onOpenPdf = onOpenPdf,
                            onDownloadPdf = onDownloadPdf,
                        )
                    }
                    item(key = "note") {
                        Text(
                            "Demi kerahasiaan, aplikasi hanya menampilkan jadwal jaga Anda sendiri. Susunan lengkap seluruh personel tersedia di PDF SP Jaga.",
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                }
            }
        }
    }

    confirmSign?.let { sp ->
        ConfirmDialog(
            title = "Tandatangani SP Jaga?",
            message = "SP Jaga ${sp.nomorSprin.orEmpty()} akan disahkan dengan TTE Pasops dan langsung diterbitkan ke seluruh personel. Pastikan isi draf sudah diperiksa.",
            confirmLabel = "Tandatangani",
            loading = signing,
            onDismiss = { confirmSign = null },
            onConfirm = { vm.approve(sp.id) { confirmSign = null } },
        )
    }
}

/** Kartu antrean TTE SP Jaga untuk Pasops. */
@Composable
private fun PendingSignCard(sp: PendingSpJagaDto, onPreview: () -> Unit, onSign: () -> Unit) {
    val period = runCatching { Fmt.monthYear(YearMonth.of(sp.tahun, sp.bulan)) }.getOrDefault("Periode ${sp.bulan}/${sp.tahun}")
    SindenCard(Modifier.fillMaxWidth(), contentPadding = Spacing.lg) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            IconBadge(Icons.Outlined.Draw, toneAccent(Tone.WARNING))
            Spacer(Modifier.width(Spacing.md))
            Column(Modifier.weight(1f)) {
                Text("Menunggu TTE Anda", style = MaterialTheme.typography.labelLarge, color = toneAccent(Tone.WARNING))
                Text("SP Jaga $period", style = MaterialTheme.typography.titleMedium)
                Text(
                    listOfNotNull(sp.nomorSprin?.takeIf { it.isNotBlank() }, if (sp.totalPersonel > 0) "${sp.totalPersonel} personel" else null)
                        .joinToString(" · ").ifEmpty { "Draf diajukan untuk ditandatangani" },
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }
        Spacer(Modifier.height(Spacing.md))
        Row(horizontalArrangement = Arrangement.spacedBy(Spacing.sm)) {
            OutlinedButton(onClick = onPreview, modifier = Modifier.weight(1f)) {
                Icon(Icons.Outlined.PictureAsPdf, contentDescription = null)
                Spacer(Modifier.width(Spacing.xs))
                Text("Lihat PDF")
            }
            Button(onClick = onSign, modifier = Modifier.weight(1f)) {
                Icon(Icons.Outlined.Draw, contentDescription = null)
                Spacer(Modifier.width(Spacing.xs))
                Text("Tandatangani")
            }
        }
    }
}

/** Kartu sorotan "Jaga berikutnya" bergaya hero Livin (gradien biru). */
@Composable
private fun NextDutyCard(next: MyDuty?) {
    val gradient = SindenTheme.heroGradient
    Column(
        Modifier
            .fillMaxWidth()
            .clip(MaterialTheme.shapes.large)
            .background(Brush.linearGradient(listOf(gradient[0], gradient[1])))
            .padding(Spacing.xl),
    ) {
        if (next == null) {
            Text("Belum ada jadwal jaga mendatang", style = MaterialTheme.typography.titleMedium, color = Color.White)
            Spacer(Modifier.height(Spacing.xs))
            Text(
                "Jadwal akan muncul setelah SP Jaga periode berikutnya diterbitkan.",
                style = MaterialTheme.typography.bodyMedium,
                color = Color.White.copy(alpha = 0.9f),
            )
            return@Column
        }
        val timing = timingOf(next.date)
        Text(
            when (timing) {
                DutyTiming.TODAY -> "Anda jaga HARI INI"
                DutyTiming.TOMORROW -> "Anda jaga BESOK"
                else -> "Jaga berikutnya"
            },
            style = MaterialTheme.typography.labelLarge,
            color = Color.White.copy(alpha = 0.92f),
        )
        Spacer(Modifier.height(Spacing.xs))
        Text(Fmt.fullDate(next.date), style = MaterialTheme.typography.headlineSmall, color = Color.White)
        if (!next.role.isNullOrBlank()) {
            Text("Sebagai ${next.role}", style = MaterialTheme.typography.bodyLarge, color = Color.White.copy(alpha = 0.92f))
        }
        Spacer(Modifier.height(Spacing.md))
        Row(
            Modifier
                .clip(MaterialTheme.shapes.small)
                .background(Color.White.copy(alpha = 0.18f))
                .padding(horizontal = Spacing.md, vertical = Spacing.sm),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Text(
                if (timing == DutyTiming.TODAY) "Selamat bertugas" else "Notifikasi pengingat dikirim H-1 pukul 07.00",
                style = MaterialTheme.typography.bodySmall,
                fontWeight = FontWeight.SemiBold,
                color = Color.White,
            )
        }
    }
}

@Composable
private fun MonthDutyCard(
    month: MonthDuty,
    isCurrent: Boolean,
    canPdf: Boolean,
    onRetry: () -> Unit,
    onOpenPdf: (String, String, String) -> Unit,
    onDownloadPdf: (String, String, String) -> Unit,
) {
    val sp = month.roster?.sp
    val mine = month.mine
    SindenCard(Modifier.fillMaxWidth(), contentPadding = Spacing.lg) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            IconBadge(Icons.Outlined.EventAvailable, MaterialTheme.colorScheme.primary)
            Spacer(Modifier.width(Spacing.md))
            Column(Modifier.weight(1f)) {
                Text(Fmt.monthYear(month.month), style = MaterialTheme.typography.titleLarge)
                Text(
                    if (isCurrent) "Bulan ini" else "Bulan lalu",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            if (mine.isNotEmpty()) {
                Column(horizontalAlignment = Alignment.End) {
                    Text("${mine.size}×", style = MaterialTheme.typography.headlineSmall, color = MaterialTheme.colorScheme.primary)
                    Text("jaga", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
            }
        }

        if (sp != null) {
            Spacer(Modifier.height(Spacing.sm))
            SpInfoRow(sp)
        }
        Spacer(Modifier.height(Spacing.md))
        SoftDivider(Modifier.fillMaxWidth())
        Spacer(Modifier.height(Spacing.sm))

        when {
            month.error != null -> ErrorState(month.error, onRetry)
            sp == null -> EmptyLine(
                if (isCurrent) "SP Jaga bulan ini belum diterbitkan." else "Tidak ada SP Jaga untuk bulan ini.",
            )
            mine.isEmpty() -> EmptyLine("Anda tidak terjadwal jaga pada SP ini.")
            else -> Column(verticalArrangement = Arrangement.spacedBy(Spacing.xs)) {
                mine.forEach { MyDutyRow(it) }
            }
        }

        if (isCurrent && mine.any { !it.date.isBefore(LocalDate.now()) }) {
            Spacer(Modifier.height(Spacing.md))
            ReminderNote()
        }

        if (sp != null && canPdf) {
            Spacer(Modifier.height(Spacing.lg))
            PdfActions(sp, onOpenPdf, onDownloadPdf)
        }
    }
}

@Composable
private fun EmptyLine(text: String) {
    Row(Modifier.fillMaxWidth().padding(vertical = Spacing.sm), verticalAlignment = Alignment.CenterVertically) {
        Icon(Icons.Outlined.EventBusy, contentDescription = null, tint = MaterialTheme.colorScheme.onSurfaceVariant, modifier = Modifier.size(22.dp))
        Spacer(Modifier.width(Spacing.sm))
        Text(text, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
    }
}

/** Tombol PDF SP Jaga: pratinjau (zoom/halaman) dan unduh langsung ke perangkat. */
@Composable
private fun PdfActions(
    sp: SpJagaSummary,
    onOpenPdf: (String, String, String) -> Unit,
    onDownloadPdf: (String, String, String) -> Unit,
) {
    val path = "api/mobile/pdf/sp-jaga/${sp.id}"
    val title = "SP Jaga ${sp.periodeLabel ?: ""}".trim()
    val file = "SP_JAGA_${sp.tahun}_${sp.bulan.toString().padStart(2, '0')}.pdf"
    Row(horizontalArrangement = Arrangement.spacedBy(Spacing.sm)) {
        OutlinedButton(
            onClick = { onOpenPdf(path, title, file) },
            modifier = Modifier.weight(1f).heightIn(min = 52.dp),
            shape = MaterialTheme.shapes.medium,
        ) {
            Icon(Icons.Outlined.PictureAsPdf, contentDescription = null, modifier = Modifier.size(20.dp))
            Spacer(Modifier.width(Spacing.xs))
            Text("Lihat PDF", style = MaterialTheme.typography.labelLarge)
        }
        Button(
            onClick = { onDownloadPdf(path, title, file) },
            modifier = Modifier.weight(1f).heightIn(min = 52.dp),
            shape = MaterialTheme.shapes.medium,
        ) {
            Icon(Icons.Outlined.FileDownload, contentDescription = null, modifier = Modifier.size(20.dp))
            Spacer(Modifier.width(Spacing.xs))
            Text("Unduh PDF", style = MaterialTheme.typography.labelLarge)
        }
    }
}
