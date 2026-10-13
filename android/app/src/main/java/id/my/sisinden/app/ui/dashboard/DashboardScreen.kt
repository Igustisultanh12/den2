package id.my.sisinden.app.ui.dashboard

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
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import id.my.sisinden.app.core.ui.components.*
import id.my.sisinden.app.core.ui.theme.*
import id.my.sisinden.app.data.model.LetterLogDto
import id.my.sisinden.app.data.model.UserDto
import java.text.NumberFormat
import java.util.Locale

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun DashboardScreen(
    viewModel: DashboardViewModel,
    onNavigateToLetters: () -> Unit,
    onNavigateToSkhpp: () -> Unit,
    onNavigateToCash: () -> Unit,
    onNavigateToTechnicalCash: () -> Unit,
    onNavigateToMitra: () -> Unit,
    onNavigateToSignature: () -> Unit,
    onNavigateToViolations: () -> Unit,
    onNavigateToPersonnel: () -> Unit,
    onNavigateToNotifications: () -> Unit,
    onNavigateToSettings: () -> Unit,
    onNavigateToAttendance: () -> Unit = {}
) {
    val context = LocalContext.current
    val currentUser by viewModel.currentUser.collectAsState(initial = null)
    val uiState by viewModel.uiState.collectAsState()
    val isPrivacyHidden by viewModel.isPrivacyHidden.collectAsState()
    val config by viewModel.configState.collectAsState()

    var showPermissionDialog by remember { mutableStateOf(false) }
    var deniedModuleName by remember { mutableStateOf("") }
    var selectedFilterCategory by remember { mutableStateOf("Sering Dipakai") }

    LaunchedEffect(Unit) {
        viewModel.loadDashboardData(context)
    }

    val formatter = remember {
        NumberFormat.getCurrencyInstance(Locale("id", "ID")).apply {
            maximumFractionDigits = 0
        }
    }

    Scaffold(
        topBar = {
            // HEADER VIVID LIVIN' SKY BLUE SESUAI FIGMA LIVIN' ELDER-FRIENDLY
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(
                        Brush.verticalGradient(
                            colors = listOf(
                                LivinSkyBlue,
                                Color(0xFF0075E3)
                            )
                        )
                    )
                    .statusBarsPadding()
                    .padding(horizontal = 20.dp, vertical = 14.dp)
            ) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column {
                        Text(
                            text = "SISINDEN",
                            style = MaterialTheme.typography.headlineSmall,
                            fontWeight = FontWeight.Black,
                            color = Color.White,
                            letterSpacing = 1.2.sp
                        )
                        Text(
                            text = "Detasemen Intelijen Kodaeral V",
                            style = MaterialTheme.typography.labelSmall,
                            fontWeight = FontWeight.SemiBold,
                            color = Color.White.copy(alpha = 0.85f)
                        )
                    }

                    Row(
                        horizontalArrangement = Arrangement.spacedBy(16.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        // Tombol Pesan / Warta Kedinasan dengan Label Underneath
                        Column(
                            horizontalAlignment = Alignment.CenterHorizontally,
                            modifier = Modifier.clickable { onNavigateToNotifications() }
                        ) {
                            BadgedBox(
                                badge = {
                                    if (uiState is DashboardUiState.Success && (uiState as DashboardUiState.Success).stats.unreadNotifCount > 0) {
                                        Badge(containerColor = Color(0xFFEF4444)) {
                                            Text("${(uiState as DashboardUiState.Success).stats.unreadNotifCount}")
                                        }
                                    }
                                }
                            ) {
                                Icon(
                                    imageVector = Icons.Default.Email,
                                    contentDescription = "Pesan",
                                    tint = Color.White,
                                    modifier = Modifier.size(24.dp)
                                )
                            }
                            Spacer(modifier = Modifier.height(2.dp))
                            Text(
                                text = "Warta",
                                style = MaterialTheme.typography.labelSmall,
                                fontSize = 10.sp,
                                fontWeight = FontWeight.Bold,
                                color = Color.White
                            )
                        }

                        // Tombol Keluar / Sesi dengan Label Underneath
                        Column(
                            horizontalAlignment = Alignment.CenterHorizontally,
                            modifier = Modifier.clickable { onNavigateToSettings() }
                        ) {
                            Icon(
                                imageVector = Icons.Default.ExitToApp,
                                contentDescription = "Keluar",
                                tint = Color.White,
                                modifier = Modifier.size(24.dp)
                            )
                            Spacer(modifier = Modifier.height(2.dp))
                            Text(
                                text = "Keluar",
                                style = MaterialTheme.typography.labelSmall,
                                fontSize = 10.sp,
                                fontWeight = FontWeight.Bold,
                                color = Color.White
                            )
                        }
                    }
                }
            }
        },
        containerColor = LivinSubtleBg
    ) { paddingValues ->
        LazyColumn(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues),
            contentPadding = PaddingValues(horizontal = 16.dp, vertical = 14.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            when (val state = uiState) {
                is DashboardUiState.Loading -> {
                    item {
                        ShimmerPlaceholder(modifier = Modifier.fillMaxWidth().height(200.dp))
                    }
                    item {
                        ShimmerPlaceholder(modifier = Modifier.fillMaxWidth().height(140.dp))
                    }
                }
                is DashboardUiState.Error -> {
                    item {
                        ErrorStateView(
                            message = state.message,
                            onRetry = { viewModel.loadDashboardData(context) }
                        )
                    }
                }
                is DashboardUiState.Success -> {
                    // 1. KARTU HERO UTAMA MELAYANG (WHITE FLOATING CARD SESUAI FIGMA LIVIN' ELDER-FRIENDLY)
                    item {
                        Surface(
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(26.dp),
                            color = Color.White,
                            shadowElevation = 4.dp
                        ) {
                            Column(modifier = Modifier.padding(18.dp)) {
                                // Header Profil Pengguna di Atas Kartu
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Row(
                                        verticalAlignment = Alignment.CenterVertically,
                                        horizontalArrangement = Arrangement.spacedBy(12.dp)
                                    ) {
                                        Surface(
                                            shape = CircleShape,
                                            color = LivinSkyBlue,
                                            modifier = Modifier.size(46.dp)
                                        ) {
                                            Box(contentAlignment = Alignment.Center) {
                                                Text(
                                                    text = (currentUser?.name?.take(2) ?: "DI").uppercase(),
                                                    style = MaterialTheme.typography.titleMedium,
                                                    fontWeight = FontWeight.Black,
                                                    color = Color.White
                                                )
                                            }
                                        }

                                        Column {
                                            Text(
                                                text = "Halo, ${currentUser?.name?.ifEmpty { "Personel Intel" } ?: "Prajurit"}",
                                                style = MaterialTheme.typography.titleMedium,
                                                fontWeight = FontWeight.Black,
                                                color = LivinElderTextPrimary
                                            )
                                            Row(
                                                verticalAlignment = Alignment.CenterVertically,
                                                horizontalArrangement = Arrangement.spacedBy(2.dp)
                                            ) {
                                                Text(
                                                    text = "${currentUser?.pangkat ?: "Personel"} Satuan",
                                                    style = MaterialTheme.typography.bodySmall,
                                                    color = LivinElderTextSecondary
                                                )
                                                Icon(
                                                    imageVector = Icons.Default.ChevronRight,
                                                    contentDescription = null,
                                                    tint = LivinElderTextSecondary,
                                                    modifier = Modifier.size(14.dp)
                                                )
                                            }
                                        }
                                    }

                                    Column(horizontalAlignment = Alignment.End) {
                                        Text(
                                            text = "Status Dinas",
                                            style = MaterialTheme.typography.labelSmall,
                                            color = LivinElderTextSecondary
                                        )
                                        Row(
                                            verticalAlignment = Alignment.CenterVertically,
                                            horizontalArrangement = Arrangement.spacedBy(2.dp)
                                        ) {
                                            Text(
                                                text = "SIAP TUGAS",
                                                style = MaterialTheme.typography.labelMedium,
                                                fontWeight = FontWeight.Black,
                                                color = LivinSkyBlueDark
                                            )
                                            Icon(
                                                imageVector = Icons.Default.ChevronRight,
                                                contentDescription = null,
                                                tint = LivinSkyBlueDark,
                                                modifier = Modifier.size(14.dp)
                                            )
                                        }
                                    }
                                }

                                Spacer(modifier = Modifier.height(14.dp))

                                // Kontainer Saldo & Kartu Bergaya Tabungan Now Livin'
                                Surface(
                                    shape = RoundedCornerShape(20.dp),
                                    color = Color(0xFFF1F5F9),
                                    modifier = Modifier.fillMaxWidth()
                                ) {
                                    Row(
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .padding(16.dp),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Column(modifier = Modifier.weight(1f)) {
                                            Text(
                                                text = "Kas Operasional Denintel IDR",
                                                style = MaterialTheme.typography.labelSmall,
                                                fontWeight = FontWeight.Bold,
                                                color = LivinElderTextSecondary
                                            )
                                            Spacer(modifier = Modifier.height(4.dp))
                                            Row(
                                                verticalAlignment = Alignment.CenterVertically,
                                                horizontalArrangement = Arrangement.spacedBy(6.dp)
                                            ) {
                                                Text(
                                                    text = if (isPrivacyHidden) "IDR •••••••" else formatter.format(state.stats.cashBalance),
                                                    style = MaterialTheme.typography.titleLarge,
                                                    fontWeight = FontWeight.Black,
                                                    color = LivinElderTextPrimary
                                                )
                                                IconButton(
                                                    onClick = { viewModel.togglePrivacy() },
                                                    modifier = Modifier.size(24.dp)
                                                ) {
                                                    Icon(
                                                        imageVector = if (isPrivacyHidden) Icons.Default.VisibilityOff else Icons.Default.Visibility,
                                                        contentDescription = "Toggle Privasi",
                                                        tint = LivinSkyBlueDark,
                                                        modifier = Modifier.size(17.dp)
                                                    )
                                                }
                                            }
                                            Spacer(modifier = Modifier.height(4.dp))
                                            Row(
                                                modifier = Modifier.clickable { onNavigateToCash() },
                                                verticalAlignment = Alignment.CenterVertically,
                                                horizontalArrangement = Arrangement.spacedBy(2.dp)
                                            ) {
                                                Text(
                                                    text = "Buka Detail",
                                                    style = MaterialTheme.typography.labelMedium,
                                                    fontWeight = FontWeight.Bold,
                                                    color = LivinSkyBlueDark
                                                )
                                                Icon(
                                                    imageVector = Icons.Default.ChevronRight,
                                                    contentDescription = null,
                                                    tint = LivinSkyBlueDark,
                                                    modifier = Modifier.size(14.dp)
                                                )
                                            }
                                        }

                                        // Grafik Simulasi Kartu Militer
                                        Surface(
                                            shape = RoundedCornerShape(12.dp),
                                            modifier = Modifier
                                                .width(90.dp)
                                                .height(56.dp),
                                            shadowElevation = 2.dp
                                        ) {
                                            Box(
                                                modifier = Modifier
                                                    .fillMaxSize()
                                                    .background(
                                                        Brush.linearGradient(
                                                            listOf(
                                                                LivinNavyDeep,
                                                                LivinRoyalBlue,
                                                                Color(0xFF0F172A)
                                                            )
                                                        )
                                                    )
                                                    .padding(6.dp)
                                            ) {
                                                Column(
                                                    modifier = Modifier.fillMaxSize(),
                                                    verticalArrangement = Arrangement.SpaceBetween
                                                ) {
                                                    Row(
                                                        modifier = Modifier.fillMaxWidth(),
                                                        horizontalArrangement = Arrangement.SpaceBetween
                                                    ) {
                                                        Icon(
                                                            imageVector = Icons.Default.Security,
                                                            contentDescription = null,
                                                            tint = LivinYellowGold,
                                                            modifier = Modifier.size(14.dp)
                                                        )
                                                        Text(
                                                            text = "KODAERAL V",
                                                            fontSize = 7.sp,
                                                            fontWeight = FontWeight.Bold,
                                                            color = Color.White
                                                        )
                                                    }
                                                    Text(
                                                        text = "•••• ${currentUser?.nrp?.takeLast(4) ?: "1234"}",
                                                        fontSize = 8.sp,
                                                        fontWeight = FontWeight.Black,
                                                        color = Color.White.copy(alpha = 0.9f)
                                                    )
                                                }
                                            }
                                        }
                                    }
                                }

                                Spacer(modifier = Modifier.height(14.dp))

                                // Indikator Carousel Dots Sesuai Figma
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.Center
                                ) {
                                    Surface(
                                        shape = CircleShape,
                                        color = LivinSkyBlue,
                                        modifier = Modifier.size(8.dp)
                                    ) {}
                                    Spacer(modifier = Modifier.width(6.dp))
                                    Surface(
                                        shape = CircleShape,
                                        color = Color(0xFFCBD5E1),
                                        modifier = Modifier.size(8.dp)
                                    ) {}
                                    Spacer(modifier = Modifier.width(6.dp))
                                    Surface(
                                        shape = CircleShape,
                                        color = Color(0xFFCBD5E1),
                                        modifier = Modifier.size(8.dp)
                                    ) {}
                                }
                            }
                        }
                    }

                    // 2. FITUR UTAMA ANDA (4 TOMBOL BULAT BESAR SESUAI FIGMA LIVIN' ELDER-FRIENDLY)
                    item {
                        Column {
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Text(
                                    text = "Fitur Utama Anda",
                                    style = MaterialTheme.typography.titleMedium,
                                    fontWeight = FontWeight.Black,
                                    color = LivinElderTextPrimary
                                )
                                Row(
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.spacedBy(4.dp),
                                    modifier = Modifier.clickable { onNavigateToLetters() }
                                ) {
                                    Text(
                                        text = "Atur",
                                        style = MaterialTheme.typography.labelMedium,
                                        fontWeight = FontWeight.Bold,
                                        color = LivinSkyBlueDark
                                    )
                                    Icon(
                                        imageVector = Icons.Default.Tune,
                                        contentDescription = null,
                                        tint = LivinSkyBlueDark,
                                        modifier = Modifier.size(16.dp)
                                    )
                                }
                            }

                            Spacer(modifier = Modifier.height(14.dp))

                            // 4 Lingkaran Aksi Utama Sejajar
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween
                            ) {
                                // 1. Presensi Kehadiran (Teal Circle)
                                LivinCircleActionButton(
                                    title = "Presensi\nKehadiran",
                                    icon = Icons.Default.HowToReg,
                                    circleColor = LivinCircleTeal,
                                    onClick = onNavigateToAttendance
                                )

                                // 2. Kas Denintel (Orange Circle)
                                LivinCircleActionButton(
                                    title = "Kas\nDenintel",
                                    icon = Icons.Default.AccountBalanceWallet,
                                    circleColor = LivinCircleOrange,
                                    onClick = onNavigateToCash
                                )

                                // 3. Kas Taktis (Blue Circle)
                                LivinCircleActionButton(
                                    title = "Kas\nTaktis",
                                    icon = Icons.Default.Security,
                                    circleColor = LivinCircleBlue,
                                    onClick = onNavigateToTechnicalCash
                                )

                                // 4. Mitra Intelijen (Lime Circle)
                                LivinCircleActionButton(
                                    title = "Mitra\nIntelijen",
                                    icon = Icons.Default.Group,
                                    circleColor = LivinCircleLime,
                                    onClick = onNavigateToMitra
                                )
                            }
                        }
                    }

                    // 3. FITUR KEDINASAN LAINNYA (FILTER CHIPS + KARTU HORIZONTAL)
                    item {
                        Column {
                            Text(
                                text = "Fitur Lainnya",
                                style = MaterialTheme.typography.titleMedium,
                                fontWeight = FontWeight.Black,
                                color = LivinElderTextPrimary
                            )

                            Spacer(modifier = Modifier.height(10.dp))

                            // Filter Chips Berjejer
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.spacedBy(8.dp)
                            ) {
                                val filterCategories = listOf("Sering Dipakai", "Surat Dinas", "SKHPP")
                                filterCategories.forEach { category ->
                                    val isSelected = selectedFilterCategory == category
                                    Surface(
                                        shape = RoundedCornerShape(20.dp),
                                        color = Color.White,
                                        border = BorderStroke(
                                            if (isSelected) 1.5.dp else 1.dp,
                                            if (isSelected) LivinSkyBlue else LivinElderBorder
                                        ),
                                        modifier = Modifier
                                            .height(36.dp)
                                            .clickable { selectedFilterCategory = category }
                                    ) {
                                        Box(
                                            contentAlignment = Alignment.Center,
                                            modifier = Modifier.padding(horizontal = 14.dp)
                                        ) {
                                            Text(
                                                text = category,
                                                style = MaterialTheme.typography.labelMedium,
                                                fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                                                color = if (isSelected) LivinSkyBlue else LivinElderTextSecondary
                                            )
                                        }
                                    }
                                }
                            }

                            Spacer(modifier = Modifier.height(12.dp))

                            // Kartu Aksi Horizontal Bergaya Livin' Menu
                            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                                LivinActionRowCard(
                                    title = "Agenda Surat Masuk & Keluar",
                                    subtitle = "Pencatatan naskah dinas satuan",
                                    icon = Icons.Default.Email,
                                    pillColor = LivinCircleTeal,
                                    onClick = onNavigateToLetters
                                )

                                LivinActionRowCard(
                                    title = "Layanan Berkas SKHPP",
                                    subtitle = "Surat keterangan penelitian personel",
                                    icon = Icons.Default.Assignment,
                                    pillColor = Color(0xFF7C3AED),
                                    onClick = onNavigateToSkhpp
                                )

                                LivinActionRowCard(
                                    title = "Tanda Tangan Elektronik Komandan",
                                    subtitle = "Persetujuan dokumen resmi dinas",
                                    icon = Icons.Default.Draw,
                                    pillColor = Color(0xFFE11D48),
                                    onClick = onNavigateToSignature
                                )

                                LivinActionRowCard(
                                    title = "Catatan Disiplin Prajurit",
                                    subtitle = "Rekap pelanggaran tata tertib",
                                    icon = Icons.Default.Report,
                                    pillColor = Color(0xFFDC2626),
                                    onClick = onNavigateToViolations
                                )

                                LivinActionRowCard(
                                    title = "Direktori Personel Satuan",
                                    subtitle = "Informasi kontak seluruh personel",
                                    icon = Icons.Default.Badge,
                                    pillColor = LivinCircleOrange,
                                    onClick = onNavigateToPersonnel
                                )
                            }
                        }
                    }

                    // 4. AGENDA SURAT TERBARU (INTEGRITAS LOG DOKUMEN)
                    item {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Text(
                                text = "AGENDA SURAT TERBARU",
                                style = MaterialTheme.typography.labelSmall,
                                fontWeight = FontWeight.Black,
                                color = LivinElderTextSecondary,
                                letterSpacing = 1.sp
                            )
                            TextButton(onClick = onNavigateToLetters) {
                                Text(
                                    text = "Lihat Semua",
                                    style = MaterialTheme.typography.labelSmall,
                                    fontWeight = FontWeight.Bold,
                                    color = LivinSkyBlueDark
                                )
                            }
                        }
                    }

                    if (state.recentLetters.isEmpty()) {
                        item {
                            EmptyStateView(
                                title = "Belum Ada Surat",
                                message = "Belum terdapat catatan surat masuk atau keluar terkini."
                            )
                        }
                    } else {
                        items(state.recentLetters) { letter ->
                            ElevatedCard(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .clickable { onNavigateToLetters() },
                                shape = RoundedCornerShape(16.dp),
                                colors = CardDefaults.elevatedCardColors(containerColor = Color.White)
                            ) {
                                Column(
                                    modifier = Modifier.padding(14.dp),
                                    verticalArrangement = Arrangement.spacedBy(6.dp)
                                ) {
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Text(
                                            text = letter.letterNumber.ifEmpty { "Tanpa Nomor" },
                                            style = MaterialTheme.typography.labelLarge,
                                            fontWeight = FontWeight.Bold,
                                            color = LivinSkyBlueDark
                                        )
                                        StatusPill(status = letter.type)
                                    }
                                    Text(
                                        text = letter.subject.ifEmpty { "Perihal Surat Kedinasan" },
                                        style = MaterialTheme.typography.bodyMedium,
                                        fontWeight = FontWeight.SemiBold,
                                        maxLines = 2,
                                        overflow = TextOverflow.Ellipsis
                                    )
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween
                                    ) {
                                        Text(
                                            text = "Dari: ${letter.sender.ifEmpty { "-" }}",
                                            style = MaterialTheme.typography.labelSmall,
                                            color = LivinElderTextSecondary
                                        )
                                        Text(
                                            text = letter.date,
                                            style = MaterialTheme.typography.labelSmall,
                                            color = LivinElderTextSecondary
                                        )
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // DIALOG AKSES TERBATAS
        if (showPermissionDialog) {
            AlertDialog(
                onDismissRequest = { showPermissionDialog = false },
                icon = {
                    Icon(
                        imageVector = Icons.Default.Lock,
                        contentDescription = null,
                        tint = LivinSkyBlueDark,
                        modifier = Modifier.size(36.dp)
                    )
                },
                title = {
                    Text(
                        text = "Akses Modul Terbatas",
                        fontWeight = FontWeight.Bold,
                        style = MaterialTheme.typography.titleMedium
                    )
                },
                text = {
                    Text(
                        text = "Akun Anda saat ini belum memiliki izin khusus untuk membuka modul '$deniedModuleName'. Silakan berkoordinasi dengan Komandan Satuan atau Administrator Denintel.",
                        style = MaterialTheme.typography.bodyMedium,
                        color = LivinElderTextSecondary
                    )
                },
                confirmButton = {
                    Button(
                        onClick = { showPermissionDialog = false },
                        colors = ButtonDefaults.buttonColors(containerColor = LivinSkyBlue)
                    ) {
                        Text("MENGERTI", fontWeight = FontWeight.Bold)
                    }
                }
            )
        }
    }
}

// KOMPONEN TOMBOL BULAT BESAR FITUR UTAMA SESUAI FIGMA LIVIN' ELDER-FRIENDLY
@Composable
fun LivinCircleActionButton(
    title: String,
    icon: ImageVector,
    circleColor: Color,
    onClick: () -> Unit
) {
    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        modifier = Modifier
            .clickable { onClick() }
            .width(76.dp)
    ) {
        Surface(
            shape = CircleShape,
            color = circleColor,
            modifier = Modifier.size(60.dp),
            shadowElevation = 3.dp
        ) {
            Box(contentAlignment = Alignment.Center) {
                Icon(
                    imageVector = icon,
                    contentDescription = title,
                    tint = Color.White,
                    modifier = Modifier.size(28.dp)
                )
            }
        }
        Spacer(modifier = Modifier.height(6.dp))
        Text(
            text = title,
            style = MaterialTheme.typography.labelSmall,
            fontWeight = FontWeight.Bold,
            color = LivinElderTextPrimary,
            textAlign = TextAlign.Center,
            fontSize = 11.sp,
            lineHeight = 14.sp
        )
    }
}

