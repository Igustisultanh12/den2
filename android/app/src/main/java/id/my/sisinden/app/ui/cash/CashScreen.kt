package id.my.sisinden.app.ui.cash

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import id.my.sisinden.app.core.ui.components.*
import id.my.sisinden.app.core.ui.theme.*
import java.text.NumberFormat
import java.util.Locale

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun CashScreen(
    viewModel: CashViewModel,
    onBackClick: () -> Unit
) {
    val uiState by viewModel.cashState.collectAsState()
    var showAddModal by remember { mutableStateOf(false) }
    var selectedFilter by remember { mutableStateOf("semua") } // semua, masuk, keluar
    var selectedTxForEdit by remember { mutableStateOf<CashTransactionDto?>(null) }
    var selectedTxForDelete by remember { mutableStateOf<CashTransactionDto?>(null) }

    val formatter = remember {
        NumberFormat.getCurrencyInstance(Locale("id", "ID")).apply {
            maximumFractionDigits = 0
        }
    }

    Scaffold(
        topBar = {
            SindenTopBar(
                title = "Kas Denintel",
                subtitle = "Pembukuan Kas Satuan Kedinasan",
                onBackClick = onBackClick
            )
        },
        floatingActionButton = {
            FloatingActionButton(
                onClick = { showAddModal = true },
                containerColor = SindenTealPrimary,
                contentColor = Color.White,
                shape = RoundedCornerShape(16.dp)
            ) {
                Icon(Icons.Default.Add, contentDescription = "Tambah Transaksi")
            }
        },
        containerColor = MaterialTheme.colorScheme.background
    ) { paddingValues ->
        Box(modifier = Modifier.fillMaxSize().padding(paddingValues)) {
            when (val state = uiState) {
                is CashUiState.Loading -> {
                    ShimmerLoadingList()
                }
                is CashUiState.Success -> {
                    val filteredTransactions = remember(state.data.transactions, selectedFilter) {
                        when (selectedFilter) {
                            "masuk" -> state.data.transactions.filter { it.isIncome }
                            "keluar" -> state.data.transactions.filter { !it.isIncome }
                            else -> state.data.transactions
                        }
                    }

                    LazyColumn(
                        contentPadding = PaddingValues(16.dp),
                        verticalArrangement = Arrangement.spacedBy(14.dp),
                        modifier = Modifier.fillMaxSize()
                    ) {
                        // 1. KARTU SALDO UTAMA BERGAYA EDITORIAL
                        item {
                            Surface(
                                modifier = Modifier.fillMaxWidth(),
                                shape = RoundedCornerShape(22.dp),
                                shadowElevation = 4.dp
                            ) {
                                Box(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .background(
                                            Brush.linearGradient(
                                                colors = listOf(
                                                    SindenNavyDark,
                                                    SindenNavyMedium,
                                                    SindenNavyLight
                                                )
                                            )
                                        )
                                        .padding(22.dp)
                                ) {
                                    Column {
                                        Text(
                                            text = "TOTAL SALDO BERJALAN",
                                            style = MaterialTheme.typography.labelSmall,
                                            fontWeight = FontWeight.Black,
                                            color = Color.White.copy(alpha = 0.85f),
                                            letterSpacing = 1.sp
                                        )
                                        Spacer(modifier = Modifier.height(4.dp))
                                        Text(
                                            text = formatter.format(state.data.totalBalance),
                                            style = MaterialTheme.typography.headlineLarge,
                                            fontWeight = FontWeight.Black,
                                            color = Color.White
                                        )

                                        Spacer(modifier = Modifier.height(18.dp))

                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.SpaceBetween
                                        ) {
                                            Column {
                                                Text(text = "Pemasukan (+)", style = MaterialTheme.typography.labelSmall, color = Color.White.copy(alpha = 0.75f))
                                                Text(
                                                    text = formatter.format(state.data.totalIncome),
                                                    style = MaterialTheme.typography.bodyMedium,
                                                    fontWeight = FontWeight.Bold,
                                                    color = Color(0xFF67E8F9)
                                                )
                                            }
                                            Column(horizontalAlignment = Alignment.End) {
                                                Text(text = "Pengeluaran (-)", style = MaterialTheme.typography.labelSmall, color = Color.White.copy(alpha = 0.75f))
                                                Text(
                                                    text = formatter.format(state.data.totalExpense),
                                                    style = MaterialTheme.typography.bodyMedium,
                                                    fontWeight = FontWeight.Bold,
                                                    color = Color(0xFFFCA5A5)
                                                )
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        // 2. FILTER PILLS
                        item {
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.spacedBy(8.dp)
                            ) {
                                FilterChip(
                                    selected = selectedFilter == "semua",
                                    onClick = { selectedFilter = "semua" },
                                    label = { Text("Semua (${state.data.transactions.size})") }
                                )
                                FilterChip(
                                    selected = selectedFilter == "masuk",
                                    onClick = { selectedFilter = "masuk" },
                                    label = { Text("Kas Masuk") }
                                )
                                FilterChip(
                                    selected = selectedFilter == "keluar",
                                    onClick = { selectedFilter = "keluar" },
                                    label = { Text("Kas Keluar") }
                                )
                            }
                        }

                        // 3. DAFTAR RIWAYAT TRANSAKSI KAS
                        if (filteredTransactions.isEmpty()) {
                            item {
                                EmptyStateView(
                                    title = "Tidak Ada Transaksi",
                                    message = "Belum ada transaksi kas yang sesuai dengan filter yang dipilih."
                                )
                            }
                        } else {
                            items(filteredTransactions) { tx ->
                                Card(
                                    modifier = Modifier.fillMaxWidth(),
                                    shape = RoundedCornerShape(16.dp),
                                    colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                                    elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
                                ) {
                                    Column(modifier = Modifier.padding(14.dp)) {
                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            verticalAlignment = Alignment.CenterVertically,
                                            horizontalArrangement = Arrangement.spacedBy(12.dp)
                                        ) {
                                            Surface(
                                                color = if (tx.isIncome) Color(0xFFDCFCE7) else Color(0xFFFFE4E6),
                                                shape = RoundedCornerShape(12.dp),
                                                modifier = Modifier.size(42.dp)
                                            ) {
                                                Box(contentAlignment = Alignment.Center) {
                                                    Icon(
                                                        imageVector = if (tx.isIncome) Icons.Default.ArrowDownward else Icons.Default.ArrowUpward,
                                                        contentDescription = null,
                                                        tint = if (tx.isIncome) Color(0xFF16A34A) else Color(0xFFE11D48),
                                                        modifier = Modifier.size(20.dp)
                                                    )
                                                }
                                            }

                                            Column(modifier = Modifier.weight(1f)) {
                                                Text(
                                                    text = tx.description.ifEmpty { "Transaksi Kas" },
                                                    style = MaterialTheme.typography.bodyMedium,
                                                    fontWeight = FontWeight.Bold,
                                                    color = MaterialTheme.colorScheme.onSurface
                                                )
                                                Text(
                                                    text = "${tx.category} • ${tx.date}",
                                                    style = MaterialTheme.typography.labelSmall,
                                                    color = MaterialTheme.colorScheme.onSurfaceVariant
                                                )
                                            }

                                            Column(horizontalAlignment = Alignment.End) {
                                                Text(
                                                    text = "${if (tx.isIncome) "+" else "-"}${formatter.format(tx.effectiveAmount)}",
                                                    style = MaterialTheme.typography.bodyMedium,
                                                    fontWeight = FontWeight.Black,
                                                    color = if (tx.isIncome) Color(0xFF16A34A) else Color(0xFFE11D48)
                                                )
                                                if (tx.balance > 0) {
                                                    Text(
                                                        text = "Sisa: ${formatter.format(tx.balance)}",
                                                        style = MaterialTheme.typography.labelSmall,
                                                        fontSize = 10.sp,
                                                        color = MaterialTheme.colorScheme.onSurfaceVariant
                                                    )
                                                }
                                            }
                                        }

                                        Spacer(modifier = Modifier.height(8.dp))
                                        Divider(color = MaterialTheme.colorScheme.outlineVariant.copy(alpha = 0.3f), thickness = 0.5.dp)
                                        Spacer(modifier = Modifier.height(4.dp))

                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.End,
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            TextButton(
                                                onClick = { selectedTxForEdit = tx },
                                                contentPadding = PaddingValues(horizontal = 8.dp, vertical = 2.dp)
                                            ) {
                                                Icon(Icons.Default.Edit, contentDescription = "Edit", modifier = Modifier.size(15.dp), tint = MaterialTheme.colorScheme.primary)
                                                Spacer(modifier = Modifier.width(4.dp))
                                                Text("Edit", style = MaterialTheme.typography.labelSmall, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.primary)
                                            }

                                            Spacer(modifier = Modifier.width(4.dp))

                                            TextButton(
                                                onClick = { selectedTxForDelete = tx },
                                                contentPadding = PaddingValues(horizontal = 8.dp, vertical = 2.dp)
                                            ) {
                                                Icon(Icons.Default.Delete, contentDescription = "Hapus", modifier = Modifier.size(15.dp), tint = Color(0xFFE11D48))
                                                Spacer(modifier = Modifier.width(4.dp))
                                                Text("Hapus", style = MaterialTheme.typography.labelSmall, fontWeight = FontWeight.Bold, color = Color(0xFFE11D48))
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                is CashUiState.Error -> {
                    ErrorStateView(
                        message = state.message,
                        onRetry = { viewModel.loadCash() }
                    )
                }
            }

            // MODAL FORM TAMBAH TRANSAKSI KAS
            if (showAddModal) {
                ModalBottomSheet(
                    onDismissRequest = { showAddModal = false }
                ) {
                    var txType by remember { mutableStateOf("masuk") }
                    var txAmount by remember { mutableStateOf("") }
                    var txCategory by remember { mutableStateOf("Operasional") }
                    var txDesc by remember { mutableStateOf("") }

                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(24.dp),
                        verticalArrangement = Arrangement.spacedBy(14.dp)
                    ) {
                        Text(
                            text = "Tambah Transaksi Kas Denintel",
                            style = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.Bold
                        )

                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            FilterChip(
                                selected = txType == "masuk",
                                onClick = { txType = "masuk" },
                                label = { Text("Kas Masuk") }
                            )
                            FilterChip(
                                selected = txType == "keluar",
                                onClick = { txType = "keluar" },
                                label = { Text("Kas Keluar") }
                            )
                        }

                        OutlinedTextField(
                            value = txAmount,
                            onValueChange = { txAmount = it },
                            label = { Text("Nominal (Rp) *") },
                            placeholder = { Text("Contoh: 1500000") },
                            singleLine = true,
                            shape = RoundedCornerShape(12.dp),
                            modifier = Modifier.fillMaxWidth()
                        )

                        OutlinedTextField(
                            value = txCategory,
                            onValueChange = { txCategory = it },
                            label = { Text("Kategori") },
                            singleLine = true,
                            shape = RoundedCornerShape(12.dp),
                            modifier = Modifier.fillMaxWidth()
                        )

                        OutlinedTextField(
                            value = txDesc,
                            onValueChange = { txDesc = it },
                            label = { Text("Uraian / Keterangan *") },
                            singleLine = true,
                            shape = RoundedCornerShape(12.dp),
                            modifier = Modifier.fillMaxWidth()
                        )

                        Button(
                            onClick = {
                                val amountVal = txAmount.toDoubleOrNull() ?: 0.0
                                if (amountVal > 0 && txDesc.isNotEmpty()) {
                                    viewModel.addCashTransaction(txType, amountVal, txCategory, txDesc) {
                                        showAddModal = false
                                    }
                                }
                            },
                            shape = RoundedCornerShape(12.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = SindenTealPrimary),
                            modifier = Modifier.fillMaxWidth().height(48.dp)
                        ) {
                            Text("SIMPAN TRANSAKSI", fontWeight = FontWeight.Bold)
                        }
                    }
                }
            }

            // MODAL FORM EDIT TRANSAKSI KAS
            selectedTxForEdit?.let { editingTx ->
                ModalBottomSheet(
                    onDismissRequest = { selectedTxForEdit = null }
                ) {
                    var editType by remember(editingTx) { mutableStateOf(if (editingTx.isIncome) "masuk" else "keluar") }
                    var editAmount by remember(editingTx) { mutableStateOf(editingTx.effectiveAmount.toLong().toString()) }
                    var editCategory by remember(editingTx) { mutableStateOf(editingTx.category) }
                    var editDesc by remember(editingTx) { mutableStateOf(editingTx.description) }

                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(24.dp),
                        verticalArrangement = Arrangement.spacedBy(14.dp)
                    ) {
                        Text(
                            text = "Edit Transaksi Kas Denintel",
                            style = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.Bold
                        )

                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            FilterChip(
                                selected = editType == "masuk",
                                onClick = { editType = "masuk" },
                                label = { Text("Kas Masuk") }
                            )
                            FilterChip(
                                selected = editType == "keluar",
                                onClick = { editType = "keluar" },
                                label = { Text("Kas Keluar") }
                            )
                        }

                        OutlinedTextField(
                            value = editAmount,
                            onValueChange = { editAmount = it },
                            label = { Text("Nominal (Rp) *") },
                            singleLine = true,
                            shape = RoundedCornerShape(12.dp),
                            modifier = Modifier.fillMaxWidth()
                        )

                        OutlinedTextField(
                            value = editCategory,
                            onValueChange = { editCategory = it },
                            label = { Text("Kategori") },
                            singleLine = true,
                            shape = RoundedCornerShape(12.dp),
                            modifier = Modifier.fillMaxWidth()
                        )

                        OutlinedTextField(
                            value = editDesc,
                            onValueChange = { editDesc = it },
                            label = { Text("Uraian / Keterangan *") },
                            singleLine = true,
                            shape = RoundedCornerShape(12.dp),
                            modifier = Modifier.fillMaxWidth()
                        )

                        Button(
                            onClick = {
                                val amountVal = editAmount.toDoubleOrNull() ?: 0.0
                                if (amountVal > 0 && editDesc.isNotEmpty()) {
                                    viewModel.editCashTransaction(editingTx.id, editType, amountVal, editCategory, editDesc) {
                                        selectedTxForEdit = null
                                    }
                                }
                            },
                            shape = RoundedCornerShape(12.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = SindenTealPrimary),
                            modifier = Modifier.fillMaxWidth().height(48.dp)
                        ) {
                            Text("PERBARUI TRANSAKSI", fontWeight = FontWeight.Bold)
                        }
                    }
                }
            }

            // DIALOG KONFIRMASI HAPUS TRANSAKSI KAS
            selectedTxForDelete?.let { deletingTx ->
                AlertDialog(
                    onDismissRequest = { selectedTxForDelete = null },
                    icon = {
                        Icon(
                            imageVector = Icons.Default.DeleteOutline,
                            contentDescription = null,
                            tint = Color(0xFFE11D48),
                            modifier = Modifier.size(36.dp)
                        )
                    },
                    title = {
                        Text(
                            text = "Hapus Transaksi Kas?",
                            fontWeight = FontWeight.Bold,
                            style = MaterialTheme.typography.titleMedium
                        )
                    },
                    text = {
                        Text(
                            text = "Apakah Anda yakin ingin menghapus transaksi '${deletingTx.description}' sebesar ${formatter.format(deletingTx.effectiveAmount)}? Saldo berjalan akan otomatis direkalkulasi secara kronologis.",
                            style = MaterialTheme.typography.bodyMedium
                        )
                    },
                    confirmButton = {
                        Button(
                            onClick = {
                                viewModel.deleteCashTransaction(deletingTx.id) {
                                    selectedTxForDelete = null
                                }
                            },
                            colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFE11D48))
                        ) {
                            Text("HAPUS", fontWeight = FontWeight.Bold)
                        }
                    },
                    dismissButton = {
                        TextButton(onClick = { selectedTxForDelete = null }) {
                            Text("BATAL")
                        }
                    }
                )
            }
        }
    }
}
