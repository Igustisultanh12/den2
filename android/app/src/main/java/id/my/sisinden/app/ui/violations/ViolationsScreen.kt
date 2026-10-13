package id.my.sisinden.app.ui.violations

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
import androidx.compose.material.icons.outlined.Delete
import androidx.compose.material.icons.outlined.Gavel
import androidx.compose.material.icons.outlined.PostAdd
import androidx.compose.material3.ExtendedFloatingActionButton
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
import id.my.sisinden.app.core.security.Perm
import id.my.sisinden.app.core.security.PermissionSet
import id.my.sisinden.app.core.ui.components.ConfirmDialog
import id.my.sisinden.app.core.ui.components.DropdownField
import id.my.sisinden.app.core.ui.components.EmptyState
import id.my.sisinden.app.core.ui.components.FilterChipRow
import id.my.sisinden.app.core.ui.components.FormSheet
import id.my.sisinden.app.core.ui.components.InfoRow
import id.my.sisinden.app.core.ui.components.InitialsAvatar
import id.my.sisinden.app.core.ui.components.ListRowCard
import id.my.sisinden.app.core.ui.components.LoadStateContent
import id.my.sisinden.app.core.ui.components.PrimaryButton
import id.my.sisinden.app.core.ui.components.RefreshableBox
import id.my.sisinden.app.core.ui.components.SearchField
import id.my.sisinden.app.core.ui.components.SindenCard
import id.my.sisinden.app.core.ui.components.SindenScaffold
import id.my.sisinden.app.core.ui.components.SindenTextField
import id.my.sisinden.app.core.ui.components.StatTile
import id.my.sisinden.app.core.ui.components.StatusChip
import id.my.sisinden.app.core.ui.components.Tone
import id.my.sisinden.app.core.ui.components.toneAccent
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.core.util.Fmt
import id.my.sisinden.app.data.model.ViolationDto
import id.my.sisinden.app.data.model.ViolationStatus
import id.my.sisinden.app.data.model.ViolationsResponse
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.ui.common.CollectMessages
import id.my.sisinden.app.ui.common.LoadableViewModel
import kotlinx.coroutines.FlowPreview
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.debounce
import kotlinx.coroutines.flow.distinctUntilChanged
import kotlinx.coroutines.flow.drop
import kotlinx.coroutines.launch

fun violationTone(status: String?): Tone = when (status) {
    "SELESAI", "DINAS_KEMBALI" -> Tone.SUCCESS
    "SIDANG", "BANDING" -> Tone.WARNING
    "PDTH" -> Tone.DANGER
    else -> Tone.INFO
}

@OptIn(FlowPreview::class)
class ViolationsViewModel(private val repository: SindenRepository) : LoadableViewModel<ViolationsResponse>() {
    val search = MutableStateFlow("")
    val status = MutableStateFlow<String?>(null)

    init {
        load()
        viewModelScope.launch { search.drop(1).debounce(400).distinctUntilChanged().collect { load() } }
    }

    override suspend fun fetch(): ApiResult<ViolationsResponse> = repository.violations(search.value, status.value)

    fun setStatus(s: String?) {
        status.value = s
        reload()
    }

    fun addProgress(id: Int, note: String, newStatus: String?, onDone: () -> Unit) =
        action("Perkembangan kasus dicatat.", onDone) { repository.addViolationProgress(id, note.trim(), newStatus) }

    fun delete(id: Int, onDone: () -> Unit) = action("Catatan dihapus.", onDone) { repository.deleteViolation(id) }
}

