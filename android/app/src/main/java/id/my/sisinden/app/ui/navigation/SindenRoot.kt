package id.my.sisinden.app.ui.navigation

import android.content.Intent
import androidx.compose.animation.Crossfade
import androidx.compose.animation.core.tween
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import id.my.sisinden.app.core.data.SessionState
import id.my.sisinden.app.data.repository.SindenRepository
import id.my.sisinden.app.ui.auth.ActivationScreen
import id.my.sisinden.app.ui.auth.LoginScreen
import id.my.sisinden.app.ui.splash.SplashScreen

/**
 * Akar UI: memilih Splash → Autentikasi → Aplikasi utama berdasarkan status sesi.
 * Saat sesi berakhir (logout/token kedaluwarsa/akun dinonaktifkan), seluruh
 * tumpukan navigasi utama dibuang sehingga tidak ada layar data yang tersisa.
 */
@Composable
fun SindenRoot(
    repository: SindenRepository,
    newIntent: Intent?,
    onIntentHandled: () -> Unit,
) {
    val session by repository.session.state.collectAsStateWithLifecycle()

    LaunchedEffect(Unit) {
        // Pulihkan sesi lokal, lalu segarkan permission dari server (jika terjangkau).
        if (repository.session.restore()) {
            repository.refreshSession()
        }
    }

    val stage = when (session) {
        is SessionState.Restoring -> 0
        is SessionState.LoggedOut -> 1
        is SessionState.LoggedIn -> 2
    }

    Crossfade(targetState = stage, animationSpec = tween(350), label = "rootStage") { s ->
        when (s) {
            0 -> SplashScreen()
            1 -> AuthNavigation(repository, (session as? SessionState.LoggedOut))
            else -> {
                val loggedIn = session as? SessionState.LoggedIn
                if (loggedIn != null) {
                    MainNavigation(
                        repository = repository,
                        session = loggedIn,
                        newIntent = newIntent,
                        onIntentHandled = onIntentHandled,
                    )
                } else {
                    SplashScreen()
                }
            }
        }
    }
}

@Composable
private fun AuthNavigation(repository: SindenRepository, state: SessionState.LoggedOut?) {
    val nav = rememberNavController()
    NavHost(navController = nav, startDestination = Routes.LOGIN) {
        composable(Routes.LOGIN) {
            LoginScreen(
                repository = repository,
                logoutReason = state?.reason,
                onOpenActivation = { nav.navigate(Routes.ACTIVATION) },
            )
        }
        composable(Routes.ACTIVATION) {
            ActivationScreen(
                repository = repository,
                onBack = { nav.popBackStack() },
                onActivated = { nav.popBackStack() },
            )
        }
    }
}
