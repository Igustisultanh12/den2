package id.my.sisinden.app.core.ui.theme

import android.app.Activity
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.Immutable
import androidx.compose.runtime.ReadOnlyComposable
import androidx.compose.runtime.SideEffect
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.unit.Density
import androidx.core.view.WindowCompat

/** Preferensi tema yang disimpan pengguna. */
enum class ThemeMode { SYSTEM, LIGHT, DARK }

/** Preferensi ukuran teks ("mode mudah dibaca"), dikalikan dengan setelan huruf sistem. */
enum class TextScale(val factor: Float, val label: String) {
    NORMAL(1f, "Normal"),
    LARGE(1.15f, "Besar"),
    EXTRA_LARGE(1.3f, "Sangat besar"),
}

private val LightColors = lightColorScheme(
    primary = Blue600,
    onPrimary = Color.White,
    primaryContainer = Blue50,
    onPrimaryContainer = Blue900,
    inversePrimary = Blue300,
    secondary = Teal700,
    onSecondary = Color.White,
    secondaryContainer = Teal100,
    onSecondaryContainer = Color(0xFF053F31),
    tertiary = Orange700,
    onTertiary = Color.White,
    tertiaryContainer = Orange100,
    onTertiaryContainer = Color(0xFF4A2300),
    background = Canvas,
    onBackground = Grey500,
    surface = Color.White,
    onSurface = Grey500,
    surfaceVariant = Wash,
    onSurfaceVariant = Grey400,
    surfaceTint = Blue600,
    inverseSurface = Night800,
    inverseOnSurface = Night100,
    error = Red500,
    onError = Color.White,
    errorContainer = Red100,
    onErrorContainer = Red900,
    outline = Grey100,
    outlineVariant = Line,
    scrim = Color.Black,
    surfaceBright = Color.White,
    surfaceDim = Color(0xFFE9EEF6),
    surfaceContainerLowest = Color.White,
    surfaceContainerLow = Color(0xFFF9FBFF),
    surfaceContainer = Color(0xFFF1F5FB),
    surfaceContainerHigh = Color(0xFFEAF0F8),
    surfaceContainerHighest = Color(0xFFE2E9F3),
)

private val DarkColors = darkColorScheme(
    primary = Blue300,
    onPrimary = Blue900,
    primaryContainer = Color(0xFF12386B),
    onPrimaryContainer = Blue50,
    inversePrimary = Blue600,
    secondary = Color(0xFF5EE0B4),
    onSecondary = Color(0xFF003828),
    secondaryContainer = Color(0xFF0D3B2E),
    onSecondaryContainer = Teal100,
    tertiary = Color(0xFFFFB866),
    onTertiary = Color(0xFF452400),
    tertiaryContainer = Color(0xFF4A2A00),
    onTertiaryContainer = Orange100,
    background = Night900,
    onBackground = Night100,
    surface = Night850,
    onSurface = Night100,
    surfaceVariant = Night800,
    onSurfaceVariant = Night400,
    surfaceTint = Blue300,
    inverseSurface = Night100,
    inverseOnSurface = Night900,
    error = Red300,
    onError = Red900,
    errorContainer = Color(0xFF7A1C1C),
    onErrorContainer = Red100,
    outline = Night600,
    outlineVariant = Night700,
    scrim = Color.Black,
    surfaceBright = Night700,
    surfaceDim = Night950,
    surfaceContainerLowest = Night950,
    surfaceContainerLow = Night900,
    surfaceContainer = Night850,
    surfaceContainerHigh = Night800,
    surfaceContainerHighest = Night700,
)

/**
 * Token "Liquid Glass": isi tembus pandang + garis tepi bergradien (atas
 * lebih terang) + bayangan lembut. Di mode terang kaca lebih pekat agar
 * kontras teks tetap ≥ 4,5:1 sesuai prinsip ramah lansia.
 */
@Immutable
data class GlassColors(
    val fill: Color,
    val fillStrong: Color,
    val edgeTop: Color,
    val edgeBottom: Color,
    val shadow: Color,
    val glowA: Color,
    val glowB: Color,
    val glowC: Color,
)

private val LightGlass = GlassColors(
    fill = Color.White.copy(alpha = 0.82f),
    fillStrong = Color.White.copy(alpha = 0.94f),
    edgeTop = Color.White,
    edgeBottom = Blue600.copy(alpha = 0.12f),
    shadow = Blue900.copy(alpha = 0.10f),
    glowA = Blue500.copy(alpha = 0.16f),
    glowB = Color(0xFF55D0F5).copy(alpha = 0.14f),
    glowC = Violet400.copy(alpha = 0.10f),
)

private val DarkGlass = GlassColors(
    fill = Color.White.copy(alpha = 0.07f),
    fillStrong = Color.White.copy(alpha = 0.11f),
    edgeTop = Color.White.copy(alpha = 0.24f),
    edgeBottom = Color.White.copy(alpha = 0.05f),
    shadow = Color.Black.copy(alpha = 0.45f),
    glowA = Blue500.copy(alpha = 0.22f),
    glowB = Color(0xFF2EC5CE).copy(alpha = 0.12f),
    glowC = Violet400.copy(alpha = 0.18f),
)

private val LocalStatusColors = staticCompositionLocalOf { LightStatusColors }
private val LocalGlassColors = staticCompositionLocalOf { LightGlass }
private val LocalIsDark = staticCompositionLocalOf { false }

/** Akses cepat token desain Sinden 2 yang tidak tersedia di MaterialTheme. */
object SindenTheme {
    val status: StatusColors
        @Composable @ReadOnlyComposable get() = LocalStatusColors.current

    val glass: GlassColors
        @Composable @ReadOnlyComposable get() = LocalGlassColors.current

    val isDark: Boolean
        @Composable @ReadOnlyComposable get() = LocalIsDark.current

    /** Warna gradien header/hero. */
    val heroGradient: List<Color>
        @Composable @ReadOnlyComposable get() = if (LocalIsDark.current) HeroGradientDark else HeroGradientLight
}

@Composable
fun SindenTheme(
    themeMode: ThemeMode = ThemeMode.SYSTEM,
    textScale: TextScale = TextScale.NORMAL,
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
                // Bagian atas setiap layar berwarna biru (header Livin) → ikon status putih.
                controller.isAppearanceLightStatusBars = false
                controller.isAppearanceLightNavigationBars = !dark
            }
        }
    }

    val density = LocalDensity.current
    val scaledDensity = Density(density.density, density.fontScale * textScale.factor)

    CompositionLocalProvider(
        LocalStatusColors provides if (dark) DarkStatusColors else LightStatusColors,
        LocalGlassColors provides if (dark) DarkGlass else LightGlass,
        LocalIsDark provides dark,
        LocalDensity provides scaledDensity,
    ) {
        MaterialTheme(
            colorScheme = colors,
            typography = SindenTypography,
            shapes = SindenShapes,
            content = content,
        )
    }
}