@Composable
fun ViolationsScreen(
    repository: SindenRepository,
    permissions: PermissionSet,
    refreshSignal: Long,
    onBack: () -> Unit,
    onCreate: () -> Unit,
) {
    val vm: ViolationsViewModel = viewModel { ViolationsViewModel(repository) }
    val state by vm.state.collectAsStateWithLifecycle()
    val refreshing by vm.refreshing.collectAsStateWithLifecycle()
    val busy by vm.busy.collectAsStateWithLifecycle()
    val search by vm.search.collectAsStateWithLifecycle()
    val status by vm.status.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }
    CollectMessages(vm.messages, snackbar)
    LaunchedEffect(refreshSignal) { if (refreshSignal > 0) vm.load() }
    val canManage = permissions.has(Perm.VIOLATIONS_MANAGE)

    var detail by remember { mutableStateOf<ViolationDto?>(null) }
    var progressFor by remember { mutableStateOf<ViolationDto?>(null) }
    var deleting by remember { mutableStateOf<ViolationDto?>(null) }

    SindenScaffold(
        title = "Pelanggaran Personel",
        subtitle = "Catatan disiplin & perkembangan kasus",
        onBack = onBack,
        snackbarHostState = snackbar,
        floatingActionButton = {
            if (canManage) {
                ExtendedFloatingActionButton(
                    onClick = onCreate,
                    icon = { Icon(Icons.Outlined.Add, contentDescription = null) },
                    text = { Text("Catat") },
                )
            }
        },
    ) { padding ->
        Column(Modifier.padding(padding)) {
            SearchField(search, { vm.search.value = it }, "Cari nama, NRP, atau kasus", Modifier.padding(horizontal = Spacing.screen))
            Spacer(Modifier.height(Spacing.sm))
            val options = listOf<String?>(null) + ViolationStatus.all
            FilterChipRow(options, status, { it?.let(ViolationStatus::label) ?: "Semua" }, vm::setStatus)
            Spacer(Modifier.height(Spacing.sm))
            RefreshableBox(refreshing, { vm.load(pullToRefresh = true) }) {
                LoadStateContent(state, onRetry = { vm.load() }) { data ->
                    LazyColumn(
                        contentPadding = PaddingValues(start = Spacing.screen, end = Spacing.screen, bottom = 96.dp),
                        verticalArrangement = Arrangement.spacedBy(Spacing.sm),
                    ) {
                        data.stats?.let { st ->
                            item {
                                Row(horizontalArrangement = Arrangement.spacedBy(Spacing.sm)) {
                                    StatTile("Total kasus", Fmt.number(st.total), Icons.Outlined.Gavel, toneAccent(Tone.NEUTRAL), Modifier.weight(1f))
                                    StatTile("Dalam proses", Fmt.number(st.proses), Icons.Outlined.Gavel, toneAccent(Tone.INFO), Modifier.weight(1f))
                                    StatTile("Selesai", Fmt.number(st.selesai), Icons.Outlined.Gavel, toneAccent(Tone.SUCCESS), Modifier.weight(1f))
                                }
                            }
                        }
                        val list = data.data.orEmpty()
                        if (list.isEmpty()) {
                            item { EmptyState("Tidak ada catatan", "Belum ada catatan pelanggaran yang sesuai filter.", icon = Icons.Outlined.Gavel) }
                        } else {
                            items(list, key = { it.id }) { v ->
                                ListRowCard(
                                    title = v.name.orEmpty(),
                                    subtitle = listOfNotNull(v.rank, v.nrp?.let { "NRP $it" }, v.unit).joinToString(" · "),
                                    supporting = v.caseDescription,
                                    leading = { InitialsAvatar(v.name.orEmpty().split(" ").take(2).joinToString("") { it.take(1) }, size = 40.dp) },
                                    trailing = { StatusChip(ViolationStatus.label(v.status), violationTone(v.status)) },
                                    onClick = { detail = v },
                                )
                            }
                        }
                    }
                }
            }
        }
    }

    detail?.let { v ->
        FormSheet(title = "Detail kasus", onDismiss = { detail = null }) {
            Row { StatusChip(ViolationStatus.label(v.status), violationTone(v.status)) }
            Spacer(Modifier.height(Spacing.sm))
            InfoRow("Nama", v.name)
            InfoRow("NRP", v.nrp)
            InfoRow("Pangkat", v.rank)
            InfoRow("Jabatan", v.position)
            InfoRow("Satuan", v.unit)
            InfoRow("TMT", Fmt.longDate(v.incidentDate))
            InfoRow("Kasus", v.caseDescription)
            Spacer(Modifier.height(Spacing.md))
            Text("Riwayat perkembangan", style = MaterialTheme.typography.titleSmall)
            Spacer(Modifier.height(Spacing.xs))
            SindenCard(Modifier.fillMaxWidth(), contentPadding = Spacing.md) {
                val history = v.history
                if (history.isEmpty()) Text("Belum ada catatan perkembangan.", style = MaterialTheme.typography.bodySmall)
                history.forEach { line -> Text("• $line", style = MaterialTheme.typography.bodySmall, modifier = Modifier.padding(vertical = 2.dp)) }
            }
            if (canManage) {
                Spacer(Modifier.height(Spacing.lg))
                Row(horizontalArrangement = Arrangement.spacedBy(Spacing.sm)) {
                    OutlinedButton(onClick = { progressFor = v }, modifier = Modifier.weight(1f)) {
                        Icon(Icons.Outlined.PostAdd, contentDescription = null)
                        Spacer(Modifier.width(Spacing.sm))
                        Text("Perkembangan")
                    }
                    OutlinedButton(onClick = { deleting = v }, modifier = Modifier.weight(1f)) {
                        Icon(Icons.Outlined.Delete, contentDescription = null, tint = MaterialTheme.colorScheme.error)
                        Spacer(Modifier.width(Spacing.sm))
                        Text("Hapus", color = MaterialTheme.colorScheme.error)
                    }
                }
            }
        }
    }

    progressFor?.let { v ->
        var note by rememberSaveable { mutableStateOf("") }
        var newStatus by rememberSaveable { mutableStateOf(v.status) }
        var error by remember { mutableStateOf<String?>(null) }
        FormSheet(title = "Catat perkembangan kasus", onDismiss = { progressFor = null }) {
            SindenTextField(note, { note = it; error = null }, "Perkembangan terbaru", error = error, singleLine = false, minLines = 3)
            DropdownField(
                label = "Status kasus",
                options = ViolationStatus.all,
                selected = newStatus,
                optionLabel = ViolationStatus::label,
                onSelect = { newStatus = it },
            )
            Spacer(Modifier.height(Spacing.lg))
            PrimaryButton("Simpan", loading = busy, onClick = {
                if (note.isBlank()) error = "Catatan wajib diisi"
                else vm.addProgress(v.id, note, newStatus) { progressFor = null; detail = null }
            })
        }
    }

    deleting?.let { v ->
        ConfirmDialog(
            title = "Hapus catatan pelanggaran?",
            message = "Catatan atas nama ${v.name.orEmpty()} akan dihapus permanen.",
            confirmLabel = "Hapus",
            destructive = true,
            loading = busy,
            onDismiss = { deleting = null },
            onConfirm = { vm.delete(v.id) { deleting = null; detail = null } },
        )
    }
}
