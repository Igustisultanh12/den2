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
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.NavigationBarItemDefaults
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.ui.Modifier
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
import id.my.sisinden.app.data.repository.CashKind
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.ui.attendance.AttendanceScreen
import id.my.sisinden.app.ui.cash.CashScreen
import id.my.sisinden.app.ui.commander.CommanderScreen
import id.my.sisinden.app.ui.common.AccessDeniedScreen
import id.my.sisinden.app.ui.dashboard.DashboardScreen
import id.my.sisinden.app.ui.duty.DutyDayScreen
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

private data class TabItem(
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

private fun deepLink(path: String) = listOf(navDeepLink { uriPattern = Routes.DEEP_LINK_PREFIX + path })

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
    val navigate: (String) -> Unit = remember(nav) {
        { route -> if (route in Routes.tabs) nav.navigateToTab(route) else nav.navigate(route) { launchSingleTop = true } }
    }

    Scaffold(
        containerColor = MaterialTheme.colorScheme.background,
        contentWindowInsets = if (showBottomBar) WindowInsets(0, 0, 0, 0) else WindowInsets.navigationBars,
        bottomBar = {
            if (showBottomBar) {
                NavigationBar(containerColor = MaterialTheme.colorScheme.surface, tonalElevation = 0.dp) {
                    tabs.forEach { tab ->
                        val selected = currentRoute == tab.route
                        NavigationBarItem(
                            selected = selected,
                            onClick = { if (!selected) nav.navigateToTab(tab.route) },
                            icon = { Icon(if (selected) tab.selectedIcon else tab.icon, contentDescription = null) },
                            label = { Text(tab.label, style = MaterialTheme.typography.labelMedium) },
                            colors = NavigationBarItemDefaults.colors(
                                indicatorColor = MaterialTheme.colorScheme.primaryContainer,
                                selectedIconColor = MaterialTheme.colorScheme.onPrimaryContainer,
                                selectedTextColor = MaterialTheme.colorScheme.primary,
                            ),
                        )
                    }
                }
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
            composable(Routes.HOME, deepLinks = deepLink("home")) {
                DashboardScreen(
                    repository = repository,
                    session = sessionState.value,
                    onNavigate = navigate,
                    onOpenDutyDay = { sp, date -> nav.navigate(Routes.dutyDay(sp, date)) },
                )
            }
            composable(Routes.DUTY, deepLinks = deepLink("duty")) {
                Guard(currentPerms(), Perm.DUTY_VIEW, back) {
                    DutyRosterScreen(
                        repository = repository,
                        permissions = currentPerms(),
                        onOpenDay = { sp, date -> nav.navigate(Routes.dutyDay(sp, date)) },
                        onOpenPdf = openPdf,
                    )
                }
            }
            composable(Routes.ATTENDANCE, deepLinks = deepLink("attendance")) {
                Guard(currentPerms(), Perm.ATTENDANCE_VIEW, back) {
                    AttendanceScreen(repository = repository, canSubmit = currentPerms().has(Perm.ATTENDANCE_CREATE))
                }
            }
            composable(Routes.MENU) {
                MenuScreen(permissions = currentPerms(), user = sessionState.value.user, onNavigate = navigate)
            }
            composable(Routes.PROFILE, deepLinks = deepLink("profile")) {
                ProfileScreen(
                    repository = repository,
                    session = sessionState.value,
                    onOpenSettings = { nav.navigate(Routes.SETTINGS) },
                    onOpenNotifications = { nav.navigate(Routes.NOTIFICATIONS) },
                )
            }

            // ------------------------------------------------------ Daftar jaga
            composable(
                Routes.DUTY_DAY,
                arguments = listOf(
                    navArgument("sp") { type = NavType.IntType; defaultValue = -1 },
                    navArgument("date") { type = NavType.StringType; defaultValue = "" },
                ),
            ) { entry ->
                Guard(currentPerms(), Perm.DUTY_VIEW, back) {
                    val sp = entry.arguments?.getInt("sp")?.takeIf { it > 0 }
                    DutyDayScreen(
                        repository = repository,
                        spId = sp,
                        date = entry.arguments?.getString("date").orEmpty(),
                        canPdf = currentPerms().has(Perm.DUTY_PDF),
                        onBack = back,
                        onOpenPdf = openPdf,
                    )
                }
            }

            // ------------------------------------------------------ Modul
            composable(Routes.NOTIFICATIONS, deepLinks = deepLink("notifications")) {
                Guard(currentPerms(), Perm.NOTIFICATIONS_VIEW, back) { NotificationsScreen(repository, onBack = back) }
            }
            composable(Routes.LETTERS, deepLinks = deepLink("letters")) {
                Guard(currentPerms(), Perm.LETTER_LOGS_VIEW, back) {
                    LetterLogsScreen(repository, currentPerms(), onBack = back, onOpenPdf = openPdf)
                }
            }
            composable(Routes.SKHPP, deepLinks = deepLink("skhpp")) { entry ->
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
            composable(Routes.SIGNATURE, deepLinks = deepLink("signature")) {
                Guard(currentPerms(), Perm.SIGNATURE_VIEW, back) { SignatureScreen(repository, currentPerms(), onBack = back) }
            }
            composable(Routes.CASH, deepLinks = deepLink("cash")) {
                Guard(currentPerms(), Perm.CASH_VIEW, back) {
                    CashScreen(repository, CashKind.BASE, currentPerms(), onBack = back, onOpenPdf = openPdf)
                }
            }
            composable(Routes.TECHNICAL_CASH, deepLinks = deepLink("technical_cash")) {
                Guard(currentPerms(), Perm.TECHNICAL_CASH_VIEW, back) {
                    CashScreen(repository, CashKind.TECHNICAL, currentPerms(), onBack = back, onOpenPdf = openPdf)
                }
            }
            composable(Routes.COMMANDER, deepLinks = deepLink("commander")) {
                Guard(currentPerms(), Perm.COMMANDER_VIEW, back) {
                    CommanderScreen(repository, currentPerms(), onBack = back, onOpenPdf = openPdf)
                }
            }
            composable(Routes.MITRA, deepLinks = deepLink("mitra")) {
                Guard(currentPerms(), Perm.MITRA_VIEW, back) { MitraScreen(repository, currentPerms(), onBack = back) }
            }
            composable(Routes.VIOLATIONS, deepLinks = deepLink("violations")) { entry ->
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
            composable(Routes.USERS, deepLinks = deepLink("users")) {
                Guard(currentPerms(), Perm.USERS_VIEW, back) { UsersScreen(repository, currentPerms(), onBack = back) }
            }
            composable(Routes.SETTINGS) {
                SettingsScreen(repository, onBack = back)
            }

            // ------------------------------------------------------ PDF
            composable(
                Routes.PDF,
                arguments = listOf(
                    navArgument("path") { type = NavType.StringType; defaultValue = "" },
                    navArgument("title") { type = NavType.StringType; defaultValue = "Dokumen" },
                    navArgument("file") { type = NavType.StringType; defaultValue = "dokumen.pdf" },
                ),
            ) { entry ->
                PdfPreviewScreen(
                    repository = repository,
                    apiPath = entry.arguments?.getString("path").orEmpty(),
                    title = entry.arguments?.getString("title").orEmpty(),
                    fileName = entry.arguments?.getString("file").orEmpty(),
                    onBack = back,
                )
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
