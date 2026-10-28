package id.my.sisinden.app.ui.navigation

import android.content.Intent
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.core.tween
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.navigationBars
import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AccountCircle
import androidx.compose.material.icons.filled.Apps
import androidx.compose.material.icons.filled.EventAvailable
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.HowToReg
import androidx.compose.material.icons.outlined.AccountCircle
import androidx.compose.material.icons.outlined.Apps
import androidx.compose.material.icons.outlined.EventAvailable
import androidx.compose.material.icons.outlined.Home
import androidx.compose.material.icons.outlined.HowToReg
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LocalLifecycleOwner
import androidx.lifecycle.repeatOnLifecycle
import id.my.sisinden.app.core.network.ApiResult
import id.my.sisinden.app.core.notification.CommsState
import id.my.sisinden.app.core.notification.NotificationSync
import id.my.sisinden.app.core.notification.PushManager
import id.my.sisinden.app.core.update.AppUpdater
import id.my.sisinden.app.ui.settings.UpdatePromptHost
import androidx.lifecycle.compose.LifecycleResumeEffect
import id.my.sisinden.app.ui.call.CallScreen
import id.my.sisinden.app.ui.chat.ChatHomeScreen
import id.my.sisinden.app.ui.chat.ChatThreadScreen
import kotlinx.coroutines.delay
import androidx.compose.ui.graphics.Color
import id.my.sisinden.app.core.ui.components.GlassBackground
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.navigation.NavBackStackEntry
import androidx.navigation.NavGraph.Companion.findStartDestination
import androidx.navigation.NavHostController
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import androidx.navigation.navDeepLink
import id.my.sisinden.app.core.data.SessionState
import id.my.sisinden.app.core.security.Perm
import id.my.sisinden.app.core.security.PermissionSet
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.ui.attendance.AttendanceScreen
import id.my.sisinden.app.ui.commander.CommanderScreen
import id.my.sisinden.app.ui.tracking.TrackingScreen
import id.my.sisinden.app.ui.common.AccessDeniedScreen
import id.my.sisinden.app.ui.dashboard.DashboardScreen
import id.my.sisinden.app.ui.duty.DutyRosterScreen
import id.my.sisinden.app.ui.letters.LetterLogsScreen
import id.my.sisinden.app.ui.menu.MenuScreen
import id.my.sisinden.app.ui.mitra.MitraScreen
import id.my.sisinden.app.ui.notifications.NotificationsScreen
import id.my.sisinden.app.ui.pdf.PdfPreviewScreen
import id.my.sisinden.app.ui.profile.ProfileScreen
import id.my.sisinden.app.ui.settings.SettingsScreen
import id.my.sisinden.app.ui.signature.SignatureScreen
import id.my.sisinden.app.ui.skhpp.SkhppFormScreen
import id.my.sisinden.app.ui.skhpp.SkhppScreen
import id.my.sisinden.app.ui.users.UsersScreen
import id.my.sisinden.app.ui.violations.ViolationFormScreen
import id.my.sisinden.app.ui.violations.ViolationsScreen

internal data class TabItem(
    val route: String,
    val label: String,
    val icon: ImageVector,
    val selectedIcon: ImageVector,
    val permission: String,
)

private val allTabs = listOf(
    TabItem(Routes.HOME, "Beranda", Icons.Outlined.Home, Icons.Filled.Home, Perm.DASHBOARD_VIEW),
    TabItem(Routes.DUTY, "Jaga", Icons.Outlined.EventAvailable, Icons.Filled.EventAvailable, Perm.DUTY_VIEW),
    TabItem(Routes.ATTENDANCE, "Presensi", Icons.Outlined.HowToReg, Icons.Filled.HowToReg, Perm.ATTENDANCE_VIEW),
    TabItem(Routes.MENU, "Menu", Icons.Outlined.Apps, Icons.Filled.Apps, Perm.DASHBOARD_VIEW),
    TabItem(Routes.PROFILE, "Profil", Icons.Outlined.AccountCircle, Icons.Filled.AccountCircle, Perm.PROFILE_VIEW),
)

private const val REFRESH_KEY = "refresh_signal"

private fun appDeepLink(path: String) = listOf(navDeepLink { uriPattern = Routes.DEEP_LINK_PREFIX + path })

/** Kirim sinyal "muat ulang" ke layar sebelumnya lalu kembali. */
private fun NavHostController.popWithRefresh() {
    previousBackStackEntry?.savedStateHandle?.set(REFRESH_KEY, System.currentTimeMillis())
    popBackStack()
}

@Composable
private fun NavBackStackEntry.refreshSignal(): Long {
    val value by savedStateHandle.getStateFlow(REFRESH_KEY, 0L).collectAsStateWithLifecycle()
    return value
}

