package id.my.sisinden.app.ui.dashboard

import androidx.compose.animation.animateContentSize
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowRight
import androidx.compose.material.icons.outlined.AccountBalanceWallet
import androidx.compose.material.icons.outlined.Build
import androidx.compose.material.icons.outlined.CheckCircle
import androidx.compose.material.icons.outlined.Create
import androidx.compose.material.icons.outlined.Email
import androidx.compose.material.icons.outlined.EventAvailable
import androidx.compose.material.icons.outlined.Groups
import androidx.compose.material.icons.outlined.HowToReg
import androidx.compose.material.icons.outlined.Notifications
import androidx.compose.material.icons.outlined.Settings
import androidx.compose.material.icons.outlined.VerifiedUser
import androidx.compose.material.icons.outlined.Visibility
import androidx.compose.material.icons.outlined.VisibilityOff
import androidx.compose.material3.Button
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import id.my.sisinden.app.R
import id.my.sisinden.app.core.data.SessionState
import id.my.sisinden.app.core.security.Perm
import id.my.sisinden.app.core.ui.components.CountBadge
import id.my.sisinden.app.core.ui.components.ErrorState
import id.my.sisinden.app.core.ui.components.FeatureTile
import id.my.sisinden.app.core.ui.components.FilterChipRow
import id.my.sisinden.app.core.ui.components.GradientIconTile
import id.my.sisinden.app.core.ui.components.LoadState
import id.my.sisinden.app.core.ui.components.RefreshableBox
import id.my.sisinden.app.core.ui.components.SectionHeader
import id.my.sisinden.app.core.ui.components.SindenCard
import id.my.sisinden.app.core.ui.components.SkeletonBlock
import id.my.sisinden.app.core.ui.components.SoftDivider
import id.my.sisinden.app.core.ui.components.StatTile
import id.my.sisinden.app.core.ui.components.Tone
import id.my.sisinden.app.core.ui.components.dataOrNull
import id.my.sisinden.app.core.ui.components.shimmerBrush
import id.my.sisinden.app.core.ui.components.toneAccent
import id.my.sisinden.app.core.ui.theme.Blue400
import id.my.sisinden.app.core.ui.theme.Blue600
import id.my.sisinden.app.core.ui.theme.SindenTheme
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.core.util.Fmt
import id.my.sisinden.app.data.model.DashboardDuty
import id.my.sisinden.app.data.model.DashboardResponse
import id.my.sisinden.app.data.model.DashboardSummary
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.ui.duty.MyDuty
import id.my.sisinden.app.ui.duty.MyDutyRow
import id.my.sisinden.app.ui.duty.ReminderNote
import id.my.sisinden.app.ui.navigation.Feature
import id.my.sisinden.app.ui.navigation.FeatureGroup
import id.my.sisinden.app.ui.navigation.Routes
import java.time.LocalDate

/**
 * Beranda bergaya "Elder Friendly Livin'": header biru bergradien dengan
 * kartu akun, "Fitur Utama Anda" (ubin bergradien berlabel), Daftar Jaga
 * pribadi, ringkasan sesuai hak akses, dan "Fitur Lainnya" berkategori.
 */
@Composable
fun DashboardScreen(
    repository: SindenRepository,
    session: SessionState.LoggedIn,
    onNavigate: (String) -> Unit,
) {
    val context = LocalContext.current
    val vm: DashboardViewModel = viewModel { DashboardViewModel(repository, context.applicationContext) }
    val state by vm.state.collectAsStateWithLifecycle()
    val refreshing by vm.refreshing.collectAsStateWithLifecycle()
    val hideBalance by vm.hideBalance.collectAsStateWithLifecycle()

    val data = state.dataOrNull
    val summary = data?.summary
    val perms = session.permissions
    val features = Feature.permitted(perms).filter { it != Feature.DUTY && it != Feature.ATTENDANCE }

    RefreshableBox(isRefreshing = refreshing, onRefresh = { vm.load(pullToRefresh = true) }) {
        Column(
            Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState()),
            verticalArrangement = Arrangement.spacedBy(Spacing.xl),
        ) {
            HeaderWithAccount(
                session = session,
                summary = summary,
                loading = state is LoadState.Loading,
                unread = summary?.notificationsUnread ?: 0,
                canOpenNotifications = perms.has(Perm.NOTIFICATIONS_VIEW),
                canAttend = perms.has(Perm.ATTENDANCE_VIEW),
                onNavigate = onNavigate,
            )

            if (features.isNotEmpty()) {
                MainFeatures(features.take(4), unread = summary?.notificationsUnread ?: 0, onNavigate = onNavigate)
            }

            if (perms.has(Perm.DUTY_VIEW)) {
                DutySection(
                    state = state,
                    duty = data?.duty,
                    onRetry = { vm.load() },
                    onOpenDuty = { onNavigate(Routes.DUTY) },
                )
            }

            if (summary != null) {
                SummarySection(summary, hideBalance, vm::toggleHideBalance, onNavigate)
            }

            if (features.size > 4) {
                OtherFeatures(features, onNavigate)
            }

            if (session.isStale) {
                Text(
                    "Hak akses ditampilkan dari data tersimpan. Tarik ke bawah untuk menyinkronkan dengan server.",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.padding(horizontal = Spacing.screen),
                )
            }
            Spacer(Modifier.height(Spacing.lg))
        }
    }
}

