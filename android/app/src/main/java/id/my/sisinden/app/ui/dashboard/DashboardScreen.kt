package id.my.sisinden.app.ui.dashboard

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.animateColorAsState
import androidx.compose.foundation.BorderStroke
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
import androidx.compose.ui.draw.clip
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
    val isDarkMode by viewModel.isDarkMode.collectAsState(initial = null)
    val uiState by viewModel.uiState.collectAsState()
    val isPrivacyHidden by viewModel.isPrivacyHidden.collectAsState()
    val config by viewModel.configState.collectAsState()

    var showPermissionDialog by remember { mutableStateOf(false) }
    var deniedModuleName by remember { mutableStateOf("") }
    var selectedFilterCategory by remember { mutableStateOf("Semua Fitur") }

    LaunchedEffect(Unit) {
        viewModel.loadDashboardData(context)
    }

    val formatter = remember {
        NumberFormat.getCurrencyInstance(Locale("id", "ID")).apply {
            maximumFractionDigits = 0
        }
    }

    val userRole = currentUser?.role?.lowercase() ?: "user"
    val canAccessCash = userRole in listOf("admin", "superadmin", "bendahara", "keuangan", "komandan")
    val canAccessSignature = userRole in listOf("admin", "superadmin", "komandan")

    Scaffold(
        topBar = {
            // Modern SaaS Dashboard Executive Header
            Surface(
                color = MaterialTheme.colorScheme.surface,
                border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant.copy(alpha = 0.5f)),
                shadowElevation = 0.dp
            ) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .statusBarsPadding()
                        .padding(horizontal = 18.dp, vertical = 12.dp)
                ) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Column {
                            Row(
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(8.dp)
                            ) {
                                Text(
                                    text = "SISINDEN",
                                    style = MaterialTheme.typography.titleLarge,
                                    fontWeight = FontWeight.Black,
                                    color = MaterialTheme.colorScheme.primary,
                                    letterSpacing = 1.sp
                                )
                                Surface(
                                    shape = RoundedCornerShape(6.dp),
                                    color = MaterialTheme.colorScheme.primaryContainer.copy(alpha = 0.6f)
                                ) {
                                    Text(
                                        text = "KODAERAL V",
                                        style = MaterialTheme.typography.labelSmall,
                                        fontWeight = FontWeight.Bold,
                                        color = MaterialTheme.colorScheme.onPrimaryContainer,
                                        fontSize = 9.sp,
                                        modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
                                    )
                                }
                            }
                            Text(
                                text = "Selamat bertugas, ${currentUser?.name?.ifEmpty { "Personel Intelijen" } ?: "Personel Intelijen"}",
                                style = MaterialTheme.typography.bodySmall,
                                color = MaterialTheme.colorScheme.onSurfaceVariant
                            )
                        }

                        Row(
                            horizontalArrangement = Arrangement.spacedBy(8.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            // Interactive Theme Toggle Button (Light / Dark)
                            Surface(
                                shape = RoundedCornerShape(10.dp),
                                color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.5f),
                                border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant.copy(alpha = 0.5f)),
                                modifier = Modifier
                                    .size(38.dp)
                                    .bounceClick {
                                        viewModel.toggleDarkMode(isDarkMode)
                                    }
                            ) {
                                Box(contentAlignment = Alignment.Center) {
                                    Icon(
                                        imageVector = if (isDarkMode == true) Icons.Default.LightMode else Icons.Default.DarkMode,
                                        contentDescription = "Ganti Tema",
                                        tint = if (isDarkMode == true) SindenGold else MaterialTheme.colorScheme.onSurfaceVariant,
                                        modifier = Modifier.size(19.dp)
                                    )
                                }
                            }

                            // Interactive Notifications Icon with Badge
                            Surface(
                                shape = RoundedCornerShape(10.dp),
                                color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.5f),
                                border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant.copy(alpha = 0.5f)),
                                modifier = Modifier
                                    .size(38.dp)
                                    .bounceClick { onNavigateToNotifications() }
                            ) {
                                Box(contentAlignment = Alignment.Center) {
                                    BadgedBox(
                                        badge = {
                                            if (uiState is DashboardUiState.Success && (uiState as DashboardUiState.Success).stats.unreadNotifCount > 0) {
                                                Badge(containerColor = StatusError) {
                                                    Text(
                                                        text = "${(uiState as DashboardUiState.Success).stats.unreadNotifCount}",
                                                        color = Color.White,
                                                        fontSize = 9.sp,
                                                        fontWeight = FontWeight.Bold
                                                    )
                                                }
                                            }
                                        }
                                    ) {
                                        Icon(
                                            imageVector = Icons.Default.Notifications,
                                            contentDescription = "Notifikasi",
                                            tint = MaterialTheme.colorScheme.onSurfaceVariant,
                                            modifier = Modifier.size(19.dp)
                                        )
                                    }
                                }
                            }
                        }
                    }
                }
            }
        },
        containerColor = MaterialTheme.colorScheme.background
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
                        ShimmerLoadingList()
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
                    val stats = state.stats

                    // 1. RINGKASAN METRIK OPERASIONAL SAAS (2x2 Grid)
                    item {
                        Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Text(
                                    text = "RINGKASAN OPERASIONAL",
                                    style = MaterialTheme.typography.labelSmall,
                                    fontWeight = FontWeight.Black,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                                    letterSpacing = 1.sp
                                )
                                Surface(
                                    shape = RoundedCornerShape(6.dp),
                                    color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.6f),
                                    modifier = Modifier.bounceClick(scaleDown = 0.9f) { viewModel.togglePrivacy() }
                                ) {
                                    Row(
                                        modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp),
                                        verticalAlignment = Alignment.CenterVertically,
                                        horizontalArrangement = Arrangement.spacedBy(4.dp)
                                    ) {
                                        Icon(
                                            imageVector = if (isPrivacyHidden) Icons.Default.VisibilityOff else Icons.Default.Visibility,
                                            contentDescription = "Privasi Saldo",
                                            tint = MaterialTheme.colorScheme.onSurfaceVariant,
                                            modifier = Modifier.size(13.dp)
                                        )
                                        Text(
                                            text = if (isPrivacyHidden) "Tampilkan" else "Sembunyikan",
                                            style = MaterialTheme.typography.labelSmall,
                                            fontSize = 10.sp,
                                            color = MaterialTheme.colorScheme.onSurfaceVariant
                                        )
                                    }
                                }
                            }

                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.spacedBy(10.dp)
                            ) {
                                // Stat 1: Presensi Kehadiran
                                SaaSStatCard(
                                    title = "Presensi Hari Ini",
                                    value = "Hadir Dinas",
                                    icon = Icons.Default.HowToReg,
                                    iconTint = MaterialTheme.colorScheme.primary,
                                    iconBg = MaterialTheme.colorScheme.primaryContainer.copy(alpha = 0.6f),
                                    badgeText = "PRESENSI",
                                    badgeColor = StatusSuccess,
                                    modifier = Modifier.weight(1f),
                                    onClick = onNavigateToAttendance
                                )

                                // Stat 2: Kas Denintel
                                val cashText = if (isPrivacyHidden) "••••••••" else formatter.format(stats.cashBalance)
                                SaaSStatCard(
                                    title = "Kas Denintel",
                                    value = cashText,
                                    icon = Icons.Default.AccountBalanceWallet,
                                    iconTint = Color(0xFF0284C7),
                                    iconBg = Color(0xFFE0F2FE),
                                    badgeText = "SATUAN",
                                    badgeColor = Color(0xFF0284C7),
                                    modifier = Modifier.weight(1f),
                                    onClick = {
                                        if (canAccessCash) onNavigateToCash()
                                        else {
                                            deniedModuleName = "Kas Denintel"
                                            showPermissionDialog = true
                                        }
                                    }
                                )
                            }

                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.spacedBy(10.dp)
                            ) {
                                // Stat 3: Kas Taktis Satuan
                                val techText = if (isPrivacyHidden) "••••••••" else formatter.format(stats.technicalCashBalance)
                                SaaSStatCard(
                                    title = "Kas Taktis Operasi",
                                    value = techText,
                                    icon = Icons.Default.MonetizationOn,
                                    iconTint = Color(0xFFFF7A00),
                                    iconBg = Color(0xFFFFEDD5),
                                    badgeText = "DANA TAKTIS",
                                    badgeColor = Color(0xFFFF7A00),
                                    modifier = Modifier.weight(1f),
                                    onClick = {
                                        if (canAccessCash) onNavigateToTechnicalCash()
                                        else {
                                            deniedModuleName = "Kas Taktis Satuan"
                                            showPermissionDialog = true
                                        }
                                    }
                                )

                                // Stat 4: Agenda Surat
                                SaaSStatCard(
                                    title = "Agenda Surat",
                                    value = "${stats.totalLetters} Naskah",
                                    icon = Icons.Default.Email,
                                    iconTint = Color(0xFF8B5CF6),
                                    iconBg = Color(0xFFEDE9FE),
                                    badgeText = "DINAS",
                                    badgeColor = Color(0xFF8B5CF6),
                                    modifier = Modifier.weight(1f),
                                    onClick = onNavigateToLetters
                                )
                            }
                        }
                    }

                    // 2. MODUL OPERASIONAL SAAS (8 Interactive Tiles)
                    item {
                        Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                            Text(
                                text = "MENU OPERASIONAL DINAS",
                                style = MaterialTheme.typography.labelSmall,
                                fontWeight = FontWeight.Black,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                                letterSpacing = 1.sp
                            )

                            // Row 1
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.spacedBy(10.dp)
                            ) {
                                SaaSActionTile(
                                    title = "Presensi",
                                    subtitle = "Kehadiran",
                                    icon = Icons.Default.HowToReg,
                                    iconColor = MaterialTheme.colorScheme.primary,
                                    modifier = Modifier.weight(1f),
                                    onClick = onNavigateToAttendance
                                )
                                SaaSActionTile(
                                    title = "Kas Denintel",
                                    subtitle = "Keuangan",
                                    icon = Icons.Default.AccountBalanceWallet,
                                    iconColor = Color(0xFF0284C7),
                                    modifier = Modifier.weight(1f),
                                    onClick = {
                                        if (canAccessCash) onNavigateToCash()
                                        else {
                                            deniedModuleName = "Kas Denintel"
                                            showPermissionDialog = true
                                        }
                                    }
                                )
                                SaaSActionTile(
                                    title = "Kas Taktis",
                                    subtitle = "Operasional",
                                    icon = Icons.Default.MonetizationOn,
                                    iconColor = Color(0xFFFF7A00),
                                    modifier = Modifier.weight(1f),
                                    onClick = {
                                        if (canAccessCash) onNavigateToTechnicalCash()
                                        else {
                                            deniedModuleName = "Kas Taktis Satuan"
                                            showPermissionDialog = true
                                        }
                                    }
                                )
                                SaaSActionTile(
                                    title = "Surat Dinas",
                                    subtitle = "Agenda",
                                    icon = Icons.Default.Email,
                                    iconColor = Color(0xFF8B5CF6),
                                    modifier = Modifier.weight(1f),
                                    onClick = onNavigateToLetters
                                )
                            }

                            // Row 2
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.spacedBy(10.dp)
                            ) {
                                SaaSActionTile(
                                    title = "Layanan SKHPP",
                                    subtitle = "Penelitian",
                                    icon = Icons.Default.Assignment,
                                    iconColor = Color(0xFF10B981),
                                    modifier = Modifier.weight(1f),
                                    onClick = onNavigateToSkhpp
                                )
                                SaaSActionTile(
                                    title = "Mitra Kerja",
                                    subtitle = "Jaringan",
                                    icon = Icons.Default.Groups,
                                    iconColor = Color(0xFF06B6D4),
                                    modifier = Modifier.weight(1f),
                                    onClick = onNavigateToMitra
                                )
                                SaaSActionTile(
                                    title = "Tanda Tangan",
                                    subtitle = "Komandan",
                                    icon = Icons.Default.Draw,
                                    iconColor = Color(0xFFEF4444),
                                    modifier = Modifier.weight(1f),
                                    onClick = {
                                        if (canAccessSignature) onNavigateToSignature()
                                        else {
                                            deniedModuleName = "Tanda Tangan Elektronik Komandan"
                                            showPermissionDialog = true
                                        }
                                    }
                                )
                                SaaSActionTile(
                                    title = "Personel",
                                    subtitle = "Direktori",
                                    icon = Icons.Default.Badge,
                                    iconColor = Color(0xFFF59E0B),
                                    modifier = Modifier.weight(1f),
                                    onClick = onNavigateToPersonnel
                                )
                            }
                        }
                    }

                    // 3. PENYARING KATEGORI SAAS & DAFTAR AGENDA TERKINI
                    item {
                        Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Text(
                                    text = "AGENDA SURAT TERAKHIR",
                                    style = MaterialTheme.typography.labelSmall,
                                    fontWeight = FontWeight.Black,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                                    letterSpacing = 1.sp
                                )
                                TextButton(onClick = onNavigateToLetters) {
                                    Text(
                                        text = "Lihat Semua",
                                        style = MaterialTheme.typography.labelSmall,
                                        fontWeight = FontWeight.Bold,
                                        color = MaterialTheme.colorScheme.primary
                                    )
                                }
                            }

                            // Filter Chips
                            Row(
                                horizontalArrangement = Arrangement.spacedBy(8.dp),
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                SaaSTabChip(
                                    selected = selectedFilterCategory == "Semua Fitur",
                                    text = "Semua",
                                    onClick = { selectedFilterCategory = "Semua Fitur" }
                                )
                                SaaSTabChip(
                                    selected = selectedFilterCategory == "Surat Masuk",
                                    text = "Surat Masuk",
                                    onClick = { selectedFilterCategory = "Surat Masuk" }
                                )
                                SaaSTabChip(
                                    selected = selectedFilterCategory == "Surat Keluar",
                                    text = "Surat Keluar",
                                    onClick = { selectedFilterCategory = "Surat Keluar" }
                                )
                            }
                        }
                    }

                    val filteredLetters = when (selectedFilterCategory) {
                        "Surat Masuk" -> state.recentLetters.filter { it.type.lowercase().contains("masuk") }
                        "Surat Keluar" -> state.recentLetters.filter { it.type.lowercase().contains("keluar") }
                        else -> state.recentLetters
                    }

                    if (filteredLetters.isEmpty()) {
                        item {
                            EmptyStateView(
                                title = "Belum Ada Naskah Dinas",
                                message = "Belum ada agenda surat terbaru pada kategori yang dipilih."
                            )
                        }
                    } else {
                        items(filteredLetters) { letter ->
                            SaaSCard(
                                modifier = Modifier.fillMaxWidth(),
                                onClick = onNavigateToLetters
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
                                            color = MaterialTheme.colorScheme.primary
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
                                            color = MaterialTheme.colorScheme.onSurfaceVariant
                                        )
                                        Text(
                                            text = letter.date,
                                            style = MaterialTheme.typography.labelSmall,
                                            color = MaterialTheme.colorScheme.onSurfaceVariant
                                        )
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // Dialog Akses Terbatas
        if (showPermissionDialog) {
            AlertDialog(
                onDismissRequest = { showPermissionDialog = false },
                icon = {
                    Icon(
                        imageVector = Icons.Default.Lock,
                        contentDescription = null,
                        tint = MaterialTheme.colorScheme.primary,
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
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                },
                confirmButton = {
                    Button(
                        onClick = { showPermissionDialog = false },
                        colors = ButtonDefaults.buttonColors(containerColor = MaterialTheme.colorScheme.primary)
                    ) {
                        Text("MENGERTI", fontWeight = FontWeight.Bold)
                    }
                }
            )
        }
    }
}

/**
 * Modern interactive SaaS action tile with tactile bounce response,
 * clean subtle border, and responsive light/dark background.
 */
@Composable
fun SaaSActionTile(
    title: String,
    subtitle: String,
    icon: ImageVector,
    iconColor: Color,
    modifier: Modifier = Modifier,
    onClick: () -> Unit
) {
    SaaSCard(
        modifier = modifier,
        onClick = onClick,
        shape = RoundedCornerShape(14.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(vertical = 12.dp, horizontal = 6.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(6.dp)
        ) {
            Surface(
                shape = RoundedCornerShape(10.dp),
                color = iconColor.copy(alpha = 0.12f),
                modifier = Modifier.size(38.dp)
            ) {
                Box(contentAlignment = Alignment.Center) {
                    Icon(
                        imageVector = icon,
                        contentDescription = title,
                        tint = iconColor,
                        modifier = Modifier.size(20.dp)
                    )
                }
            }

            Column(
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.spacedBy(1.dp)
            ) {
                Text(
                    text = title,
                    style = MaterialTheme.typography.labelSmall,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.onSurface,
                    textAlign = TextAlign.Center,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
                Text(
                    text = subtitle,
                    style = MaterialTheme.typography.labelSmall,
                    fontSize = 9.sp,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    textAlign = TextAlign.Center,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
            }
        }
    }
}
