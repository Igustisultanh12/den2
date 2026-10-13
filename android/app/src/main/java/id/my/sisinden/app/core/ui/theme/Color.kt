package id.my.sisinden.app.core.ui.theme

import androidx.compose.runtime.Immutable
import androidx.compose.ui.graphics.Color

/*
 * Palet "Samudra" SISINDEN v1.1.0
 *
 * Evaluasi palet lama: aplikasi sebelumnya mencampur teal (#00A884/#0D9488),
 * biru navy, dan beberapa palet "Livin" sekaligus sehingga identitasnya kabur.
 * Web SISINDEN dan konfigurasi server (`mobile_primary_color` = #1E3A8A)
 * memakai biru angkatan laut. Palet baru menjadikan biru samudra sebagai warna
 * utama, mempertahankan teal lama sebagai aksen sekunder (kesinambungan
 * identitas), dan amber sebagai aksen peringatan/komando.
 */

// Brand
val Ocean50 = Color(0xFFEEF3FF)
val Ocean100 = Color(0xFFDCE6FF)
val Ocean200 = Color(0xFFB7CAFF)
val Ocean300 = Color(0xFF8FB0FF)
val Ocean500 = Color(0xFF2F5FE3)
val Ocean600 = Color(0xFF1F4FD8)
val Ocean700 = Color(0xFF1A3FAE)
val Ocean800 = Color(0xFF15328A)
val Navy900 = Color(0xFF0B1B4D)
val Navy950 = Color(0xFF070F2E)

val Teal100 = Color(0xFFCFF5EE)
val Teal300 = Color(0xFF5FD9C4)
val Teal600 = Color(0xFF0E9F8A)
val Teal800 = Color(0xFF0A5F54)

val Amber100 = Color(0xFFFFF0D1)
val Amber300 = Color(0xFFFFCB6B)
val Amber600 = Color(0xFFD98A0B)
val Amber900 = Color(0xFF5A3500)

// Neutrals (slate)
val Slate50 = Color(0xFFF5F7FB)
val Slate100 = Color(0xFFEEF2F8)
val Slate200 = Color(0xFFE2E7F0)
val Slate300 = Color(0xFFC3CBDA)
val Slate400 = Color(0xFF94A0B6)
val Slate500 = Color(0xFF66728A)
val Slate600 = Color(0xFF4A5468)
val Slate700 = Color(0xFF333B4D)
val Slate800 = Color(0xFF1C2436)
val Slate850 = Color(0xFF151C2D)
val Slate900 = Color(0xFF111829)
val Slate950 = Color(0xFF0A0F1E)

val Red500 = Color(0xFFD93A3A)
val Red300 = Color(0xFFFF8A80)
val Red100 = Color(0xFFFDE3E3)
val Red900 = Color(0xFF5C1111)

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
    success = Color(0xFF13885A),
    onSuccessContainer = Color(0xFF0B4D33),
    successContainer = Color(0xFFD7F5E6),
    warning = Color(0xFFB86E00),
    onWarningContainer = Color(0xFF5A3500),
    warningContainer = Color(0xFFFFEDC7),
    danger = Color(0xFFC62828),
    onDangerContainer = Color(0xFF5C1111),
    dangerContainer = Color(0xFFFDE3E3),
    info = Ocean600,
    onInfoContainer = Color(0xFF0B1F5C),
    infoContainer = Ocean100,
    neutral = Slate500,
    onNeutralContainer = Slate700,
    neutralContainer = Slate100,
)

val DarkStatusColors = StatusColors(
    success = Color(0xFF5EDBA0),
    onSuccessContainer = Color(0xFFC9F5DF),
    successContainer = Color(0xFF0F3D2A),
    warning = Color(0xFFFFC266),
    onWarningContainer = Color(0xFFFFE7BF),
    warningContainer = Color(0xFF4A3000),
    danger = Color(0xFFFF8A80),
    onDangerContainer = Color(0xFFFFDAD6),
    dangerContainer = Color(0xFF5C1717),
    info = Ocean300,
    onInfoContainer = Ocean100,
    infoContainer = Color(0xFF1B2E6B),
    neutral = Slate400,
    onNeutralContainer = Slate200,
    neutralContainer = Slate800,
)