// ---------------------------------------------------------------- Header

@Composable
private fun HeaderWithAccount(
    session: SessionState.LoggedIn,
    summary: DashboardSummary?,
    loading: Boolean,
    unread: Int,
    canOpenNotifications: Boolean,
    canAttend: Boolean,
    onNavigate: (String) -> Unit,
) {
    val gradient = SindenTheme.heroGradient
    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(bottomStart = 32.dp, bottomEnd = 32.dp))
            .background(Brush.verticalGradient(gradient))
            .statusBarsPadding()
            .padding(start = Spacing.lg, end = Spacing.lg, top = Spacing.sm, bottom = Spacing.xl),
    ) {
        // Baris navigasi atas: logo + tombol berlabel (Livin: "Pesan", "Keluar")
        Row(Modifier.fillMaxWidth().padding(vertical = Spacing.sm), verticalAlignment = Alignment.CenterVertically) {
            Image(
                painterResource(R.drawable.ic_sinden_mark),
                contentDescription = null,
                modifier = Modifier.size(44.dp).clip(RoundedCornerShape(12.dp)),
            )
            Spacer(Modifier.width(Spacing.sm))
            Column(Modifier.weight(1f)) {
                Text("SINDEN 2", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Black, color = Color.White)
                Text("Denintel Koarmada 2", style = MaterialTheme.typography.labelSmall, color = Color.White.copy(alpha = 0.9f))
            }
            if (canOpenNotifications) {
                HeaderAction(Icons.Outlined.Notifications, "Warta", badge = unread) { onNavigate(Routes.NOTIFICATIONS) }
                Spacer(Modifier.width(Spacing.sm))
            }
            HeaderAction(Icons.Outlined.Settings, "Atur") { onNavigate(Routes.SETTINGS) }
        }
        Spacer(Modifier.height(Spacing.md))
        AccountCard(session, summary, loading, canAttend, onOpenAttendance = { onNavigate(Routes.ATTENDANCE) })
    }
}

/** Tombol ikon + label di header (ikon saja menyulitkan pengguna lansia). */
@Composable
private fun HeaderAction(icon: ImageVector, label: String, badge: Int = 0, onClick: () -> Unit) {
    Column(
        Modifier
            .clip(RoundedCornerShape(14.dp))
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 8.dp, vertical = 4.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Box {
            Icon(
                icon,
                contentDescription = if (badge > 0) "$label, $badge belum dibaca" else label,
                tint = Color.White,
                modifier = Modifier.size(28.dp),
            )
            if (badge > 0) CountBadge(badge, Modifier.align(Alignment.TopEnd))
        }
        Text(label, style = MaterialTheme.typography.labelSmall, color = Color.White)
    }
}

