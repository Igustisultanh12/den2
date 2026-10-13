package id.my.sisinden.app.ui.attendance

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
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
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import id.my.sisinden.app.core.ui.components.SindenTopBar
import id.my.sisinden.app.core.ui.theme.*
import id.my.sisinden.app.data.model.AttendanceDto
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AttendanceScreen(
    viewModel: AttendanceViewModel,
    onBackClick: () -> Unit
) {
    val uiState by viewModel.uiState.collectAsState()
    val actionState by viewModel.actionState.collectAsState()
    val snackbarHostState = remember { SnackbarHostState() }

    var selectedStatus by remember { mutableStateOf("hadir") }
    var notes by remember { mutableStateOf("") }
    var locationName by remember { mutableStateOf("Mako Denintel Kodaeral V, Surabaya") }
    var latitude by remember { mutableDoubleStateOf(-7.2053) }
    var longitude by remember { mutableDoubleStateOf(112.7358) }

    val indonesianDate = remember {
        val sdf = SimpleDateFormat("EEEE, d MMMM yyyy", Locale("id", "ID"))
        sdf.format(Date())
    }

    LaunchedEffect(actionState) {
        when (val state = actionState) {
            is AttendanceActionUiState.Success -> {
                snackbarHostState.showSnackbar(state.message)
                viewModel.resetActionState()
            }
            is AttendanceActionUiState.Error -> {
                snackbarHostState.showSnackbar(state.message)
                viewModel.resetActionState()
            }
            else -> {}
        }
    }

    Scaffold(
        topBar = {
            SindenTopBar(
                title = "Presensi Kehadiran",
                subtitle = "Pencatatan Kehadiran Personel Satuan",
                onBackClick = onBackClick
            )
        },
        snackbarHost = { SnackbarHost(snackbarHostState) },
        containerColor = LivinSubtleBg
    ) { paddingValues ->
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
        ) {
            when (val state = uiState) {
                is AttendanceUiState.Loading -> {
                    Box(
                        modifier = Modifier.fillMaxSize(),
                        contentAlignment = Alignment.Center
                    ) {
                        CircularProgressIndicator(color = LivinRoyalBlue)
                    }
                }
                is AttendanceUiState.Error -> {
                    Column(
                        modifier = Modifier
                            .fillMaxSize()
                            .padding(24.dp),
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.Center
                    ) {
                        Icon(
                            imageVector = Icons.Default.Warning,
                            contentDescription = null,
                            tint = StatusError,
                            modifier = Modifier.size(48.dp)
                        )
                        Spacer(modifier = Modifier.height(12.dp))
                        Text(
                            text = "Gagal Memuat Data Presensi",
                            style = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.Bold,
                            color = LivinElderTextPrimary
                        )
                        Spacer(modifier = Modifier.height(6.dp))
                        Text(
                            text = state.message,
                            style = MaterialTheme.typography.bodyMedium,
                            color = LivinElderTextSecondary
                        )
                        Spacer(modifier = Modifier.height(16.dp))
                        Button(
                            onClick = { viewModel.loadAttendances() },
                            colors = ButtonDefaults.buttonColors(containerColor = LivinRoyalBlue),
                            shape = RoundedCornerShape(14.dp)
                        ) {
                            Text("Coba Lagi", fontWeight = FontWeight.Bold)
                        }
                    }
                }
                is AttendanceUiState.Success -> {
                    LazyColumn(
                        contentPadding = PaddingValues(16.dp),
                        verticalArrangement = Arrangement.spacedBy(16.dp),
                        modifier = Modifier.fillMaxSize()
                    ) {
                        // 1. HERO CARD: STATUS PRESENSI HARI INI (LIVIN' ROYAL BLUE STYLE)
                        item {
                            Surface(
                                modifier = Modifier.fillMaxWidth(),
                                shape = RoundedCornerShape(24.dp),
                                shadowElevation = 4.dp
                            ) {
                                Box(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .background(
                                            Brush.linearGradient(
                                                colors = listOf(
                                                    LivinNavyDeep,
                                                    LivinRoyalBlue,
                                                    LivinBlueAccent
                                                )
                                            )
                                        )
                                        .padding(22.dp)
                                ) {
                                    Column {
                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.SpaceBetween,
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            Text(
                                                text = indonesianDate.uppercase(),
                                                style = MaterialTheme.typography.labelSmall,
                                                fontWeight = FontWeight.Bold,
                                                letterSpacing = 0.8.sp,
                                                color = Color(0xFFBAE6FD)
                                            )

                                            val hasAttended = state.todayAttendance != null
                                            Surface(
                                                shape = RoundedCornerShape(10.dp),
                                                color = if (hasAttended) Color(0xFF16A34A) else LivinYellowGold
                                            ) {
                                                Text(
                                                    text = if (hasAttended) "TERCATAT" else "BELUM ABSEN",
                                                    style = MaterialTheme.typography.labelSmall,
                                                    fontWeight = FontWeight.Black,
                                                    color = if (hasAttended) Color.White else Color(0xFF0F172A),
                                                    modifier = Modifier.padding(horizontal = 10.dp, vertical = 4.dp)
                                                )
                                            }
                                        }

                                        Spacer(modifier = Modifier.height(10.dp))

                                        val today = state.todayAttendance
                                        if (today != null) {
                                            Text(
                                                text = today.displayTime,
                                                style = MaterialTheme.typography.headlineLarge,
                                                fontWeight = FontWeight.Black,
                                                color = Color.White,
                                                letterSpacing = 0.5.sp
                                            )
                                            Spacer(modifier = Modifier.height(4.dp))
                                            Text(
                                                text = "Status: ${today.displayStatus}",
                                                style = MaterialTheme.typography.titleMedium,
                                                fontWeight = FontWeight.Bold,
                                                color = Color(0xFF67E8F9)
                                            )
                                            Spacer(modifier = Modifier.height(8.dp))
                                            Row(
                                                verticalAlignment = Alignment.CenterVertically,
                                                horizontalArrangement = Arrangement.spacedBy(6.dp)
                                            ) {
                                                Icon(
                                                    imageVector = Icons.Default.LocationOn,
                                                    contentDescription = null,
                                                    tint = Color.White.copy(alpha = 0.85f),
                                                    modifier = Modifier.size(16.dp)
                                                )
                                                Text(
                                                    text = today.locationName ?: "Mako Denintel Kodaeral V",
                                                    style = MaterialTheme.typography.bodySmall,
                                                    color = Color.White.copy(alpha = 0.85f),
                                                    maxLines = 1,
                                                    overflow = TextOverflow.Ellipsis
                                                )
                                            }
                                        } else {
                                            Text(
                                                text = "--:-- WIB",
                                                style = MaterialTheme.typography.headlineLarge,
                                                fontWeight = FontWeight.Black,
                                                color = Color.White.copy(alpha = 0.8f)
                                            )
                                            Spacer(modifier = Modifier.height(4.dp))
                                            Text(
                                                text = "Silakan catat kehadiran dinas hari ini.",
                                                style = MaterialTheme.typography.bodyMedium,
                                                color = Color(0xFFE2E8F0)
                                            )
                                        }
                                    }
                                }
                            }
                        }

                        // 2. FORMULIR PRESENSI MANDIRI (ELDER-FRIENDLY TACTILE INPUT)
                        item {
                            Surface(
                                modifier = Modifier.fillMaxWidth(),
                                shape = RoundedCornerShape(22.dp),
                                color = LivinCardBg,
                                border = BorderStroke(1.dp, LivinElderBorder),
                                shadowElevation = 2.dp
                            ) {
                                Column(
                                    modifier = Modifier.padding(20.dp),
                                    verticalArrangement = Arrangement.spacedBy(14.dp)
                                ) {
                                    Text(
                                        text = "Isi Presensi Kehadiran",
                                        style = MaterialTheme.typography.titleMedium,
                                        fontWeight = FontWeight.Black,
                                        color = LivinElderTextPrimary
                                    )

                                    Text(
                                        text = "Pilih Status Kehadiran Kedinasan:",
                                        style = MaterialTheme.typography.labelMedium,
                                        fontWeight = FontWeight.Bold,
                                        color = LivinElderTextSecondary
                                    )

                                    // 4 PILIHAN STATUS PRESENSI (TOMBOL BESAR RAMAH PENGGUNA)
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                                    ) {
                                        val statusOptions = listOf(
                                            Pair("hadir", "Hadir Kantor"),
                                            Pair("dinas_luar", "Dinas Luar"),
                                            Pair("piket", "Piket Jaga"),
                                            Pair("izin", "Izin Dinas")
                                        )

                                        statusOptions.take(2).forEach { (code, label) ->
                                            val isSelected = selectedStatus == code
                                            Surface(
                                                modifier = Modifier
                                                    .weight(1f)
                                                    .height(48.dp)
                                                    .clickable { selectedStatus = code },
                                                shape = RoundedCornerShape(14.dp),
                                                color = if (isSelected) LivinRoyalBlue else Color(0xFFF1F5F9),
                                                border = BorderStroke(
                                                    1.dp,
                                                    if (isSelected) LivinRoyalBlue else LivinElderBorder
                                                )
                                            ) {
                                                Box(contentAlignment = Alignment.Center) {
                                                    Text(
                                                        text = label,
                                                        style = MaterialTheme.typography.labelMedium,
                                                        fontWeight = FontWeight.Bold,
                                                        color = if (isSelected) Color.White else LivinElderTextPrimary
                                                    )
                                                }
                                            }
                                        }
                                    }

                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                                    ) {
                                        val statusOptions = listOf(
                                            Pair("piket", "Piket Jaga"),
                                            Pair("izin", "Izin Dinas")
                                        )

                                        statusOptions.forEach { (code, label) ->
                                            val isSelected = selectedStatus == code
                                            Surface(
                                                modifier = Modifier
                                                    .weight(1f)
                                                    .height(48.dp)
                                                    .clickable { selectedStatus = code },
                                                shape = RoundedCornerShape(14.dp),
                                                color = if (isSelected) LivinRoyalBlue else Color(0xFFF1F5F9),
                                                border = BorderStroke(
                                                    1.dp,
                                                    if (isSelected) LivinRoyalBlue else LivinElderBorder
                                                )
                                            ) {
                                                Box(contentAlignment = Alignment.Center) {
                                                    Text(
                                                        text = label,
                                                        style = MaterialTheme.typography.labelMedium,
                                                        fontWeight = FontWeight.Bold,
                                                        color = if (isSelected) Color.White else LivinElderTextPrimary
                                                    )
                                                }
                                            }
                                        }
                                    }

                                    // LOKASI TERDETEKSI
                                    Surface(
                                        modifier = Modifier.fillMaxWidth(),
                                        shape = RoundedCornerShape(14.dp),
                                        color = Color(0xFFF8FAFC),
                                        border = BorderStroke(1.dp, LivinElderBorder)
                                    ) {
                                        Row(
                                            modifier = Modifier.padding(12.dp),
                                            verticalAlignment = Alignment.CenterVertically,
                                            horizontalArrangement = Arrangement.spacedBy(10.dp)
                                        ) {
                                            Icon(
                                                imageVector = Icons.Default.Place,
                                                contentDescription = null,
                                                tint = LivinRoyalBlue,
                                                modifier = Modifier.size(24.dp)
                                            )
                                            Column(modifier = Modifier.weight(1f)) {
                                                Text(
                                                    text = "Titik Koordinat Kedinasan",
                                                    style = MaterialTheme.typography.labelSmall,
                                                    color = LivinElderTextSecondary,
                                                    fontWeight = FontWeight.Bold
                                                )
                                                Text(
                                                    text = locationName,
                                                    style = MaterialTheme.typography.bodySmall,
                                                    fontWeight = FontWeight.SemiBold,
                                                    color = LivinElderTextPrimary,
                                                    maxLines = 1,
                                                    overflow = TextOverflow.Ellipsis
                                                )
                                            }
                                        }
                                    }

                                    // INPUT CATATAN PENUGASAN
                                    OutlinedTextField(
                                        value = notes,
                                        onValueChange = { notes = it },
                                        label = { Text("Keterangan Penugasan (Opsional)") },
                                        placeholder = { Text("Contoh: Monitoring pengamanan pangkalan laut") },
                                        shape = RoundedCornerShape(14.dp),
                                        modifier = Modifier.fillMaxWidth(),
                                        minLines = 2
                                    )

                                    // TOMBOL BESAR EKSEKUSI PRESENSI (LIVIN' GOLDEN / BLUE BUTTON)
                                    Button(
                                        onClick = {
                                            viewModel.submitAttendance(
                                                status = selectedStatus,
                                                latitude = latitude,
                                                longitude = longitude,
                                                locationName = locationName,
                                                notes = notes.ifEmpty { null }
                                            )
                                        },
                                        enabled = actionState !is AttendanceActionUiState.Loading,
                                        shape = RoundedCornerShape(16.dp),
                                        colors = ButtonDefaults.buttonColors(
                                            containerColor = LivinRoyalBlue,
                                            contentColor = Color.White
                                        ),
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .height(54.dp)
                                    ) {
                                        if (actionState is AttendanceActionUiState.Loading) {
                                            CircularProgressIndicator(
                                                modifier = Modifier.size(24.dp),
                                                color = Color.White,
                                                strokeWidth = 2.dp
                                            )
                                        } else {
                                            Row(
                                                verticalAlignment = Alignment.CenterVertically,
                                                horizontalArrangement = Arrangement.spacedBy(8.dp)
                                            ) {
                                                Icon(
                                                    imageVector = Icons.Default.CheckCircle,
                                                    contentDescription = null,
                                                    modifier = Modifier.size(20.dp)
                                                )
                                                Text(
                                                    text = if (state.todayAttendance != null) "PERBARUI PRESENSI HARI INI" else "KIRIM PRESENSI KEHADIRAN",
                                                    fontWeight = FontWeight.Black,
                                                    fontSize = 14.sp
                                                )
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        // 3. RIWAYAT PRESENSI KEHADIRAN (30 HARI TERAKHIR)
                        item {
                            Text(
                                text = "RIWAYAT PRESENSI (30 HARI TERAKHIR)",
                                style = MaterialTheme.typography.labelSmall,
                                fontWeight = FontWeight.Black,
                                color = LivinElderTextSecondary,
                                letterSpacing = 1.sp
                            )
                        }

                        if (state.attendances.isEmpty()) {
                            item {
                                Surface(
                                    modifier = Modifier.fillMaxWidth(),
                                    shape = RoundedCornerShape(16.dp),
                                    color = LivinCardBg,
                                    border = BorderStroke(1.dp, LivinElderBorder)
                                ) {
                                    Column(
                                        modifier = Modifier.padding(24.dp),
                                        horizontalAlignment = Alignment.CenterHorizontally
                                    ) {
                                        Text(
                                            text = "Belum Ada Riwayat Presensi",
                                            style = MaterialTheme.typography.bodyMedium,
                                            fontWeight = FontWeight.Bold,
                                            color = LivinElderTextSecondary
                                        )
                                    }
                                }
                            }
                        } else {
                            items(state.attendances) { item ->
                                AttendanceItemCard(attendance = item)
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun AttendanceItemCard(attendance: AttendanceDto) {
    Surface(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(18.dp),
        color = LivinCardBg,
        border = BorderStroke(1.dp, LivinElderBorder),
        shadowElevation = 1.dp
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(14.dp)
            ) {
                val (badgeBg, badgeColor) = when (attendance.status.lowercase()) {
                    "hadir" -> Pair(Color(0xFFDCFCE7), Color(0xFF15803D))
                    "dinas_luar" -> Pair(Color(0xFFE0F2FE), Color(0xFF0369A1))
                    "piket" -> Pair(Color(0xFFFEF3C7), Color(0xFFB45309))
                    else -> Pair(Color(0xFFF1F5F9), Color(0xFF475569))
                }

                Surface(
                    shape = CircleShape,
                    color = badgeBg,
                    modifier = Modifier.size(44.dp)
                ) {
                    Box(contentAlignment = Alignment.Center) {
                        Icon(
                            imageVector = when (attendance.status.lowercase()) {
                                "hadir" -> Icons.Default.Check
                                "dinas_luar" -> Icons.Default.DirectionsCar
                                "piket" -> Icons.Default.Security
                                else -> Icons.Default.Assignment
                            },
                            contentDescription = null,
                            tint = badgeColor,
                            modifier = Modifier.size(22.dp)
                        )
                    }
                }

                Column {
                    Text(
                        text = attendance.attendanceDate,
                        style = MaterialTheme.typography.bodyMedium,
                        fontWeight = FontWeight.Bold,
                        color = LivinElderTextPrimary
                    )
                    Text(
                        text = "Masuk: ${attendance.displayTime}",
                        style = MaterialTheme.typography.labelSmall,
                        color = LivinElderTextSecondary
                    )
                    if (!attendance.notes.isNullOrEmpty()) {
                        Text(
                            text = attendance.notes,
                            style = MaterialTheme.typography.labelSmall,
                            color = LivinRoyalBlue,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                    }
                }
            }

            Surface(
                shape = RoundedCornerShape(10.dp),
                color = when (attendance.status.lowercase()) {
                    "hadir" -> Color(0xFFDCFCE7)
                    "dinas_luar" -> Color(0xFFE0F2FE)
                    "piket" -> Color(0xFFFEF3C7)
                    else -> Color(0xFFF1F5F9)
                }
            ) {
                Text(
                    text = attendance.displayStatus,
                    style = MaterialTheme.typography.labelSmall,
                    fontWeight = FontWeight.Bold,
                    color = when (attendance.status.lowercase()) {
                        "hadir" -> Color(0xFF15803D)
                        "dinas_luar" -> Color(0xFF0369A1)
                        "piket" -> Color(0xFFB45309)
                        else -> Color(0xFF475569)
                    },
                    modifier = Modifier.padding(horizontal = 10.dp, vertical = 4.dp)
                )
            }
        }
    }
}
