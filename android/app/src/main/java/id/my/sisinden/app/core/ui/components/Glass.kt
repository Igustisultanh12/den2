package id.my.sisinden.app.core.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxScope
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Shape
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.semantics.Role
import androidx.compose.foundation.clickable
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import id.my.sisinden.app.core.ui.theme.SindenTheme
import id.my.sisinden.app.core.ui.theme.TileGradient
import id.my.sisinden.app.core.ui.theme.TilePalette

/**
 * Permukaan "Liquid Glass": isi tembus pandang, garis tepi bergradien (atas
 * terang → bawah samar), dan bayangan lembut. Tidak memakai blur latar
 * (RenderEffect) agar ringan di perangkat lama dan teks tetap tajam.
 */
@Composable
fun Modifier.glass(
    shape: Shape = MaterialTheme.shapes.large,
    strong: Boolean = false,
    elevation: Dp = 0.dp,
): Modifier {
    val g = SindenTheme.glass
    val shadowed = if (elevation > 0.dp) {
        this.shadow(elevation, shape, clip = false, ambientColor = g.shadow, spotColor = g.shadow)
    } else this
    return shadowed
        .clip(shape)
        .background(if (strong) g.fillStrong else g.fill)
        .border(1.dp, Brush.verticalGradient(listOf(g.edgeTop, g.edgeBottom)), shape)
}

/** Latar aplikasi: warna kanvas + cahaya lembut bergaya iOS 26. */
@Composable
fun GlassBackground(modifier: Modifier = Modifier, content: @Composable BoxScope.() -> Unit) {
    val g = SindenTheme.glass
    val base = MaterialTheme.colorScheme.background
    Box(
        modifier
            .fillMaxSize()
            .background(base)
            .drawBehind {
                val r = size.maxDimension * 0.55f
                drawCircle(
                    Brush.radialGradient(listOf(g.glowA, Color.Transparent), center = Offset(size.width * 0.95f, size.height * 0.08f), radius = r),
                    radius = r,
                    center = Offset(size.width * 0.95f, size.height * 0.08f),
                )
                drawCircle(
                    Brush.radialGradient(listOf(g.glowB, Color.Transparent), center = Offset(0f, size.height * 0.55f), radius = r * 0.9f),
                    radius = r * 0.9f,
                    center = Offset(0f, size.height * 0.55f),
                )
                drawCircle(
                    Brush.radialGradient(listOf(g.glowC, Color.Transparent), center = Offset(size.width, size.height), radius = r * 0.8f),
                    radius = r * 0.8f,
                    center = Offset(size.width, size.height),
                )
            },
        content = content,
    )
}

/** Kotak ikon bergradien (ubin fitur Livin), ikon putih. */
@Composable
fun GradientIconTile(
    icon: ImageVector,
    gradient: TileGradient,
    modifier: Modifier = Modifier,
    size: Dp = 56.dp,
    iconSize: Dp = 28.dp,
    corner: Dp = 20.dp,
    contentDescription: String? = null,
) {
    Box(
        modifier
            .size(size)
            .shadow(3.dp, RoundedCornerShape(corner), ambientColor = gradient.bottom.copy(alpha = 0.35f), spotColor = gradient.bottom.copy(alpha = 0.35f))
            .clip(RoundedCornerShape(corner))
            .background(Brush.verticalGradient(listOf(gradient.top, gradient.bottom)))
            .border(1.dp, Brush.verticalGradient(listOf(Color.White.copy(alpha = 0.55f), Color.White.copy(alpha = 0.05f))), RoundedCornerShape(corner)),
        contentAlignment = Alignment.Center,
    ) {
        Icon(icon, contentDescription = contentDescription, tint = Color.White, modifier = Modifier.size(iconSize))
    }
}

/**
 * Tombol fitur vertikal ala "Fitur Utama Anda" Livin: ubin bergradien 56dp +
 * label 2 baris di bawahnya. Area sentuh ≥ 48dp, label selalu tampil.
 */
@Composable
fun FeatureTile(
    title: String,
    icon: ImageVector,
    gradient: TileGradient,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    badge: Int = 0,
) {
    val interaction = remember { MutableInteractionSource() }
    Column(
        modifier
            .widthIn(min = 64.dp)
            .clip(MaterialTheme.shapes.medium)
            .clickable(interactionSource = interaction, indication = null, role = Role.Button, onClick = onClick)
            .pressScale(interaction)
            .padding(vertical = 4.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        Box {
            GradientIconTile(icon, gradient)
            if (badge > 0) CountBadge(badge, Modifier.align(Alignment.TopEnd).padding(top = 0.dp))
        }
        Text(
            title,
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurface,
            textAlign = TextAlign.Center,
            maxLines = 2,
            minLines = 2,
            overflow = TextOverflow.Ellipsis,
        )
    }
}

/** Gradien bawaan per [Tone], untuk ubin yang tidak punya palet khusus. */
fun toneGradient(tone: Tone): TileGradient = when (tone) {
    Tone.SUCCESS -> TilePalette.Teal
    Tone.WARNING -> TilePalette.Orange
    Tone.DANGER -> TilePalette.Rose
    Tone.INFO -> TilePalette.Blue
    Tone.NEUTRAL -> TilePalette.Slate
}

/** Garis pemisah tipis antarbagian (Livin memakai garis 1px di bawah "Fitur Utama"). */
@Composable
fun SoftDivider(modifier: Modifier = Modifier) {
    Box(
        modifier
            .height(1.dp)
            .background(MaterialTheme.colorScheme.outlineVariant)
    )
}
