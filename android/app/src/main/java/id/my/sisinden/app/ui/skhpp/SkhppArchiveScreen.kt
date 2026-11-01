package id.my.sisinden.app.ui.skhpp

import android.content.Context
import android.net.Uri
import android.provider.OpenableColumns
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Add
import androidx.compose.material.icons.outlined.Archive
import androidx.compose.material.icons.outlined.PictureAsPdf
import androidx.compose.material3.ExtendedFloatingActionButton
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import id.my.sisinden.app.core.network.ApiResult
import id.my.sisinden.app.core.security.Perm
import id.my.sisinden.app.core.security.PermissionSet
import id.my.sisinden.app.core.ui.components.DateField
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
import id.my.sisinden.app.core.ui.components.toneAccent
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.core.util.Fmt
import id.my.sisinden.app.data.model.SkhppArchiveDto
import id.my.sisinden.app.data.model.SkhppArchiveResponse
import id.my.sisinden.app.data.model.SkhppNumberRequest
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.ui.common.CollectMessages
import id.my.sisinden.app.ui.common.LoadableViewModel
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.FlowPreview
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.debounce
import kotlinx.coroutines.flow.distinctUntilChanged
import kotlinx.coroutines.flow.drop
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.time.LocalDate

/** Batas unggahan sama dengan server (20 MB). */
private const val MAX_PDF_BYTES = 20L * 1024 * 1024

enum class SkhppArchiveFilter(val label: String, val jenis: String?, val status: String?) {
    ALL("Semua", null, null),
    WAITING("Menunggu PDF", null, "menunggu_pdf"),
    STORED("PDF Tersimpan", null, "tersimpan"),
    D("SKHPP-D", "D", null),
    P("SKHPP-P", "P", null),
}

fun archiveTone(a: SkhppArchiveDto): Tone = when (a.status) {
    "tersimpan" -> Tone.SUCCESS
    "dicabut" -> Tone.DANGER
    else -> Tone.WARNING
}

/** PDF terpilih dari penyimpanan perangkat. */
class PickedPdf(val name: String, val bytes: ByteArray)

@OptIn(FlowPreview::class)
class SkhppArchiveViewModel(private val repository: SindenRepository) : LoadableViewModel<SkhppArchiveResponse>() {
    val filter = MutableStateFlow(SkhppArchiveFilter.ALL)
    val search = MutableStateFlow("")

    init {
        load()
        viewModelScope.launch { search.drop(1).debounce(400).distinctUntilChanged().collect { load() } }
    }

    override suspend fun fetch(): ApiResult<SkhppArchiveResponse> =
        repository.skhppArchives(search.value, filter.value.jenis, filter.value.status)

    fun setFilter(f: SkhppArchiveFilter) {
        filter.value = f
        reload()
    }

    fun requestNumber(request: SkhppNumberRequest, onDone: () -> Unit) =
        action("Nomor SKHPP berhasil dibuat.", onDone) { repository.requestSkhppNumber(request) }

    fun upload(id: Int, pdf: PickedPdf, onDone: () -> Unit) =
        action("PDF SKHPP tersimpan.", onDone) { repository.uploadSkhppArchive(id, pdf.name, pdf.bytes) }

    fun notify(message: String) = messages.post(message)
}

/** Baca PDF dari Storage Access Framework; null bila bukan PDF atau terlalu besar. */
private fun readPdf(context: Context, uri: Uri): Result<PickedPdf> = runCatching {
    val resolver = context.contentResolver
    var name = "skhpp.pdf"
    var size = -1L
    resolver.query(uri, arrayOf(OpenableColumns.DISPLAY_NAME, OpenableColumns.SIZE), null, null, null)?.use { c ->
        if (c.moveToFirst()) {
            c.getString(0)?.let { name = it }
            if (!c.isNull(1)) size = c.getLong(1)
        }
    }
    require(size <= MAX_PDF_BYTES) { "Ukuran PDF maksimal 20 MB." }
    val bytes = resolver.openInputStream(uri)?.use { it.readBytes() } ?: error("Berkas tidak dapat dibaca.")
    require(bytes.size <= MAX_PDF_BYTES) { "Ukuran PDF maksimal 20 MB." }
    require(bytes.size > 4 && String(bytes, 0, 4, Charsets.US_ASCII) == "%PDF") { "Berkas yang dipilih bukan PDF." }
    PickedPdf(if (name.endsWith(".pdf", ignoreCase = true)) name else "$name.pdf", bytes)
}

