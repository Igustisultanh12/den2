package id.my.sisinden.app.ui.dashboard

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
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import id.my.sisinden.app.core.ui.components.*
import id.my.sisinden.app.core.ui.theme.*
import id.my.sisinden.app.data.model.CashTransactionDto
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
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(MaterialTheme.colorScheme.background)
                    .statusBarsPadding()
                    .padding(horizontal = 20.dp, vertical = 12.dp)
            ) {
                // Header Profil Pengguna & Aksi Cepat (Sesuai Referensi 2)
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
                            color = SindenOceanPrimary,
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
                                text = "Halo, ${currentUser?.name?.ifEmpty { "Personel Intel" } ?: "Personel"}",
                                style = MaterialTheme.typography.titleMedium,
                                fontWeight = FontWeight.Black,
                                color = MaterialTheme.colorScheme.onBackground
                            )
                            Text(
                                text = "Mencatat sebagai ${currentUser?.pangkat ?: "Prajurit"} (${currentUser?.role?.uppercase() ?: "USER"})",
                                style = MaterialTheme.typography.labelSmall,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                                fontWeight = FontWeight.SemiBold
                            )
                        }
                    }

                    Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                        IconButton(onClick = onNavigateToNotifications) {
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
                                    imageVector = Icons.Default.Notifications,
                                    contentDescription = "Notifikasi",
                                    tint = MaterialTheme.colorScheme.onSurface
                                )
                            }
                        }

                        IconButton(onClick = onNavigateToSettings) {
                            Icon(
                                imageVector = Icons.Default.Settings,
                                contentDescription = "Pengaturan",
                                tint = MaterialTheme.colorScheme.onSurface
                            )
                        }
                    }
                }

                Spacer(modifier = Modifier.height(10.dp))

                // Filter Pill Periode (Referensi 2)
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    Surface(
                        shape = RoundedCornerShape(20.dp),
                        color = MaterialTheme.colorScheme.surface,
                        border = androidx.compose.foundation.BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant.copy(alpha = 0.5f)),
                        modifier = Modifier.clickable { viewModel.loadDashboardData(context) }
                    ) {
                        Row(
                            modifier = Modifier.padding(horizontal = 14.dp, vertical = 6.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(6.dp)
                        ) {
                            Icon(Icons.Default.CalendarToday, contentDescription = null, modifier = Modifier.size(14.dp), tint = MaterialTheme.colorScheme.primary)
                            Text("Bulan", style = MaterialTheme.typography.labelMedium, fontWeight = FontWeight.Bold)
                            Icon(Icons.Default.ArrowDropDown, contentDescription = null, modifier = Modifier.size(16.dp))
                        }
                    }

                    Surface(
                        shape = RoundedCornerShape(20.dp),
                        color = MaterialTheme.colorScheme.surface,
                        border = androidx.compose.foundation.BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant.copy(alpha = 0.5f))
                    ) {
                        Row(
                            modifier = Modifier.padding(horizontal = 14.dp, vertical = 6.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(6.dp)
                        ) {
                            Text("Oktober 2026", style = MaterialTheme.typography.labelMedium, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.primary)
                            Icon(Icons.Default.ArrowDropDown, contentDescription = null, modifier = Modifier.size(16.dp))
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
            contentPadding = PaddingValues(horizontal = 16.dp, vertical = 12.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            when (val state = uiState) {
                is DashboardUiState.Loading -> {
                    item {
                        ShimmerPlaceholder(modifier = Modifier.fillMaxWidth().height(180.dp))
                    }
                    item {
                        ShimmerPlaceholder(modifier = Modifier.fillMaxWidth().height(120.dp))
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
                    // 0. KARTU PRESENSI KEHADIRAN DINAS CEPAT (LIVIN' ELDER-FRIENDLY ATTENDANCE BANNER)
                    item {
                        Surface(
                            modifier = Modifier
                                .fillMaxWidth()
                                .clickable { onNavigateToAttendance() },
                            shape = RoundedCornerShape(22.dp),
                            color = LivinCardBg,
                            border = androidx.compose.foundation.BorderStroke(1.dp, LivinElderBorder),
                            shadowElevation = 3.dp
                        ) {
                            Row(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(18.dp),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.SpaceBetween
                            ) {
                                Row(
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.spacedBy(14.dp),
                                    modifier = Modifier.weight(1f)
                                ) {
                                    Surface(
                                        shape = RoundedCornerShape(16.dp),
                                        color = Color(0xFFE0F2FE),
                                        modifier = Modifier.size(50.dp)
                                    ) {
                                        Box(contentAlignment = Alignment.Center) {
                                            Icon(
                                                imageVector = Icons.Default.HowToReg,
                                                contentDescription = "Presensi",
                                                tint = LivinRoyalBlue,
                                                modifier = Modifier.size(28.dp)
                                            )
                                        }
                                    }

                                    Column {
                                        Text(
                                            text = "Presensi Kehadiran Dinas",
                                            style = MaterialTheme.typography.titleSmall,
                                            fontWeight = FontWeight.Black,
                                            color = LivinElderTextPrimary
                                        )
                                        Spacer(modifier = Modifier.height(2.dp))
                                        Text(
                                            text = "Lapor kehadiran personel & dinas luar",
                                            style = MaterialTheme.typography.bodySmall,
                                            color = LivinElderTextSecondary
                                        )
                                    }
                                }

                                Surface(
                                    shape = RoundedCornerShape(12.dp),
                                    color = LivinRoyalBlue
                                ) {
                                    Row(
                                        modifier = Modifier.padding(horizontal = 12.dp, vertical = 8.dp),
                                        verticalAlignment = Alignment.CenterVertically,
                                        horizontalArrangement = Arrangement.spacedBy(4.dp)
                                    ) {
                                        Text(
                                            text = "ABSEN",
                                            style = MaterialTheme.typography.labelSmall,
                                            fontWeight = FontWeight.Black,
                                            color = Color.White
                                        )
                                        Icon(
                                            imageVector = Icons.Default.ChevronRight,
                                            contentDescription = null,
                                            tint = Color.White,
                                            modifier = Modifier.size(16.dp)
                                        )
                                    }
                                }
                            }
                        }
                    }

                    // 1. KARTU SALDO UTAMA (LIVIN' ROYAL BLUE GRADIENT DENGAN PRIVASI TOGGLE)
                    if (config?.mobileShowBalanceCard != false) {
                        item {
                            Surface(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .clickable { onNavigateToCash() },
                                shape = RoundedCornerShape(24.dp),
                                shadowElevation = 6.dp
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
                                                text = "SALDO KAS UTAMA DENINTEL",
                                                style = MaterialTheme.typography.labelSmall,
                                                fontWeight = FontWeight.Bold,
                                                letterSpacing = 0.8.sp,
                                                color = Color(0xFFBAE6FD)
                                            )

                                            Row(
                                                verticalAlignment = Alignment.CenterVertically,
                                                horizontalArrangement = Arrangement.spacedBy(6.dp)
                                            ) {
                                                Surface(
                                                    shape = RoundedCornerShape(10.dp),
                                                    color = SindenNavyAccent
                                                ) {
                                                    Text(
                                                        text = "Terverifikasi",
                                                        style = MaterialTheme.typography.labelSmall,
                                                        fontSize = 10.sp,
                                                        fontWeight = FontWeight.Bold,
                                                        color = Color(0xFF60A5FA),
                                                        modifier = Modifier.padding(horizontal = 8.dp, vertical = 3.dp)
                                                    )
                                                }

                                                IconButton(
                                                    onClick = { viewModel.togglePrivacy() },
                                                    modifier = Modifier.size(30.dp)
                                                ) {
                                                    Icon(
                                                        imageVector = if (isPrivacyHidden) Icons.Default.VisibilityOff else Icons.Default.Visibility,
                                                        contentDescription = "Toggle Privasi",
                                                        tint = Color.White.copy(alpha = 0.85f),
                                                        modifier = Modifier.size(17.dp)
                                                    )
                                                }
                                            }
                                        }

                                        Spacer(modifier = Modifier.height(4.dp))

                                        Text(
                                            text = if (isPrivacyHidden) "IDR •••••••" else formatter.format(state.stats.cashBalance),
                                            style = MaterialTheme.typography.headlineLarge,
                                            fontWeight = FontWeight.Black,
                                            color = Color.White,
                                            letterSpacing = 0.5.sp
                                        )

                                        Spacer(modifier = Modifier.height(14.dp))

                                        // Trend Pill
                                        Surface(
                                            shape = RoundedCornerShape(12.dp),
                                            color = Color.White.copy(alpha = 0.15f)
                                        ) {
                                            Row(
                                                modifier = Modifier.padding(horizontal = 10.dp, vertical = 5.dp),
                                                verticalAlignment = Alignment.CenterVertically,
                                                horizontalArrangement = Arrangement.spacedBy(6.dp)
                                            ) {
                                                Icon(
                                                    imageVector = Icons.Default.ArrowUpward,
                                                    contentDescription = null,
                                                    tint = Color(0xFF67E8F9),
                                                    modifier = Modifier.size(14.dp)
                                                )
                                                Text(
                                                    text = "${state.stats.totalTransactions} Transaksi Terverifikasi",
                                                    style = MaterialTheme.typography.labelSmall,
                                                    color = Color.White,
                                                    fontWeight = FontWeight.Bold
                                                )
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }

                    // 2. TRIO RINGKASAN KEUANGAN (SESUAI REFERENSI 2)
                    if (config?.mobileShowSummaryTrio != false) {
                        item {
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.spacedBy(10.dp)
                            ) {
                                // Pemasukan (White Card, Green Arrow Down)
                                Surface(
                                    modifier = Modifier.weight(1f),
                                    shape = RoundedCornerShape(18.dp),
                                    color = MaterialTheme.colorScheme.surface,
                                    shadowElevation = 2.dp
                                ) {
                                    Column(modifier = Modifier.padding(14.dp)) {
                                        Surface(
                                            shape = CircleShape,
                                            color = Color(0xFFDCFCE7),
                                            modifier = Modifier.size(32.dp)
                                        ) {
                                            Box(contentAlignment = Alignment.Center) {
                                                Icon(
                                                    imageVector = Icons.Default.ArrowDownward,
                                                    contentDescription = null,
                                                    tint = Color(0xFF16A34A),
                                                    modifier = Modifier.size(18.dp)
                                                )
                                            }
                                        }

                                        Spacer(modifier = Modifier.height(10.dp))
                                        Text("Pemasukan", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                                        Spacer(modifier = Modifier.height(2.dp))
                                        Text(
                                            text = if (isPrivacyHidden) "IDR •••••••" else formatter.format(state.stats.totalIncome),
                                            style = MaterialTheme.typography.bodyMedium,
                                            fontWeight = FontWeight.Black,
                                            maxLines = 1,
                                            overflow = TextOverflow.Ellipsis
                                        )
                                        Spacer(modifier = Modifier.height(6.dp))
                                        Text("Dasar perbandingan", style = MaterialTheme.typography.labelSmall, fontSize = 9.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                                    }
                                }

                                // Pengeluaran (Warm/Peach Card, Orange Arrow Up)
                                Surface(
                                    modifier = Modifier.weight(1f),
                                    shape = RoundedCornerShape(18.dp),
                                    color = Color(0xFFFFF7ED),
                                    shadowElevation = 2.dp
                                ) {
                                    Column(modifier = Modifier.padding(14.dp)) {
                                        Surface(
                                            shape = CircleShape,
                                            color = Color(0xFFFFEDD5),
                                            modifier = Modifier.size(32.dp)
                                        ) {
                                            Box(contentAlignment = Alignment.Center) {
                                                Icon(
                                                    imageVector = Icons.Default.ArrowUpward,
                                                    contentDescription = null,
                                                    tint = Color(0xFFEA580C),
                                                    modifier = Modifier.size(18.dp)
                                                )
                                            }
                                        }

                                        Spacer(modifier = Modifier.height(10.dp))
                                        Text("Pengeluaran", style = MaterialTheme.typography.labelSmall, color = Color(0xFF9A3412))
                                        Spacer(modifier = Modifier.height(2.dp))
                                        Text(
                                            text = if (isPrivacyHidden) "IDR •••••••" else formatter.format(state.stats.totalExpense),
                                            style = MaterialTheme.typography.bodyMedium,
                                            fontWeight = FontWeight.Black,
                                            color = Color(0xFF7C2D12),
                                            maxLines = 1,
                                            overflow = TextOverflow.Ellipsis
                                        )
                                        Spacer(modifier = Modifier.height(6.dp))
                                        val expensePct = if (state.stats.totalIncome > 0) ((state.stats.totalExpense / state.stats.totalIncome) * 100).toInt() else 0
                                        Text("$expensePct% dari pemasukan", style = MaterialTheme.typography.labelSmall, fontSize = 9.sp, color = Color(0xFFC2410C))
                                    }
                                }

                                // Kas Taktis (Cool Grey/Lavender Card, Wallet Icon)
                                Surface(
                                    modifier = Modifier.weight(1f),
                                    shape = RoundedCornerShape(18.dp),
                                    color = Color(0xFFF1F5F9),
                                    shadowElevation = 2.dp
                                ) {
                                    Column(modifier = Modifier.padding(14.dp)) {
                                        Surface(
                                            shape = CircleShape,
                                            color = Color(0xFFE2E8F0),
                                            modifier = Modifier.size(32.dp)
                                        ) {
                                            Box(contentAlignment = Alignment.Center) {
                                                Icon(
                                                    imageVector = Icons.Default.AccountBalanceWallet,
                                                    contentDescription = null,
                                                    tint = Color(0xFF475569),
                                                    modifier = Modifier.size(18.dp)
                                                )
                                            }
                                        }

                                        Spacer(modifier = Modifier.height(10.dp))
                                        Text("Kas Taktis", style = MaterialTheme.typography.labelSmall, color = Color(0xFF334155))
                                        Spacer(modifier = Modifier.height(2.dp))
                                        Text(
                                            text = if (isPrivacyHidden) "IDR •••••••" else formatter.format(state.stats.technicalCashBalance),
                                            style = MaterialTheme.typography.bodyMedium,
                                            fontWeight = FontWeight.Black,
                                            color = Color(0xFF0F172A),
                                            maxLines = 1,
                                            overflow = TextOverflow.Ellipsis
                                        )
                                        Spacer(modifier = Modifier.height(6.dp))
                                        Text("Dana cadangan dinas", style = MaterialTheme.typography.labelSmall, fontSize = 9.sp, color = Color(0xFF64748B))
                                    }
                                }
                            }
                        }
                    }

                    // 3. KONDISI HARI INI (SESUAI REFERENSI 2)
                    item {
                        Surface(
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(20.dp),
                            color = MaterialTheme.colorScheme.surface,
                            shadowElevation = 2.dp
                        ) {
                            Column(modifier = Modifier.padding(18.dp)) {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Row(
                                        verticalAlignment = Alignment.CenterVertically,
                                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                                    ) {
                                        Surface(
                                            shape = RoundedCornerShape(8.dp),
                                            color = Color(0xFFEFF6FF),
                                            modifier = Modifier.size(28.dp)
                                        ) {
                                            Box(contentAlignment = Alignment.Center) {
                                                Icon(Icons.Default.Home, contentDescription = null, tint = SindenOceanPrimary, modifier = Modifier.size(16.dp))
                                            }
                                        }
                                        Text(
                                            text = "KONDISI HARI INI",
                                            style = MaterialTheme.typography.labelMedium,
                                            fontWeight = FontWeight.Black,
                                            color = MaterialTheme.colorScheme.onSurfaceVariant
                                        )
                                    }

                                    Button(
                                        onClick = { onNavigateToCash() },
                                        shape = RoundedCornerShape(16.dp),
                                        colors = ButtonDefaults.buttonColors(
                                            containerColor = Color.Transparent,
                                            contentColor = MaterialTheme.colorScheme.primary
                                        ),
                                        border = androidx.compose.foundation.BorderStroke(1.dp, MaterialTheme.colorScheme.primary),
                                        contentPadding = PaddingValues(horizontal = 12.dp, vertical = 4.dp),
                                        modifier = Modifier.height(32.dp)
                                    ) {
                                        Text("Kelola Kas", style = MaterialTheme.typography.labelSmall, fontWeight = FontWeight.Bold)
                                    }
                                }

                                Spacer(modifier = Modifier.height(14.dp))

                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Column {
                                        Text(
                                            text = if (isPrivacyHidden) "IDR •••••••" else formatter.format(state.stats.cashBalance),
                                            style = MaterialTheme.typography.titleLarge,
                                            fontWeight = FontWeight.Black,
                                            color = MaterialTheme.colorScheme.onSurface
                                        )
                                        Text("Saldo operasional berjalan", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                                    }

                                    Surface(
                                        shape = RoundedCornerShape(12.dp),
                                        color = MaterialTheme.colorScheme.surfaceVariant
                                    ) {
                                        Text(
                                            text = "${state.stats.totalTransactions} Transaksi",
                                            style = MaterialTheme.typography.labelSmall,
                                            fontWeight = FontWeight.Bold,
                                            modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp)
                                        )
                                    }
                                }
                            }
                        }
                    }

                    // 4. MODUL AKSI CEPAT KEDINASAN (SEMUA MENU BISA DIKLIK, TIDAK ADA DEAD CLICK!)
                    if (config?.mobileShowQuickActions != false) {
                        item {
                            Text(
                                text = "MODUL UTAMA KEDINASAN",
                                style = MaterialTheme.typography.labelSmall,
                                fontWeight = FontWeight.Black,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                                letterSpacing = 1.sp
                            )
                        }

                        item {
                            QuickActionsGridRedesign(
                                user = currentUser,
                                onActionClick = { title, permitted, navigate ->
                                    if (permitted) {
                                        navigate()
                                    } else {
                                        deniedModuleName = title
                                        showPermissionDialog = true
                                    }
                                },
                                onNavigateToLetters = onNavigateToLetters,
                                onNavigateToSkhpp = onNavigateToSkhpp,
                                onNavigateToCash = onNavigateToCash,
                                onNavigateToTechnicalCash = onNavigateToTechnicalCash,
                                onNavigateToMitra = onNavigateToMitra,
                                onNavigateToSignature = onNavigateToSignature,
                                onNavigateToViolations = onNavigateToViolations,
                                onNavigateToPersonnel = onNavigateToPersonnel,
                                onNavigateToAttendance = onNavigateToAttendance
                            )
                        }
                    }

                    // 5. AGENDA SURAT TERBARU
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
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                                letterSpacing = 1.sp
                            )
                            TextButton(onClick = onNavigateToLetters) {
                                Text("Lihat Semua", style = MaterialTheme.typography.labelSmall, fontWeight = FontWeight.Bold)
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
                                colors = CardDefaults.elevatedCardColors(containerColor = MaterialTheme.colorScheme.surface)
                            ) {
                                Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
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
                                        Text("Dari: ${letter.sender.ifEmpty { "-" }}", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                                        Text(letter.date, style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // DIALOG INFORMASI HAK AKSES RAMAH (MENGATASI MENU TIDAK BISA DIKLIK)
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
                        text = "Akun Anda saat ini belum memiliki izin khusus untuk membuka modul '$deniedModuleName'. Silakan berkoordinasi dengan Komandan Satuan atau Administrator Denintel untuk pembukaan hak akses.",
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                },
                confirmButton = {
                    Button(
                        onClick = { showPermissionDialog = false },
                        colors = ButtonDefaults.buttonColors(containerColor = SindenTealPrimary)
                    ) {
                        Text("MENGERTI", fontWeight = FontWeight.Bold)
                    }
                }
            )
        }
    }
}

@Composable
fun QuickActionsGridRedesign(
    user: UserDto?,
    onActionClick: (String, Boolean, () -> Unit) -> Unit,
    onNavigateToLetters: () -> Unit,
    onNavigateToSkhpp: () -> Unit,
    onNavigateToCash: () -> Unit,
    onNavigateToTechnicalCash: () -> Unit,
    onNavigateToMitra: () -> Unit,
    onNavigateToSignature: () -> Unit,
    onNavigateToViolations: () -> Unit,
    onNavigateToPersonnel: () -> Unit,
    onNavigateToAttendance: () -> Unit = {}
) {
    val isAdmin = user?.isAdminOrOfficer == true

    data class ActionItem(
        val title: String,
        val icon: ImageVector,
        val color: Color,
        val containerColor: Color,
        val permitted: Boolean,
        val onClick: () -> Unit
    )

    val items = listOf(
        ActionItem("Presensi", Icons.Default.HowToReg, LivinRoyalBlue, Color(0xFFE0F2FE), true, onNavigateToAttendance),
        ActionItem("Kas Denintel", Icons.Default.AccountBalanceWallet, SindenTealPrimary, SindenPastelGreen, user == null || user.canOpenCash, onNavigateToCash),
        ActionItem("Kas Taktis", Icons.Default.Security, SindenOceanPrimary, SindenPastelBlue, user == null || user.canOpenTechnicalCash, onNavigateToTechnicalCash),
        ActionItem("Mitra Intel", Icons.Default.Group, Color(0xFFC2410C), SindenPastelOrange, user == null || user.canOpenMitra, onNavigateToMitra),
        ActionItem("Agenda Surat", Icons.Default.Email, Color(0xFF0369A1), SindenPastelBlue, true, onNavigateToLetters),
        ActionItem("SKHPP", Icons.Default.Assignment, Color(0xFF7C3AED), SindenPastelPurple, true, onNavigateToSkhpp),
        ActionItem("Tanda Tangan", Icons.Default.Draw, Color(0xFFE11D48), SindenPastelRed, user == null || user.isAdminOrOfficer || user.canAccessSignature, onNavigateToSignature),
        ActionItem("Pelanggaran", Icons.Default.Report, Color(0xFFDC2626), SindenPastelRed, true, onNavigateToViolations),
        ActionItem("Personel", Icons.Default.Badge, Color(0xFFD97706), SindenPastelYellow, true, onNavigateToPersonnel)
    )

    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
        items.chunked(4).forEach { rowItems ->
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                rowItems.forEach { item ->
                    Surface(
                        modifier = Modifier
                            .weight(1f)
                            .clickable {
                                onActionClick(item.title, item.permitted, item.onClick)
                            },
                        shape = RoundedCornerShape(18.dp),
                        color = MaterialTheme.colorScheme.surface,
                        shadowElevation = 1.dp
                    ) {
                        Column(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(vertical = 12.dp, horizontal = 4.dp),
                            horizontalAlignment = Alignment.CenterHorizontally,
                            verticalArrangement = Arrangement.Center
                        ) {
                            Surface(
                                shape = RoundedCornerShape(14.dp),
                                color = if (item.permitted) item.containerColor else MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.5f),
                                modifier = Modifier.size(46.dp)
                            ) {
                                Box(contentAlignment = Alignment.Center) {
                                    Icon(
                                        imageVector = item.icon,
                                        contentDescription = item.title,
                                        tint = if (item.permitted) item.color else MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.5f),
                                        modifier = Modifier.size(24.dp)
                                    )
                                }
                            }
                            Spacer(modifier = Modifier.height(6.dp))
                            Text(
                                text = item.title,
                                style = MaterialTheme.typography.labelSmall,
                                fontWeight = FontWeight.Bold,
                                color = if (item.permitted) MaterialTheme.colorScheme.onSurface else MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.6f),
                                maxLines = 1,
                                overflow = TextOverflow.Ellipsis
                            )
                        }
                    }
                }
                if (rowItems.size < 4) {
                    repeat(4 - rowItems.size) {
                        Spacer(modifier = Modifier.weight(1f))
                    }
                }
            }
        }
    }
}
