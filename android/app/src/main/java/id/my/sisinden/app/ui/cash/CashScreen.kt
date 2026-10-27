package id.my.sisinden.app.ui.cash

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
import androidx.compose.foundation.selection.selectableGroup
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Add
import androidx.compose.material.icons.outlined.ArrowDownward
import androidx.compose.material.icons.outlined.ArrowUpward
import androidx.compose.material.icons.outlined.Delete
import androidx.compose.material.icons.outlined.Edit
import androidx.compose.material.icons.outlined.PictureAsPdf
import androidx.compose.material.icons.outlined.Visibility
import androidx.compose.material.icons.outlined.VisibilityOff
import androidx.compose.material3.ExtendedFloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.SegmentedButton
import androidx.compose.material3.SegmentedButtonDefaults
import androidx.compose.material3.SingleChoiceSegmentedButtonRow
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
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import id.my.sisinden.app.core.network.ApiResult
import id.my.sisinden.app.core.security.Perm
import id.my.sisinden.app.core.security.PermissionSet
import id.my.sisinden.app.core.ui.components.ConfirmDialog
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
import id.my.sisinden.app.core.ui.components.SindenCard
import id.my.sisinden.app.core.ui.components.SindenScaffold
import id.my.sisinden.app.core.ui.components.SindenTextField
import id.my.sisinden.app.core.ui.components.Tone
import id.my.sisinden.app.core.ui.components.toneAccent
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.core.util.Fmt
import id.my.sisinden.app.data.model.CashEntryDto
import id.my.sisinden.app.data.model.CashEntryRequest
import id.my.sisinden.app.data.model.CashLedgerResponse
import id.my.sisinden.app.data.repository.CashKind
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.ui.common.CollectMessages
import id.my.sisinden.app.ui.common.LoadableViewModel
import kotlinx.coroutines.launch
import java.time.LocalDate

class CashViewModel(
    private val repository: SindenRepository,
    private val kind: CashKind,
) : LoadableViewModel<CashLedgerResponse>() {
    init { load() }

    override suspend fun fetch(): ApiResult<CashLedgerResponse> = repository.cashLedger(kind)

    fun save(id: Int?, date: String, description: String, income: Boolean, amount: Double, onDone: () -> Unit) =
        action(if (id == null) "Transaksi tersimpan." else "Transaksi diperbarui.", onDone) {
            repository.saveCash(kind, id, CashEntryRequest(date, description.trim(), if (income) "masuk" else "keluar", amount))
        }

    fun delete(id: Int, onDone: () -> Unit) = action("Transaksi dihapus.", onDone) { repository.deleteCash(kind, id) }
}

private enum class CashFilter(val label: String) { ALL("Semua"), IN("Pemasukan"), OUT("Pengeluaran") }

