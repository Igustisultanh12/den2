package id.my.sisinden.app.ui.navigation

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Description
import androidx.compose.material.icons.filled.Email
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.Settings
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.NavigationBarItemDefaults
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.navigation.NavGraph.Companion.findStartDestination
import androidx.navigation.NavHostController
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.ui.auth.AuthViewModel
import id.my.sisinden.app.ui.auth.LoginScreen
import id.my.sisinden.app.ui.cash.CashScreen
import id.my.sisinden.app.ui.cash.CashViewModel
import id.my.sisinden.app.ui.cash.TechnicalCashScreen
import id.my.sisinden.app.ui.dashboard.DashboardScreen
import id.my.sisinden.app.ui.dashboard.DashboardViewModel
import id.my.sisinden.app.ui.letters.CreateLetterScreen
import id.my.sisinden.app.ui.letters.LetterLogsScreen
import id.my.sisinden.app.ui.letters.LetterViewModel
import id.my.sisinden.app.ui.mitra.MitraScreen
import id.my.sisinden.app.ui.mitra.MitraViewModel
import id.my.sisinden.app.ui.notifications.NotificationsScreen
import id.my.sisinden.app.ui.notifications.NotificationsViewModel
import id.my.sisinden.app.ui.personnel.PersonnelScreen
import id.my.sisinden.app.ui.personnel.PersonnelViewModel
import id.my.sisinden.app.ui.settings.SettingsScreen
import id.my.sisinden.app.ui.settings.SettingsViewModel
import id.my.sisinden.app.ui.signature.SignatureScreen
import id.my.sisinden.app.ui.signature.SignatureViewModel
import id.my.sisinden.app.ui.skhpp.CreateSkhppScreen
import id.my.sisinden.app.ui.skhpp.SkhppScreen
import id.my.sisinden.app.ui.skhpp.SkhppViewModel
import id.my.sisinden.app.ui.violations.ViolationsScreen
import id.my.sisinden.app.ui.violations.ViolationsViewModel

