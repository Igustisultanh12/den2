package id.my.sisinden.app.ui.duty

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowRight
import androidx.compose.material.icons.outlined.Star
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import id.my.sisinden.app.core.ui.components.InitialsAvatar
import id.my.sisinden.app.core.ui.components.SindenCard
import id.my.sisinden.app.core.ui.components.StatusChip
import id.my.sisinden.app.core.ui.components.Tone
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.core.util.Fmt
import id.my.sisinden.app.data.model.DutyDay
import id.my.sisinden.app.data.model.DutyEntry
import id.my.sisinden.app.data.model.SpJagaSummary

fun spStatusTone(sp: SpJagaSummary): Tone = when (sp.status) {
    "published" -> Tone.SUCCESS
    "pending_signature" -> Tone.WARNING
    "signed" -> Tone.INFO
    else -> Tone.NEUTRAL
}

fun roleTone(entry: DutyEntry): Tone = when (entry.roleCode) {
    "PERWIRA" -> Tone.INFO
    "BAGA" -> Tone.WARNING
    else -> Tone.NEUTRAL
}

/** Kotak tanggal kalender kecil (hari + tanggal), disorot untuk hari ini. */
@Composable
fun DateBlock(date: String, highlight: Boolean, modifier: Modifier = Modifier) {
    val d = Fmt.parseDate(date)
    val bg = if (highlight) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.surfaceVariant
    val fg = if (highlight) MaterialTheme.colorScheme.onPrimary else MaterialTheme.colorScheme.onSurface
    Column(
        modifier
            .width(54.dp)
            .clip(MaterialTheme.shapes.small)
            .background(bg)
            .padding(vertical = Spacing.sm),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Text(d?.let { Fmt.weekdayShort(it) } ?: "-", style = MaterialTheme.typography.labelSmall, color = fg.copy(alpha = 0.8f))
        Text(d?.dayOfMonth?.toString() ?: "-", style = MaterialTheme.typography.titleLarge, color = fg, fontWeight = FontWeight.Bold)
        Text(d?.let { Fmt.monthShort(it.monthValue) } ?: "", style = MaterialTheme.typography.labelSmall, color = fg.copy(alpha = 0.8f))
    }
}

/** Baris satu personel jaga. */
@Composable
fun DutyEntryRow(entry: DutyEntry, modifier: Modifier = Modifier) {
    Row(modifier.fillMaxWidth().padding(vertical = Spacing.xs), verticalAlignment = Alignment.CenterVertically) {
        InitialsAvatar(
            text = entry.name.orEmpty().split(" ").filter { it.isNotBlank() }.take(2).joinToString("") { it.take(1) },
            size = 36.dp,
            highlight = entry.isMe,
        )
        Spacer(Modifier.width(Spacing.md))
        Column(Modifier.weight(1f)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    entry.name.orEmpty().ifBlank { "-" },
                    style = MaterialTheme.typography.titleSmall,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.weight(1f, fill = false),
                )
                if (entry.isMe) {
                    Spacer(Modifier.width(Spacing.xs))
                    StatusChip("Anda", Tone.SUCCESS)
                }
            }
            Text(
                listOfNotNull(
                    entry.pangkatKorps?.takeIf { it.isNotBlank() },
                    entry.nrp?.takeIf { it.isNotBlank() }?.let { "NRP $it" },
                    entry.divisiNo?.let { "Divisi $it" },
                ).joinToString(" · "),
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
        }
        Spacer(Modifier.width(Spacing.sm))
        StatusChip(entry.role?.substringBefore(" (")?.ifBlank { null } ?: "Jaga", roleTone(entry))
    }
}

/** Kartu satu hari jaga: tanggal di kiri, ringkasan personel di kanan. */
@Composable
fun DutyDayCard(
    day: DutyDay,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    maxEntries: Int = 3,
) {
    val highlight = day.isToday
    SindenCard(
        modifier = modifier.fillMaxWidth(),
        onClick = onClick,
        contentPadding = Spacing.md,
        border = BorderStroke(
            if (day.hasMe) 1.5.dp else 1.dp,
            if (day.hasMe) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.outlineVariant,
        ),
    ) {
        Row(verticalAlignment = Alignment.Top) {
            DateBlock(day.date, highlight)
            Spacer(Modifier.width(Spacing.md))
            Column(Modifier.weight(1f)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    val d = Fmt.parseDate(day.date)
                    Text(
                        d?.let { Fmt.relativeDay(it) } ?: day.date,
                        style = MaterialTheme.typography.titleSmall,
                        modifier = Modifier.weight(1f),
                    )
                    if (day.hasMe) {
                        Icon(Icons.Outlined.Star, contentDescription = "Anda bertugas", tint = MaterialTheme.colorScheme.primary, modifier = Modifier.size(18.dp))
                    }
                    Icon(Icons.AutoMirrored.Filled.KeyboardArrowRight, contentDescription = null, tint = MaterialTheme.colorScheme.onSurfaceVariant)
                }
                Text(
                    "${day.safeEntries.size} personel bertugas",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                Spacer(Modifier.height(Spacing.xs))
                day.safeEntries.sortedByDescending { it.isMe }.take(maxEntries).forEach { e ->
                    Text(
                        buildString {
                            append("• ")
                            append(e.name.orEmpty())
                            e.role?.let { append(" — ").append(it.substringBefore(" (")) }
                        },
                        style = MaterialTheme.typography.bodySmall,
                        fontWeight = if (e.isMe) FontWeight.SemiBold else FontWeight.Normal,
                        color = if (e.isMe) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurface,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                }
                val more = day.safeEntries.size - maxEntries
                if (more > 0) {
                    Text("+$more lainnya", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.primary)
                }
            }
        }
    }
}

/** Kartu tanggal ringkas untuk baris horizontal di dashboard. */
@Composable
fun UpcomingDutyChip(day: DutyDay, onClick: () -> Unit) {
    SindenCard(
        modifier = Modifier.widthIn(min = 96.dp),
        onClick = onClick,
        contentPadding = Spacing.md,
        containerColor = if (day.hasMe) MaterialTheme.colorScheme.primaryContainer else MaterialTheme.colorScheme.surface,
    ) {
        val d = Fmt.parseDate(day.date)
        Text(
            d?.let { Fmt.weekdayShort(it) } ?: "",
            style = MaterialTheme.typography.labelMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        Text(d?.let { Fmt.dayMonth(it) } ?: day.date, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
        Spacer(Modifier.height(Spacing.xs))
        Row(verticalAlignment = Alignment.CenterVertically) {
            Box(
                Modifier.size(8.dp).clip(CircleShape).background(
                    if (day.hasMe) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.outline
                )
            )
            Spacer(Modifier.width(6.dp))
            Text(
                if (day.hasMe) "Anda jaga" else "${day.safeEntries.size} personel",
                style = MaterialTheme.typography.labelSmall,
                textAlign = TextAlign.Start,
            )
        }
    }
}

/** Ringkas info SP Jaga (nomor, periode, status). */
@Composable
fun SpInfoRow(sp: SpJagaSummary, modifier: Modifier = Modifier) {
    Row(modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(Spacing.sm)) {
        Column(Modifier.weight(1f)) {
            Text(sp.nomorSprin ?: "SP Jaga", style = MaterialTheme.typography.titleSmall, maxLines = 1, overflow = TextOverflow.Ellipsis)
            Text(
                "Periode ${sp.periodeLabel ?: "${sp.bulan}/${sp.tahun}"} · ${sp.totalPersonel} personel",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        StatusChip(sp.statusLabel, spStatusTone(sp))
    }
}
