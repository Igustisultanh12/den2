package id.my.sisinden.app.ui.skhpp

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
import androidx.compose.material.icons.outlined.Check
import androidx.compose.material.icons.outlined.Delete
import androidx.compose.material.icons.outlined.PictureAsPdf
import androidx.compose.material.icons.outlined.Undo
import androidx.compose.material.icons.outlined.VerifiedUser
import androidx.compose.material3.Button
import androidx.compose.material3.ExtendedFloatingActionButton
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
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
import id.my.sisinden.app.data.model.SkhppDto
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.ui.common.CollectMessages
import id.my.sisinden.app.ui.common.LoadableViewModel
import kotlinx.coroutines.FlowPreview
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.debounce
import kotlinx.coroutines.flow.distinctUntilChanged
import kotlinx.coroutines.flow.drop
import kotlinx.coroutines.launch

enum class SkhppFilter(val label: String, val status: String?) {
    ALL("Semua", null), PENDING("Menunggu", "pending"), APPROVED("Disetujui", "approved"), REJECTED("Dikembalikan", "rejected")
}

fun skhppTone(s: SkhppDto): Tone = when (s.normalizedStatus) {
    "approved" -> Tone.SUCCESS
    "rejected" -> Tone.DANGER
    "draft" -> Tone.NEUTRAL
    else -> Tone.WARNING
}

@OptIn(FlowPreview::class)
class SkhppViewModel(private val repository: SindenRepository) : LoadableViewModel<List<SkhppDto>>() {
    val filter = MutableStateFlow(SkhppFilter.ALL)
    val search = MutableStateFlow("")

    init {
        load()
        viewModelScope.launch { search.drop(1).debounce(400).distinctUntilChanged().collect { load() } }
    }

    override suspend fun fetch(): ApiResult<List<SkhppDto>> =
        repository.skhpp(filter.value.status, search.value).map { it.data.orEmpty() }

    fun setFilter(f: SkhppFilter) {
        filter.value = f
        reload()
    }

    fun approve(id: Int, priority: String, onDone: () -> Unit) =
        action("SKHPP disetujui.", onDone) { repository.approveSkhpp(id, priority) }

    fun reject(id: Int, note: String, onDone: () -> Unit) =
        action("SKHPP dikembalikan untuk revisi.", onDone) { repository.rejectSkhpp(id, note.trim()) }

    fun delete(id: Int, onDone: () -> Unit) = action("SKHPP dihapus.", onDone) { repository.deleteSkhpp(id) }
}