@Composable
fun SindenApp(
    repository: SindenRepository,
    navController: NavHostController = rememberNavController()
) {
    val tokenState by repository.tokenFlow.collectAsState(initial = "INITIALIZING")
    val factory = SindenViewModelFactory(repository)

    if (tokenState == "INITIALIZING") {
        Box(
            modifier = Modifier.fillMaxSize(),
            contentAlignment = Alignment.Center
        ) {
            CircularProgressIndicator(color = MaterialTheme.colorScheme.primary)
        }
        return
    }

    val startDestination = if (tokenState.isNullOrEmpty()) {
        NavRoute.Login.route
    } else {
        NavRoute.Main.route
    }

    val navBackStackEntry by navController.currentBackStackEntryAsState()
    val currentRoute = navBackStackEntry?.destination?.route

    val bottomBarRoutes = listOf(
        NavRoute.Main.route,
        NavRoute.LetterLogs.route,
        NavRoute.Skhpp.route,
        NavRoute.Settings.route
    )

    val showBottomBar = currentRoute in bottomBarRoutes

    Scaffold(
        bottomBar = {
            if (showBottomBar) {
                NavigationBar(
                    containerColor = MaterialTheme.colorScheme.surface,
                    contentColor = MaterialTheme.colorScheme.onSurface
                ) {
                    NavigationBarItem(
                        selected = currentRoute == NavRoute.Main.route,
                        onClick = {
                            if (currentRoute != NavRoute.Main.route) {
                                navController.navigate(NavRoute.Main.route) {
                                    popUpTo(navController.graph.findStartDestination().id) {
                                        saveState = true
                                    }
                                    launchSingleTop = true
                                    restoreState = true
                                }
                            }
                        },
                        icon = { Icon(Icons.Default.Home, contentDescription = "Beranda") },
                        label = { Text("Beranda", fontWeight = FontWeight.Medium) },
                        colors = NavigationBarItemDefaults.colors(
                            selectedIconColor = MaterialTheme.colorScheme.primary,
                            selectedTextColor = MaterialTheme.colorScheme.primary,
                            indicatorColor = MaterialTheme.colorScheme.primaryContainer
                        )
                    )

                    NavigationBarItem(
                        selected = currentRoute == NavRoute.LetterLogs.route,
                        onClick = {
                            if (currentRoute != NavRoute.LetterLogs.route) {
                                navController.navigate(NavRoute.LetterLogs.route) {
                                    popUpTo(navController.graph.findStartDestination().id) {
                                        saveState = true
                                    }
                                    launchSingleTop = true
                                    restoreState = true
                                }
                            }
                        },
                        icon = { Icon(Icons.Default.Email, contentDescription = "Surat") },
                        label = { Text("Surat", fontWeight = FontWeight.Medium) },
                        colors = NavigationBarItemDefaults.colors(
                            selectedIconColor = MaterialTheme.colorScheme.primary,
                            selectedTextColor = MaterialTheme.colorScheme.primary,
                            indicatorColor = MaterialTheme.colorScheme.primaryContainer
                        )
                    )

                    NavigationBarItem(
                        selected = currentRoute == NavRoute.Skhpp.route,
                        onClick = {
                            if (currentRoute != NavRoute.Skhpp.route) {
                                navController.navigate(NavRoute.Skhpp.route) {
                                    popUpTo(navController.graph.findStartDestination().id) {
                                        saveState = true
                                    }
                                    launchSingleTop = true
                                    restoreState = true
                                }
                            }
                        },
                        icon = { Icon(Icons.Default.Description, contentDescription = "SKHPP") },
                        label = { Text("SKHPP", fontWeight = FontWeight.Medium) },
                        colors = NavigationBarItemDefaults.colors(
                            selectedIconColor = MaterialTheme.colorScheme.primary,
                            selectedTextColor = MaterialTheme.colorScheme.primary,
                            indicatorColor = MaterialTheme.colorScheme.primaryContainer
                        )
                    )

                    NavigationBarItem(
                        selected = currentRoute == NavRoute.Settings.route,
                        onClick = {
                            if (currentRoute != NavRoute.Settings.route) {
                                navController.navigate(NavRoute.Settings.route) {
                                    popUpTo(navController.graph.findStartDestination().id) {
                                        saveState = true
                                    }
                                    launchSingleTop = true
                                    restoreState = true
                                }
                            }
                        },
                        icon = { Icon(Icons.Default.Settings, contentDescription = "Pengaturan") },
                        label = { Text("Pengaturan", fontWeight = FontWeight.Medium) },
                        colors = NavigationBarItemDefaults.colors(
                            selectedIconColor = MaterialTheme.colorScheme.primary,
                            selectedTextColor = MaterialTheme.colorScheme.primary,
                            indicatorColor = MaterialTheme.colorScheme.primaryContainer
                        )
                    )
                }
            }
        }
    ) { innerPadding ->
        NavHost(
            navController = navController,
            startDestination = startDestination,
            modifier = Modifier.padding(innerPadding)
        ) {
            composable(NavRoute.Login.route) {
                val authVm: AuthViewModel = viewModel(factory = factory)
                LoginScreen(
                    viewModel = authVm,
                    onLoginSuccess = {
                        navController.navigate(NavRoute.Main.route) {
                            popUpTo(NavRoute.Login.route) { inclusive = true }
                        }
                    }
                )
            }

            composable(NavRoute.Main.route) {
                val dashVm: DashboardViewModel = viewModel(factory = factory)
                DashboardScreen(
                    viewModel = dashVm,
                    onNavigateToLetters = { navController.navigate(NavRoute.LetterLogs.route) },
                    onNavigateToSkhpp = { navController.navigate(NavRoute.Skhpp.route) },
                    onNavigateToCash = { navController.navigate(NavRoute.Cash.route) },
                    onNavigateToTechnicalCash = { navController.navigate(NavRoute.TechnicalCash.route) },
                    onNavigateToMitra = { navController.navigate(NavRoute.Mitra.route) },
                    onNavigateToSignature = { navController.navigate(NavRoute.Signature.route) },
                    onNavigateToViolations = { navController.navigate(NavRoute.Violations.route) },
                    onNavigateToPersonnel = { navController.navigate(NavRoute.Personnel.route) },
                    onNavigateToNotifications = { navController.navigate(NavRoute.Notifications.route) },
                    onNavigateToSettings = { navController.navigate(NavRoute.Settings.route) }
                )
            }

            composable(NavRoute.LetterLogs.route) {
                val letterVm: LetterViewModel = viewModel(factory = factory)
                LetterLogsScreen(
                    viewModel = letterVm,
                    onBackClick = {
                        if (navController.previousBackStackEntry != null) {
                            navController.popBackStack()
                        } else {
                            navController.navigate(NavRoute.Main.route)
                        }
                    },
                    onCreateLetterClick = { navController.navigate(NavRoute.CreateLetter.route) }
                )
            }

            composable(NavRoute.CreateLetter.route) {
                val letterVm: LetterViewModel = viewModel(factory = factory)
                CreateLetterScreen(
                    viewModel = letterVm,
                    onBackClick = { navController.popBackStack() }
                )
            }

            composable(NavRoute.Skhpp.route) {
                val skhppVm: SkhppViewModel = viewModel(factory = factory)
                SkhppScreen(
                    viewModel = skhppVm,
                    onBackClick = {
                        if (navController.previousBackStackEntry != null) {
                            navController.popBackStack()
                        } else {
                            navController.navigate(NavRoute.Main.route)
                        }
                    },
                    onCreateSkhppClick = { navController.navigate(NavRoute.CreateSkhpp.route) }
                )
            }

            composable(NavRoute.CreateSkhpp.route) {
                val skhppVm: SkhppViewModel = viewModel(factory = factory)
                CreateSkhppScreen(
                    viewModel = skhppVm,
                    onBackClick = { navController.popBackStack() }
                )
            }

            composable(NavRoute.Cash.route) {
                val cashVm: CashViewModel = viewModel(factory = factory)
                CashScreen(
                    viewModel = cashVm,
                    onBackClick = { navController.popBackStack() }
                )
            }

            composable(NavRoute.TechnicalCash.route) {
                val cashVm: CashViewModel = viewModel(factory = factory)
                TechnicalCashScreen(
                    viewModel = cashVm,
                    onBackClick = { navController.popBackStack() }
                )
            }

            composable(NavRoute.Mitra.route) {
                val mitraVm: MitraViewModel = viewModel(factory = factory)
                MitraScreen(
                    viewModel = mitraVm,
                    onBackClick = { navController.popBackStack() }
                )
            }

            composable(NavRoute.Signature.route) {
                val sigVm: SignatureViewModel = viewModel(factory = factory)
                SignatureScreen(
                    viewModel = sigVm,
                    onBackClick = { navController.popBackStack() }
                )
            }

            composable(NavRoute.Violations.route) {
                val vioVm: ViolationsViewModel = viewModel(factory = factory)
                ViolationsScreen(
                    viewModel = vioVm,
                    onBackClick = { navController.popBackStack() }
                )
            }

            composable(NavRoute.Personnel.route) {
                val perVm: PersonnelViewModel = viewModel(factory = factory)
                PersonnelScreen(
                    viewModel = perVm,
                    onBackClick = { navController.popBackStack() }
                )
            }

            composable(NavRoute.Notifications.route) {
                val notifVm: NotificationsViewModel = viewModel(factory = factory)
                NotificationsScreen(
                    viewModel = notifVm,
                    onBack = { navController.popBackStack() }
                )
            }

            composable(NavRoute.Settings.route) {
                val setVm: SettingsViewModel = viewModel(factory = factory)
                SettingsScreen(
                    viewModel = setVm,
                    onLogoutSuccess = {
                        navController.navigate(NavRoute.Login.route) {
                            popUpTo(0) { inclusive = true }
                        }
                    }
                )
            }
        }
    }
}
