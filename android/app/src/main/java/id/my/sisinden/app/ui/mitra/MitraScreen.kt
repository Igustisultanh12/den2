package id.my.sisinden.app.ui.mitra

import androidx.compose.foundation.BorderStroke
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
import androidx.compose.material.icons.outlined.Add
import androidx.compose.material.icons.outlined.Delete
import androidx.compose.material.icons.outlined.Edit
import androidx.compose.material.icons.outlined.People
import androidx.compose.material3.ExtendedFloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import id.my.sisinden.app.core.network.ApiResult
import id.my.sisinden.app.core.security.Perm
import id.my.sisinden.app.core.security.PermissionSet
import id.my.sisinden.app.core.ui.components.ConfirmDialog
import id.my.sisinden.app.core.ui.components.EmptyState
import id.my.sisinden.app.core.ui.components.FormSheet
import id.my.sisinden.app.core.ui.components.LoadStateContent
import id.my.sisinden.app.core.ui.components.PrimaryButton
import id.my.sisinden.app.core.ui.components.RefreshableBox
import id.my.sisinden.app.core.ui.components.SearchField
import id.my.sisinden.app.core.ui.components.SindenCard
import id.my.sisinden.app.core.ui.components.SindenScaffold
import id.my.sisinden.app.core.ui.components.SindenTextField
import id.my.sisinden.app.core.ui.components.StatusChip
import id.my.sisinden.app.core.ui.components.Tone
import id.my.sisinden.app.core.ui.theme.SindenTheme
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.core.util.Fmt
import id.my.sisinden.app.data.model.MitraDto
import id.my.sisinden.app.data.model.MitraRequest
import id.my.sisinden.app.data.model.MitraResponse
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.ui.common.CollectMessages
import id.my.sisinden.app.ui.common.LoadableViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import java.time.LocalDate

class MitraViewModel(private val repository: SindenRepository) : LoadableViewModel<MitraResponse>() {
    val year = MutableStateFlow(LocalDate.now().year)

    init { load() }

    override suspend fun fetch(): ApiResult<MitraResponse> = repository.mitra(year.value)

    fun shiftYear(delta: Int) {
        year.value += delta
        reload()
    }

    fun toggle(mitra: MitraDto, month: Int) = action(
        "Pembayaran ${Fmt.monthShort(month)} ${mitra.nama.orEmpty()} diperbarui.",
    ) { repository.toggleMitraPayment(mitra.id, year.value, month) }

    fun save(id: Int?, nama: String, pt: String, phone: String, note: String, onDone: () -> Unit) =
        action(if (id == null) "Mitra ditambahkan." else "Data mitra diperbarui.", onDone) {
            repository.saveMitra(id, MitraRequest(nama.trim(), pt.trim().ifBlank { null }, phone.trim().ifBlank { null }, note.trim().ifBlank { null }))
        }

    fun delete(id: Int, onDone: () -> Unit) = action("Mitra dihapus.", onDone) { repository.deleteMitra(id) }
}

@Composable
fun MitraScreen(repository: SindenRepository, permissions: PermissionSet, onBack: () -> Unit) {
    val vm: MitraViewModel = viewModel { MitraViewModel(repository) }
    val state by vm.state.collectAsStateWithLifecycle()
    val refreshing by vm.refreshing.collectAsStateWithLifecycle()
    val busy by vm.busy.collectAsStateWithLifecycle()
    val year by vm.year.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }
    CollectMessages(vm.messages, snackbar)
    val canManage = permissions.has(Perm.MITRA_MANAGE)

    var query by rememberSaveable { mutableStateOf("") }
    var editing by remember { mutableStateOf<MitraDto?>(null) }
    var creating by rememberSaveable { mutableStateOf(false) }
    var confirmDelete by remember { mutableStateOf<MitraDto?>(null) }

    SindenScaffold(
        title = "Mitra",
        subtitle = "Matriks pembayaran tahun $year",
        onBack = onBack,
        snackbarHostState = snackbar,
        floatingActionButton = {
            if (canManage) {
                ExtendedFloatingActionButton(
                    onClick = { creating = true },
                    icon = { Icon(Icons.Outlined.Add, contentDescription = null) },
                    text = { Text("Mitra") },
                )
            }
        },
    ) { padding ->
        Column(Modifier.padding(padding)) {
            Row(Modifier.fillMaxWidth().padding(horizontal = Spacing.sm), verticalAlignment = Alignment.CenterVertically) {
                IconButton(onClick = { vm.shiftYear(-1) }) { Icon(Icons.AutoMirrored.Filled.KeyboardArrowLeft, contentDescription = "Tahun sebelumnya") }
                Text("Tahun $year", style = MaterialTheme.typography.titleMedium, textAlign = TextAlign.Center, modifier = Modifier.weight(1f))
                IconButton(onClick = { vm.shiftYear(1) }) { Icon(Icons.AutoMirrored.Filled.KeyboardArrowRight, contentDescription = "Tahun berikutnya") }
            }
            SearchField(query, { query = it }, "Cari nama mitra atau perusahaan", Modifier.padding(horizontal = Spacing.screen))
            Spacer(Modifier.height(Spacing.sm))
            RefreshableBox(refreshing, { vm.load(pullToRefresh = true) }) {
                LoadStateContent(state, onRetry = { vm.load() }) { data ->
                    val q = query.trim().lowercase()
                    val list = data.data.orEmpty().filter {
                        q.isEmpty() || it.nama.orEmpty().lowercase().contains(q) || it.pt.orEmpty().lowercase().contains(q)
                    }
                    if (list.isEmpty()) {
                        EmptyState("Belum ada mitra", "Data mitra yang terdaftar akan tampil di sini.", icon = Icons.Outlined.People)
                    } else {
                        LazyColumn(
                            contentPadding = PaddingValues(start = Spacing.screen, end = Spacing.screen, bottom = 96.dp),
                            verticalArrangement = Arrangement.spacedBy(Spacing.sm),
                        ) {
                            items(list, key = { it.id }) { mitra ->
                                MitraCard(
                                    mitra = mitra,
                                    year = year,
                                    canManage = canManage,
                                    busy = busy,
                                    onToggle = { m -> vm.toggle(mitra, m) },
                                    onEdit = { editing = mitra },
                                    onDelete = { confirmDelete = mitra },
                                )
                            }
                        }
                    }
                }
            }
        }
    }

    if (creating || editing != null) {
        val initial = editing
        var nama by rememberSaveable(initial?.id) { mutableStateOf(initial?.nama.orEmpty()) }
        var pt by rememberSaveable(initial?.id) { mutableStateOf(initial?.pt.orEmpty()) }
        var phone by rememberSaveable(initial?.id) { mutableStateOf(initial?.noTlp.orEmpty()) }
        var note by rememberSaveable(initial?.id) { mutableStateOf(initial?.keterangan.orEmpty()) }
        var error by remember { mutableStateOf<String?>(null) }
        FormSheet(title = if (initial == null) "Mitra baru" else "Ubah mitra", onDismiss = { creating = false; editing = null }) {
            SindenTextField(nama, { nama = it; error = null }, "Nama mitra", error = error)
            SindenTextField(pt, { pt = it }, "Perusahaan / sektor")
            SindenTextField(phone, { phone = it }, "No. telepon", keyboardType = KeyboardType.Phone)
            SindenTextField(note, { note = it }, "Keterangan", singleLine = false, minLines = 2)
            Spacer(Modifier.height(Spacing.lg))
            PrimaryButton("Simpan", loading = busy, onClick = {
                if (nama.isBlank()) error = "Nama mitra wajib diisi"
                else vm.save(initial?.id, nama, pt, phone, note) { creating = false; editing = null }
            })
        }
    }

    confirmDelete?.let { m ->
        ConfirmDialog(
            title = "Hapus mitra?",
            message = "Mitra \"${m.nama.orEmpty()}\" beserta riwayat pembayarannya akan dihapus.",
            confirmLabel = "Hapus",
            destructive = true,
            loading = busy,
            onDismiss = { confirmDelete = null },
            onConfirm = { vm.delete(m.id) { confirmDelete = null } },
        )
    }
}