@Composable
fun SkhppArchiveScreen(
    repository: SindenRepository,
    permissions: PermissionSet,
    onBack: () -> Unit,
    onOpenPdf: (String, String, String) -> Unit,
) {
    val vm: SkhppArchiveViewModel = viewModel { SkhppArchiveViewModel(repository) }
    val state by vm.state.collectAsStateWithLifecycle()
    val refreshing by vm.refreshing.collectAsStateWithLifecycle()
    val busy by vm.busy.collectAsStateWithLifecycle()
    val filter by vm.filter.collectAsStateWithLifecycle()
    val search by vm.search.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }
    CollectMessages(vm.messages, snackbar)

    val context = LocalContext.current
    val scope = rememberCoroutineScope()

    var detail by remember { mutableStateOf<SkhppArchiveDto?>(null) }
    var requesting by remember { mutableStateOf<String?>(null) } // jenis "D" / "P"
    var uploadTarget by remember { mutableStateOf<SkhppArchiveDto?>(null) }

    val picker = rememberLauncherForActivityResult(ActivityResultContracts.OpenDocument()) { uri ->
        val target = uploadTarget
        if (uri == null || target == null) return@rememberLauncherForActivityResult
        scope.launch {
            val picked = withContext(Dispatchers.IO) { readPdf(context, uri) }
            picked.fold(
                onSuccess = { pdf -> vm.upload(target.id, pdf) { uploadTarget = null; detail = null } },
                onFailure = { vm.notify(it.message ?: "Gagal membaca PDF.") },
            )
        }
    }

    SindenScaffold(
        title = "Arsip SKHPP",
        subtitle = "Arsip PDF & nomor SKHPP-D / SKHPP-P",
        onBack = onBack,
        snackbarHostState = snackbar,
        floatingActionButton = {
            if (permissions.has(Perm.SKHPP_ARCHIVE_CREATE)) {
                ExtendedFloatingActionButton(
                    onClick = { requesting = "D" },
                    icon = { Icon(Icons.Outlined.Add, contentDescription = null) },
                    text = { Text("Minta Nomor") },
                )
            }
        },
    ) { padding ->
        Column(Modifier.padding(padding)) {
            SearchField(search, { vm.search.value = it }, "Cari nomor, nama, atau perihal", Modifier.padding(horizontal = Spacing.screen))
            Spacer(Modifier.height(Spacing.sm))
            FilterChipRow(SkhppArchiveFilter.entries, filter, { it.label }, vm::setFilter)
            Spacer(Modifier.height(Spacing.sm))
            RefreshableBox(refreshing, { vm.load(pullToRefresh = true) }) {
                LoadStateContent(state, onRetry = { vm.load() }) { res ->
                    val list = res.data.orEmpty()
                    LazyColumn(
                        contentPadding = PaddingValues(start = Spacing.screen, end = Spacing.screen, bottom = 96.dp),
                        verticalArrangement = Arrangement.spacedBy(Spacing.sm),
                    ) {
                        item {
                            val d = res.summary?.get("D")
                            val p = res.summary?.get("P")
                            Text(
                                "Nomor berikutnya ${res.year}: SKHPP-D ${d?.next ?: "-"} · SKHPP-P ${p?.next ?: "-"}",
                                style = MaterialTheme.typography.labelLarge,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                                modifier = Modifier.padding(vertical = Spacing.xs),
                            )
                        }
                        if (list.isEmpty()) {
                            item { EmptyState("Belum ada arsip", "Tidak ada arsip SKHPP yang sesuai filter.", icon = Icons.Outlined.Archive) }
                        }
                        items(list, key = { it.id }) { a ->
                            ListRowCard(
                                title = a.nomorSkhpp ?: "Belum bernomor",
                                subtitle = a.perihal.orEmpty().ifBlank { a.nama ?: "-" },
                                supporting = "${a.jenisLabel} · ${if (a.isManual) "Manual" else "Menu SINDEN"} · ${Fmt.shortDate(a.tanggal)}",
                                leading = { IconBadge(Icons.Outlined.Archive, toneAccent(archiveTone(a))) },
                                trailing = { StatusChip(a.statusLabel ?: "-", archiveTone(a)) },
                                onClick = { detail = a },
                            )
                        }
                    }
                }
            }
        }
    }

    detail?.let { a ->
        FormSheet(title = "Detail Arsip SKHPP", onDismiss = { detail = null }) {
            Row { StatusChip(a.statusLabel ?: "-", archiveTone(a)) }
            Spacer(Modifier.height(Spacing.sm))
            InfoRow("Nomor", a.nomorSkhpp ?: "Belum bernomor (terbit saat TTE Komandan)")
            InfoRow("Jenis", a.jenisLabel)
            InfoRow("Sumber", if (a.isManual) "Nomor manual" else "Menu Penerbitan SKHPP")
            a.stageLabel?.let { InfoRow("Tahap", it) }
            InfoRow("Perihal", a.perihal)
            InfoRow("Nama", a.nama)
            InfoRow("Peruntukan", a.peruntukan)
            InfoRow("Tanggal", Fmt.longDate(a.tanggal))
            InfoRow("Keterangan", a.keterangan)
            InfoRow("Dibuat oleh", a.createdBy)
            Spacer(Modifier.height(Spacing.lg))
            Column(verticalArrangement = Arrangement.spacedBy(Spacing.sm)) {
                if (a.hasFile) {
                    OutlinedButton(
                        onClick = {
                            detail = null
                            val label = a.nomorSkhpp ?: a.perihal.orEmpty()
                            onOpenPdf("api/mobile/pdf/skhpp-archive/${a.id}", "Arsip $label", "SKHPP-${a.jenis}_${a.nomorUrutLabel ?: a.id}.pdf")
                        },
                        modifier = Modifier.fillMaxWidth(),
                    ) {
                        Icon(Icons.Outlined.PictureAsPdf, contentDescription = null)
                        Spacer(Modifier.width(Spacing.sm))
                        Text("Lihat PDF")
                    }
                }
                if (permissions.has(Perm.SKHPP_ARCHIVE_UPLOAD) && a.canUpload) {
                    PrimaryButton(
                        text = if (a.hasFile) "Ganti PDF" else "Unggah PDF",
                        loading = busy && uploadTarget?.id == a.id,
                        onClick = {
                            uploadTarget = a
                            picker.launch(arrayOf("application/pdf"))
                        },
                    )
                } else if (a.nomorSkhpp.isNullOrBlank()) {
                    Text(
                        "PDF otomatis tersimpan di sini setelah TTE Komandan terbit.",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }
        }
    }

    requesting?.let { initialJenis ->
        val summary = (state as? id.my.sisinden.app.core.ui.components.LoadState.Ready)?.data?.summary
        RequestNumberSheet(
            initialJenis = initialJenis,
            nextNumber = { j -> summary?.get(j)?.next },
            busy = busy,
            onDismiss = { requesting = null },
            onSubmit = { req -> vm.requestNumber(req) { requesting = null } },
        )
    }
}

@Composable
private fun RequestNumberSheet(
    initialJenis: String,
    nextNumber: (String) -> Int?,
    busy: Boolean,
    onDismiss: () -> Unit,
    onSubmit: (SkhppNumberRequest) -> Unit,
) {
    var jenis by rememberSaveable { mutableStateOf(initialJenis) }
    var tanggal by rememberSaveable { mutableStateOf(LocalDate.now().toString()) }
    var custom by rememberSaveable { mutableStateOf("") }
    var perihal by rememberSaveable { mutableStateOf("") }
    var nama by rememberSaveable { mutableStateOf("") }
    var peruntukan by rememberSaveable { mutableStateOf("") }
    var keterangan by rememberSaveable { mutableStateOf("") }
    var errors by remember { mutableStateOf(emptyMap<String, String>()) }

    FormSheet(title = "Minta Nomor SKHPP Manual", onDismiss = onDismiss) {
        Text(
            "Untuk SKHPP yang dibuat di luar menu SINDEN. Nomor melanjutkan urutan SKHPP-$jenis yang sama dengan menu Penerbitan SKHPP.",
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        Spacer(Modifier.height(Spacing.sm))
        Text("Jenis", style = MaterialTheme.typography.labelLarge)
        Row(horizontalArrangement = Arrangement.spacedBy(Spacing.sm)) {
            listOf("D" to "Dinas Militer & PNS", "P" to "Mitra / Perusahaan").forEach { (code, label) ->
                FilterChip(selected = jenis == code, onClick = { jenis = code }, label = { Text("SKHPP-$code · $label") })
            }
        }
        Spacer(Modifier.height(Spacing.sm))
        val shown = custom.trim().trimStart('0').lowercase().ifBlank { nextNumber(jenis)?.toString() }
        shown?.let {
            Text(
                "Sifat RAHASIA · perkiraan nomor: R / $it / SKHPP (nomor final dikunci server saat disimpan)",
                style = MaterialTheme.typography.labelLarge,
                color = MaterialTheme.colorScheme.primary,
            )
            Spacer(Modifier.height(Spacing.sm))
        }
        DateField(tanggal, { tanggal = it }, "Tanggal", error = errors["tanggal"])
        SindenTextField(
            custom,
            { custom = it.take(10); errors = errors - "custom" },
            "Nomor sisipan (opsional)",
            placeholder = "cth: 12a",
            supporting = "Kosongkan untuk nomor berikutnya. Isi angka + huruf untuk nomor sisipan.",
            error = errors["custom"],
        )
        SindenTextField(perihal, { perihal = it; errors = errors - "perihal" }, "Perihal", error = errors["perihal"], singleLine = false, minLines = 2)
        SindenTextField(nama, { nama = it }, "Nama personel / pemohon (opsional)")
        SindenTextField(peruntukan, { peruntukan = it }, "Peruntukan (opsional)", singleLine = false, minLines = 2)
        SindenTextField(keterangan, { keterangan = it }, "Keterangan (opsional)", singleLine = false, minLines = 2)
        Spacer(Modifier.height(Spacing.lg))
        PrimaryButton(
            text = "Buat Nomor",
            loading = busy,
            onClick = {
                errors = buildMap {
                    if (perihal.isBlank()) put("perihal", "Perihal wajib diisi")
                    if (tanggal.isBlank()) put("tanggal", "Tanggal wajib diisi")
                    if (custom.isNotBlank() && !Regex("^\\s*\\d{1,6}\\s*[a-zA-Z]{0,3}\\s*$").matches(custom)) {
                        put("custom", "Gunakan angka, boleh diikuti huruf (cth: 12a)")
                    }
                }
                if (errors.isEmpty()) {
                    onSubmit(
                        SkhppNumberRequest(
                            jenis = jenis,
                            tanggal = tanggal,
                            customNomorUrut = custom.trim().ifBlank { null },
                            perihal = perihal.trim(),
                            nama = nama.trim().ifBlank { null },
                            peruntukan = peruntukan.trim().ifBlank { null },
                            keterangan = keterangan.trim().ifBlank { null },
                        )
                    )
                }
            },
        )
    }
}
