package id.my.sisinden.app.ui.mitra

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.Call
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Pending
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import id.my.sisinden.app.core.ui.components.*
import id.my.sisinden.app.core.ui.theme.SindenOceanPrimary
import id.my.sisinden.app.core.ui.theme.SindenTealPrimary

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun MitraScreen(
    viewModel: MitraViewModel,
    onBackClick: () -> Unit
) {
    val uiState by viewModel.uiState.collectAsState()
    val searchQuery by viewModel.searchQuery.collectAsState()
    var showAddModal by remember { mutableStateOf(false) }

    Scaffold(
        topBar = {
            SindenTopBar(
                title = "Mitra Intelijen",
                subtitle = "Pembinaan Jaringan Kerja Lapangan",
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
                Icon(Icons.Default.Add, contentDescription = "Tambah Mitra")
            }
        },
        containerColor = MaterialTheme.colorScheme.background
    ) { paddingValues ->
        Box(modifier = Modifier.fillMaxSize().padding(paddingValues)) {
            Column(modifier = Modifier.fillMaxSize()) {
                OutlinedTextField(
                    value = searchQuery,
                    onValueChange = { viewModel.setSearchQuery(it) },
                    placeholder = { Text("Cari nama mitra, perusahaan, no HP...") },
                    leadingIcon = { Icon(Icons.Default.Search, contentDescription = null) },
                    singleLine = true,
                    shape = RoundedCornerShape(14.dp),
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 16.dp, vertical = 8.dp)
                )

                when (val state = uiState) {
                    is MitraUiState.Loading -> {
                        ShimmerLoadingList()
                    }
                    is MitraUiState.Success -> {
                        if (state.list.isEmpty()) {
                            EmptyStateView(
                                title = "Belum Ada Mitra Terdaftar",
                                message = "Belum ada catatan pembinaan mitra intelijen yang cocok."
                            )
                        } else {
                            LazyColumn(
                                contentPadding = PaddingValues(16.dp),
                                verticalArrangement = Arrangement.spacedBy(10.dp),
                                modifier = Modifier.fillMaxSize()
                            ) {
                                items(state.list) { mitra ->
                                    Card(
                                        modifier = Modifier.fillMaxWidth(),
                                        shape = RoundedCornerShape(16.dp),
                                        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                                        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
                                    ) {
                                        Column(
                                            modifier = Modifier.padding(16.dp),
                                            verticalArrangement = Arrangement.spacedBy(8.dp)
                                        ) {
                                            Row(
                                                modifier = Modifier.fillMaxWidth(),
                                                horizontalArrangement = Arrangement.SpaceBetween,
                                                verticalAlignment = Alignment.CenterVertically
                                            ) {
                                                Row(
                                                    verticalAlignment = Alignment.CenterVertically,
                                                    horizontalArrangement = Arrangement.spacedBy(10.dp)
                                                ) {
                                                    Surface(
                                                        shape = CircleShape,
                                                        color = Color(0xFF0D9488).copy(alpha = 0.12f),
                                                        modifier = Modifier.size(38.dp)
                                                    ) {
                                                        Box(contentAlignment = Alignment.Center) {
                                                            Text(
                                                                text = mitra.name.take(2).uppercase(),
                                                                fontWeight = FontWeight.Black,
                                                                color = Color(0xFF0D9488),
                                                                fontSize = 13.sp
                                                            )
                                                        }
                                                    }
                                                    Column {
                                                        Text(
                                                            text = mitra.name,
                                                            style = MaterialTheme.typography.titleMedium,
                                                            fontWeight = FontWeight.Bold,
                                                            color = MaterialTheme.colorScheme.onSurface
                                                        )
                                                        Text(
                                                            text = mitra.sector,
                                                            style = MaterialTheme.typography.labelSmall,
                                                            color = MaterialTheme.colorScheme.onSurfaceVariant
                                                        )
                                                    }
                                                }

                                                val isPaid = mitra.isPaidThisMonth
                                                Surface(
                                                    shape = RoundedCornerShape(12.dp),
                                                    color = if (isPaid) Color(0xFFDCFCE7) else Color(0xFFFEF3C7)
                                                ) {
                                                    Row(
                                                        modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp),
                                                        verticalAlignment = Alignment.CenterVertically,
                                                        horizontalArrangement = Arrangement.spacedBy(4.dp)
                                                    ) {
                                                        Icon(
                                                            imageVector = if (isPaid) Icons.Default.CheckCircle else Icons.Default.Pending,
                                                            contentDescription = null,
                                                            tint = if (isPaid) Color(0xFF16A34A) else Color(0xFFD97706),
                                                            modifier = Modifier.size(14.dp)
                                                        )
                                                        Text(
                                                            text = if (isPaid) "Lunas" else "Belum",
                                                            style = MaterialTheme.typography.labelSmall,
                                                            fontWeight = FontWeight.Bold,
                                                            color = if (isPaid) Color(0xFF16A34A) else Color(0xFFD97706)
                                                        )
                                                    }
                                                }
                                            }

                                            Divider(color = MaterialTheme.colorScheme.outlineVariant.copy(alpha = 0.4f), thickness = 0.5.dp)

                                            Row(
                                                modifier = Modifier.fillMaxWidth(),
                                                horizontalArrangement = Arrangement.SpaceBetween,
                                                verticalAlignment = Alignment.CenterVertically
                                            ) {
                                                Row(
                                                    verticalAlignment = Alignment.CenterVertically,
                                                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                                                ) {
                                                    Icon(Icons.Default.Call, contentDescription = null, tint = SindenOceanPrimary, modifier = Modifier.size(16.dp))
                                                    Text(
                                                        text = mitra.phone,
                                                        style = MaterialTheme.typography.bodySmall,
                                                        fontWeight = FontWeight.SemiBold
                                                    )
                                                }

                                                TextButton(
                                                    onClick = {
                                                        val current = if (mitra.isPaidThisMonth) "sudah_dibayar" else "belum_dibayar"
                                                        viewModel.togglePayment(mitra.id, current)
                                                    }
                                                ) {
                                                    Text(
                                                        text = if (mitra.isPaidThisMonth) "Tandai Belum" else "Tandai Lunas",
                                                        style = MaterialTheme.typography.labelSmall,
                                                        fontWeight = FontWeight.Bold
                                                    )
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    is MitraUiState.Error -> {
                        ErrorStateView(
                            message = state.message,
                            onRetry = { viewModel.loadMitra() }
                        )
                    }
                }
            }

            if (showAddModal) {
                ModalBottomSheet(onDismissRequest = { showAddModal = false }) {
                    var name by remember { mutableStateOf("") }
                    var phone by remember { mutableStateOf("") }
                    var sector by remember { mutableStateOf("") }
                    var notes by remember { mutableStateOf("") }

                    Column(
                        modifier = Modifier.fillMaxWidth().padding(24.dp),
                        verticalArrangement = Arrangement.spacedBy(14.dp)
                    ) {
                        Text(
                            text = "Daftarkan Mitra Lapangan Baru",
                            style = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.Bold
                        )

                        OutlinedTextField(
                            value = name,
                            onValueChange = { name = it },
                            label = { Text("Nama Mitra *") },
                            placeholder = { Text("Contoh: Pak Rahman") },
                            singleLine = true,
                            shape = RoundedCornerShape(12.dp),
                            modifier = Modifier.fillMaxWidth()
                        )

                        OutlinedTextField(
                            value = sector,
                            onValueChange = { sector = it },
                            label = { Text("Perusahaan / Sektor *") },
                            placeholder = { Text("Contoh: PT. Ocean Energi") },
                            singleLine = true,
                            shape = RoundedCornerShape(12.dp),
                            modifier = Modifier.fillMaxWidth()
                        )

                        OutlinedTextField(
                            value = phone,
                            onValueChange = { phone = it },
                            label = { Text("Nomor HP / WhatsApp *") },
                            placeholder = { Text("Contoh: 08123456789") },
                            singleLine = true,
                            shape = RoundedCornerShape(12.dp),
                            modifier = Modifier.fillMaxWidth()
                        )

                        OutlinedTextField(
                            value = notes,
                            onValueChange = { notes = it },
                            label = { Text("Keterangan Tambahan") },
                            minLines = 2,
                            shape = RoundedCornerShape(12.dp),
                            modifier = Modifier.fillMaxWidth()
                        )

                        Button(
                            onClick = {
                                if (name.isNotEmpty() && sector.isNotEmpty() && phone.isNotEmpty()) {
                                    viewModel.createMitra(name, phone, sector, notes) {
                                        showAddModal = false
                                    }
                                }
                            },
                            shape = RoundedCornerShape(12.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = SindenTealPrimary),
                            modifier = Modifier.fillMaxWidth().height(48.dp)
                        ) {
                            Text("SIMPAN DATA MITRA", fontWeight = FontWeight.Bold)
                        }
                    }
                }
            }
        }
    }
}