@Composable
fun SkhppScreen(
    repository: SindenRepository,
    permissions: PermissionSet,
    refreshSignal: Long,
    onBack: () -> Unit,
    onCreate: () -> Unit,
    onOpenPdf: (String, String, String) -> Unit,
) {
    val vm: SkhppViewModel = viewModel { SkhppViewModel(repository) }
    val state by vm.state.collectAsStateWithLifecycle()
    val refreshing by vm.refreshing.collectAsStateWithLifecycle()
    val busy by vm.busy.collectAsStateWithLifecycle()
    val filter by vm.filter.collectAsStateWithLifecycle()
    val search by vm.search.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }
    CollectMessages(vm.messages, snackbar)

    LaunchedEffect(refreshSignal) { if (refreshSignal > 0) vm.load() }

    var detail by remember { mutableStateOf<SkhppDto?>(null) }
    var approving by remember { mutableStateOf<SkhppDto?>(null) }
    var rejecting by remember { mutableStateOf<SkhppDto?>(null) }
    var deleting by remember { mutableStateOf<SkhppDto?>(null) }

    SindenScaffold(
        title = "SKHPP",
        subtitle = "Surat Keterangan Hasil Penelitian Personel",
        onBack = onBack,
        snackbarHostState = snackbar,
        floatingActionButton = {
            if (permissions.has(Perm.SKHPP_CREATE)) {
                ExtendedFloatingActionButton(
                    onClick = onCreate,
                    icon = { Icon(Icons.Outlined.Add, contentDescription = null) },
                    text = { Text("Ajukan") },
                )
            }
        },
    ) { padding ->
        Column(Modifier.padding(padding)) {
            SearchField(search, { vm.search.value = it }, "Cari nama, nomor, atau NRP", Modifier.padding(horizontal = Spacing.screen))
            Spacer(Modifier.height(Spacing.sm))
            FilterChipRow(SkhppFilter.entries, filter, { it.label }, vm::setFilter)
            Spacer(Modifier.height(Spacing.sm))
            RefreshableBox(refreshing, { vm.load(pullToRefresh = true) }) {
                LoadStateContent(state, onRetry = { vm.load() }) { list ->
                    if (list.isEmpty()) {
                        EmptyState("Tidak ada SKHPP", "Belum ada pengajuan SKHPP yang sesuai filter.", icon = Icons.Outlined.VerifiedUser)
                    } else {
                        LazyColumn(
                            contentPadding = PaddingValues(start = Spacing.screen, end = Spacing.screen, bottom = 96.dp),
                            verticalArrangement = Arrangement.spacedBy(Spacing.sm),
                        ) {
                            items(list, key = { it.id }) { s ->
                                ListRowCard(
                                    title = s.nama.orEmpty().ifBlank { "-" },
                                    subtitle = s.pangkatKorpsNrp?.takeIf { it.isNotBlank() && it != "-" } ?: s.kategoriLabel,
                                    supporting = "${s.nomorSkhpp ?: "Belum bernomor"} · ${Fmt.shortDate(s.tanggalSurat)}",
                                    leading = { IconBadge(Icons.Outlined.VerifiedUser, toneAccent(skhppTone(s))) },
                                    trailing = { StatusChip(s.statusLabel, skhppTone(s)) },
                                    onClick = { detail = s },
                                )
                            }
                        }
                    }
                }
            }
        }
    }

    detail?.let { s ->
        FormSheet(title = "Detail SKHPP", onDismiss = { detail = null }) {
            Row { StatusChip(s.statusLabel, skhppTone(s)) }
            Spacer(Modifier.height(Spacing.sm))
            InfoRow("Nomor", s.nomorSkhpp ?: "Belum bernomor")
            InfoRow("Nama", s.nama)
            InfoRow("Pangkat/NRP", s.pangkatKorpsNrp)
            InfoRow("Jabatan", s.jabatan)
            InfoRow("Kategori", s.kategoriLabel)
            InfoRow("Peruntukan", s.peruntukan)
            InfoRow("Tanggal", Fmt.longDate(s.tanggalSurat))
            InfoRow("Operator", s.createdBy)
            Spacer(Modifier.height(Spacing.lg))
            Column(verticalArrangement = Arrangement.spacedBy(Spacing.sm)) {
                if (permissions.has(Perm.SKHPP_PDF)) {
                    OutlinedButton(
                        onClick = {
                            detail = null
                            onOpenPdf("api/mobile/pdf/skhpp/${s.id}", "SKHPP ${s.nama.orEmpty()}", "SKHPP_${s.nama.orEmpty()}.pdf")
                        },
                        modifier = Modifier.fillMaxWidth(),
                    ) {
                        Icon(Icons.Outlined.PictureAsPdf, contentDescription = null)
                        Spacer(Modifier.width(Spacing.sm))
                        Text("Lihat dokumen PDF")
                    }
                }
                if (permissions.has(Perm.SKHPP_APPROVE) && s.normalizedStatus == "pending") {
                    Row(horizontalArrangement = Arrangement.spacedBy(Spacing.sm)) {
                        Button(onClick = { approving = s }, modifier = Modifier.weight(1f)) {
                            Icon(Icons.Outlined.Check, contentDescription = null)
                            Spacer(Modifier.width(Spacing.sm))
                            Text("Setujui")
                        }
                        OutlinedButton(onClick = { rejecting = s }, modifier = Modifier.weight(1f)) {
                            Icon(Icons.Outlined.Undo, contentDescription = null)
                            Spacer(Modifier.width(Spacing.sm))
                            Text("Kembalikan")
                        }
                    }
                }
                if (permissions.has(Perm.SKHPP_DELETE)) {
                    OutlinedButton(onClick = { deleting = s }, modifier = Modifier.fillMaxWidth()) {
                        Icon(Icons.Outlined.Delete, contentDescription = null, tint = MaterialTheme.colorScheme.error)
                        Spacer(Modifier.width(Spacing.sm))
                        Text("Hapus / cabut SKHPP", color = MaterialTheme.colorScheme.error)
                    }
                }
            }
        }
    }

    approving?.let { s ->
        var priority by rememberSaveable { mutableStateOf("R") }
        FormSheet(title = "Setujui SKHPP", onDismiss = { approving = null }) {
            Text(
                "SKHPP atas nama ${s.nama.orEmpty()} akan diberi nomor resmi dan TTE Komandan, sama seperti persetujuan di web.",
                style = MaterialTheme.typography.bodyMedium,
            )
            Spacer(Modifier.height(Spacing.md))
            Text("Derajat surat", style = MaterialTheme.typography.labelLarge)
            Row(horizontalArrangement = Arrangement.spacedBy(Spacing.sm)) {
                listOf("R" to "Rahasia", "B" to "Biasa", "K" to "Kilat").forEach { (code, label) ->
                    FilterChip(selected = priority == code, onClick = { priority = code }, label = { Text("$code — $label") })
                }
            }
            Spacer(Modifier.height(Spacing.lg))
            PrimaryButton("Setujui & terbitkan", loading = busy, onClick = {
                vm.approve(s.id, priority) { approving = null; detail = null }
            })
        }
    }

    rejecting?.let { s ->
        var note by rememberSaveable { mutableStateOf("") }
        var error by remember { mutableStateOf<String?>(null) }
        FormSheet(title = "Kembalikan untuk revisi", onDismiss = { rejecting = null }) {
            SindenTextField(note, { note = it; error = null }, "Catatan revisi untuk pengaju", error = error, singleLine = false, minLines = 3)
            Spacer(Modifier.height(Spacing.lg))
            PrimaryButton("Kirim catatan revisi", loading = busy, onClick = {
                if (note.isBlank()) error = "Catatan revisi wajib diisi"
                else vm.reject(s.id, note) { rejecting = null; detail = null }
            })
        }
    }

    deleting?.let { s ->
        ConfirmDialog(
            title = "Hapus SKHPP?",
            message = "SKHPP atas nama ${s.nama.orEmpty()} akan dihapus dan status validasi QR-nya dicabut. Tindakan ini tercatat di log audit.",
            confirmLabel = "Hapus",
            destructive = true,
            loading = busy,
            onDismiss = { deleting = null },
            onConfirm = { vm.delete(s.id) { deleting = null; detail = null } },
        )
    }
}
