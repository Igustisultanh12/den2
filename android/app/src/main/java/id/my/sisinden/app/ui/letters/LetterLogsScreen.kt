package id.my.sisinden.app.ui.letters

import android.net.Uri
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Add
import androidx.compose.material.icons.outlined.Delete
import androidx.compose.material.icons.outlined.Edit
import androidx.compose.material.icons.outlined.Email
import androidx.compose.material.icons.outlined.PictureAsPdf
import androidx.compose.material3.ExtendedFloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import id.my.sisinden.app.core.network.ApiResult
import id.my.sisinden.app.core.network.map
import id.my.sisinden.app.core.security.Perm
import id.my.sisinden.app.core.security.PermissionSet
import id.my.sisinden.app.core.ui.components.ConfirmDialog
import id.my.sisinden.app.core.ui.components.DateField
import id.my.sisinden.app.core.ui.components.DropdownField
import id.my.sisinden.app.core.ui.components.EmptyState
import id.my.sisinden.app.core.ui.components.FilterChipRow
import id.my.sisinden.app.core.ui.components.FormSheet
import id.my.sisinden.app.core.ui.components.IconBadge
import id.my.sisinden.app.core.ui.components.InfoRow
import id.my.sisinden.app.core.ui.components.ListRowCard
import id.my.sisinden.app.core.ui.components.LoadStateContent
import id.my.sisinden.app.core.ui.components.PrimaryButton
import id.my.sisinden.app.core.ui.components.RefreshableBox
import id.my.sisinden.app.core.ui.components.SearchField
import id.my.sisinden.app.core.ui.components.SindenScaffold
import id.my.sisinden.app.core.ui.components.SindenTextField
import id.my.sisinden.app.core.ui.components.StatusChip
import id.my.sisinden.app.core.ui.components.Tone
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.core.util.Fmt
import id.my.sisinden.app.data.model.CategoryDto
import id.my.sisinden.app.data.model.CreateLetterLogRequest
import id.my.sisinden.app.data.model.LetterLogDto
import id.my.sisinden.app.data.model.UpdateLetterLogRequest
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.ui.common.CollectMessages
import id.my.sisinden.app.ui.common.LoadableViewModel
import kotlinx.coroutines.FlowPreview
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.debounce
import kotlinx.coroutines.flow.distinctUntilChanged
import kotlinx.coroutines.flow.drop
import kotlinx.coroutines.launch
import java.time.LocalDate

@OptIn(FlowPreview::class)
class LetterLogsViewModel(private val repository: SindenRepository) : LoadableViewModel<List<LetterLogDto>>() {
    val search = MutableStateFlow("")
    val categoryId = MutableStateFlow<Int?>(null)
    val categories = MutableStateFlow<List<CategoryDto>>(emptyList())

    init {
        load()
        viewModelScope.launch {
            (repository.categories() as? ApiResult.Success)?.let { categories.value = it.data.data.orEmpty() }
        }
        // Pencarian ke server dengan jeda agar tidak membanjiri request.
        viewModelScope.launch {
            search.drop(1).debounce(400).distinctUntilChanged().collect { load() }
        }
    }

    override suspend fun fetch(): ApiResult<List<LetterLogDto>> =
        repository.letterLogs(search.value, categoryId.value).map { it.data.orEmpty() }

    fun selectCategory(id: Int?) {
        categoryId.value = id
        reload()
    }

    fun create(categoryId: Int, subject: String, recipient: String, date: String, onDone: () -> Unit) =
        action("Agenda surat tercatat.", onDone) {
            repository.createLetterLog(CreateLetterLogRequest(categoryId, subject.trim(), recipient.trim(), date))
        }

    fun update(id: Int, subject: String, recipient: String, date: String, onDone: () -> Unit) =
        action("Agenda surat diperbarui.", onDone) {
            repository.updateLetterLog(id, UpdateLetterLogRequest(subject.trim(), recipient.trim(), date))
        }

    fun delete(id: Int, onDone: () -> Unit) = action("Agenda surat dihapus.", onDone) { repository.deleteLetterLog(id) }
}

private sealed interface LetterSheet {
    data object Create : LetterSheet
    data class Detail(val item: LetterLogDto) : LetterSheet
    data class Edit(val item: LetterLogDto) : LetterSheet
    data object Report : LetterSheet
}