@Composable
private fun MitraCard(
    mitra: MitraDto,
    year: Int,
    canManage: Boolean,
    busy: Boolean,
    onToggle: (Int) -> Unit,
    onEdit: () -> Unit,
    onDelete: () -> Unit,
) {
    val now = LocalDate.now()
    val currentMonth = if (now.year == year) now.monthValue else null
    SindenCard(Modifier.fillMaxWidth(), contentPadding = Spacing.md) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f)) {
                Text(mitra.nama.orEmpty().ifBlank { "-" }, style = MaterialTheme.typography.titleSmall)
                Text(
                    listOfNotNull(mitra.pt?.takeIf { it.isNotBlank() }, mitra.noTlp?.takeIf { it.isNotBlank() }).joinToString(" · ").ifEmpty { "-" },
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            if (currentMonth != null) {
                StatusChip(if (mitra.isPaid(currentMonth)) "Lunas bulan ini" else "Belum lunas", if (mitra.isPaid(currentMonth)) Tone.SUCCESS else Tone.WARNING)
            }
            if (canManage) {
                IconButton(onClick = onEdit) { Icon(Icons.Outlined.Edit, contentDescription = "Ubah ${mitra.nama.orEmpty()}") }
                IconButton(onClick = onDelete) { Icon(Icons.Outlined.Delete, contentDescription = "Hapus ${mitra.nama.orEmpty()}") }
            }
        }
        Spacer(Modifier.height(Spacing.sm))
        Text("${mitra.paidCount}/12 bulan lunas", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
        Spacer(Modifier.height(Spacing.xs))
        (1..12).chunked(6).forEach { row ->
            Row(Modifier.fillMaxWidth().padding(vertical = 2.dp), horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                row.forEach { m ->
                    MonthCell(
                        month = m,
                        paid = mitra.isPaid(m),
                        current = m == currentMonth,
                        enabled = canManage && !busy,
                        onClick = { onToggle(m) },
                        modifier = Modifier.weight(1f),
                    )
                }
            }
        }
    }
}

@Composable
private fun MonthCell(month: Int, paid: Boolean, current: Boolean, enabled: Boolean, onClick: () -> Unit, modifier: Modifier) {
    val status = SindenTheme.status
    val label = Fmt.monthShort(month)
    Surface(
        onClick = onClick,
        enabled = enabled,
        modifier = modifier
            .height(40.dp)
            .semantics { contentDescription = "$label: ${if (paid) "lunas" else "belum lunas"}" },
        shape = MaterialTheme.shapes.extraSmall,
        color = if (paid) status.successContainer else MaterialTheme.colorScheme.surfaceVariant,
        border = if (current) BorderStroke(1.5.dp, MaterialTheme.colorScheme.primary) else null,
    ) {
        Column(Modifier.fillMaxSize(), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.Center) {
            Text(
                label,
                style = MaterialTheme.typography.labelSmall,
                fontWeight = if (paid) FontWeight.Bold else FontWeight.Normal,
                color = if (paid) status.onSuccessContainer else MaterialTheme.colorScheme.onSurfaceVariant,
            )
            Text(if (paid) "✓" else "–", style = MaterialTheme.typography.labelSmall, color = if (paid) status.success else MaterialTheme.colorScheme.outline)
        }
    }
}