// KOMPONEN KARTU BARIS AKSI SESUAI FIGMA LIVIN'
@Composable
fun LivinActionRowCard(
    title: String,
    subtitle: String,
    icon: ImageVector,
    pillColor: Color,
    onClick: () -> Unit
) {
    Surface(
        modifier = Modifier
            .fillMaxWidth()
            .clickable { onClick() },
        shape = RoundedCornerShape(18.dp),
        color = Color.White,
        border = BorderStroke(1.dp, LivinElderBorder),
        shadowElevation = 1.dp
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(14.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(14.dp),
                modifier = Modifier.weight(1f)
            ) {
                Surface(
                    shape = RoundedCornerShape(12.dp),
                    color = pillColor.copy(alpha = 0.15f),
                    modifier = Modifier.size(42.dp)
                ) {
                    Box(contentAlignment = Alignment.Center) {
                        Icon(
                            imageVector = icon,
                            contentDescription = null,
                            tint = pillColor,
                            modifier = Modifier.size(22.dp)
                        )
                    }
                }

                Column {
                    Text(
                        text = title,
                        style = MaterialTheme.typography.bodyMedium,
                        fontWeight = FontWeight.Bold,
                        color = LivinElderTextPrimary
                    )
                    Text(
                        text = subtitle,
                        style = MaterialTheme.typography.labelSmall,
                        color = LivinElderTextSecondary
                    )
                }
            }

            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(2.dp)
            ) {
                Text(
                    text = "Buka",
                    style = MaterialTheme.typography.labelMedium,
                    fontWeight = FontWeight.Bold,
                    color = LivinSkyBlueDark
                )
                Icon(
                    imageVector = Icons.Default.ChevronRight,
                    contentDescription = null,
                    tint = LivinSkyBlueDark,
                    modifier = Modifier.size(16.dp)
                )
            }
        }
    }
}