/** Kartu akun Livin: sapaan + identitas + bar info presensi hari ini. */
@Composable
private fun AccountCard(
    session: SessionState.LoggedIn,
    summary: DashboardSummary?,
    loading: Boolean,
    canAttend: Boolean,
    onOpenAttendance: () -> Unit,
) {
    val user = session.user
    val dark = SindenTheme.isDark
    val glass = SindenTheme.glass
    val shape = RoundedCornerShape(24.dp)
    Column(
        Modifier
            .fillMaxWidth()
            .shadow(8.dp, shape, ambientColor = glass.shadow, spotColor = glass.shadow)
            .clip(shape)
            .background(if (dark) glass.fillStrong else MaterialTheme.colorScheme.surface)
            .border(2.dp, Brush.verticalGradient(listOf(glass.edgeTop, glass.edgeBottom)), shape)
            .padding(Spacing.lg),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Box(
                Modifier
                    .size(52.dp)
                    .clip(CircleShape)
                    .background(Brush.verticalGradient(listOf(Blue400, Blue600))),
                contentAlignment = Alignment.Center,
            ) {
                Text(user.initials, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Black, color = Color.White)
            }
            Spacer(Modifier.width(Spacing.md))
            Column(Modifier.weight(1f)) {
                Text(
                    "${Fmt.greeting()}, ${user.firstName}",
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Black,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
                Text(
                    user.subtitle,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
                Text(
                    Fmt.fullDate(LocalDate.now()),
                    style = MaterialTheme.typography.labelMedium,
                    color = MaterialTheme.colorScheme.primary,
                )
            }
        }

        if (canAttend) {
            Spacer(Modifier.height(Spacing.md))
            AttendanceBar(summary, loading, onOpenAttendance)
        }
    }
}

/** Bar info di dalam kartu akun (setara "Tabungan Now IDR · Buka Detail" di Livin). */
@Composable
private fun AttendanceBar(summary: DashboardSummary?, loading: Boolean, onClick: () -> Unit) {
    val today = summary?.attendance?.today
    val att = summary?.attendance
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(MaterialTheme.colorScheme.surfaceVariant)
            .border(1.dp, MaterialTheme.colorScheme.outlineVariant, RoundedCornerShape(16.dp))
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = Spacing.lg, vertical = Spacing.md),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(Modifier.weight(1f)) {
            Text("Presensi hari ini", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            when {
                loading && summary == null -> {
                    Spacer(Modifier.height(4.dp))
                    SkeletonBlock(Modifier.fillMaxWidth(0.6f), 18.dp)
                }
                today != null -> Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Outlined.CheckCircle, contentDescription = null, tint = toneAccent(Tone.SUCCESS), modifier = Modifier.size(20.dp))
                    Spacer(Modifier.width(6.dp))
                    Text(
                        "${today.statusLabel} · ${today.timeShort ?: "-"} WIB",
                        style = MaterialTheme.typography.titleSmall,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                }
                else -> Text("Belum presensi", style = MaterialTheme.typography.titleSmall, color = toneAccent(Tone.WARNING))
            }
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    if (today != null) "Lihat riwayat" else "Lapor kehadiran sekarang",
                    style = MaterialTheme.typography.labelMedium,
                    color = MaterialTheme.colorScheme.primary,
                )
                Icon(Icons.AutoMirrored.Filled.KeyboardArrowRight, contentDescription = null, tint = MaterialTheme.colorScheme.primary, modifier = Modifier.size(18.dp))
            }
        }
        if (att != null && att.totalActivePersonnel > 0) {
            Column(horizontalAlignment = Alignment.End) {
                Text(
                    "${att.totalAttendedToday}/${att.totalActivePersonnel}",
                    style = MaterialTheme.typography.titleLarge,
                    color = MaterialTheme.colorScheme.onSurface,
                )
                Text("hadir", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        } else {
            GradientIconTile(Icons.Outlined.HowToReg, Feature.ATTENDANCE.gradient, size = 48.dp, iconSize = 26.dp)
        }
    }
}

// ---------------------------------------------------------------- Fitur utama

@Composable
private fun MainFeatures(features: List<Feature>, unread: Int, onNavigate: (String) -> Unit) {
    Column(verticalArrangement = Arrangement.spacedBy(Spacing.md)) {
        SectionHeader("Fitur Utama Anda", actionLabel = "Semua", onAction = { onNavigate(Routes.MENU) })
        Row(
            Modifier.fillMaxWidth().padding(horizontal = Spacing.lg),
            horizontalArrangement = Arrangement.SpaceBetween,
        ) {
            features.forEach { f ->
                FeatureTile(
                    title = f.title,
                    icon = f.icon,
                    gradient = f.gradient,
                    onClick = { onNavigate(f.route) },
                    badge = if (f == Feature.NOTIFICATIONS) unread else 0,
                    modifier = Modifier.weight(1f),
                )
            }
            repeat(4 - features.size) { Spacer(Modifier.weight(1f)) }
        }
        SoftDivider(Modifier.fillMaxWidth().padding(horizontal = Spacing.screen))
    }
}

