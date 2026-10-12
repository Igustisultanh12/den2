package id.my.sisinden.app.ui.cash

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.ArrowDownward
import androidx.compose.material.icons.filled.ArrowUpward
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
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

    val formatter = remember {
        NumberFormat.getCurrencyInstance(Locale("id", "ID")).apply {
            maximumFractionDigits = 0
        }
    }

    Scaffold(
        topBar = {
            SindenTopBar(
                title = "Kas Denintel",
                subtitle = "Pembukuan Keuangan Kedinasan",
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
                    LazyColumn(
                        contentPadding = PaddingValues(16.dp),
                        verticalArrangement = Arrangement.spacedBy(14.dp),
                        modifier = Modifier.fillMaxSize()
                    ) {
                        // Kartu Saldo Utama
                        item {
                            ElevatedCard(
                                modifier = Modifier.fillMaxWidth(),
                                shape = RoundedCornerShape(20.dp),
                                colors = CardDefaults.elevatedCardColors(containerColor = SindenTealPrimary)
                            ) {
                                Column(modifier = Modifier.padding(20.dp)) {
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

                                    Spacer(modifier = Modifier.height(16.dp))

                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween
                                    ) {
                                        Column {
                                            Text(text = "Pemasukan", style = MaterialTheme.typography.labelSmall, color = Color.White.copy(alpha = 0.75f))
                                            Text(text = formatter.format(state.data.totalIncome), style = MaterialTheme.typography.bodyMedium, fontWeight = FontWeight.Bold, color = Color.White)
                                        }
                                        Column(horizontalAlignment = Alignment.End) {
                                            Text(text = "Pengeluaran", style = MaterialTheme.typography.labelSmall, color = Color.White.copy(alpha = 0.75f))
                                            Text(text = formatter.format(state.data.totalExpense), style = MaterialTheme.typography.bodyMedium, fontWeight = FontWeight.Bold, color = Color.White)
                                        }
                                    }
                                }
                            }
                        }

                        item {
                            Text(
                                text = "RIWAYAT TRANSAKSI KAS",
                                style = MaterialTheme.typography.labelSmall,
                                fontWeight = FontWeight.Black,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                                letterSpacing = 1.sp
                            )
                        }

                        if (state.data.transactions.isEmpty()) {
                            item {
                                EmptyStateView(
                                    title = "Belum Ada Transaksi",
                                    message = "Belum terdapat transaksi kas masuk atau keluar yang tercatat."
                                )
                            }
                        } else {
                            items(state.data.transactions) { tx ->
                                Card(
                                    modifier = Modifier.fillMaxWidth(),
                                    shape = RoundedCornerShape(16.dp),
                                    colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
                                ) {
                                    Row(
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .padding(16.dp),
                                        verticalAlignment = Alignment.CenterVertically,
                                        horizontalArrangement = Arrangement.spacedBy(14.dp)
                                    ) {
                                        val isIncome = tx.type.lowercase() == "masuk"
                                        Surface(
                                            color = if (isIncome) StatusSuccess.copy(alpha = 0.12f) else StatusError.copy(alpha = 0.12f),
                                            shape = RoundedCornerShape(12.dp),
                                            modifier = Modifier.size(44.dp)
                                        ) {
                                            Box(contentAlignment = Alignment.Center) {
                                                Icon(
                                                    imageVector = if (isIncome) Icons.Default.ArrowDownward else Icons.Default.ArrowUpward,
                                                    contentDescription = null,
                                                    tint = if (isIncome) StatusSuccess else StatusError,
                                                    modifier = Modifier.size(22.dp)
                                                )
                                            }
                                        }

                                        Column(modifier = Modifier.weight(1f)) {
                                            Text(
                                                text = tx.description.ifEmpty { "Transaksi Kas" },
                                                style = MaterialTheme.typography.titleMedium,
                                                fontWeight = FontWeight.Bold,
                                                color = MaterialTheme.colorScheme.onSurface
                                            )
                                            Text(
                                                text = "${tx.category} | ${tx.date}",
                                                style = MaterialTheme.typography.labelSmall,
                                                color = MaterialTheme.colorScheme.onSurfaceVariant
                                            )
                                        }

                                        Text(
                                            text = "${if (isIncome) "+" else "-"}${formatter.format(tx.amount)}",
                                            style = MaterialTheme.typography.bodyMedium,
                                            fontWeight = FontWeight.Black,
                                            color = if (isIncome) StatusSuccess else StatusError
                                        )
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

            // Modal Form Tambah Transaksi Kas
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
                            text = "Tambah Transaksi Kas",
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
                            placeholder = { Text("Contoh: 5000000") },
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
        }
    }
}
