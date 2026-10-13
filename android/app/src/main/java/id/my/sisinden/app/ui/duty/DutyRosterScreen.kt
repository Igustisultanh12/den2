package id.my.sisinden.app.ui.duty

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowLeft
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowRight
import androidx.compose.material.icons.outlined.EventAvailable
import androidx.compose.material.icons.outlined.FolderOpen
import androidx.compose.material.icons.outlined.PictureAsPdf
import androidx.compose.material.icons.outlined.Search
import androidx.compose.material3.AssistChip
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.style.TextAlign
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import id.my.sisinden.app.core.security.Perm
import id.my.sisinden.app.core.security.PermissionSet
import id.my.sisinden.app.core.ui.components.EmptyState
import id.my.sisinden.app.core.ui.components.FilterChipRow
import id.my.sisinden.app.core.ui.components.FormSheet
import id.my.sisinden.app.core.ui.components.LoadState
import id.my.sisinden.app.core.ui.components.dataOrNull
import id.my.sisinden.app.core.ui.components.LoadStateContent
import id.my.sisinden.app.core.ui.components.RefreshableBox
import id.my.sisinden.app.core.ui.components.SearchField
import id.my.sisinden.app.core.ui.components.SindenCard
import id.my.sisinden.app.core.ui.components.SindenScaffold
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.core.util.Fmt
import id.my.sisinden.app.data.model.DutyRosterResponse
import id.my.sisinden.app.data.model.SpJagaSummary
import id.my.sisinden.app.data.repository.SindenRepository
import java.time.YearMonth

@Composable
fun DutyRosterScreen(
    repository: SindenRepository,
    permissions: PermissionSet,
    onOpenDay: (Int?, String) -> Unit,
    onOpenPdf: (String, String, String) -> Unit,
) {
    val vm: DutyRosterViewModel = viewModel { DutyRosterViewModel(repository) }
    val state by vm.state.collectAsStateWithLifecycle()
    val refreshing by vm.refreshing.collectAsStateWithLifecycle()
    val period by vm.period.collectAsStateWithLifecycle()
    val filter by vm.filter.collectAsStateWithLifecycle()
    val query by vm.query.collectAsStateWithLifecycle()
    var showPicker by rememberSaveable { mutableStateOf(false) }

    val data = state.dataOrNull
    val sp = data?.sp

    SindenScaffold(
        title = "Daftar Jaga",
        subtitle = sp?.nomorSprin ?: Fmt.monthYear(period),
        actions = {
            if (!data?.available.isNullOrEmpty()) {
                IconButton(onClick = { showPicker = true }) {
                    Icon(Icons.Outlined.FolderOpen, contentDescription = "Pilih SP Jaga lain")
                }
            }
            if (sp != null && permissions.has(Perm.DUTY_PDF)) {
                IconButton(onClick = {
                    onOpenPdf("api/mobile/pdf/sp-jaga/${sp.id}", "SP Jaga ${sp.periodeLabel ?: ""}".trim(), "SP_JAGA_${sp.tahun}_${sp.bulan}.pdf")
                }) {
                    Icon(Icons.Outlined.PictureAsPdf, contentDescription = "Lihat PDF SP Jaga")
                }
            }
        },
    ) { padding ->
        Column(Modifier.fillMaxSize().padding(padding)) {
            PeriodBar(
                period = period,
                onPrev = { vm.shiftMonth(-1) },
                onNext = { vm.shiftMonth(1) },
                onToday = { vm.resetToCurrent() },
            )
            RefreshableBox(isRefreshing = refreshing, onRefresh = { vm.load(pullToRefresh = true) }) {
                LoadStateContent(state = state, onRetry = { vm.load() }) { roster ->
                    RosterList(
                        roster = roster,
                        filter = filter,
                        query = query,
                        todayIso = vm.todayIso(),
                        onFilter = { vm.filter.value = it },
                        onQuery = { vm.query.value = it },
                        onOpenDay = onOpenDay,
                        onPickOther = { showPicker = true },
                    )
                }
            }
        }
    }

    if (showPicker && data != null) {
        FormSheet(title = "Pilih Surat Perintah Jaga", onDismiss = { showPicker = false }) {
            data.available.orEmpty().forEach { item ->
                SindenCard(
                    modifier = Modifier.fillMaxWidth().padding(bottom = Spacing.sm),
                    onClick = {
                        showPicker = false
                        vm.selectSp(item.id, item.bulan, item.tahun)
                    },
                ) { SpInfoRow(item) }
            }
        }
    }
}