@Composable
fun MainNavigation(
    repository: SindenRepository,
    session: SessionState.LoggedIn,
    newIntent: Intent?,
    onIntentHandled: () -> Unit,
) {
    val nav = rememberNavController()
    val perms = session.permissions
    // Graf navigasi TIDAK boleh bergantung langsung pada objek sesi: perubahan sesi
    // (mis. sinkronisasi permission) akan membuat graf baru dan mereset back stack.
    // Destinasi membaca sesi terbaru lewat State yang stabil.
    val sessionState = rememberUpdatedState(session)
    val currentPerms: () -> PermissionSet = remember { { sessionState.value.permissions } }
    val backStack by nav.currentBackStackEntryAsState()
    val currentRoute = backStack?.destination?.route
    val tabs = allTabs.filter { perms.has(it.permission) }
    val showBottomBar = currentRoute in Routes.tabs

    val context = LocalContext.current
    val lifecycle = LocalLifecycleOwner.current.lifecycle
    val myUserId = session.user.id
    val canChat = perms.has(Perm.CHAT_VIEW)

    // Navigasi utama tampil (tidak terkunci): panggilan masuk ditangani poller, bukan notifikasi.
    LifecycleResumeEffect(Unit) {
        CommsState.mainUiVisible = true
        onPauseOrDispose { CommsState.mainUiVisible = false }
    }

    // Notifikasi push (FCM): ambil konfigurasi Firebase dari server lalu daftarkan perangkat.
    LaunchedEffect(myUserId) {
        val cfg = when (val res = repository.getConfig()) {
            is ApiResult.Success -> res.data
            is ApiResult.Failure -> null
        }
        PushManager.configure(context, cfg?.push)
        PushManager.register(context, repository)
        // Pembaruan aplikasi dari APK yang diunggah Admin di Pengaturan web.
        AppUpdater.schedule(context)
        AppUpdater.autoCheckOnOpen(context)
    }
    UpdatePromptHost()

    // Notifikasi bersuara: WorkManager di latar belakang + sinkron cepat saat aplikasi terbuka.
    LaunchedEffect(Unit) {
        NotificationSync.schedule(context)
        lifecycle.repeatOnLifecycle(Lifecycle.State.STARTED) {
            while (true) {
                delay(15_000)
                NotificationSync.run(context, repository)
            }
        }
    }

    // Panggilan masuk (Chat Dinas): cek tiap 4 detik selama aplikasi terbuka.
    LaunchedEffect(canChat, myUserId) {
        if (!canChat) return@LaunchedEffect
        lifecycle.repeatOnLifecycle(Lifecycle.State.STARTED) {
            while (true) {
                if (CommsState.activeCallThread == null) {
                    val call = when (val res = repository.incomingCall()) {
                        is ApiResult.Success -> res.data.call
                        is ApiResult.Failure -> null
                    }
                    val threadUuid = call?.threadUuid
                    val callId = call?.callId
                    if (call != null && threadUuid != null && callId != null &&
                        call.normalizedStatus == "RINGING" && call.caller?.id != myUserId &&
                        CommsState.claimCall(callId)
                    ) {
                        val peer = listOfNotNull(call.caller?.pangkat, call.caller?.name).joinToString(" ").ifBlank { "Panggilan Dinas" }
                        nav.navigate(Routes.call(threadUuid, call.isVideo, incoming = true, peer = peer)) { launchSingleTop = true }
                    }
                }
                delay(4_000)
            }
        }
    }

    LaunchedEffect(newIntent) {
        if (newIntent != null) {
            nav.handleDeepLink(newIntent)
            onIntentHandled()
        }
    }

    val back: () -> Unit = remember(nav) {
        { if (!nav.popBackStack()) nav.navigate(Routes.HOME) }
    }
    val openPdf: (String, String, String) -> Unit = remember(nav) {
        { path, title, file -> nav.navigate(Routes.pdf(path, title, file)) }
    }
    val downloadPdf: (String, String, String) -> Unit = remember(nav) {
        { path, title, file -> nav.navigate(Routes.pdf(path, title, file, save = true)) }
    }
    val navigate: (String) -> Unit = remember(nav) {
        { route -> if (route in Routes.tabs) nav.navigateToTab(route) else nav.navigate(route) { launchSingleTop = true } }
    }

    GlassBackground {
    Scaffold(
        containerColor = Color.Transparent,
        contentWindowInsets = if (showBottomBar) WindowInsets(0, 0, 0, 0) else WindowInsets.navigationBars,
        bottomBar = {
            if (showBottomBar) {
                SindenBottomBar(tabs = tabs, currentRoute = currentRoute, onSelect = { nav.navigateToTab(it) })
            }
        },
    ) { padding ->
        NavHost(
            navController = nav,
            startDestination = Routes.HOME,
            modifier = Modifier.padding(padding),
            enterTransition = { fadeIn(animationSpec = tween(220)) },
            exitTransition = { fadeOut(animationSpec = tween(160)) },
            popEnterTransition = { fadeIn(animationSpec = tween(220)) },
            popExitTransition = { fadeOut(animationSpec = tween(160)) },
        ) {
            // ------------------------------------------------------ Tab utama
            composable(Routes.HOME, deepLinks = appDeepLink("home")) {
                DashboardScreen(
                    repository = repository,
                    session = sessionState.value,
                    onNavigate = navigate,
                )
            }
            composable(Routes.DUTY, deepLinks = appDeepLink("duty")) {
                Guard(currentPerms(), Perm.DUTY_VIEW, back) {
                    DutyRosterScreen(
                        repository = repository,
                        permissions = currentPerms(),
                        onOpenPdf = openPdf,
                        onDownloadPdf = downloadPdf,
                    )
                }
            }
            composable(Routes.ATTENDANCE, deepLinks = appDeepLink("attendance")) {
                Guard(currentPerms(), Perm.ATTENDANCE_VIEW, back) {
                    AttendanceScreen(repository = repository, canSubmit = currentPerms().has(Perm.ATTENDANCE_CREATE))
                }
            }
            composable(Routes.MENU) {
                MenuScreen(permissions = currentPerms(), user = sessionState.value.user, onNavigate = navigate)
            }
            composable(Routes.PROFILE, deepLinks = appDeepLink("profile")) {
                ProfileScreen(
                    repository = repository,
                    session = sessionState.value,
                    onOpenSettings = { nav.navigate(Routes.SETTINGS) },
                    onOpenNotifications = { nav.navigate(Routes.NOTIFICATIONS) },
                )
            }

            // ------------------------------------------------------ Modul
            composable(Routes.NOTIFICATIONS, deepLinks = appDeepLink("notifications")) {
                Guard(currentPerms(), Perm.NOTIFICATIONS_VIEW, back) { NotificationsScreen(repository, onBack = back) }
            }
            composable(Routes.LETTERS, deepLinks = appDeepLink("letters")) {
                Guard(currentPerms(), Perm.LETTER_LOGS_VIEW, back) {
                    LetterLogsScreen(repository, currentPerms(), onBack = back, onOpenPdf = openPdf)
                }
            }
            composable(Routes.SKHPP, deepLinks = appDeepLink("skhpp")) { entry ->
                Guard(currentPerms(), Perm.SKHPP_VIEW, back) {
                    SkhppScreen(
                        repository = repository,
                        permissions = currentPerms(),
                        refreshSignal = entry.refreshSignal(),
                        onBack = back,
                        onCreate = { nav.navigate(Routes.SKHPP_FORM) },
                        onOpenPdf = openPdf,
                    )
                }
            }
            composable(Routes.SKHPP_FORM) {
                Guard(currentPerms(), Perm.SKHPP_CREATE, back) {
                    SkhppFormScreen(repository, onBack = back, onSubmitted = { nav.popWithRefresh() })
                }
            }
            composable(Routes.SIGNATURE, deepLinks = appDeepLink("signature")) {
                Guard(currentPerms(), Perm.SIGNATURE_VIEW, back) {
                    SignatureScreen(
                        repository = repository,
                        permissions = currentPerms(),
                        onBack = back,
                        onOpenPdf = openPdf,
                        onOpenSkhpp = { nav.navigate(Routes.SKHPP) { launchSingleTop = true } },
                    )
                }
            }
            composable(Routes.COMMANDER, deepLinks = appDeepLink("commander")) {
                Guard(currentPerms(), Perm.COMMANDER_VIEW, back) {
                    CommanderScreen(repository, currentPerms(), onBack = back, onOpenPdf = openPdf)
                }
            }
            composable(Routes.MITRA, deepLinks = appDeepLink("mitra")) {
                Guard(currentPerms(), Perm.MITRA_VIEW, back) { MitraScreen(repository, currentPerms(), onBack = back) }
            }
            composable(Routes.VIOLATIONS, deepLinks = appDeepLink("violations")) { entry ->
                Guard(currentPerms(), Perm.VIOLATIONS_VIEW, back) {
                    ViolationsScreen(
                        repository = repository,
                        permissions = currentPerms(),
                        refreshSignal = entry.refreshSignal(),
                        onBack = back,
                        onCreate = { nav.navigate(Routes.VIOLATION_FORM) },
                    )
                }
            }
            composable(Routes.VIOLATION_FORM) {
                Guard(currentPerms(), Perm.VIOLATIONS_MANAGE, back) {
                    ViolationFormScreen(repository, onBack = back, onSubmitted = { nav.popWithRefresh() })
                }
            }
            composable(Routes.TRACKING, deepLinks = appDeepLink("tracking")) {
                Guard(currentPerms(), Perm.TRACKING_VIEW, back) { TrackingScreen(repository, onBack = back) }
            }
            composable(Routes.USERS, deepLinks = appDeepLink("users")) {
                Guard(currentPerms(), Perm.USERS_VIEW, back) { UsersScreen(repository, currentPerms(), onBack = back) }
            }
            composable(Routes.SETTINGS, deepLinks = appDeepLink("settings")) {
                SettingsScreen(repository, onBack = back)
            }

            // ------------------------------------------------------ Chat Dinas & Vicon
            composable(Routes.CHAT, deepLinks = appDeepLink("chat")) {
                Guard(currentPerms(), Perm.CHAT_VIEW, back) {
                    ChatHomeScreen(
                        repository = repository,
                        onBack = back,
                        onOpenThread = { uuid, title -> nav.navigate(Routes.chatThread(uuid, title)) { launchSingleTop = true } },
                    )
                }
            }
            composable(
                Routes.CHAT_THREAD,
                arguments = listOf(
                    navArgument("uuid") { type = NavType.StringType },
                    navArgument("title") { type = NavType.StringType; defaultValue = "Chat Dinas" },
                ),
            ) { entry ->
                // Semua personel dapat membuka percakapannya; akses per utas dicek server.
                Guard(currentPerms(), Perm.CHAT_VIEW, back) {
                    val uuid = entry.arguments?.getString("uuid").orEmpty()
                    val title = entry.arguments?.getString("title").orEmpty().ifBlank { "Chat Dinas" }
                    ChatThreadScreen(
                        repository = repository,
                        uuid = uuid,
                        title = title,
                        myUserId = sessionState.value.user.id,
                        onBack = back,
                        onCall = { video -> nav.navigate(Routes.call(uuid, video, incoming = false, peer = title)) },
                    )
                }
            }
            composable(
                Routes.CALL,
                deepLinks = appDeepLink("call/{uuid}?video={video}&incoming={incoming}&peer={peer}"),
                arguments = listOf(
                    navArgument("uuid") { type = NavType.StringType },
                    navArgument("video") { type = NavType.BoolType; defaultValue = true },
                    navArgument("incoming") { type = NavType.BoolType; defaultValue = false },
                    navArgument("peer") { type = NavType.StringType; defaultValue = "Panggilan Dinas" },
                ),
            ) { entry ->
                Guard(currentPerms(), Perm.CHAT_VIEW, back) {
                    CallScreen(
                        repository = repository,
                        threadUuid = entry.arguments?.getString("uuid").orEmpty(),
                        video = entry.arguments?.getBoolean("video") ?: true,
                        incoming = entry.arguments?.getBoolean("incoming") ?: false,
                        peerName = entry.arguments?.getString("peer").orEmpty().ifBlank { "Panggilan Dinas" },
                        myUserId = sessionState.value.user.id,
                        onClose = { nav.popBackStack() },
                    )
                }
            }

            // ------------------------------------------------------ PDF
            composable(
                Routes.PDF,
                arguments = listOf(
                    navArgument("path") { type = NavType.StringType; defaultValue = "" },
                    navArgument("title") { type = NavType.StringType; defaultValue = "Dokumen" },
                    navArgument("file") { type = NavType.StringType; defaultValue = "dokumen.pdf" },
                    navArgument("save") { type = NavType.BoolType; defaultValue = false },
                ),
            ) { entry ->
                PdfPreviewScreen(
                    repository = repository,
                    apiPath = entry.arguments?.getString("path").orEmpty(),
                    title = entry.arguments?.getString("title").orEmpty(),
                    fileName = entry.arguments?.getString("file").orEmpty(),
                    autoSave = entry.arguments?.getBoolean("save") ?: false,
                    onBack = back,
                )
            }
        }
    }
    }
}

/** Navigasi antar-tab dengan pemulihan state (scroll, filter) masing-masing tab. */
fun NavHostController.navigateToTab(route: String) {
    navigate(route) {
        popUpTo(graph.findStartDestination().id) { saveState = true }
        launchSingleTop = true
        restoreState = true
    }
}

/** Penjaga rute: konten hanya dirender bila server memberi permission. */
@Composable
fun Guard(
    permissions: PermissionSet,
    permission: String,
    onBack: () -> Unit,
    content: @Composable () -> Unit,
) {
    if (permissions.has(permission)) content() else AccessDeniedScreen(onBack = onBack)
}
