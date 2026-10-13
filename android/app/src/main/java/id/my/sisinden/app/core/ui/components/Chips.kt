package id.my.sisinden.app.core.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Check
import androidx.compose.material3.FilterChip
import androidx.compose.material3.FilterChipDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import id.my.sisinden.app.core.ui.theme.SindenTheme
import id.my.sisinden.app.core.ui.theme.Spacing

enum class Tone { SUCCESS, WARNING, DANGER, INFO, NEUTRAL }

data class ToneColors(val fg: Color, val bg: Color)

@Composable
fun toneColors(tone: Tone): ToneColors {
    val s = SindenTheme.status
    return when (tone) {
        Tone.SUCCESS -> ToneColors(s.onSuccessContainer, s.successContainer)
        Tone.WARNING -> ToneColors(s.onWarningContainer, s.warningContainer)
        Tone.DANGER -> ToneColors(s.onDangerContainer, s.dangerContainer)
        Tone.INFO -> ToneColors(s.onInfoContainer, s.infoContainer)
        Tone.NEUTRAL -> ToneColors(s.onNeutralContainer, s.neutralContainer)
    }
}

@Composable
fun toneAccent(tone: Tone): Color {
    val s = SindenTheme.status
    return when (tone) {
        Tone.SUCCESS -> s.success
        Tone.WARNING -> s.warning
        Tone.DANGER -> s.danger
        Tone.INFO -> s.info
        Tone.NEUTRAL -> s.neutral
    }
}

/** Label status kecil dengan titik warna — warna tidak menjadi satu-satunya penanda (ada teks). */
@Composable
fun StatusChip(text: String, tone: Tone, modifier: Modifier = Modifier) {
    val c = toneColors(tone)
    Row(
        modifier
            .clip(RoundedCornerShape(50))
            .background(c.bg)
            .padding(horizontal = 10.dp, vertical = 4.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(Modifier.size(6.dp).clip(CircleShape).background(toneAccent(tone)))
        Spacer(Modifier.width(6.dp))
        Text(text, style = MaterialTheme.typography.labelMedium, color = c.fg, fontWeight = FontWeight.SemiBold)
    }
}

/** Baris chip filter yang dapat digulir horizontal. */
@Composable
fun <T> FilterChipRow(
    options: List<T>,
    selected: T,
    label: (T) -> String,
    onSelect: (T) -> Unit,
    modifier: Modifier = Modifier,
    contentPadding: PaddingValues = PaddingValues(horizontal = Spacing.screen),
) {
    Row(
        modifier
            .horizontalScroll(rememberScrollState())
            .padding(contentPadding),
        horizontalArrangement = Arrangement.spacedBy(Spacing.sm),
    ) {
        options.forEach { option ->
            val isSelected = option == selected
            FilterChip(
                selected = isSelected,
                onClick = { onSelect(option) },
                label = { Text(label(option)) },
                leadingIcon = if (isSelected) {
                    { Icon(Icons.Filled.Check, contentDescription = null, modifier = Modifier.size(16.dp)) }
                } else null,
                shape = RoundedCornerShape(50),
                colors = FilterChipDefaults.filterChipColors(
                    selectedContainerColor = MaterialTheme.colorScheme.primaryContainer,
                    selectedLabelColor = MaterialTheme.colorScheme.onPrimaryContainer,
                    selectedLeadingIconColor = MaterialTheme.colorScheme.onPrimaryContainer,
                ),
            )
        }
    }
}

/** Lencana angka kecil (mis. jumlah notifikasi belum dibaca). */
@Composable
fun CountBadge(count: Int, modifier: Modifier = Modifier) {
    if (count <= 0) return
    Box(
        modifier
            .clip(RoundedCornerShape(50))
            .background(MaterialTheme.colorScheme.error)
            .padding(horizontal = 6.dp, vertical = 1.dp),
        contentAlignment = Alignment.Center,
    ) {
        Text(
            if (count > 99) "99+" else count.toString(),
            color = MaterialTheme.colorScheme.onError,
            style = MaterialTheme.typography.labelSmall,
            fontWeight = FontWeight.Bold,
        )
    }
}
