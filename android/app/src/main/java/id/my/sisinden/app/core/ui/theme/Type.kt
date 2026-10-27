package id.my.sisinden.app.core.ui.theme

import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Shapes
import androidx.compose.material3.Typography
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.Font
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import id.my.sisinden.app.R

/*
 * Tipografi ramah lansia (mengikuti kit "Elder Friendly Livin'"):
 *  - Keluarga huruf Figtree (OFL, lihat android/licenses/Figtree-OFL.txt).
 *  - Ukuran minimum 14sp, isi utama 16sp, judul seksi 20sp tebal (Black).
 *  - Line-height lega agar mudah dibaca; ukuran mengikuti setelan huruf
 *    sistem dan preferensi "Ukuran teks" di Pengaturan.
 */
val Figtree = FontFamily(
    Font(R.font.figtree_regular, FontWeight.Normal),
    Font(R.font.figtree_medium, FontWeight.Medium),
    Font(R.font.figtree_semibold, FontWeight.SemiBold),
    Font(R.font.figtree_bold, FontWeight.Bold),
    Font(R.font.figtree_black, FontWeight.Black),
)

private val Base = Figtree

val SindenTypography = Typography(
    displayLarge = TextStyle(fontFamily = Base, fontWeight = FontWeight.Black, fontSize = 44.sp, lineHeight = 50.sp, letterSpacing = (-0.6).sp),
    displayMedium = TextStyle(fontFamily = Base, fontWeight = FontWeight.Black, fontSize = 38.sp, lineHeight = 44.sp, letterSpacing = (-0.5).sp),
    displaySmall = TextStyle(fontFamily = Base, fontWeight = FontWeight.Black, fontSize = 32.sp, lineHeight = 38.sp, letterSpacing = (-0.4).sp),
    headlineLarge = TextStyle(fontFamily = Base, fontWeight = FontWeight.Black, fontSize = 28.sp, lineHeight = 34.sp, letterSpacing = (-0.3).sp),
    headlineMedium = TextStyle(fontFamily = Base, fontWeight = FontWeight.Black, fontSize = 24.sp, lineHeight = 30.sp, letterSpacing = (-0.2).sp),
    headlineSmall = TextStyle(fontFamily = Base, fontWeight = FontWeight.Black, fontSize = 22.sp, lineHeight = 28.sp),
    titleLarge = TextStyle(fontFamily = Base, fontWeight = FontWeight.Black, fontSize = 20.sp, lineHeight = 26.sp),
    titleMedium = TextStyle(fontFamily = Base, fontWeight = FontWeight.Bold, fontSize = 18.sp, lineHeight = 24.sp),
    titleSmall = TextStyle(fontFamily = Base, fontWeight = FontWeight.Bold, fontSize = 16.sp, lineHeight = 22.sp),
    bodyLarge = TextStyle(fontFamily = Base, fontWeight = FontWeight.Medium, fontSize = 17.sp, lineHeight = 25.sp),
    bodyMedium = TextStyle(fontFamily = Base, fontWeight = FontWeight.Normal, fontSize = 16.sp, lineHeight = 23.sp),
    bodySmall = TextStyle(fontFamily = Base, fontWeight = FontWeight.Normal, fontSize = 14.sp, lineHeight = 20.sp),
    labelLarge = TextStyle(fontFamily = Base, fontWeight = FontWeight.Bold, fontSize = 16.sp, lineHeight = 22.sp),
    labelMedium = TextStyle(fontFamily = Base, fontWeight = FontWeight.SemiBold, fontSize = 14.sp, lineHeight = 18.sp),
    labelSmall = TextStyle(fontFamily = Base, fontWeight = FontWeight.Medium, fontSize = 13.sp, lineHeight = 18.sp),
)

/** Radius lebar ala Liquid Glass: chip/input, kartu, sheet/hero. */
val SindenShapes = Shapes(
    extraSmall = RoundedCornerShape(10.dp),
    small = RoundedCornerShape(14.dp),
    medium = RoundedCornerShape(20.dp),
    large = RoundedCornerShape(24.dp),
    extraLarge = RoundedCornerShape(32.dp),
)

/** Skala spasi 4-pt. */
object Spacing {
    val xxs = 2.dp
    val xs = 4.dp
    val sm = 8.dp
    val md = 12.dp
    val lg = 16.dp
    val xl = 20.dp
    val xxl = 24.dp
    val xxxl = 32.dp

    /** Padding horizontal standar halaman. */
    val screen = 20.dp

    /** Ukuran area sentuh minimum (aksesibilitas; Livin memakai 48dp). */
    val touchTarget = 48.dp
}
