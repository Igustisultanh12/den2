package id.my.sisinden.app.ui.mitra

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
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import id.my.sisinden.app.core.ui.components.*
import id.my.sisinden.app.core.ui.theme.*
import id.my.sisinden.app.data.model.MitraDto
import java.util.Calendar

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun MitraScreen(
    viewModel: MitraViewModel,
    onBackClick: () -> Unit
) {
    val uiState by viewModel.uiState.collectAsState()
    val searchQuery by viewModel.searchQuery.collectAsState()
    var showAddModal by remember { mutableStateOf(false) }
    var selectedMitraForMatrix by remember { mutableStateOf<MitraDto?>(null) }
    var selectedMitraForEdit by remember { mutableStateOf<MitraDto?>(null) }
    var selectedMitraForDelete by remember { mutableStateOf<MitraDto?>(null) }

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
                            val totalMitra = state.list.size
                            val lunasCount = state.list.count { it.isPaidThisMonth }
                            val belumCount = totalMitra - lunasCount

                            LazyColumn(
                                contentPadding = PaddingValues(16.dp),
                                verticalArrangement = Arrangement.spacedBy(12.dp),
                                modifier = Modifier.fillMaxSize()
                            ) {
                                // RINGKASAN REKAPITULASI MITRA
                                item {
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.spacedBy(10.dp)
                                    ) {
                                        Surface(
                                            modifier = Modifier.weight(1f),
                                            shape = RoundedCornerShape(16.dp),
                                            color = MaterialTheme.colorScheme.surface,
                                            shadowElevation = 1.dp
                                        ) {
                                            Column(modifier = Modifier.padding(12.dp)) {
                                                Text("Total Mitra", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                                                Spacer(modifier = Modifier.height(2.dp))
                                                Text("$totalMitra Mitra", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Black)
                                            }
                                        }

                                        Surface(
                                            modifier = Modifier.weight(1f),
                                            shape = RoundedCornerShape(16.dp),
                                            color = Color(0xFFDCFCE7),
                                            shadowElevation = 1.dp
                                        ) {
                                            Column(modifier = Modifier.padding(12.dp)) {
                                                Text("Lunas Bulan Ini", style = MaterialTheme.typography.labelSmall, color = Color(0xFF166534))
                                                Spacer(modifier = Modifier.height(2.dp))
                                                Text("$lunasCount Mitra", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Black, color = Color(0xFF16A34A))
                                            }
                                        }

                                        Surface(
                                            modifier = Modifier.weight(1f),
                                            shape = RoundedCornerShape(16.dp),
                                            color = Color(0xFFFEF3C7),
                                            shadowElevation = 1.dp
                                        ) {
                                            Column(modifier = Modifier.padding(12.dp)) {
                                                Text("Belum Lunas", style = MaterialTheme.typography.labelSmall, color = Color(0xFF92400E))
                                                Spacer(modifier = Modifier.height(2.dp))
                                                Text("$belumCount Mitra", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Black, color = Color(0xFFD97706))
                                            }
                                        }
                                    }
                                }

                                items(state.list) { mitra ->
                                    Card(
                                        modifier = Modifier.fillMaxWidth(),
                                        shape = RoundedCornerShape(16.dp),
                                        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                                        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
                                    ) {
                                        Column(
                                            modifier = Modifier.padding(16.dp),
                                            verticalArrangement = Arrangement.spacedBy(10.dp)
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
                                                        modifier = Modifier.size(40.dp)
                                                    ) {
                                                        Box(contentAlignment = Alignment.Center) {
                                                            Text(
                                                                text = mitra.name.take(2).uppercase(),
                                                                fontWeight = FontWeight.Black,
                                                                color = Color(0xFF0D9488),
                                                                fontSize = 14.sp
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

                                            Divider(color = MaterialTheme.colorScheme.outlineVariant.copy(alpha = 0.3f), thickness = 0.5.dp)

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

                                            Divider(color = MaterialTheme.colorScheme.outlineVariant.copy(alpha = 0.2f), thickness = 0.5.dp)

                                            // BARIS TOMBOL AKSI: MATRIKS 12 BULAN, EDIT, HAPUS
                                            Row(
                                                modifier = Modifier.fillMaxWidth(),
                                                horizontalArrangement = Arrangement.SpaceBetween,
                                                verticalAlignment = Alignment.CenterVertically
                                            ) {
                                                OutlinedButton(
                                                    onClick = { selectedMitraForMatrix = mitra },
                                                    shape = RoundedCornerShape(10.dp),
                                                    contentPadding = PaddingValues(horizontal = 8.dp, vertical = 2.dp),
                                                    modifier = Modifier.height(30.dp)
                                                ) {
                                                    Icon(Icons.Default.CalendarMonth, contentDescription = null, modifier = Modifier.size(14.dp))
                                                    Spacer(modifier = Modifier.width(4.dp))
                                                    Text("Matriks 12 Bulan", fontSize = 10.sp, fontWeight = FontWeight.Bold)
                                                }

                                                Row(horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                                                    TextButton(
                                                        onClick = { selectedMitraForEdit = mitra },
                                                        contentPadding = PaddingValues(horizontal = 8.dp, vertical = 2.dp),
                                                        modifier = Modifier.height(30.dp)
                                                    ) {
                                                        Icon(Icons.Default.Edit, contentDescription = "Edit", modifier = Modifier.size(14.dp), tint = MaterialTheme.colorScheme.primary)
                                                        Spacer(modifier = Modifier.width(4.dp))
                                                        Text("Edit", style = MaterialTheme.typography.labelSmall, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.primary)
                                                    }

                                                    TextButton(
                                                        onClick = { selectedMitraForDelete = mitra },
                                                        contentPadding = PaddingValues(horizontal = 8.dp, vertical = 2.dp),
                                                        modifier = Modifier.height(30.dp)
                                                    ) {
                                                        Icon(Icons.Default.Delete, contentDescription = "Hapus", modifier = Modifier.size(14.dp), tint = Color(0xFFE11D48))
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
                    }
                    is MitraUiState.Error -> {
                        ErrorStateView(
                            message = state.message,
                            onRetry = { viewModel.loadMitra() }
                        )
                    }
                }
            }

            // MODAL FORM TAMBAH MITRA
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

            // MODAL FORM EDIT MITRA
            val editingMitra = selectedMitraForEdit
            if (editingMitra != null) {
                ModalBottomSheet(onDismissRequest = { selectedMitraForEdit = null }) {
                    var editName by remember(editingMitra) { mutableStateOf(editingMitra.name) }
                    var editPhone by remember(editingMitra) { mutableStateOf(editingMitra.phone) }
                    var editSector by remember(editingMitra) { mutableStateOf(editingMitra.sector) }
                    var editNotes by remember(editingMitra) { mutableStateOf(editingMitra.keterangan ?: "") }

                    Column(
                        modifier = Modifier.fillMaxWidth().padding(24.dp),
                        verticalArrangement = Arrangement.spacedBy(14.dp)
                    ) {
                        Text(
                            text = "Edit Data Mitra Intelijen",
                            style = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.Bold
                        )

                        OutlinedTextField(
                            value = editName,
                            onValueChange = { editName = it },
                            label = { Text("Nama Mitra *") },
                            singleLine = true,
                            shape = RoundedCornerShape(12.dp),
                            modifier = Modifier.fillMaxWidth()
                        )

                        OutlinedTextField(
                            value = editSector,
                            onValueChange = { editSector = it },
                            label = { Text("Perusahaan / Sektor *") },
                            singleLine = true,
                            shape = RoundedCornerShape(12.dp),
                            modifier = Modifier.fillMaxWidth()
                        )

                        OutlinedTextField(
                            value = editPhone,
                            onValueChange = { editPhone = it },
                            label = { Text("Nomor HP / WhatsApp *") },
                            singleLine = true,
                            shape = RoundedCornerShape(12.dp),
                            modifier = Modifier.fillMaxWidth()
                        )

                        OutlinedTextField(
                            value = editNotes,
                            onValueChange = { editNotes = it },
                            label = { Text("Keterangan Tambahan") },
                            minLines = 2,
                            shape = RoundedCornerShape(12.dp),
                            modifier = Modifier.fillMaxWidth()
                        )

                        Button(
                            onClick = {
                                if (editName.isNotEmpty() && editSector.isNotEmpty() && editPhone.isNotEmpty()) {
                                    viewModel.updateMitra(editingMitra.id, editName, editPhone, editSector, editNotes) {
                                        selectedMitraForEdit = null
                                    }
                                }
                            },
                            shape = RoundedCornerShape(12.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = SindenTealPrimary),
                            modifier = Modifier.fillMaxWidth().height(48.dp)
                        ) {
                            Text("PERBARUI DATA MITRA", fontWeight = FontWeight.Bold)
                        }
                    }
                }
            }

            // MODAL MATRIKS 12 BULAN PEMBAYARAN MITRA
            val matrixMitra = selectedMitraForMatrix
            if (matrixMitra != null) {
                val currentYear = remember { Calendar.getInstance().get(Calendar.YEAR) }
                val monthNames = remember {
                    listOf("Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Ags", "Sep", "Okt", "Nov", "Des")
                }

                ModalBottomSheet(onDismissRequest = { selectedMitraForMatrix = null }) {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(24.dp),
                        verticalArrangement = Arrangement.spacedBy(16.dp)
                    ) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Column {
                                Text(
                                    text = "Matriks Pembayaran $currentYear",
                                    style = MaterialTheme.typography.titleMedium,
                                    fontWeight = FontWeight.Bold
                                )
                                Text(
                                    text = "${matrixMitra.name} • ${matrixMitra.sector}",
                                    style = MaterialTheme.typography.labelSmall,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant
                                )
                            }
                        }

                        Text(
                            text = "Tekan bulan untuk mengubah status pembayaran secara langsung:",
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )

                        // GRID 3 x 4 UNTUK 12 BULAN
                        Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            for (row in 0..3) {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                                ) {
                                    for (col in 0..2) {
                                        val monthIndex = row * 3 + col + 1
                                        val monthLabel = monthNames[monthIndex - 1]
                                        val isPaid = matrixMitra.paymentMatrix?.get(monthIndex.toString()) == true

                                        Surface(
                                            modifier = Modifier
                                                .weight(1f)
                                                .height(48.dp),
                                            shape = RoundedCornerShape(12.dp),
                                            color = if (isPaid) Color(0xFFDCFCE7) else Color(0xFFF1F5F9),
                                            border = androidx.compose.foundation.BorderStroke(
                                                1.dp,
                                                if (isPaid) Color(0xFF16A34A) else Color(0xFFCBD5E1)
                                            ),
                                            onClick = {
                                                viewModel.togglePaymentMonth(matrixMitra.id, currentYear, monthIndex)
                                                // Refresh selected partner matrix in dialog
                                                val updatedMap = (matrixMitra.paymentMatrix ?: emptyMap()).toMutableMap()
                                                updatedMap[monthIndex.toString()] = !isPaid
                                                selectedMitraForMatrix = matrixMitra.copy(paymentMatrix = updatedMap)
                                            }
                                        ) {
                                            Row(
                                                modifier = Modifier.fillMaxSize().padding(horizontal = 8.dp),
                                                verticalAlignment = Alignment.CenterVertically,
                                                horizontalArrangement = Arrangement.SpaceBetween
                                            ) {
                                                Text(
                                                    text = monthLabel,
                                                    style = MaterialTheme.typography.labelMedium,
                                                    fontWeight = FontWeight.Bold,
                                                    color = if (isPaid) Color(0xFF166534) else Color(0xFF475569)
                                                )
                                                Icon(
                                                    imageVector = if (isPaid) Icons.Default.CheckCircle else Icons.Default.RadioButtonUnchecked,
                                                    contentDescription = null,
                                                    tint = if (isPaid) Color(0xFF16A34A) else Color(0xFF94A3B8),
                                                    modifier = Modifier.size(18.dp)
                                                )
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        Button(
                            onClick = { selectedMitraForMatrix = null },
                            shape = RoundedCornerShape(12.dp),
                            modifier = Modifier.fillMaxWidth().height(44.dp)
                        ) {
                            Text("SELESAI", fontWeight = FontWeight.Bold)
                        }
                    }
                }
            }

            // DIALOG KONFIRMASI HAPUS MITRA
            val deletingMitra = selectedMitraForDelete
            if (deletingMitra != null) {
                AlertDialog(
                    onDismissRequest = { selectedMitraForDelete = null },
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
                            text = "Hapus Data Mitra?",
                            fontWeight = FontWeight.Bold,
                            style = MaterialTheme.typography.titleMedium
                        )
                    },
                    text = {
                        Text(
                            text = "Apakah Anda yakin ingin menghapus data mitra '${deletingMitra.name}'? Seluruh riwayat catatan pembayaran terkait mitra ini juga akan dihapus.",
                            style = MaterialTheme.typography.bodyMedium
                        )
                    },
                    confirmButton = {
                        Button(
                            onClick = {
                                viewModel.deleteMitra(deletingMitra.id) {
                                    selectedMitraForDelete = null
                                }
                            },
                            colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFE11D48))
                        ) {
                            Text("HAPUS", fontWeight = FontWeight.Bold)
                        }
                    },
                    dismissButton = {
                        TextButton(onClick = { selectedMitraForDelete = null }) {
                            Text("BATAL")
                        }
                    }
                )
            }
        }
    }
}