@Composable
fun CashScreen(
    repository: SindenRepository,
    kind: CashKind,
    permissions: PermissionSet,
    onBack: () -> Unit,
    onOpenPdf: (String, String, String) -> Unit,
) {
    val vm: CashViewModel = viewModel(key = "cash-${kind.name}") { CashViewModel(repository, kind) }
    val state by vm.state.collectAsStateWithLifecycle()
    val refreshing by vm.refreshing.collectAsStateWithLifecycle()
    val busy by vm.busy.collectAsStateWithLifecycle()
    val hide by repository.hideBalanceFlow.collectAsStateWithLifecycle(initialValue = false)
    val scope = rememberCoroutineScope()
    val snackbar = remember { SnackbarHostState() }
    CollectMessages(vm.messages, snackbar)

    val canManage = permissions.has(if (kind == CashKind.BASE) Perm.CASH_MANAGE else Perm.TECHNICAL_CASH_MANAGE)
    val canPdf = kind == CashKind.BASE && permissions.has(Perm.CASH_PDF)

    var filter by rememberSaveable { mutableStateOf(CashFilter.ALL) }
    var editing by remember { mutableStateOf<CashEntryDto?>(null) }
    var creating by rememberSaveable { mutableStateOf(false) }
    var detail by remember { mutableStateOf<CashEntryDto?>(null) }
    var confirmDelete by remember { mutableStateOf<CashEntryDto?>(null) }

    SindenScaffold(
        title = kind.title,
        subtitle = kind.subtitle,
        onBack = onBack,
        snackbarHostState = snackbar,
        actions = {
            IconButton(onClick = { scope.launch { repository.setHideBalance(!hide) } }) {
                Icon(if (hide) Icons.Outlined.VisibilityOff else Icons.Outlined.Visibility, contentDescription = if (hide) "Tampilkan nominal" else "Sembunyikan nominal")
            }
            if (canPdf) {
                IconButton(onClick = { onOpenPdf("api/mobile/pdf/cash", "Laporan Buku Kas", "BUKU_KAS_SINDEN2_${LocalDate.now()}.pdf") }) {
                    Icon(Icons.Outlined.PictureAsPdf, contentDescription = "Laporan PDF")
                }
            }
        },
        floatingActionButton = {
            if (canManage) {
                ExtendedFloatingActionButton(
                    onClick = { creating = true },
                    icon = { Icon(Icons.Outlined.Add, contentDescription = null) },
                    text = { Text("Transaksi") },
                )
            }
        },
    ) { padding ->
        RefreshableBox(refreshing, { vm.load(pullToRefresh = true) }, Modifier.padding(padding)) {
            LoadStateContent(state, onRetry = { vm.load() }) { ledger ->
                val entries = ledger.data.orEmpty().filter {
                    when (filter) {
                        CashFilter.ALL -> true
                        CashFilter.IN -> it.isIncome
                        CashFilter.OUT -> !it.isIncome
                    }
                }
                LazyColumn(
                    contentPadding = PaddingValues(start = Spacing.screen, end = Spacing.screen, bottom = 96.dp),
                    verticalArrangement = Arrangement.spacedBy(Spacing.sm),
                ) {
                    item { BalanceCard(ledger, hide) }
                    item {
                        FilterChipRow(
                            CashFilter.entries, filter, { it.label }, { filter = it },
                            contentPadding = PaddingValues(0.dp),
                            modifier = Modifier.padding(vertical = Spacing.xs),
                        )
                    }
                    if (entries.isEmpty()) {
                        item { EmptyState("Belum ada transaksi", "Transaksi yang tercatat akan tampil di sini.") }
                    } else {
                        items(entries, key = { it.id }) { e ->
                            ListRowCard(
                                title = e.description.orEmpty().ifBlank { "-" },
                                subtitle = Fmt.longDate(e.date),
                                supporting = "Saldo: ${Fmt.rupiah(e.balance, hide)}",
                                leading = {
                                    IconBadge(
                                        if (e.isIncome) Icons.Outlined.ArrowDownward else Icons.Outlined.ArrowUpward,
                                        toneAccent(if (e.isIncome) Tone.SUCCESS else Tone.DANGER),
                                        size = 40.dp, iconSize = 20.dp,
                                    )
                                },
                                trailing = {
                                    Text(
                                        (if (e.isIncome) "+" else "−") + Fmt.rupiah(e.amount, hide).removePrefix("-"),
                                        style = MaterialTheme.typography.titleSmall,
                                        color = toneAccent(if (e.isIncome) Tone.SUCCESS else Tone.DANGER),
                                    )
                                },
                                onClick = { detail = e },
                            )
                        }
                    }
                }
            }
        }
    }

    detail?.let { e ->
        FormSheet(title = "Detail transaksi", onDismiss = { detail = null }) {
            InfoRow("Tanggal", Fmt.longDate(e.date))
            InfoRow("Uraian", e.description)
            InfoRow("Jenis", if (e.isIncome) "Pemasukan (debit)" else "Pengeluaran (kredit)")
            InfoRow("Nominal", Fmt.rupiah(e.amount))
            InfoRow("Saldo setelah", Fmt.rupiah(e.balance))
            if (!e.receiptUrls.isNullOrEmpty()) InfoRow("Nota", "${e.receiptUrls.size} lampiran (lihat di web)")
            if (canManage) {
                Spacer(Modifier.height(Spacing.lg))
                Row(horizontalArrangement = Arrangement.spacedBy(Spacing.sm)) {
                    OutlinedButton(onClick = { editing = e; detail = null }, modifier = Modifier.weight(1f)) {
                        Icon(Icons.Outlined.Edit, contentDescription = null)
                        Spacer(Modifier.width(Spacing.sm))
                        Text("Ubah")
                    }
                    OutlinedButton(onClick = { confirmDelete = e }, modifier = Modifier.weight(1f)) {
                        Icon(Icons.Outlined.Delete, contentDescription = null, tint = MaterialTheme.colorScheme.error)
                        Spacer(Modifier.width(Spacing.sm))
                        Text("Hapus", color = MaterialTheme.colorScheme.error)
                    }
                }
            }
        }
    }

    if (creating || editing != null) {
        CashForm(
            initial = editing,
            busy = busy,
            onDismiss = { creating = false; editing = null },
            onSubmit = { date, desc, income, amount ->
                vm.save(editing?.id, date, desc, income, amount) { creating = false; editing = null }
            },
        )
    }

    confirmDelete?.let { e ->
        ConfirmDialog(
            title = "Hapus transaksi?",
            message = "Transaksi \"${e.description.orEmpty()}\" sebesar ${Fmt.rupiah(e.amount)} akan dihapus dan saldo dihitung ulang.",
            confirmLabel = "Hapus",
            destructive = true,
            loading = busy,
            onDismiss = { confirmDelete = null },
            onConfirm = { vm.delete(e.id) { confirmDelete = null; detail = null } },
        )
    }
}