@Composable
fun LetterLogsScreen(
    repository: SindenRepository,
    permissions: PermissionSet,
    onBack: () -> Unit,
    onOpenPdf: (String, String, String) -> Unit,
) {
    val vm: LetterLogsViewModel = viewModel { LetterLogsViewModel(repository) }
    val state by vm.state.collectAsStateWithLifecycle()
    val refreshing by vm.refreshing.collectAsStateWithLifecycle()
    val busy by vm.busy.collectAsStateWithLifecycle()
    val search by vm.search.collectAsStateWithLifecycle()
    val categoryId by vm.categoryId.collectAsStateWithLifecycle()
    val categories by vm.categories.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }
    CollectMessages(vm.messages, snackbar)

    var sheet by remember { mutableStateOf<LetterSheet?>(null) }
    var confirmDelete by remember { mutableStateOf<LetterLogDto?>(null) }

    SindenScaffold(
        title = "Agenda Surat",
        subtitle = "Penomoran & rekapitulasi naskah dinas",
        onBack = onBack,
        snackbarHostState = snackbar,
        actions = {
            if (permissions.has(Perm.LETTER_LOGS_PDF)) {
                IconButton(onClick = { sheet = LetterSheet.Report }) {
                    Icon(Icons.Outlined.PictureAsPdf, contentDescription = "Rekap PDF")
                }
            }
        },
        floatingActionButton = {
            if (permissions.has(Perm.LETTER_LOGS_CREATE)) {
                ExtendedFloatingActionButton(
                    onClick = { sheet = LetterSheet.Create },
                    icon = { Icon(Icons.Outlined.Add, contentDescription = null) },
                    text = { Text("Agenda baru") },
                )
            }
        },
    ) { padding ->
        Column(Modifier.fillMaxSize().padding(padding)) {
            SearchField(search, { vm.search.value = it }, "Cari perihal, nomor, atau tujuan", Modifier.padding(horizontal = Spacing.screen))
            Spacer(Modifier.height(Spacing.sm))
            val options = listOf<CategoryDto?>(null) + categories
            FilterChipRow(
                options = options,
                selected = options.firstOrNull { it?.id == categoryId },
                label = { it?.name ?: "Semua" },
                onSelect = { vm.selectCategory(it?.id) },
            )
            Spacer(Modifier.height(Spacing.sm))
            RefreshableBox(refreshing, { vm.load(pullToRefresh = true) }) {
                LoadStateContent(state, onRetry = { vm.load() }) { list ->
                    if (list.isEmpty()) {
                        EmptyState(
                            title = "Agenda tidak ditemukan",
                            message = if (search.isNotBlank()) "Tidak ada agenda yang cocok dengan pencarian." else "Belum ada agenda surat pada kategori ini.",
                            icon = Icons.Outlined.Email,
                        )
                    } else {
                        LazyColumn(
                            contentPadding = PaddingValues(start = Spacing.screen, end = Spacing.screen, bottom = 96.dp),
                            verticalArrangement = Arrangement.spacedBy(Spacing.sm),
                        ) {
                            items(list, key = { it.id }) { item ->
                                ListRowCard(
                                    title = item.subject.orEmpty().ifBlank { "(Tanpa perihal)" },
                                    subtitle = "Kepada: ${item.recipient.orEmpty().ifBlank { "-" }}",
                                    supporting = "${item.fullNumber ?: "No. ${item.sequence}"} · ${Fmt.shortDate(item.date)}",
                                    leading = { IconBadge(Icons.Outlined.Email, MaterialTheme.colorScheme.primary) },
                                    trailing = { StatusChip(item.categoryName ?: "Umum", Tone.INFO) },
                                    onClick = { sheet = LetterSheet.Detail(item) },
                                )
                            }
                        }
                    }
                }
            }
        }
    }

    when (val s = sheet) {
        null -> Unit
        LetterSheet.Create -> LetterForm(
            title = "Agenda surat baru",
            categories = categories,
            initial = null,
            busy = busy,
            onDismiss = { sheet = null },
            onSubmit = { cat, subject, recipient, date -> vm.create(cat, subject, recipient, date) { sheet = null } },
        )
        is LetterSheet.Edit -> LetterForm(
            title = "Ubah agenda surat",
            categories = categories,
            initial = s.item,
            busy = busy,
            onDismiss = { sheet = null },
            onSubmit = { _, subject, recipient, date -> vm.update(s.item.id, subject, recipient, date) { sheet = null } },
        )
        is LetterSheet.Detail -> FormSheet(title = "Detail agenda", onDismiss = { sheet = null }) {
            InfoRow("Nomor", s.item.fullNumber ?: s.item.sequence)
            InfoRow("Perihal", s.item.subject)
            InfoRow("Kepada", s.item.recipient)
            InfoRow("Tanggal", Fmt.longDate(s.item.date))
            InfoRow("Kategori", listOfNotNull(s.item.categoryName, s.item.subCategoryName).joinToString(" / "))
            Spacer(Modifier.height(Spacing.lg))
            Row(horizontalArrangement = Arrangement.spacedBy(Spacing.sm)) {
                if (permissions.has(Perm.LETTER_LOGS_UPDATE)) {
                    OutlinedButton(onClick = { sheet = LetterSheet.Edit(s.item) }, modifier = Modifier.weight(1f)) {
                        Icon(Icons.Outlined.Edit, contentDescription = null)
                        Spacer(Modifier.width(Spacing.sm))
                        Text("Ubah")
                    }
                }
                if (permissions.has(Perm.LETTER_LOGS_DELETE)) {
                    OutlinedButton(onClick = { confirmDelete = s.item }, modifier = Modifier.weight(1f)) {
                        Icon(Icons.Outlined.Delete, contentDescription = null, tint = MaterialTheme.colorScheme.error)
                        Spacer(Modifier.width(Spacing.sm))
                        Text("Hapus", color = MaterialTheme.colorScheme.error)
                    }
                }
            }
        }
        LetterSheet.Report -> ReportSheet(
            categories = categories,
            onDismiss = { sheet = null },
            onGenerate = { catId, start, end ->
                sheet = null
                val query = buildList {
                    catId?.let { add("category_id=$it") }
                    if (start.isNotBlank() && end.isNotBlank()) {
                        add("start_date=${Uri.encode(start)}")
                        add("end_date=${Uri.encode(end)}")
                    }
                }.joinToString("&")
                onOpenPdf(
                    "api/mobile/pdf/letter-logs" + if (query.isNotEmpty()) "?$query" else "",
                    "Rekap Agenda Surat",
                    "REKAP_AGENDA_SURAT_${LocalDate.now()}.pdf",
                )
            },
        )
    }

    confirmDelete?.let { item ->
        ConfirmDialog(
            title = "Hapus agenda surat?",
            message = "Agenda \"${item.subject.orEmpty()}\" akan dihapus permanen dari buku agenda.",
            confirmLabel = "Hapus",
            destructive = true,
            loading = busy,
            onDismiss = { confirmDelete = null },
            onConfirm = { vm.delete(item.id) { confirmDelete = null; sheet = null } },
        )
    }
}