// ---------------------------------------------------------------- Daftar jaga (pribadi)

@Composable
private fun DutySection(
    state: LoadState<DashboardResponse>,
    duty: DashboardDuty?,
    onRetry: () -> Unit,
    onOpenDuty: () -> Unit,
) {
    Column(verticalArrangement = Arrangement.spacedBy(Spacing.md)) {
        SectionHeader("Daftar Jaga Anda", actionLabel = "Detail", onAction = onOpenDuty)
        Box(Modifier.padding(horizontal = Spacing.screen).animateContentSize()) {
            val failed = state as? LoadState.Failed
            when {
                state is LoadState.Loading && duty == null -> DutySkeleton()
                failed != null && duty == null -> SindenCard(Modifier.fillMaxWidth()) { ErrorState(failed.error, onRetry) }
                else -> DutyCard(duty, onOpenDuty)
            }
        }
    }
}

@Composable
private fun DutySkeleton() {
    val brush = shimmerBrush()
    SindenCard(Modifier.fillMaxWidth()) {
        SkeletonBlock(Modifier.fillMaxWidth(0.5f), 18.dp, brush)
        Spacer(Modifier.height(Spacing.md))
        repeat(2) {
            SkeletonBlock(Modifier.fillMaxWidth(), 56.dp, brush)
            Spacer(Modifier.height(Spacing.sm))
        }
    }
}

/** Kartu jadwal jaga pribadi: hanya tanggal milik pengguna, tanpa daftar personel lain. */
@Composable
private fun DutyCard(duty: DashboardDuty?, onOpenDuty: () -> Unit) {
    val today = LocalDate.now()
    val sources = duty?.sources.orEmpty()
    val roleByDate = buildMap {
        (listOfNotNull(duty?.today) + duty?.upcoming.orEmpty()).forEach { day ->
            val mine = day.safeEntries.firstOrNull { it.isMe } ?: return@forEach
            Fmt.parseDate(day.date)?.let { put(it, mine.role) }
        }
    }
    val myDates = duty?.myDates.orEmpty().mapNotNull { Fmt.parseDate(it) }.distinct().sorted()
    val thisMonth = myDates.filter { it.year == today.year && it.month == today.month }
    val upcoming = myDates.filter { !it.isBefore(today) }.take(3).map { MyDuty(it, roleByDate[it], null) }

    SindenCard(Modifier.fillMaxWidth(), onClick = onOpenDuty) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            GradientIconTile(Icons.Outlined.EventAvailable, Feature.DUTY.gradient, size = 48.dp, iconSize = 26.dp)
            Spacer(Modifier.width(Spacing.md))
            Column(Modifier.weight(1f)) {
                Text("Jadwal jaga saya", style = MaterialTheme.typography.titleSmall)
                Text(
                    when {
                        sources.isEmpty() -> "SP Jaga belum diterbitkan"
                        thisMonth.isEmpty() -> "Tidak ada jadwal bulan ini"
                        else -> "Bulan ini: ${thisMonth.size}× · tgl ${thisMonth.joinToString(", ") { it.dayOfMonth.toString() }}"
                    },
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                )
            }
        }

        if (upcoming.isNotEmpty()) {
            Spacer(Modifier.height(Spacing.md))
            Column(verticalArrangement = Arrangement.spacedBy(Spacing.xs)) {
                upcoming.forEach { MyDutyRow(it) }
            }
            Spacer(Modifier.height(Spacing.md))
            ReminderNote()
        } else if (sources.isNotEmpty()) {
            Spacer(Modifier.height(Spacing.sm))
            Text(
                "Belum ada jadwal jaga mendatang untuk Anda.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }

        Spacer(Modifier.height(Spacing.md))
        Button(
            onClick = onOpenDuty,
            modifier = Modifier.fillMaxWidth().height(52.dp),
            shape = MaterialTheme.shapes.medium,
        ) {
            Text("Lihat jadwal & PDF SP Jaga", style = MaterialTheme.typography.labelLarge)
        }
    }
}

// ---------------------------------------------------------------- Ringkasan

