package id.my.sisinden.app.core.ui.theme

import android.app.Activity
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.ReadOnlyComposable
import androidx.compose.runtime.SideEffect
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalView
import androidx.core.view.WindowCompat

/** Preferensi tema yang disimpan pengguna. */
enum class ThemeMode { SYSTEM, LIGHT, DARK }

private val LightColors = lightColorScheme(
    primary = Ocean600,
    onPrimary = Color.White,
    primaryContainer = Ocean100,
    onPrimaryContainer = Color(0xFF0B1F5C),
    inversePrimary = Ocean300,
    secondary = Teal600,
    onSecondary = Color.White,
    secondaryContainer = Teal100,
    onSecondaryContainer = Teal800,
    tertiary = Amber600,
    onTertiary = Color.White,
    tertiaryContainer = Amber100,
    onTertiaryContainer = Amber900,
    background = Slate50,
    onBackground = Color(0xFF0F172A),
    surface = Color.White,
    onSurface = Color(0xFF0F172A),
    surfaceVariant = Slate100,
    onSurfaceVariant = Slate500,
    surfaceTint = Ocean600,
    inverseSurface = Slate800,
    inverseOnSurface = Slate50,
    error = Red500,
    onError = Color.White,
    errorContainer = Red100,
    onErrorContainer = Red900,
    outline = Slate300,
    outlineVariant = Slate200,
    scrim = Color.Black,
    surfaceBright = Color.White,
    surfaceDim = Slate100,
    surfaceContainerLowest = Color.White,
    surfaceContainerLow = Color(0xFFF8FAFD),
    surfaceContainer = Slate100,
    surfaceContainerHigh = Slate200,
    surfaceContainerHighest = Color(0xFFD9DFEA),
)

private val DarkColors = darkColorScheme(
    primary = Ocean300,
    onPrimary = Color(0xFF0A1F5C),
    primaryContainer = Color(0xFF1B3A9A),
    onPrimaryContainer = Ocean100,
    inversePrimary = Ocean600,
    secondary = Teal300,
    onSecondary = Color(0xFF00382F),
    secondaryContainer = Teal800,
    onSecondaryContainer = Teal100,
    tertiary = Amber300,
    onTertiary = Color(0xFF452B00),
    tertiaryContainer = Color(0xFF5E3D00),
    onTertiaryContainer = Amber100,
    background = Slate950,
    onBackground = Color(0xFFE8ECF4),
    surface = Slate900,
    onSurface = Color(0xFFE8ECF4),
    surfaceVariant = Slate800,
    onSurfaceVariant = Slate400,
    surfaceTint = Ocean300,
    inverseSurface = Slate100,
    inverseOnSurface = Slate900,
    error = Red300,
    onError = Color(0xFF5C1111),
    errorContainer = Color(0xFF7A1C1C),
    onErrorContainer = Red100,
    outline = Slate600,
    outlineVariant = Slate700,
    scrim = Color.Black,
    surfaceBright = Slate800,
    surfaceDim = Slate950,
    surfaceContainerLowest = Slate950,
    surfaceContainerLow = Slate900,
    surfaceContainer = Slate850,
    surfaceContainerHigh = Slate800,
    surfaceContainerHighest = Slate700,
)

private val LocalStatusColors = staticCompositionLocalOf { LightStatusColors }
private val LocalIsDark = staticCompositionLocalOf { false }

/** Akses cepat token desain Sinden 2 yang tidak tersedia di MaterialTheme. */
object SindenTheme {
    val status: StatusColors
        @Composable @ReadOnlyComposable get() = LocalStatusColors.current

    val isDark: Boolean
        @Composable @ReadOnlyComposable get() = LocalIsDark.current
}

@Composable
fun SindenTheme(
    themeMode: ThemeMode = ThemeMode.SYSTEM,
    content: @Composable () -> Unit,
) {
    val dark = when (themeMode) {
        ThemeMode.SYSTEM -> isSystemInDarkTheme()
        ThemeMode.LIGHT -> false
        ThemeMode.DARK -> true
    }
    val colors = if (dark) DarkColors else LightColors

    val view = LocalView.current
    if (!view.isInEditMode) {
        SideEffect {
            val window = (view.context as? Activity)?.window
            if (window != null) {
                val controller = WindowCompat.getInsetsController(window, view)
                controller.isAppearanceLightStatusBars = !dark
                controller.isAppearanceLightNavigationBars = !dark
            }
        }
    }

    CompositionLocalProvider(
        LocalStatusColors provides if (dark) DarkStatusColors else LightStatusColors,
        LocalIsDark provides dark,
    ) {
        MaterialTheme(
            colorScheme = colors,
            typography = SindenTypography,
            shapes = SindenShapes,
            content = content,
        )
    }
}