@Composable
private fun LetterForm(
    title: String,
    categories: List<CategoryDto>,
    initial: LetterLogDto?,
    busy: Boolean,
    onDismiss: () -> Unit,
    onSubmit: (categoryId: Int, subject: String, recipient: String, date: String) -> Unit,
) {
    var category by rememberSaveable { mutableStateOf(initial?.categoryId) }
    var subject by rememberSaveable { mutableStateOf(initial?.subject.orEmpty()) }
    var recipient by rememberSaveable { mutableStateOf(initial?.recipient.orEmpty()) }
    var date by rememberSaveable { mutableStateOf(initial?.date ?: LocalDate.now().toString()) }
    var errors by remember { mutableStateOf(emptyMap<String, String>()) }

    FormSheet(title = title, onDismiss = onDismiss) {
        if (initial == null) {
            DropdownField(
                label = "Kategori surat",
                options = categories,
                selected = categories.firstOrNull { it.id == category },
                optionLabel = { listOfNotNull(it.code, it.name).joinToString(" — ") },
                onSelect = { category = it.id },
                error = errors["category"],
            )
            Text(
                "Nomor urut diberikan otomatis oleh server sesuai kategori.",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            Spacer(Modifier.height(Spacing.sm))
        } else {
            InfoRow("Nomor", initial.fullNumber ?: initial.sequence)
        }
        SindenTextField(subject, { subject = it }, "Perihal", error = errors["subject"], singleLine = false, minLines = 2)
        SindenTextField(recipient, { recipient = it }, "Kepada / tujuan", error = errors["recipient"])
        DateField(date, { date = it }, "Tanggal surat", error = errors["date"])
        Spacer(Modifier.height(Spacing.lg))
        PrimaryButton(
            text = "Simpan",
            loading = busy,
            onClick = {
                errors = buildMap {
                    if (initial == null && category == null) put("category", "Pilih kategori")
                    if (subject.isBlank()) put("subject", "Perihal wajib diisi")
                    if (recipient.isBlank()) put("recipient", "Tujuan wajib diisi")
                    if (date.isBlank()) put("date", "Tanggal wajib diisi")
                }
                if (errors.isEmpty()) onSubmit(category ?: initial?.categoryId ?: 0, subject, recipient, date)
            },
        )
    }
}

@Composable
private fun ReportSheet(
    categories: List<CategoryDto>,
    onDismiss: () -> Unit,
    onGenerate: (categoryId: Int?, start: String, end: String) -> Unit,
) {
    var category by rememberSaveable { mutableStateOf<Int?>(null) }
    var start by rememberSaveable { mutableStateOf(LocalDate.now().withDayOfMonth(1).toString()) }
    var end by rememberSaveable { mutableStateOf(LocalDate.now().toString()) }
    var error by remember { mutableStateOf<String?>(null) }
    val options = listOf<CategoryDto?>(null) + categories

    FormSheet(title = "Rekap agenda surat (PDF)", onDismiss = onDismiss) {
        Text(
            "Dokumen dibuat oleh server dengan format resmi yang sama seperti di web SISINDEN.",
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        Spacer(Modifier.height(Spacing.md))
        DropdownField(
            label = "Kategori",
            options = options,
            selected = options.firstOrNull { it?.id == category },
            optionLabel = { it?.name ?: "Semua kategori" },
            onSelect = { category = it?.id },
        )
        DateField(start, { start = it; error = null }, "Dari tanggal")
        DateField(end, { end = it; error = null }, "Sampai tanggal", error = error)
        Spacer(Modifier.height(Spacing.lg))
        PrimaryButton(text = "Buat PDF", onClick = {
            if (start.isNotBlank() && end.isNotBlank() && end < start) {
                error = "Tanggal akhir tidak boleh sebelum tanggal awal"
            } else {
                onGenerate(category, start, end)
            }
        })
    }
}
