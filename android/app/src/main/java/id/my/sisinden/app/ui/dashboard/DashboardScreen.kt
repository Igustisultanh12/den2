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
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import id.my.sisinden.app.core.ui.components.*
import id.my.sisinden.app.core.ui.theme.*
import id.my.sisinden.app.data.model.UserDto
import java.text.NumberFormat
import java.util.Locale

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
    onNavigateToSettings: () -> Unit
) {
    val currentUser by viewModel.currentUser.collectAsState(initial = null)
    val uiState by viewModel.uiState.collectAsState()

    Scaffold(
        topBar = {
            SindenTopBar(
                title = "SI SINDEN",
                subtitle = "Detasemen Intelijen Kodaeral V",
                actions = {
                    IconButton(onClick = onNavigateToNotifications) {
                        Icon(
                            imageVector = Icons.Default.Notifications,
                            contentDescription = "Notifikasi",
                            tint = MaterialTheme.colorScheme.onSurface
                        )
                    }
                    IconButton(onClick = onNavigateToSettings) {
                        Icon(
                            imageVector = Icons.Default.Settings,
                            contentDescription = "Pengaturan",
                            tint = MaterialTheme.colorScheme.onSurface
                        )
                    }
                }
            )
        },
        containerColor = MaterialTheme.colorScheme.background
    ) { paddingValues ->
        LazyColumn(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues),
            contentPadding = PaddingValues(16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            // 1. Kartu Profil & Sambutan Personel
            item {
                UserProfileCard(user = currentUser)
            }

            // 2. Statistik Cepat Kedinasan
            item {
                when (val state = uiState) {
                    is DashboardUiState.Loading -> {
                        ShimmerPlaceholder(
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(96.dp)
                        )
                    }
                    is DashboardUiState.Success -> {
                        StatsSection(
                            stats = state.stats,
                            onCashClick = onNavigateToCash,
                            onLettersClick = onNavigateToLetters,
                            onSkhppClick = onNavigateToSkhpp
                        )
                    }
                    is DashboardUiState.Error -> {
                        ErrorStateView(
                            message = state.message,
                            onRetry = { viewModel.loadDashboardData() }
                        )
                    }
                }
            }

            // 3. Grid Menu Modul Kedinasan
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
                QuickActionsGrid(
                    user = currentUser,
                    onNavigateToLetters = onNavigateToLetters,
                    onNavigateToSkhpp = onNavigateToSkhpp,
                    onNavigateToCash = onNavigateToCash,
                    onNavigateToTechnicalCash = onNavigateToTechnicalCash,
                    onNavigateToMitra = onNavigateToMitra,
                    onNavigateToSignature = onNavigateToSignature,
                    onNavigateToViolations = onNavigateToViolations,
                    onNavigateToPersonnel = onNavigateToPersonnel
                )
            }

            // 4. Riwayat Agenda Surat Terkini
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
                        Text(
                            text = "Lihat Semua",
                            style = MaterialTheme.typography.labelSmall,
                            color = MaterialTheme.colorScheme.primary,
                            fontWeight = FontWeight.Bold
                        )
                    }
                }
            }

            when (val state = uiState) {
                is DashboardUiState.Success -> {
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
                                        color = MaterialTheme.colorScheme.onSurface,
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
                else -> {}
            }
        }
    }
}

@Composable
fun UserProfileCard(user: UserDto?) {
    Card(
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        modifier = Modifier.fillMaxWidth()
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(14.dp)
        ) {
            Box(
                modifier = Modifier
                    .size(52.dp)
                    .clip(CircleShape)
                    .background(SindenTealPrimary),
                contentAlignment = Alignment.Center
            ) {
                Text(
                    text = (user?.name?.take(2) ?: "DE").uppercase(),
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Black,
                    color = Color.White
                )
            }

            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = "${user?.pangkat ?: "Personel"} ${user?.name ?: "Denintel"}",
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.onSurface,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
                Text(
                    text = "NRP: ${user?.nrp ?: "-"} | Korps: ${user?.korps?.ifEmpty { "-" } ?: "-"}",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
                Text(
                    text = "Jabatan: ${user?.jabatan ?: "Staf Denintel"}",
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.primary,
                    fontWeight = FontWeight.Bold
                )
            }
        }
    }
}

