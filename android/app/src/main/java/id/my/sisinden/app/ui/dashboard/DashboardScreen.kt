package id.my.sisinden.app.ui.dashboard

import androidx.compose.animation.animateContentSize
import androidx.compose.foundation.background
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.AccountBalanceWallet
import androidx.compose.material.icons.outlined.Build
import androidx.compose.material.icons.outlined.CheckCircle
import androidx.compose.material.icons.outlined.Create
import androidx.compose.material.icons.outlined.Email
import androidx.compose.material.icons.outlined.EventAvailable
import androidx.compose.material.icons.outlined.Groups
import androidx.compose.material.icons.outlined.HowToReg
import androidx.compose.material.icons.outlined.Notifications
import androidx.compose.material.icons.outlined.VerifiedUser
import androidx.compose.material.icons.outlined.Visibility
import androidx.compose.material.icons.outlined.VisibilityOff
import androidx.compose.material3.BadgedBox
import androidx.compose.material3.Badge
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import id.my.sisinden.app.core.data.SessionState
import id.my.sisinden.app.core.security.Perm
import id.my.sisinden.app.core.ui.components.ErrorState
import id.my.sisinden.app.core.ui.components.IconBadge
import id.my.sisinden.app.core.ui.components.LoadState
import id.my.sisinden.app.core.ui.components.dataOrNull
import id.my.sisinden.app.core.ui.components.RefreshableBox
import id.my.sisinden.app.core.ui.components.SectionHeader
import id.my.sisinden.app.core.ui.components.SindenCard
import id.my.sisinden.app.core.ui.components.SkeletonBlock
import id.my.sisinden.app.core.ui.components.StatTile
import id.my.sisinden.app.core.ui.components.StatusChip
import id.my.sisinden.app.core.ui.components.Tone
import id.my.sisinden.app.core.ui.components.shimmerBrush
import id.my.sisinden.app.core.ui.components.toneAccent
import id.my.sisinden.app.core.ui.theme.Navy900
import id.my.sisinden.app.core.ui.theme.Ocean600
import id.my.sisinden.app.core.ui.theme.Ocean700
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.core.util.Fmt
import id.my.sisinden.app.data.model.DashboardDuty
import id.my.sisinden.app.data.model.DashboardResponse
import id.my.sisinden.app.data.model.DashboardSummary
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.ui.duty.DutyEntryRow
import id.my.sisinden.app.ui.duty.UpcomingDutyChip
import id.my.sisinden.app.ui.navigation.Feature
import id.my.sisinden.app.ui.navigation.Routes
import java.time.LocalDate

@Composable
fun DashboardScreen(
    repository: SindenRepository,
    session: SessionState.LoggedIn,
    onNavigate: (String) -> Unit,
    onOpenDutyDay: (Int?, String) -> Unit,
) {
    val context = LocalContext.current
    val vm: DashboardViewModel = viewModel { DashboardViewModel(repository, context.applicationContext) }
    val state by vm.state.collectAsStateWithLifecycle()
    val refreshing by vm.refreshing.collectAsStateWithLifecycle()
    val hideBalance by vm.hideBalance.collectAsStateWithLifecycle()

    val data = state.dataOrNull
    val summary = data?.summary
    val unread = summary?.notificationsUnread ?: 0
    val perms = session.permissions

    RefreshableBox(isRefreshing = refreshing, onRefresh = { vm.load(pullToRefresh = true) }) {
        Column(
            Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState()),
        ) {
            HeroHeader(
                session = session,
                unread = unread,
                canOpenNotifications = perms.has(Perm.NOTIFICATIONS_VIEW),
                onOpenNotifications = { onNavigate(Routes.NOTIFICATIONS) },
            )

            // Kartu presensi menumpang di bawah header
            AttendanceQuickCard(
                summary = summary,
                loading = state is LoadState.Loading,
                onClick = { onNavigate(Routes.ATTENDANCE) },
                modifier = Modifier.offset(y = (-36).dp).padding(horizontal = Spacing.screen),
            )

            Column(Modifier.offset(y = (-20).dp), verticalArrangement = Arrangement.spacedBy(Spacing.xl)) {
                // ---------------------------------------------- DAFTAR JAGA (utama)
                if (perms.has(Perm.DUTY_VIEW)) {
                    DutySection(
                        state = state,
                        duty = data?.duty,
                        onRetry = { vm.load() },
                        onOpenDay = onOpenDutyDay,
                        onOpenAll = { onNavigate(Routes.DUTY) },
                    )
                }

                // ---------------------------------------------- Ringkasan per hak akses
                if (summary != null) {
                    SummarySection(summary, hideBalance, vm::toggleHideBalance, onNavigate)
                }

                // ---------------------------------------------- Akses cepat
                QuickAccess(features = Feature.permitted(perms).filter { it != Feature.DUTY && it != Feature.ATTENDANCE }, onNavigate = onNavigate)

                if (session.isStale) {
                    Text(
                        "Hak akses ditampilkan dari data tersimpan. Tarik ke bawah untuk menyinkronkan dengan server.",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier.padding(horizontal = Spacing.screen),
                    )
                }
                Spacer(Modifier.height(Spacing.lg))
            }
        }
    }
}