@Composable
private fun PeriodBar(period: YearMonth, onPrev: () -> Unit, onNext: () -> Unit, onToday: () -> Unit) {
    Row(
        Modifier.fillMaxWidth().padding(horizontal = Spacing.sm),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        IconButton(onClick = onPrev) { Icon(Icons.AutoMirrored.Filled.KeyboardArrowLeft, contentDescription = "Bulan sebelumnya") }
        Text(
            Fmt.monthYear(period),
            style = MaterialTheme.typography.titleMedium,
            textAlign = TextAlign.Center,
            modifier = Modifier.weight(1f),
        )
        IconButton(onClick = onNext) { Icon(Icons.AutoMirrored.Filled.KeyboardArrowRight, contentDescription = "Bulan berikutnya") }
        if (period != YearMonth.now()) {
            AssistChip(onClick = onToday, label = { Text("Bulan ini") })
        }
    }
}

@Composable
private fun RosterList(
    roster: DutyRosterResponse,
    filter: DutyFilter,
    query: String,
    todayIso: String,
    onFilter: (DutyFilter) -> Unit,
    onQuery: (String) -> Unit,
    onOpenDay: (Int?, String) -> Unit,
    onPickOther: () -> Unit,
) {
    val sp = roster.sp
    if (sp == null) {
        EmptyState(
            title = "Belum ada SP Jaga",
            message = "Tidak ada Surat Perintah Jaga yang dapat Anda lihat untuk periode ini.",
            icon = Icons.Outlined.EventAvailable,
            action = if (!roster.available.isNullOrEmpty()) {
                { OutlinedButton(onClick = onPickOther) { Text("Lihat SP periode lain") } }
            } else null,
        )
        return
    }

    val q = query.trim().lowercase()
    val days = roster.days.orEmpty()
        .filter { day ->
            when (filter) {
                DutyFilter.ALL -> true
                DutyFilter.MINE -> day.hasMe
                DutyFilter.UPCOMING -> day.date >= todayIso
            }
        }
        .filter { day ->
            q.isEmpty() || day.safeEntries.any { e ->
                e.name.orEmpty().lowercase().contains(q) || e.nrp.orEmpty().lowercase().contains(q)
            }
        }

    LazyColumn(
        contentPadding = PaddingValues(bottom = Spacing.xxl),
        verticalArrangement = Arrangement.spacedBy(Spacing.md),
    ) {
        item { SpSummaryCard(sp, roster.myDates.orEmpty(), roster.myRoles.orEmpty()) }
        item {
            Column(verticalArrangement = Arrangement.spacedBy(Spacing.sm)) {
                SearchField(query, onQuery, "Cari nama atau NRP personel", Modifier.padding(horizontal = Spacing.screen))
                FilterChipRow(DutyFilter.entries, filter, { it.label }, onFilter)
            }
        }
        if (days.isEmpty()) {
            item {
                EmptyState(
                    title = "Tidak ada jadwal",
                    message = when {
                        q.isNotEmpty() -> "Tidak ditemukan personel dengan kata kunci \"$query\"."
                        filter == DutyFilter.MINE -> "Anda tidak tercantum dalam SP Jaga ini."
                        else -> "Tidak ada tanggal jaga yang cocok dengan filter."
                    },
                    icon = Icons.Outlined.Search,
                )
            }
        } else {
            items(days, key = { it.date }) { day ->
                DutyDayCard(
                    day = day,
                    onClick = { onOpenDay(sp.id, day.date) },
                    modifier = Modifier.padding(horizontal = Spacing.screen),
                )
            }
        }
    }
}

@Composable
private fun SpSummaryCard(sp: SpJagaSummary, myDates: List<String>, myRoles: List<String>) {
    SindenCard(Modifier.padding(horizontal = Spacing.screen).fillMaxWidth()) {
        SpInfoRow(sp)
        Spacer(Modifier.height(Spacing.md))
        Text(
            "TMT ${Fmt.shortDate(sp.tmtMulai)} – ${Fmt.shortDate(sp.tmtSelesai)}",
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        if (!sp.penandatangan.isNullOrBlank()) {
            Text(
                "Disahkan: ${sp.penandatangan}",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        Spacer(Modifier.height(Spacing.md))
        if (myDates.isEmpty()) {
            Text("Anda tidak tercantum dalam SP Jaga ini.", style = MaterialTheme.typography.bodyMedium)
        } else {
            Text(
                "Anda bertugas ${myDates.size}× ${if (myRoles.isNotEmpty()) "sebagai ${myRoles.joinToString(", ")}" else ""}".trim(),
                style = MaterialTheme.typography.titleSmall,
                color = MaterialTheme.colorScheme.primary,
            )
            Text(
                myDates.mapNotNull { Fmt.parseDate(it) }.joinToString(", ") { Fmt.dayMonth(it) },
                style = MaterialTheme.typography.bodyMedium,
            )
        }
    }
}