@Composable
fun StatsSection(
    stats: DashboardStats,
    onCashClick: () -> Unit,
    onLettersClick: () -> Unit,
    onSkhppClick: () -> Unit
) {
    val formatter = NumberFormat.getCurrencyInstance(Locale("id", "ID")).apply {
        maximumFractionDigits = 0
    }

    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
        // Saldo Kas Utama
        ElevatedCard(
            modifier = Modifier
                .fillMaxWidth()
                .clickable { onCashClick() },
            shape = RoundedCornerShape(18.dp),
            colors = CardDefaults.elevatedCardColors(containerColor = SindenTealPrimary)
        ) {
            Column(modifier = Modifier.padding(18.dp)) {
                Text(
                    text = "SALDO KAS DENINTEL",
                    style = MaterialTheme.typography.labelSmall,
                    fontWeight = FontWeight.Black,
                    color = Color.White.copy(alpha = 0.85f),
                    letterSpacing = 1.sp
                )
                Spacer(modifier = Modifier.height(4.dp))
                Text(
                    text = formatter.format(stats.cashBalance),
                    style = MaterialTheme.typography.headlineMedium,
                    fontWeight = FontWeight.Black,
                    color = Color.White
                )
                Spacer(modifier = Modifier.height(6.dp))
                Text(
                    text = "Klik untuk rincian arus kas masuk & keluar",
                    style = MaterialTheme.typography.labelSmall,
                    color = Color.White.copy(alpha = 0.75f)
                )
            }
        }

        // Row Statistik Surat & SKHPP
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            Card(
                modifier = Modifier
                    .weight(1f)
                    .clickable { onLettersClick() },
                shape = RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
            ) {
                Column(modifier = Modifier.padding(14.dp)) {
                    Text(text = "Total Surat", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    Text(text = "${stats.totalLetters}", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Black, color = SindenOceanPrimary)
                    Text(text = "Agenda terdaftar", style = MaterialTheme.typography.labelSmall, fontSize = 9.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
            }

            Card(
                modifier = Modifier
                    .weight(1f)
                    .clickable { onSkhppClick() },
                shape = RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
            ) {
                Column(modifier = Modifier.padding(14.dp)) {
                    Text(text = "SKHPP Tertunda", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    Text(text = "${stats.pendingSkhpp}", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Black, color = SindenGold)
                    Text(text = "Menunggu verifikasi", style = MaterialTheme.typography.labelSmall, fontSize = 9.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
            }
        }
    }
}

@Composable
fun QuickActionsGrid(
    user: UserDto?,
    onNavigateToLetters: () -> Unit,
    onNavigateToSkhpp: () -> Unit,
    onNavigateToCash: () -> Unit,
    onNavigateToTechnicalCash: () -> Unit,
    onNavigateToMitra: () -> Unit,
    onNavigateToSignature: () -> Unit,
    onNavigateToViolations: () -> Unit,
    onNavigateToPersonnel: () -> Unit
) {
    data class MenuItem(
        val title: String,
        val icon: ImageVector,
        val color: Color,
        val enabled: Boolean,
        val onClick: () -> Unit
    )

    val items = listOf(
        MenuItem("Agenda Surat", Icons.Default.Email, SindenOceanPrimary, user?.canAccessAgenda ?: true, onNavigateToLetters),
        MenuItem("Penerbitan SKHPP", Icons.Default.Assignment, SindenTealPrimary, user?.canAccessSkhpp ?: true, onNavigateToSkhpp),
        MenuItem("Kas Denintel", Icons.Default.AccountBalanceWallet, SindenGold, user?.canAccessCash ?: false, onNavigateToCash),
        MenuItem("Kas Taktis", Icons.Default.Security, Color(0xFF8B5CF6), user?.canAccessTechnicalCash ?: false, onNavigateToTechnicalCash),
        MenuItem("Mitra Intel", Icons.Default.Group, Color(0xFF0D9488), user?.canAccessMitra ?: false, onNavigateToMitra),
        MenuItem("Tanda Tangan", Icons.Default.Draw, Color(0xFFE11D48), user?.canAccessSignature ?: true, onNavigateToSignature),
        MenuItem("Pelanggaran", Icons.Default.Report, Color(0xFFDC2626), user?.canAccessViolations ?: true, onNavigateToViolations),
        MenuItem("Personel", Icons.Default.Badge, SindenOceanPrimary, user?.canAccessUsers ?: true, onNavigateToPersonnel)
    )

    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
        items.chunked(4).forEach { rowItems ->
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                rowItems.forEach { item ->
                    Card(
                        modifier = Modifier
                            .weight(1f)
                            .clickable(enabled = item.enabled) { item.onClick() },
                        shape = RoundedCornerShape(16.dp),
                        colors = CardDefaults.cardColors(
                            containerColor = if (item.enabled) MaterialTheme.colorScheme.surface else MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.5f)
                        )
                    ) {
                        Column(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(vertical = 12.dp, horizontal = 4.dp),
                            horizontalAlignment = Alignment.CenterHorizontally,
                            verticalArrangement = Arrangement.Center
                        ) {
                            Box(
                                modifier = Modifier
                                    .size(40.dp)
                                    .clip(CircleShape)
                                    .background(item.color.copy(alpha = if (item.enabled) 0.12f else 0.05f)),
                                contentAlignment = Alignment.Center
                            ) {
                                Icon(
                                    imageVector = item.icon,
                                    contentDescription = item.title,
                                    tint = if (item.enabled) item.color else MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.4f),
                                    modifier = Modifier.size(22.dp)
                                )
                            }
                            Spacer(modifier = Modifier.height(6.dp))
                            Text(
                                text = item.title,
                                style = MaterialTheme.typography.labelSmall,
                                fontWeight = FontWeight.Bold,
                                color = if (item.enabled) MaterialTheme.colorScheme.onSurface else MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.5f),
                                maxLines = 1,
                                overflow = TextOverflow.Ellipsis
                            )
                        }
                    }
                }
            }
        }
    }
}
