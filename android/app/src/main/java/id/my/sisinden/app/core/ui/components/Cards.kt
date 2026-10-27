package id.my.sisinden.app.core.ui.components

import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import id.my.sisinden.app.core.ui.theme.SindenTheme
import id.my.sisinden.app.core.ui.theme.Spacing

/** Skala tekan halus untuk elemen yang dapat diketuk. */
@Composable
fun Modifier.pressScale(interaction: MutableInteractionSource): Modifier {
    val pressed by interaction.collectIsPressedAsState()
    val scale by animateFloatAsState(
        targetValue = if (pressed) 0.97f else 1f,
        animationSpec = spring(dampingRatio = 0.6f, stiffness = 600f),
        label = "pressScale",
    )
    return this.graphicsLayer { scaleX = scale; scaleY = scale }
}

/**
 * Kartu dasar Sinden 2.
 * Terang: putih bersih dengan garis #EDEDED 1,5dp (gaya Livin).
 * Gelap: permukaan kaca tembus pandang dengan tepi bergradien (iOS 26).
 */
@Composable
fun SindenCard(
    modifier: Modifier = Modifier,
    onClick: (() -> Unit)? = null,
    containerColor: Color = MaterialTheme.colorScheme.surface,
    border: BorderStroke? = BorderStroke(1.5.dp, MaterialTheme.colorScheme.outlineVariant),
    contentPadding: Dp = Spacing.lg,
    content: @Composable ColumnScope.() -> Unit,
) {
    val glass = SindenTheme.glass
    val useGlass = SindenTheme.isDark && containerColor == MaterialTheme.colorScheme.surface
    val color = if (useGlass) glass.fill else containerColor
    val stroke = when {
        border == null -> null
        useGlass -> BorderStroke(1.dp, Brush.verticalGradient(listOf(glass.edgeTop, glass.edgeBottom)))
        else -> border
    }
    if (onClick != null) {
        val interaction = remember { MutableInteractionSource() }
        Surface(
            onClick = onClick,
            modifier = modifier.pressScale(interaction),
            shape = MaterialTheme.shapes.medium,
            color = color,
            border = stroke,
            interactionSource = interaction,
        ) {
            Column(Modifier.padding(contentPadding), content = content)
        }
    } else {
        Surface(
            modifier = modifier,
            shape = MaterialTheme.shapes.medium,
            color = color,
            border = stroke,
        ) {
            Column(Modifier.padding(contentPadding), content = content)
        }
    }
}

/** Wadah ikon "squircle" berwarna lembut (sudut 30%, seperti ubin Livin). */
@Composable
fun IconBadge(
    icon: ImageVector,
    tint: Color,
    modifier: Modifier = Modifier,
    size: Dp = 44.dp,
    iconSize: Dp = 22.dp,
    container: Color = tint.copy(alpha = 0.12f),
) {
    Box(
        modifier.size(size).clip(RoundedCornerShape(30)).background(container),
        contentAlignment = Alignment.Center,
    ) {
        Icon(icon, contentDescription = null, tint = tint, modifier = Modifier.size(iconSize))
    }
}

/** Kotak inisial nama (avatar teks). */
@Composable
fun InitialsAvatar(text: String, modifier: Modifier = Modifier, size: Dp = 44.dp, highlight: Boolean = false) {
    val bg = if (highlight) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.primaryContainer
    val fg = if (highlight) MaterialTheme.colorScheme.onPrimary else MaterialTheme.colorScheme.onPrimaryContainer
    Box(modifier.size(size).clip(CircleShape).background(bg), contentAlignment = Alignment.Center) {
        Text(text.take(2).uppercase(), color = fg, style = MaterialTheme.typography.labelLarge, fontWeight = FontWeight.Bold)
    }
}

/** Kartu ringkasan angka (dashboard & modul). */
@Composable
fun StatTile(
    label: String,
    value: String,
    icon: ImageVector,
    tint: Color,
    modifier: Modifier = Modifier,
    caption: String? = null,
    onClick: (() -> Unit)? = null,
) {
    SindenCard(modifier = modifier, onClick = onClick, contentPadding = Spacing.md) {
        IconBadge(icon, tint, size = 44.dp, iconSize = 24.dp)
        Spacer(Modifier.height(Spacing.md))
        Text(
            value,
            style = MaterialTheme.typography.titleLarge,
            fontWeight = FontWeight.Bold,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
        )
        Text(
            label,
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            maxLines = 2,
            overflow = TextOverflow.Ellipsis,
        )
        if (caption != null) {
            Spacer(Modifier.height(Spacing.xxs))
            Text(caption, style = MaterialTheme.typography.labelSmall, color = tint, maxLines = 1)
        }
    }
}

/** Baris label–nilai untuk halaman detail. */
@Composable
fun InfoRow(label: String, value: String?, modifier: Modifier = Modifier) {
    Row(modifier.fillMaxWidth().padding(vertical = Spacing.xs), verticalAlignment = Alignment.Top) {
        Text(
            label,
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            modifier = Modifier.width(128.dp),
        )
        Text(
            value?.takeIf { it.isNotBlank() } ?: "-",
            style = MaterialTheme.typography.bodyMedium,
            fontWeight = FontWeight.Medium,
            modifier = Modifier.weight(1f),
        )
    }
}

/** Item daftar standar: ikon/avatar, judul, subjudul, trailing. */
@Composable
fun ListRowCard(
    title: String,
    modifier: Modifier = Modifier,
    subtitle: String? = null,
    supporting: String? = null,
    leading: (@Composable () -> Unit)? = null,
    trailing: (@Composable RowScope.() -> Unit)? = null,
    onClick: (() -> Unit)? = null,
) {
    SindenCard(modifier = modifier.fillMaxWidth(), onClick = onClick, contentPadding = Spacing.md) {
        Row(Modifier.heightIn(min = 40.dp), verticalAlignment = Alignment.CenterVertically) {
            if (leading != null) {
                leading()
                Spacer(Modifier.width(Spacing.md))
            }
            Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(title, style = MaterialTheme.typography.titleSmall, maxLines = 2, overflow = TextOverflow.Ellipsis)
                if (!subtitle.isNullOrBlank()) {
                    Text(
                        subtitle,
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        maxLines = 2,
                        overflow = TextOverflow.Ellipsis,
                    )
                }
                if (!supporting.isNullOrBlank()) {
                    Text(
                        supporting,
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                }
            }
            if (trailing != null) {
                Spacer(Modifier.width(Spacing.sm))
                Row(verticalAlignment = Alignment.CenterVertically, content = trailing)
            }
        }
    }
}
