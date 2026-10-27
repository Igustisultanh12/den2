package id.my.sisinden.app.ui.commander

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowLeft
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowRight
import androidx.compose.material.icons.outlined.Add
import androidx.compose.material.icons.outlined.ArrowDownward
import androidx.compose.material.icons.outlined.ArrowUpward
import androidx.compose.material.icons.outlined.Delete
import androidx.compose.material.icons.outlined.Edit
import androidx.compose.material.icons.outlined.PictureAsPdf
import androidx.compose.material3.ExtendedFloatingActionButton
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
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
import id.my.sisinden.app.core.ui.components.DateField
import id.my.sisinden.app.core.ui.components.EmptyState
import id.my.sisinden.app.core.ui.components.FormSheet
import id.my.sisinden.app.core.ui.components.IconBadge
import id.my.sisinden.app.core.ui.components.ListRowCard
import id.my.sisinden.app.core.ui.components.LoadStateContent
import id.my.sisinden.app.core.ui.components.PrimaryButton
import id.my.sisinden.app.core.ui.components.RefreshableBox
import id.my.sisinden.app.core.ui.components.SindenCard
import id.my.sisinden.app.core.ui.components.SindenScaffold
import id.my.sisinden.app.core.ui.components.SindenTextField
import id.my.sisinden.app.core.ui.components.Tone
import id.my.sisinden.app.core.ui.components.toneAccent
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.core.util.Fmt
import id.my.sisinden.app.data.model.CommanderAccountResponse
import id.my.sisinden.app.data.model.CommanderTxDto
import id.my.sisinden.app.data.model.CommanderTxRequest
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.ui.common.CollectMessages
import id.my.sisinden.app.ui.common.LoadableViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import java.time.LocalDate
import java.time.YearMonth

class CommanderViewModel(private val repository: SindenRepository) : LoadableViewModel<CommanderAccountResponse>() {
    /** null = semua periode. */
    val month = MutableStateFlow<YearMonth?>(YearMonth.now())

    init { load() }

    override suspend fun fetch(): ApiResult<CommanderAccountResponse> =
        repository.commanderAccount(month.value?.toString())

    fun setMonth(ym: YearMonth?) {
        month.value = ym
        reload()
    }

    /** Tambah ([id] null) atau ubah mutasi. */
    fun save(id: Int?, date: String, income: Boolean, amount: Double, note: String, onDone: () -> Unit) {
        val request = CommanderTxRequest(date, if (income) "MASUK" else "KELUAR", amount, note.trim())
        action(if (id == null) "Transaksi tersimpan." else "Transaksi diperbarui.", onDone) {
            if (id == null) repository.createCommanderTx(request) else repository.updateCommanderTx(id, request)
        }
    }

    fun delete(id: Int, onDone: () -> Unit) = action("Transaksi dihapus.", onDone) { repository.deleteCommanderTx(id) }
}

