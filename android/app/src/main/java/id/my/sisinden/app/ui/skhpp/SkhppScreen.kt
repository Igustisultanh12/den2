package id.my.sisinden.app.ui.skhpp

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import id.my.sisinden.app.core.ui.components.*
import id.my.sisinden.app.core.ui.theme.StatusSuccess

@Composable
fun SkhppScreen(
    viewModel: SkhppViewModel,
    onBackClick: () -> Unit,
    onCreateSkhppClick: () -> Unit
) {
    val uiState by viewModel.uiState.collectAsState()
    val statusFilter by viewModel.statusFilter.collectAsState()
    val searchQuery by viewModel.searchQuery.collectAsState()
    val currentUser by viewModel.currentUser.collectAsState(initial = null)

    val filters = listOf(
        Pair("Semua Status", null),
        Pair("Menunggu (Pending)", "pending"),
        Pair("Terverifikasi", "verified")
    )

    Scaffold(
        topBar = {
            SindenTopBar(
                title = "Penerbitan SKHPP",
                subtitle = "Surat Keterangan Hasil Penelitian Personel",
                onBackClick = onBackClick
            )
        },
        floatingActionButton = {
            FloatingActionButton(
                onClick = onCreateSkhppClick,
                containerColor = MaterialTheme.colorScheme.primary,
                contentColor = MaterialTheme.colorScheme.onPrimary,
                shape = RoundedCornerShape(16.dp)
            ) {
                Icon(Icons.Default.Add, contentDescription = "Ajukan SKHPP")
            }
        },
        containerColor = MaterialTheme.colorScheme.background
    ) { paddingValues ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
        ) {
            // Kolom Pencarian
            OutlinedTextField(
                value = searchQuery,
                onValueChange = { viewModel.setSearchQuery(it) },
                placeholder = { Text("Cari nama, NRP, atau keperluan...") },
                leadingIcon = { Icon(Icons.Default.Search, contentDescription = null) },
                singleLine = true,
                shape = RoundedCornerShape(14.dp),
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 8.dp)
            )

            LazyRow(
                contentPadding = PaddingValues(horizontal = 16.dp),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
                modifier = Modifier.padding(bottom = 8.dp)
            ) {
                items(filters) { (label, value) ->
                    val isSelected = statusFilter == value
                    SaaSTabChip(
                        selected = isSelected,
                        text = label,
                        onClick = { viewModel.setFilter(value) }
                    )
                }
            }

            // Konten Daftar SKHPP
            when (val state = uiState) {
                is SkhppUiState.Loading -> {
                    ShimmerLoadingList()
                }
                is SkhppUiState.Success -> {
                    if (state.list.isEmpty()) {
                        EmptyStateView(
                            title = "Tidak Ada Data SKHPP",
                            message = "Belum ada berkas pengajuan SKHPP yang terdaftar."
                        )
                    } else {
                        LazyColumn(
                            contentPadding = PaddingValues(16.dp),
                            verticalArrangement = Arrangement.spacedBy(10.dp),
                            modifier = Modifier.fillMaxSize()
                        ) {
                            items(state.list) { item ->
                                SaaSCard(
                                    modifier = Modifier.fillMaxWidth()
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
                                            Text(
                                                text = item.referenceNumber ?: "NOMOR BELUM TERBIT",
                                                style = MaterialTheme.typography.labelLarge,
                                                fontWeight = FontWeight.Bold,
                                                color = MaterialTheme.colorScheme.primary
                                            )
                                            StatusPill(status = item.status)
                                        }

                                        Text(
                                            text = "${item.pangkat.ifEmpty { "Prajurit" }} ${item.name}",
                                            style = MaterialTheme.typography.titleMedium,
                                            fontWeight = FontWeight.Bold,
                                            color = MaterialTheme.colorScheme.onSurface
                                        )

                                        Text(
                                            text = "NRP: ${item.nrp} ${if (item.jabatan.isNotEmpty()) "| Jabatan: " + item.jabatan else ""}",
                                            style = MaterialTheme.typography.bodySmall,
                                            color = MaterialTheme.colorScheme.onSurfaceVariant
                                        )

                                        HorizontalDivider(color = MaterialTheme.colorScheme.outline.copy(alpha = 0.5f), thickness = 0.5.dp)

                                        Text(
                                            text = "Keperluan: ${item.purpose}",
                                            style = MaterialTheme.typography.bodyMedium,
                                            color = MaterialTheme.colorScheme.onSurface
                                        )

                                        // Tombol Verifikasi jika pengguna memiliki role admin/komandan dan status pending
                                        val canVerify = (currentUser?.role == "admin" || currentUser?.role == "komandan") && item.status.lowercase() == "pending"
                                        if (canVerify) {
                                            Button(
                                                onClick = { viewModel.verifySkhpp(item.id) },
                                                colors = ButtonDefaults.buttonColors(containerColor = StatusSuccess),
                                                shape = RoundedCornerShape(10.dp),
                                                modifier = Modifier.align(Alignment.End)
                                            ) {
                                                Icon(Icons.Default.CheckCircle, contentDescription = null, modifier = Modifier.size(16.dp))
                                                Spacer(modifier = Modifier.width(6.dp))
                                                Text("Verifikasi Sekarang", fontWeight = FontWeight.Bold)
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                is SkhppUiState.Error -> {
                    ErrorStateView(
                        message = state.message,
                        onRetry = { viewModel.loadSkhpp() }
                    )
                }
            }
        }
    }
}
