package id.my.sisinden.app.ui.duty

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.CheckCircle
import androidx.compose.material.icons.outlined.NotificationsActive
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.semantics.clearAndSetSemantics
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import id.my.sisinden.app.core.notification.DutyReminders
import id.my.sisinden.app.core.ui.components.StatusChip
import id.my.sisinden.app.core.ui.components.Tone
import id.my.sisinden.app.core.ui.theme.Blue400
import id.my.sisinden.app.core.ui.theme.Blue600
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.core.util.Fmt
import id.my.sisinden.app.data.model.DutyRosterResponse
import id.my.sisinden.app.data.model.SpJagaSummary
import java.time.LocalDate

fun spStatusTone(sp: SpJagaSummary): Tone = when (sp.status) {
    "published" -> Tone.SUCCESS
    "pending_signature" -> Tone.WARNING
    "signed" -> Tone.INFO
    else -> Tone.NEUTRAL
}

/** Satu tanggal jaga milik pengguna yang sedang masuk. */
data class MyDuty(val date: LocalDate, val role: String?, val spId: Int?)

/**
 * Ambil HANYA jadwal milik pengguna dari respons SP Jaga. Daftar personel lain
 * sengaja tidak ditampilkan di aplikasi; jadwal lengkap tersedia lewat PDF resmi.
 */
fun myDutiesOf(roster: DutyRosterResponse?): List<MyDuty> {
    if (roster == null) return emptyList()
    val fromDays = roster.days.orEmpty()
        .filter { it.hasMe }
        .mapNotNull { day ->
            val date = Fmt.parseDate(day.date) ?: return@mapNotNull null
            MyDuty(date, day.safeEntries.firstOrNull { it.isMe }?.role, day.spId ?: roster.sp?.id)
        }
    if (fromDays.isNotEmpty()) return fromDays.sortedBy { it.date }
    // Cadangan: server hanya mengirim my_dates.
    return roster.myDates.orEmpty()
        .mapNotNull { Fmt.parseDate(it) }
        .map { MyDuty(it, roster.myRoles?.firstOrNull(), roster.sp?.id) }
        .sortedBy { it.date }
}

/** Status relatif satu tanggal jaga terhadap hari ini. */
enum class DutyTiming { PAST, TODAY, TOMORROW, UPCOMING }

fun timingOf(date: LocalDate, today: LocalDate = LocalDate.now()): DutyTiming = when {
    date.isBefore(today) -> DutyTiming.PAST
    date == today -> DutyTiming.TODAY
    date == today.plusDays(1) -> DutyTiming.TOMORROW
    else -> DutyTiming.UPCOMING
}

/**
 * Baris tanggal jaga: blok tanggal besar (mudah dibaca), hari lengkap, peran,
 * dan status relatif. Warna bukan satu-satunya penanda — selalu ada teks.
 */
@Composable
fun MyDutyRow(duty: MyDuty, modifier: Modifier = Modifier) {
    val timing = timingOf(duty.date)
    val scheme = MaterialTheme.colorScheme
    val past = timing == DutyTiming.PAST
    val highlight = timing == DutyTiming.TODAY || timing == DutyTiming.TOMORROW
    val label = when (timing) {
        DutyTiming.PAST -> "Selesai"
        DutyTiming.TODAY -> "Hari ini"
        DutyTiming.TOMORROW -> "Besok"
        DutyTiming.UPCOMING -> Fmt.relativeDay(duty.date).let { if (it == Fmt.dayMonth(duty.date)) "Mendatang" else it }
    }
    val spoken = "${Fmt.fullDate(duty.date)}${duty.role?.let { ", $it" } ?: ""}, $label"

    Row(
        modifier
            .fillMaxWidth()
            .clip(MaterialTheme.shapes.small)
            .background(if (highlight) scheme.primaryContainer else Color.Transparent)
            .padding(horizontal = Spacing.sm, vertical = Spacing.sm)
            .clearAndSetSemantics { contentDescription = spoken },
        verticalAlignment = Alignment.CenterVertically,
    ) {
        DateBadge(duty.date, emphasized = highlight, muted = past)
        Spacer(Modifier.width(Spacing.md))
        Column(Modifier.weight(1f)) {
            Text(
                Fmt.weekday(duty.date),
                style = MaterialTheme.typography.titleSmall,
                color = if (past) scheme.onSurfaceVariant else scheme.onSurface,
            )
            Text(
                duty.role ?: "Petugas jaga",
                style = MaterialTheme.typography.bodySmall,
                color = scheme.onSurfaceVariant,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
        }
        if (past) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(Icons.Outlined.CheckCircle, contentDescription = null, tint = scheme.onSurfaceVariant, modifier = Modifier.size(18.dp))
                Spacer(Modifier.width(4.dp))
                Text(label, style = MaterialTheme.typography.labelMedium, color = scheme.onSurfaceVariant)
            }
        } else {
            StatusChip(label, if (highlight) Tone.INFO else Tone.SUCCESS)
        }
    }
}

/** Blok tanggal: angka besar + bulan singkat. */
@Composable
fun DateBadge(date: LocalDate, emphasized: Boolean, muted: Boolean, modifier: Modifier = Modifier) {
    val scheme = MaterialTheme.colorScheme
    val shape = MaterialTheme.shapes.small
    val bg: Modifier = when {
        emphasized -> Modifier.background(Brush.verticalGradient(listOf(Blue400, Blue600)))
        else -> Modifier.background(scheme.surfaceVariant)
    }
    val fg = when {
        emphasized -> Color.White
        muted -> scheme.onSurfaceVariant
        else -> scheme.onSurface
    }
    Column(
        modifier
            .width(58.dp)
            .clip(shape)
            .then(bg)
            .border(1.dp, if (emphasized) Color.White.copy(alpha = 0.35f) else scheme.outlineVariant, shape)
            .padding(vertical = 6.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Text(date.dayOfMonth.toString(), style = MaterialTheme.typography.headlineSmall, color = fg)
        Text(Fmt.monthShort(date.monthValue), style = MaterialTheme.typography.labelMedium, color = fg)
    }
}

/** Info SP Jaga ringkas (nomor + status), tanpa jumlah/nama personel lain. */
@Composable
fun SpInfoRow(sp: SpJagaSummary, modifier: Modifier = Modifier) {
    Row(modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(Spacing.sm)) {
        Column(Modifier.weight(1f)) {
            Text(sp.nomorSprin ?: "SP Jaga", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant, maxLines = 1, overflow = TextOverflow.Ellipsis)
        }
        StatusChip(sp.statusLabel, spStatusTone(sp))
    }
}

/** Keterangan pengingat H-1 aktif. */
@Composable
fun ReminderNote(modifier: Modifier = Modifier) {
    Row(modifier, verticalAlignment = Alignment.CenterVertically) {
        Icon(Icons.Outlined.NotificationsActive, contentDescription = null, tint = MaterialTheme.colorScheme.primary, modifier = Modifier.size(20.dp))
        Spacer(Modifier.width(Spacing.sm))
        Text(
            "Pengingat otomatis H-1 pukul %02d.%02d".format(DutyReminders.REMIND_AT.hour, DutyReminders.REMIND_AT.minute),
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            fontWeight = FontWeight.Medium,
        )
    }
}

/** Jarak kecil antarbaris jadwal. */
@Composable
fun DutyRowSpacer() = Spacer(Modifier.height(Spacing.xs))