@Composable
private fun BalanceCard(ledger: CashLedgerResponse, hide: Boolean) {
    SindenCard(Modifier.fillMaxWidth(), containerColor = MaterialTheme.colorScheme.primary, border = null) {
        Text("Saldo saat ini", style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onPrimary.copy(alpha = 0.8f))
        Text(
            Fmt.rupiah(ledger.balance, hide),
            style = MaterialTheme.typography.headlineMedium,
            color = MaterialTheme.colorScheme.onPrimary,
            fontWeight = FontWeight.Bold,
        )
        Spacer(Modifier.height(Spacing.md))
        Row {
            Column(Modifier.weight(1f)) {
                Text("Total pemasukan", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onPrimary.copy(alpha = 0.8f))
                Text(Fmt.rupiah(ledger.totalDebit, hide), style = MaterialTheme.typography.titleSmall, color = MaterialTheme.colorScheme.onPrimary)
            }
            Column(Modifier.weight(1f)) {
                Text("Total pengeluaran", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onPrimary.copy(alpha = 0.8f))
                Text(Fmt.rupiah(ledger.totalCredit, hide), style = MaterialTheme.typography.titleSmall, color = MaterialTheme.colorScheme.onPrimary)
            }
        }
    }
}

@OptIn(androidx.compose.material3.ExperimentalMaterial3Api::class)
@Composable
private fun CashForm(
    initial: CashEntryDto?,
    busy: Boolean,
    onDismiss: () -> Unit,
    onSubmit: (date: String, description: String, income: Boolean, amount: Double) -> Unit,
) {
    var date by rememberSaveable { mutableStateOf(initial?.date?.take(10) ?: LocalDate.now().toString()) }
    var description by rememberSaveable { mutableStateOf(initial?.description.orEmpty()) }
    var income by rememberSaveable { mutableStateOf(initial?.isIncome ?: true) }
    var amountText by rememberSaveable {
        mutableStateOf(initial?.amount?.let { if (it % 1.0 == 0.0) it.toLong().toString() else it.toString().replace('.', ',') }.orEmpty())
    }
    var errors by remember { mutableStateOf(emptyMap<String, String>()) }

    FormSheet(title = if (initial == null) "Transaksi baru" else "Ubah transaksi", onDismiss = onDismiss) {
        SingleChoiceSegmentedButtonRow(Modifier.fillMaxWidth().selectableGroup()) {
            SegmentedButton(
                selected = income,
                onClick = { income = true },
                shape = SegmentedButtonDefaults.itemShape(index = 0, count = 2),
            ) { Text("Pemasukan") }
            SegmentedButton(
                selected = !income,
                onClick = { income = false },
                shape = SegmentedButtonDefaults.itemShape(index = 1, count = 2),
            ) { Text("Pengeluaran") }
        }
        Spacer(Modifier.height(Spacing.md))
        DateField(date, { date = it }, "Tanggal", error = errors["date"])
        SindenTextField(description, { description = it }, "Uraian", error = errors["description"], singleLine = false, minLines = 2)
        SindenTextField(
            amountText, { v -> amountText = v.filter { it.isDigit() || it == ',' || it == '.' } }, "Nominal (Rp)",
            error = errors["amount"], keyboardType = KeyboardType.Decimal,
            supporting = Fmt.parseAmount(amountText)?.let { Fmt.rupiah(it) },
        )
        Spacer(Modifier.height(Spacing.lg))
        PrimaryButton(text = "Simpan", loading = busy, onClick = {
            val amount = Fmt.parseAmount(amountText)
            errors = buildMap {
                if (date.isBlank()) put("date", "Tanggal wajib diisi")
                if (description.isBlank()) put("description", "Uraian wajib diisi")
                if (amount == null || amount <= 0.0) put("amount", "Nominal harus lebih dari 0")
            }
            if (errors.isEmpty() && amount != null) onSubmit(date, description, income, amount)
        })
    }
}