@Composable
fun CommanderScreen(
    repository: SindenRepository,
    permissions: PermissionSet,
    onBack: () -> Unit,
    onOpenPdf: (String, String, String) -> Unit,
) {
    val vm: CommanderViewModel = viewModel { CommanderViewModel(repository) }
    val state by vm.state.collectAsStateWithLifecycle()
    val refreshing by vm.refreshing.collectAsStateWithLifecycle()
    val busy by vm.busy.collectAsStateWithLifecycle()
    val month by vm.month.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }
    CollectMessages(vm.messages, snackbar)
    val canManage = permissions.has(Perm.COMMANDER_MANAGE)
    /** null = form tertutup; id 0 = transaksi baru. */
    var editing by remember { mutableStateOf<CommanderTxDto?>(null) }
    var confirmDelete by remember { mutableStateOf<CommanderTxDto?>(null) }

    SindenScaffold(
        title = "Rekening Komandan",
        subtitle = month?.let { Fmt.monthYear(it) } ?: "Semua periode",
        onBack = onBack,
        snackbarHostState = snackbar,
        actions = {
            if (permissions.has(Perm.COMMANDER_PDF)) {
                IconButton(onClick = {
                    val q = month?.let { "?month=$it" } ?: ""
                    onOpenPdf("api/mobile/pdf/commander-account$q", "Rekening Koran Komandan", "REKENING_KOMANDAN_${month ?: LocalDate.now()}.pdf")
                }) { Icon(Icons.Outlined.PictureAsPdf, contentDescription = "Rekening koran PDF") }
            }
        },
        floatingActionButton = {
            if (canManage) {
                ExtendedFloatingActionButton(
                    onClick = { editing = CommanderTxDto() },
                    icon = { Icon(Icons.Outlined.Add, contentDescription = null) },
                    text = { Text("Transaksi") },
                )
            }
        },
    ) { padding ->
        Column(Modifier.padding(padding)) {
            Row(Modifier.fillMaxWidth().padding(horizontal = Spacing.sm), verticalAlignment = Alignment.CenterVertically) {
                IconButton(onClick = { vm.setMonth((month ?: YearMonth.now()).minusMonths(1)) }) {
                    Icon(Icons.AutoMirrored.Filled.KeyboardArrowLeft, contentDescription = "Bulan sebelumnya")
                }
                Text(
                    month?.let { Fmt.monthYear(it) } ?: "Semua periode",
                    style = MaterialTheme.typography.titleMedium,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.weight(1f),
                )
                IconButton(onClick = { vm.setMonth((month ?: YearMonth.now()).plusMonths(1)) }) {
                    Icon(Icons.AutoMirrored.Filled.KeyboardArrowRight, contentDescription = "Bulan berikutnya")
                }
                FilterChip(selected = month == null, onClick = { vm.setMonth(if (month == null) YearMonth.now() else null) }, label = { Text("Semua") })
            }
            RefreshableBox(refreshing, { vm.load(pullToRefresh = true) }) {
                LoadStateContent(state, onRetry = { vm.load() }) { data ->
                    LazyColumn(
                        contentPadding = PaddingValues(start = Spacing.screen, end = Spacing.screen, bottom = 96.dp),
                        verticalArrangement = Arrangement.spacedBy(Spacing.sm),
                    ) {
                        item {
                            SindenCard(Modifier.fillMaxWidth(), containerColor = MaterialTheme.colorScheme.primary, border = null) {
                                Text("Saldo akhir", style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onPrimary.copy(alpha = 0.8f))
                                Text(Fmt.rupiah(data.saldoAkhir), style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onPrimary)
                                Spacer(Modifier.height(Spacing.sm))
                                Row {
                                    Column(Modifier.weight(1f)) {
                                        Text("Total masuk", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onPrimary.copy(alpha = 0.8f))
                                        Text(Fmt.rupiah(data.totalMasuk), style = MaterialTheme.typography.titleSmall, color = MaterialTheme.colorScheme.onPrimary)
                                    }
                                    Column(Modifier.weight(1f)) {
                                        Text("Total keluar", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onPrimary.copy(alpha = 0.8f))
                                        Text(Fmt.rupiah(data.totalKeluar), style = MaterialTheme.typography.titleSmall, color = MaterialTheme.colorScheme.onPrimary)
                                    }
                                }
                            }
                        }
                        val list = data.data.orEmpty()
                        if (list.isEmpty()) {
                            item { EmptyState("Tidak ada transaksi", "Belum ada mutasi rekening pada periode ini.") }
                        } else {
                            items(list, key = { it.id }) { tx ->
                                val tone = if (tx.isIncome) Tone.SUCCESS else Tone.DANGER
                                ListRowCard(
                                    title = tx.keterangan.orEmpty().ifBlank { "-" },
                                    subtitle = Fmt.longDate(tx.dateOnly),
                                    supporting = tx.petugasInput?.let { "Dicatat oleh $it" },
                                    onClick = if (canManage) ({ editing = tx }) else null,
                                    leading = {
                                        IconBadge(
                                            if (tx.isIncome) Icons.Outlined.ArrowDownward else Icons.Outlined.ArrowUpward,
                                            toneAccent(tone), size = 40.dp, iconSize = 20.dp,
                                        )
                                    },
                                    trailing = {
                                        Text(
                                            (if (tx.isIncome) "+" else "−") + Fmt.rupiah(tx.jumlah),
                                            style = MaterialTheme.typography.titleSmall,
                                            color = toneAccent(tone),
                                        )
                                        if (canManage) {
                                            IconButton(onClick = { editing = tx }) {
                                                Icon(Icons.Outlined.Edit, contentDescription = "Ubah transaksi", tint = MaterialTheme.colorScheme.onSurfaceVariant)
                                            }
                                            IconButton(onClick = { confirmDelete = tx }) {
                                                Icon(Icons.Outlined.Delete, contentDescription = "Hapus transaksi", tint = MaterialTheme.colorScheme.onSurfaceVariant)
                                            }
                                        }
                                    },
                                )
                            }
                        }
                    }
                }
            }
        }
    }

    editing?.let { tx ->
        val isNew = tx.id <= 0
        var date by rememberSaveable(tx.id) { mutableStateOf(tx.dateOnly ?: LocalDate.now().toString()) }
        var income by rememberSaveable(tx.id) { mutableStateOf(isNew || tx.isIncome) }
        var amount by rememberSaveable(tx.id) {
            mutableStateOf(if (isNew) "" else java.math.BigDecimal.valueOf(tx.jumlah).stripTrailingZeros().toPlainString())
        }
        var note by rememberSaveable(tx.id) { mutableStateOf(tx.keterangan.orEmpty()) }
        var errors by remember(tx.id) { mutableStateOf(emptyMap<String, String>()) }
        FormSheet(title = if (isNew) "Transaksi rekening" else "Ubah transaksi", onDismiss = { editing = null }) {
            Row(horizontalArrangement = Arrangement.spacedBy(Spacing.sm)) {
                FilterChip(selected = income, onClick = { income = true }, label = { Text("Masuk") })
                FilterChip(selected = !income, onClick = { income = false }, label = { Text("Keluar") })
            }
            DateField(date, { date = it }, "Tanggal")
            SindenTextField(
                amount, { v -> amount = v.filter { it.isDigit() || it == ',' || it == '.' } }, "Jumlah (Rp)",
                error = errors["amount"], keyboardType = KeyboardType.Decimal,
                supporting = Fmt.parseAmount(amount)?.let { Fmt.rupiah(it) },
            )
            SindenTextField(note, { note = it }, "Keterangan", error = errors["note"], singleLine = false, minLines = 2)
            if (!isNew) {
                Text(
                    "Perubahan dicatat atas nama Anda dengan tanda (EDIT), sama seperti di web.",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            Spacer(Modifier.height(Spacing.lg))
            PrimaryButton(if (isNew) "Simpan" else "Simpan perubahan", loading = busy, onClick = {
                val value = Fmt.parseAmount(amount)
                errors = buildMap {
                    if (value == null || value < 1.0) put("amount", "Jumlah minimal Rp1")
                    if (note.isBlank()) put("note", "Keterangan wajib diisi")
                }
                if (errors.isEmpty() && value != null) vm.save(tx.id.takeIf { it > 0 }, date, income, value, note) { editing = null }
            })
        }
    }

    confirmDelete?.let { tx ->
        ConfirmDialog(
            title = "Hapus transaksi?",
            message = "Mutasi \"${tx.keterangan.orEmpty()}\" sebesar ${Fmt.rupiah(tx.jumlah)} akan dihapus.",
            confirmLabel = "Hapus",
            destructive = true,
            loading = busy,
            onDismiss = { confirmDelete = null },
            onConfirm = { vm.delete(tx.id) { confirmDelete = null } },
        )
    }
}
