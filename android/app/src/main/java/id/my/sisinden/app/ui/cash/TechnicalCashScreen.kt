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
import id.my.sisinden.app.data.model.TechnicalCashTransactionDto
import java.text.NumberFormat
import java.util.Locale

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TechnicalCashScreen(
    viewModel: CashViewModel,
    onBackClick: () -> Unit
) {
    val uiState by viewModel.technicalCashState.collectAsState()
    var showAddModal by remember { mutableStateOf(false) }
    var selectedFilter by remember { mutableStateOf("semua") } // semua, masuk, keluar
    var selectedTxForEdit by remember { mutableStateOf<TechnicalCashTransactionDto?>(null) }
    var selectedTxForDelete by remember { mutableStateOf<TechnicalCashTransactionDto?>(null) }

    val formatter = remember {
        NumberFormat.getCurrencyInstance(Locale("id", "ID")).apply {
            maximumFractionDigits = 0
        }
    }

    Scaffold(
        topBar = {
            SindenTopBar(
                title = "Kas Taktis Operasi",
                subtitle = "Dana Taktis Intelijen Lapangan",
                onBackClick = onBackClick
            )
        },
        floatingActionButton = {
            FloatingActionButton(
                onClick = { showAddModal = true },
                containerColor = SindenOceanPrimary,
                contentColor = Color.White,
                shape = RoundedCornerShape(16.dp)
            ) {
                Icon(Icons.Default.Add, contentDescription = "Catat Kas Taktis")
            }
        },
        containerColor = MaterialTheme.colorScheme.background
    ) { paddingValues ->
        Box(modifier = Modifier.fillMaxSize().padding(paddingValues)) {
            when (val state = uiState) {
                is TechnicalCashUiState.Loading -> {
                    ShimmerLoadingList()
                }
                is TechnicalCashUiState.Success -> {
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
                        // KARTU SALDO GRADIENT PREMIUM
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
                                                    SindenOceanPrimary
                                                )
                                            )
                                        )
                                        .padding(22.dp)
                                ) {
                                    Column {
                                        Text(
                                            text = "SALDO KAS TAKTIS AKTIF",
                                            style = MaterialTheme.typography.labelSmall,
                                            fontWeight = FontWeight.Black,
                                            color = Color.White.copy(alpha = 0.85f),
                                            letterSpacing = 1.sp
                                        )
                                        Spacer(modifier = Modifier.height(4.dp))
                                        Text(
                                            text = formatter.format(state.data.balance),
                                            style = MaterialTheme.typography.headlineLarge,
                                            fontWeight = FontWeight.Black,
                                            color = Color.White
                                        )

                                        Spacer(modifier = Modifier.height(14.dp))

                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.spacedBy(12.dp)
                                        ) {
                                            Surface(
                                                shape = RoundedCornerShape(12.dp),
                                                color = Color.White.copy(alpha = 0.15f),
                                                modifier = Modifier.weight(1f)
                                            ) {
                                                Column(modifier = Modifier.padding(10.dp)) {
                                                    Text("Total Masuk", style = MaterialTheme.typography.labelSmall, color = Color.White.copy(alpha = 0.8f), fontSize = 10.sp)
                                                    Text(formatter.format(state.data.totalDebit), style = MaterialTheme.typography.bodySmall, fontWeight = FontWeight.Bold, color = Color.White)
                                                }
                                            }

                                            Surface(
                                                shape = RoundedCornerShape(12.dp),
                                                color = Color.White.copy(alpha = 0.15f),
                                                modifier = Modifier.weight(1f)
                                            ) {
                                                Column(modifier = Modifier.padding(10.dp)) {
                                                    Text("Total Keluar", style = MaterialTheme.typography.labelSmall, color = Color.White.copy(alpha = 0.8f), fontSize = 10.sp)
                                                    Text(formatter.format(state.data.totalCredit), style = MaterialTheme.typography.bodySmall, fontWeight = FontWeight.Bold, color = Color.White)
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        // FILTER CHIPS
                        item {
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.spacedBy(8.dp)
                            ) {
                                FilterChip(
                                    selected = selectedFilter == "semua",
                                    onClick = { selectedFilter = "semua" },
                                    label = { Text("Semua") }
                                )
                                FilterChip(
                                    selected = selectedFilter == "masuk",
                                    onClick = { selectedFilter = "masuk" },
                                    label = { Text("Penerimaan") }
                                )
                                FilterChip(
                                    selected = selectedFilter == "keluar",
                                    onClick = { selectedFilter = "keluar" },
                                    label = { Text("Pengeluaran") }
                                )
                            }
                        }

                        if (filteredTransactions.isEmpty()) {
                            item {
                                EmptyStateView(
                                    title = "Belum Ada Catatan Taktis",
                                    message = "Belum ada transaksi dana taktis operasional yang cocok dengan filter ini.",
                                    icon = Icons.Default.Security
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
                                                    text = tx.description.ifEmpty { "Operasi Taktis" },
                                                    style = MaterialTheme.typography.bodyMedium,
                                                    fontWeight = FontWeight.Bold,
                                                    color = MaterialTheme.colorScheme.onSurface
                                                )
                                                Text(
                                                    text = tx.date,
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
                is TechnicalCashUiState.Error -> {
                    ErrorStateView(
                        message = state.message,
                        onRetry = { viewModel.loadTechnicalCash() }
                    )
                }
            }

            // MODAL FORM TAMBAH TRANSAKSI KAS TAKTIS
            if (showAddModal) {
                ModalBottomSheet(onDismissRequest = { showAddModal = false }) {
                    var txType by remember { mutableStateOf("keluar") }
                    var amount by remember { mutableStateOf("") }
                    var desc by remember { mutableStateOf("") }

                    Column(
                        modifier = Modifier.fillMaxWidth().padding(24.dp),
                        verticalArrangement = Arrangement.spacedBy(14.dp)
                    ) {
                        Text(
                            text = "Catat Dana Taktis Operasi",
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
                            value = amount,
                            onValueChange = { amount = it },
                            label = { Text("Nominal Dana (Rp) *") },
                            placeholder = { Text("Contoh: 2500000") },
                            singleLine = true,
                            shape = RoundedCornerShape(12.dp),
                            modifier = Modifier.fillMaxWidth()
                        )

                        OutlinedTextField(
                            value = desc,
                            onValueChange = { desc = it },
                            label = { Text("Uraian Operasi Lapangan *") },
                            minLines = 2,
                            shape = RoundedCornerShape(12.dp),
                            modifier = Modifier.fillMaxWidth()
                        )

                        Button(
                            onClick = {
                                val amountVal = amount.toDoubleOrNull() ?: 0.0
                                if (amountVal > 0 && desc.isNotEmpty()) {
                                    viewModel.addTechnicalCashTransaction(txType, amountVal, desc) {
                                        showAddModal = false
                                    }
                                }
                            },
                            shape = RoundedCornerShape(12.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = SindenOceanPrimary),
                            modifier = Modifier.fillMaxWidth().height(48.dp)
                        ) {
                            Text("SIMPAN CATATAN TAKTIS", fontWeight = FontWeight.Bold)
                        }
                    }
                }
            }

            // MODAL FORM EDIT TRANSAKSI KAS TAKTIS
            val editingTx = selectedTxForEdit
            if (editingTx != null) {
                ModalBottomSheet(onDismissRequest = { selectedTxForEdit = null }) {
                    var editType by remember(editingTx) { mutableStateOf(if (editingTx.isIncome) "masuk" else "keluar") }
                    var editAmount by remember(editingTx) { mutableStateOf(editingTx.effectiveAmount.toLong().toString()) }
                    var editDesc by remember(editingTx) { mutableStateOf(editingTx.description) }

                    Column(
                        modifier = Modifier.fillMaxWidth().padding(24.dp),
                        verticalArrangement = Arrangement.spacedBy(14.dp)
                    ) {
                        Text(
                            text = "Edit Catatan Kas Taktis",
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
                            label = { Text("Nominal Dana (Rp) *") },
                            singleLine = true,
                            shape = RoundedCornerShape(12.dp),
                            modifier = Modifier.fillMaxWidth()
                        )

                        OutlinedTextField(
                            value = editDesc,
                            onValueChange = { editDesc = it },
                            label = { Text("Uraian Operasi Lapangan *") },
                            minLines = 2,
                            shape = RoundedCornerShape(12.dp),
                            modifier = Modifier.fillMaxWidth()
                        )

                        Button(
                            onClick = {
                                val amountVal = editAmount.toDoubleOrNull() ?: 0.0
                                if (amountVal > 0 && editDesc.isNotEmpty()) {
                                    viewModel.editTechnicalCashTransaction(editingTx.id, editType, amountVal, editDesc) {
                                        selectedTxForEdit = null
                                    }
                                }
                            },
                            shape = RoundedCornerShape(12.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = SindenOceanPrimary),
                            modifier = Modifier.fillMaxWidth().height(48.dp)
                        ) {
                            Text("PERBARUI CATATAN TAKTIS", fontWeight = FontWeight.Bold)
                        }
                    }
                }
            }

            // DIALOG KONFIRMASI HAPUS TRANSAKSI KAS TAKTIS
            val deletingTx = selectedTxForDelete
            if (deletingTx != null) {
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
                            text = "Hapus Catatan Kas Taktis?",
                            fontWeight = FontWeight.Bold,
                            style = MaterialTheme.typography.titleMedium
                        )
                    },
                    text = {
                        Text(
                            text = "Apakah Anda yakin ingin menghapus catatan '${deletingTx.description}' sebesar ${formatter.format(deletingTx.effectiveAmount)}? Saldo taktikal akan direkalkulasi secara kronologis.",
                            style = MaterialTheme.typography.bodyMedium
                        )
                    },
                    confirmButton = {
                        Button(
                            onClick = {
                                viewModel.deleteTechnicalCashTransaction(deletingTx.id) {
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