@Composable
private fun HeroHeader(
    session: SessionState.LoggedIn,
    unread: Int,
    canOpenNotifications: Boolean,
    onOpenNotifications: () -> Unit,
) {
    val user = session.user
    Box(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(bottomStart = 28.dp, bottomEnd = 28.dp))
            .background(Brush.linearGradient(listOf(Navy900, Ocean700, Ocean600)))
            .statusBarsPadding()
            .padding(start = Spacing.screen, end = Spacing.screen, top = Spacing.lg, bottom = 56.dp),
    ) {
        Column {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(
                    Modifier.size(46.dp).clip(RoundedCornerShape(14.dp)).background(Color.White.copy(alpha = 0.18f)),
                    contentAlignment = Alignment.Center,
                ) {
                    Text(user.initials, color = Color.White, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
                }
                Spacer(Modifier.width(Spacing.md))
                Column(Modifier.weight(1f)) {
                    Text("${Fmt.greeting()},", color = Color.White.copy(alpha = 0.8f), style = MaterialTheme.typography.bodyMedium)
                    Text(
                        user.displayName,
                        color = Color.White,
                        style = MaterialTheme.typography.titleLarge,
                        fontWeight = FontWeight.Bold,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                }
                if (canOpenNotifications) {
                    IconButton(onClick = onOpenNotifications) {
                        BadgedBox(badge = { if (unread > 0) Badge { Text(if (unread > 99) "99+" else unread.toString()) } }) {
                            Icon(
                                Icons.Outlined.Notifications,
                                contentDescription = if (unread > 0) "Notifikasi, $unread belum dibaca" else "Notifikasi",
                                tint = Color.White,
                            )
                        }
                    }
                }
            }
            Spacer(Modifier.height(Spacing.lg))
            Text(Fmt.fullDate(LocalDate.now()), color = Color.White, style = MaterialTheme.typography.titleSmall)
            Spacer(Modifier.height(Spacing.xs))
            Text(
                user.subtitle,
                color = Color.White.copy(alpha = 0.75f),
                style = MaterialTheme.typography.bodySmall,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
        }
    }
}

@Composable
private fun AttendanceQuickCard(
    summary: DashboardSummary?,
    loading: Boolean,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val today = summary?.attendance?.today
    Surface(
        onClick = onClick,
        modifier = modifier.fillMaxWidth(),
        shape = MaterialTheme.shapes.large,
        color = MaterialTheme.colorScheme.surface,
        shadowElevation = 4.dp,
    ) {
        Row(Modifier.padding(Spacing.lg), verticalAlignment = Alignment.CenterVertically) {
            IconBadge(
                if (today != null) Icons.Outlined.CheckCircle else Icons.Outlined.HowToReg,
                tint = if (today != null) toneAccent(Tone.SUCCESS) else MaterialTheme.colorScheme.primary,
            )
            Spacer(Modifier.width(Spacing.md))
            Column(Modifier.weight(1f)) {
                when {
                    loading && summary == null -> {
                        SkeletonBlock(Modifier.fillMaxWidth(0.6f), 14.dp)
                        Spacer(Modifier.height(6.dp))
                        SkeletonBlock(Modifier.fillMaxWidth(0.4f), 12.dp)
                    }
                    today != null -> {
                        Text("Presensi tercatat", style = MaterialTheme.typography.titleSmall)
                        Text(
                            "${today.statusLabel} · pukul ${today.timeShort ?: "-"} WIB",
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                    else -> {
                        Text("Belum presensi hari ini", style = MaterialTheme.typography.titleSmall)
                        Text("Ketuk untuk lapor kehadiran", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                }
            }
            val att = summary?.attendance
            if (att != null) {
                Column(horizontalAlignment = Alignment.End) {
                    Text(
                        "${att.totalAttendedToday}/${att.totalActivePersonnel}",
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold,
                    )
                    Text("hadir hari ini", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
            }
        }
    }
}

@Composable
private fun DutySection(
    state: LoadState<DashboardResponse>,
    duty: DashboardDuty?,
    onRetry: () -> Unit,
    onOpenDay: (Int?, String) -> Unit,
    onOpenAll: () -> Unit,
) {
    Column(verticalArrangement = Arrangement.spacedBy(Spacing.md)) {
        SectionHeader(
            title = "Daftar Jaga",
            subtitle = duty?.sources?.firstOrNull()?.let { "${it.nomorSprin ?: "SP Jaga"} · ${it.periodeLabel ?: ""}" }
                ?: "Jadwal jaga siaga sintel",
            actionLabel = "Lihat semua",
            onAction = onOpenAll,
        )

        Box(Modifier.animateContentSize()) {
            val failed = state as? LoadState.Failed
            when {
                state is LoadState.Loading && duty == null -> DutySkeleton()
                failed != null && duty == null -> SindenCard(Modifier.padding(horizontal = Spacing.screen).fillMaxWidth()) {
                    ErrorState(failed.error, onRetry)
                }
                else -> DutyContent(duty, onOpenDay, onOpenAll)
            }
        }
    }
}

@Composable
private fun DutySkeleton() {
    val brush = shimmerBrush()
    SindenCard(Modifier.padding(horizontal = Spacing.screen).fillMaxWidth()) {
        SkeletonBlock(Modifier.fillMaxWidth(0.5f), 16.dp, brush)
        Spacer(Modifier.height(Spacing.md))
        repeat(3) {
            SkeletonBlock(Modifier.fillMaxWidth(), 36.dp, brush)
            Spacer(Modifier.height(Spacing.sm))
        }
    }
}

@Composable
private fun DutyContent(
    duty: DashboardDuty?,
    onOpenDay: (Int?, String) -> Unit,
    onOpenAll: () -> Unit,
) {
    val hasSource = !duty?.sources.isNullOrEmpty()
    Column(verticalArrangement = Arrangement.spacedBy(Spacing.md)) {
        if (!hasSource) {
            SindenCard(Modifier.padding(horizontal = Spacing.screen).fillMaxWidth()) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    IconBadge(Icons.Outlined.EventAvailable, MaterialTheme.colorScheme.primary)
                    Spacer(Modifier.width(Spacing.md))
                    Column(Modifier.weight(1f)) {
                        Text("Belum ada SP Jaga terbit", style = MaterialTheme.typography.titleSmall)
                        Text(
                            "Jadwal akan muncul di sini setelah Surat Perintah Jaga periode ini disahkan.",
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                }
            }
            return@Column
        }

        val today = duty?.today
        val todayDate = LocalDate.now()
        SindenCard(
            modifier = Modifier.padding(horizontal = Spacing.screen).fillMaxWidth(),
            onClick = { onOpenDay(today?.spId, today?.date ?: Fmt.iso(todayDate)) },
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Column(Modifier.weight(1f)) {
                    Text("Jaga hari ini", style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.primary)
                    Text(Fmt.fullDate(todayDate), style = MaterialTheme.typography.titleSmall)
                }
                StatusChip(
                    if (today != null) "${today.safeEntries.size} personel" else "Tidak ada",
                    if (today != null) Tone.INFO else Tone.NEUTRAL,
                )
            }
            if (today?.hasMe == true) {
                Spacer(Modifier.height(Spacing.md))
                val myRole = today.safeEntries.firstOrNull { it.isMe }?.role
                Row(
                    Modifier.fillMaxWidth().clip(MaterialTheme.shapes.small)
                        .background(MaterialTheme.colorScheme.primaryContainer).padding(Spacing.md),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Icon(Icons.Outlined.VerifiedUser, contentDescription = null, tint = MaterialTheme.colorScheme.onPrimaryContainer)
                    Spacer(Modifier.width(Spacing.sm))
                    Text(
                        "Anda bertugas hari ini${myRole?.let { " sebagai $it" } ?: ""}.",
                        style = MaterialTheme.typography.bodyMedium,
                        fontWeight = FontWeight.SemiBold,
                        color = MaterialTheme.colorScheme.onPrimaryContainer,
                    )
                }
            }
            Spacer(Modifier.height(Spacing.sm))
            if (today == null || today.safeEntries.isEmpty()) {
                Text(
                    "Tidak ada personel terjadwal jaga hari ini.",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            } else {
                today.safeEntries.sortedByDescending { it.isMe }.take(5).forEach { DutyEntryRow(it) }
                val more = today.safeEntries.size - 5
                if (more > 0) Text("+$more personel lainnya", style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.primary)
            }
        }

        // Jadwal saya berikutnya
        val next = Fmt.parseDate(duty?.myNextDate)
        if (next != null && next != todayDate) {
            SindenCard(
                modifier = Modifier.padding(horizontal = Spacing.screen).fillMaxWidth(),
                onClick = { onOpenDay(null, Fmt.iso(next)) },
                containerColor = MaterialTheme.colorScheme.secondaryContainer,
                border = null,
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    IconBadge(Icons.Outlined.EventAvailable, MaterialTheme.colorScheme.onSecondaryContainer)
                    Spacer(Modifier.width(Spacing.md))
                    Column(Modifier.weight(1f)) {
                        Text("Jadwal jaga Anda berikutnya", style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onSecondaryContainer)
                        Text(Fmt.fullDate(next), style = MaterialTheme.typography.titleSmall, color = MaterialTheme.colorScheme.onSecondaryContainer)
                    }
                    StatusChip(Fmt.relativeDay(next), Tone.SUCCESS)
                }
            }
        }

        val upcoming = duty?.upcoming.orEmpty()
        if (upcoming.isNotEmpty()) {
            Row(
                Modifier.horizontalScroll(rememberScrollState()).padding(PaddingValues(horizontal = Spacing.screen)),
                horizontalArrangement = Arrangement.spacedBy(Spacing.sm),
            ) {
                upcoming.forEach { day -> UpcomingDutyChip(day) { onOpenDay(day.spId, day.date) } }
            }
        }

        OutlinedButton(onClick = onOpenAll, modifier = Modifier.padding(horizontal = Spacing.screen).fillMaxWidth()) {
            Text("Buka daftar jaga lengkap")
        }
    }
}

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
        s.cashBalance?.let { add(Tile("Saldo kas pangkalan", Fmt.rupiah(it, hideBalance), Icons.Outlined.AccountBalanceWallet, Tone.SUCCESS, Routes.CASH)) }
        s.technicalCashBalance?.let { add(Tile("Saldo kas unit teknis", Fmt.rupiah(it, hideBalance), Icons.Outlined.Build, Tone.SUCCESS, Routes.TECHNICAL_CASH)) }
        s.attendance?.let { add(Tile("Personel aktif", Fmt.number(it.totalActivePersonnel), Icons.Outlined.Groups, Tone.NEUTRAL, Routes.ATTENDANCE)) }
    }
    if (tiles.isEmpty()) return
    val hasMoney = s.cashBalance != null || s.technicalCashBalance != null

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

@Composable
private fun QuickAccess(features: List<Feature>, onNavigate: (String) -> Unit) {
    if (features.isEmpty()) return
    Column(verticalArrangement = Arrangement.spacedBy(Spacing.md)) {
        SectionHeader("Akses cepat", actionLabel = "Semua menu", onAction = { onNavigate(Routes.MENU) })
        features.take(8).chunked(4).forEach { row ->
            Row(Modifier.padding(horizontal = Spacing.screen), horizontalArrangement = Arrangement.spacedBy(Spacing.sm)) {
                row.forEach { f ->
                    Column(
                        Modifier.weight(1f).clip(MaterialTheme.shapes.medium),
                        horizontalAlignment = Alignment.CenterHorizontally,
                    ) {
                        Surface(
                            onClick = { onNavigate(f.route) },
                            shape = MaterialTheme.shapes.medium,
                            color = toneAccent(f.tone).copy(alpha = 0.12f),
                            modifier = Modifier.size(56.dp),
                        ) {
                            Box(contentAlignment = Alignment.Center) {
                                Icon(f.icon, contentDescription = f.title, tint = toneAccent(f.tone))
                            }
                        }
                        Spacer(Modifier.height(6.dp))
                        Text(
                            f.title,
                            style = MaterialTheme.typography.labelSmall,
                            maxLines = 2,
                            overflow = TextOverflow.Ellipsis,
                            textAlign = androidx.compose.ui.text.style.TextAlign.Center,
                        )
                    }
                }
                repeat(4 - row.size) { Spacer(Modifier.weight(1f)) }
            }
        }
    }
}

private data class Tile(
    val label: String,
    val value: String,
    val icon: androidx.compose.ui.graphics.vector.ImageVector,
    val tone: Tone,
    val route: String,
    val caption: String? = null,
)