@Composable
private fun SummarySection(
    s: DashboardSummary,
    hideBalance: Boolean,
    onToggleHide: () -> Unit,
    onNavigate: (String) -> Unit,
) {
    val tiles = buildList {
        s.letterLogsThisMonth?.let { add(Tile("Agenda surat bulan ini", Fmt.number(it), Icons.Outlined.Email, Tone.INFO, Routes.LETTERS, s.letterLogsTotal?.let { t -> "Total ${Fmt.number(t)}" })) }
        s.skhppPending?.let { add(Tile("SKHPP menunggu", Fmt.number(it), Icons.Outlined.VerifiedUser, Tone.WARNING, Routes.SKHPP)) }
        s.signaturePending?.let { add(Tile("Dokumen menunggu TTE", Fmt.number(it), Icons.Outlined.Create, Tone.WARNING, Routes.SIGNATURE)) }
        s.attendance?.let { add(Tile("Personel aktif", Fmt.number(it.totalActivePersonnel), Icons.Outlined.Groups, Tone.NEUTRAL, Routes.ATTENDANCE)) }
    }
    if (tiles.isEmpty()) return
    val hasMoney = false

    Column(verticalArrangement = Arrangement.spacedBy(Spacing.md)) {
        Row(Modifier.fillMaxWidth().padding(end = Spacing.sm), verticalAlignment = Alignment.CenterVertically) {
            SectionHeader("Ringkasan", Modifier.weight(1f))
            if (hasMoney) {
                IconButton(onClick = onToggleHide) {
                    Icon(
                        if (hideBalance) Icons.Outlined.VisibilityOff else Icons.Outlined.Visibility,
                        contentDescription = if (hideBalance) "Tampilkan saldo" else "Sembunyikan saldo",
                    )
                }
            }
        }
        tiles.chunked(2).forEach { row ->
            Row(Modifier.padding(horizontal = Spacing.screen), horizontalArrangement = Arrangement.spacedBy(Spacing.md)) {
                row.forEach { t ->
                    StatTile(
                        label = t.label,
                        value = t.value,
                        icon = t.icon,
                        tint = toneAccent(t.tone),
                        caption = t.caption,
                        onClick = { onNavigate(t.route) },
                        modifier = Modifier.weight(1f),
                    )
                }
                if (row.size == 1) Spacer(Modifier.weight(1f))
            }
        }
    }
}

private data class Tile(
    val label: String,
    val value: String,
    val icon: ImageVector,
    val tone: Tone,
    val route: String,
    val caption: String? = null,
)

// ---------------------------------------------------------------- Fitur lainnya

/** "Fitur Lainnya" Livin: chip kategori + kartu baris dengan ubin gradien dan tautan "Buka ›". */
@Composable
private fun OtherFeatures(features: List<Feature>, onNavigate: (String) -> Unit) {
    val groups = remember(features) { listOf<FeatureGroup?>(null) + features.map { it.group }.distinct() }
    var selected by rememberSaveable { mutableStateOf<String?>(null) }
    val selectedGroup = groups.firstOrNull { it?.name == selected }
    val shown = features.filter { selectedGroup == null || it.group == selectedGroup }

    Column(verticalArrangement = Arrangement.spacedBy(Spacing.md)) {
        SectionHeader("Fitur Lainnya")
        FilterChipRow(
            options = groups,
            selected = selectedGroup,
            label = { it?.title ?: "Semua" },
            onSelect = { selected = it?.name },
        )
        Column(Modifier.padding(horizontal = Spacing.screen), verticalArrangement = Arrangement.spacedBy(Spacing.sm)) {
            shown.forEach { f -> FeatureRowCard(f) { onNavigate(f.route) } }
        }
    }
}

@Composable
private fun FeatureRowCard(feature: Feature, onClick: () -> Unit) {
    SindenCard(Modifier.fillMaxWidth(), onClick = onClick, contentPadding = Spacing.md) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            GradientIconTile(feature.icon, feature.gradient, size = 48.dp, iconSize = 26.dp, corner = 16.dp)
            Spacer(Modifier.width(Spacing.md))
            Column(Modifier.weight(1f)) {
                Text(feature.title, style = MaterialTheme.typography.titleSmall, maxLines = 1, overflow = TextOverflow.Ellipsis)
                Text(
                    feature.description,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
            }
            Text("Buka", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
            Icon(Icons.AutoMirrored.Filled.KeyboardArrowRight, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
        }
    }
}
