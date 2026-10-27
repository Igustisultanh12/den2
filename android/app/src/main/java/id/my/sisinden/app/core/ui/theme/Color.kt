package id.my.sisinden.app.core.ui.theme

import androidx.compose.runtime.Immutable
import androidx.compose.ui.graphics.Color

/*
 * Palet "Livin Glass" Sinden 2 v1.2
 *
 * Perpaduan dua referensi Figma:
 *  - Elder Friendly Livin' (Mandiri redesign): biru #2B93FE, putih bersih,
 *    abu #333 untuk teks, ubin fitur bergradien, kontras minimal 4,5:1.
 *  - iOS 26 Liquid Glass: permukaan kaca tembus pandang, garis tepi terang,
 *    cahaya lembut di latar, tab bar mengambang.
 *
 * Biru Livin asli (#2B93FE) hanya 3,1:1 terhadap putih, jadi dipakai untuk
 * gradien/dekorasi. Teks, tautan, dan tombol memakai [Blue600] (4,9:1).
 */

// Biru Livin
val Blue50 = Color(0xFFE6F2FF)
val Blue100 = Color(0xFFCCE5FF)
val Blue200 = Color(0xFF9ECEFF)
val Blue300 = Color(0xFF6BB4FF)
val Blue400 = Color(0xFF4BA3FE)
val Blue500 = Color(0xFF2B93FE) // Foundation/Blue/blue-500 (dekoratif)
val Blue600 = Color(0xFF0F6FD6) // primer aksesibel
val Blue700 = Color(0xFF0A5CB8)
val Blue800 = Color(0xFF08498F)
val Blue900 = Color(0xFF062F5E)

// Abu Livin (Foundation/Grey)
val Grey50 = Color(0xFFEBEBEB)
val Grey100 = Color(0xFFC0C0C0)
val Grey200 = Color(0xFFA1A1A1)
val Grey300 = Color(0xFF767676)
val Grey400 = Color(0xFF5E6670) // teks sekunder (5,8:1)
val Grey500 = Color(0xFF333333) // teks utama
val Line = Color(0xFFEDEDED) // garis kartu Livin
val Wash = Color(0xFFF7F6F6) // latar bar info di dalam kartu
val Canvas = Color(0xFFF4F8FF) // latar aplikasi terang

// Malam (iOS glass)
val Night950 = Color(0xFF07080B)
val Night900 = Color(0xFF0B0D12)
val Night850 = Color(0xFF10131A)
val Night800 = Color(0xFF161A23)
val Night700 = Color(0xFF222838)
val Night600 = Color(0xFF353C4E)
val Night400 = Color(0xFF8D96A8)
val Night200 = Color(0xFFD5DAE4)
val Night100 = Color(0xFFEDF0F6)

// Aksen
val Teal500 = Color(0xFF16C79A)
val Teal700 = Color(0xFF0B7A5F)
val Teal100 = Color(0xFFD5F7EC)
val Orange500 = Color(0xFFFF8A1F)
val Orange700 = Color(0xFFB45300)
val Orange100 = Color(0xFFFFEBD6)
val Violet400 = Color(0xFF9B87F5)
val Red500 = Color(0xFFD93A3A)
val Red300 = Color(0xFFFF8A80)
val Red100 = Color(0xFFFDE3E3)
val Red900 = Color(0xFF5C1111)

/** Gradien hero/header Livin (≈175°): biru pekat → biru Livin → biru muda. */
val HeroGradientLight = listOf(Blue600, Blue500, Blue200)
val HeroGradientDark = listOf(Color(0xFF0B2A55), Color(0xFF123B73), Night900)

/** Pasangan warna ubin fitur bergradien (atas → bawah), mengikuti kit Livin. */
@Immutable
data class TileGradient(val top: Color, val bottom: Color)

object TilePalette {
    val Teal = TileGradient(Color(0xFF1FE3BE), Color(0xFF14B08A))
    val Orange = TileGradient(Color(0xFFFF942E), Color(0xFFFF7E03))
    val Sky = TileGradient(Color(0xFF55D0F5), Color(0xFF2AA8D6))
    val Lime = TileGradient(Color(0xFFB5D162), Color(0xFF88AA3A))
    val Blue = TileGradient(Color(0xFF5AB0FF), Color(0xFF1F7FE6))
    val Violet = TileGradient(Color(0xFFA48BFA), Color(0xFF7356E8))
    val Rose = TileGradient(Color(0xFFFF7D8C), Color(0xFFE8475A))
    val Slate = TileGradient(Color(0xFF8C9AB3), Color(0xFF5C6A85))
}

/** Warna status semantik (sukses/peringatan/bahaya/info/netral) untuk chip & badge. */
@Immutable
data class StatusColors(
    val success: Color,
    val onSuccessContainer: Color,
    val successContainer: Color,
    val warning: Color,
    val onWarningContainer: Color,
    val warningContainer: Color,
    val danger: Color,
    val onDangerContainer: Color,
    val dangerContainer: Color,
    val info: Color,
    val onInfoContainer: Color,
    val infoContainer: Color,
    val neutral: Color,
    val onNeutralContainer: Color,
    val neutralContainer: Color,
)

val LightStatusColors = StatusColors(
    success = Color(0xFF0E8A63),
    onSuccessContainer = Color(0xFF064A35),
    successContainer = Teal100,
    warning = Orange700,
    onWarningContainer = Color(0xFF5A2B00),
    warningContainer = Orange100,
    danger = Color(0xFFC62828),
    onDangerContainer = Red900,
    dangerContainer = Red100,
    info = Blue600,
    onInfoContainer = Blue900,
    infoContainer = Blue50,
    neutral = Grey400,
    onNeutralContainer = Grey500,
    neutralContainer = Color(0xFFF0F2F5),
)

val DarkStatusColors = StatusColors(
    success = Color(0xFF5EE0B4),
    onSuccessContainer = Color(0xFFC9F7E6),
    successContainer = Color(0xFF0D3B2E),
    warning = Color(0xFFFFB866),
    onWarningContainer = Color(0xFFFFE6C7),
    warningContainer = Color(0xFF4A2A00),
    danger = Red300,
    onDangerContainer = Color(0xFFFFDAD6),
    dangerContainer = Color(0xFF5C1717),
    info = Blue300,
    onInfoContainer = Blue50,
    infoContainer = Color(0xFF12305A),
    neutral = Night400,
    onNeutralContainer = Night200,
    neutralContainer = Night700,
)
