package id.my.sisinden.app.core.util

import java.text.NumberFormat
import java.time.LocalDate
import java.time.LocalDateTime
import java.time.LocalTime
import java.time.OffsetDateTime
import java.time.YearMonth
import java.time.format.DateTimeFormatter
import java.time.format.TextStyle
import java.util.Locale

/** Format angka, tanggal, dan waktu dalam Bahasa Indonesia (WIB lokal perangkat). */
object Fmt {
    val ID: Locale = Locale("id", "ID")

    private val dayMonthYear = DateTimeFormatter.ofPattern("d MMMM yyyy", ID)
    private val dayMonthShort = DateTimeFormatter.ofPattern("d MMM yyyy", ID)
    private val dayMonth = DateTimeFormatter.ofPattern("d MMM", ID)
    private val isoDate = DateTimeFormatter.ISO_LOCAL_DATE
    private val timeShort = DateTimeFormatter.ofPattern("HH:mm", ID)

    fun rupiah(value: Double, hidden: Boolean = false): String {
        if (hidden) return "Rp ••••••"
        val nf = NumberFormat.getNumberInstance(ID).apply {
            maximumFractionDigits = if (value % 1.0 == 0.0) 0 else 2
            minimumFractionDigits = 0
        }
        val sign = if (value < 0) "-" else ""
        return "${sign}Rp ${nf.format(kotlin.math.abs(value))}"
    }

    fun number(value: Int): String = NumberFormat.getIntegerInstance(ID).format(value)

    fun parseDate(raw: String?): LocalDate? {
        if (raw.isNullOrBlank()) return null
        return runCatching { LocalDate.parse(raw.take(10), isoDate) }.getOrNull()
    }

    fun parseDateTime(raw: String?): LocalDateTime? {
        if (raw.isNullOrBlank()) return null
        return runCatching { OffsetDateTime.parse(raw).atZoneSameInstant(java.time.ZoneId.systemDefault()).toLocalDateTime() }.getOrNull()
            ?: runCatching { LocalDateTime.parse(raw.replace(' ', 'T').take(19)) }.getOrNull()
    }

    /** "13 Oktober 2026" */
    fun longDate(date: LocalDate): String = date.format(dayMonthYear)
    fun longDate(raw: String?): String = parseDate(raw)?.let(::longDate) ?: (raw ?: "-")

    /** "13 Okt 2026" */
    fun shortDate(raw: String?): String = parseDate(raw)?.format(dayMonthShort) ?: (raw ?: "-")

    /** "13 Okt" */
    fun dayMonth(date: LocalDate): String = date.format(dayMonth)

    /** "Selasa" */
    fun weekday(date: LocalDate): String =
        date.dayOfWeek.getDisplayName(TextStyle.FULL, ID).replaceFirstChar { it.uppercase() }

    /** "Sel" */
    fun weekdayShort(date: LocalDate): String =
        date.dayOfWeek.getDisplayName(TextStyle.SHORT, ID).replaceFirstChar { it.uppercase() }.take(3)

    /** "Selasa, 13 Oktober 2026" */
    fun fullDate(date: LocalDate): String = "${weekday(date)}, ${longDate(date)}"

    fun monthYear(ym: YearMonth): String =
        ym.month.getDisplayName(TextStyle.FULL, ID).replaceFirstChar { it.uppercase() } + " " + ym.year

    fun monthShort(month: Int): String =
        java.time.Month.of(month).getDisplayName(TextStyle.SHORT, ID).replaceFirstChar { it.uppercase() }.take(3)

    fun iso(date: LocalDate): String = date.format(isoDate)

    fun time(dt: LocalDateTime): String = dt.format(timeShort)

    /** "Baru saja", "5 menit lalu", "Kemarin", atau tanggal. */
    fun relative(raw: String?): String {
        val dt = parseDateTime(raw) ?: return shortDate(raw)
        val now = LocalDateTime.now()
        val minutes = java.time.Duration.between(dt, now).toMinutes()
        return when {
            minutes < 1 -> "Baru saja"
            minutes < 60 -> "$minutes menit lalu"
            minutes < 60 * 24 && dt.toLocalDate() == now.toLocalDate() -> "${minutes / 60} jam lalu"
            dt.toLocalDate() == now.toLocalDate().minusDays(1) -> "Kemarin, ${time(dt)}"
            else -> dt.toLocalDate().format(dayMonthShort)
        }
    }

    /** Selisih hari kalender dari hari ini: 0 = hari ini, 1 = besok. */
    fun daysFromToday(date: LocalDate): Long =
        java.time.temporal.ChronoUnit.DAYS.between(LocalDate.now(), date)

    fun relativeDay(date: LocalDate): String = when (val d = daysFromToday(date)) {
        0L -> "Hari ini"
        1L -> "Besok"
        -1L -> "Kemarin"
        in 2L..6L -> "$d hari lagi"
        else -> dayMonth(date)
    }

    fun greeting(now: LocalTime = LocalTime.now()): String = when (now.hour) {
        in 4..10 -> "Selamat pagi"
        in 11..14 -> "Selamat siang"
        in 15..17 -> "Selamat sore"
        else -> "Selamat malam"
    }

    /** Parsing nominal yang diketik pengguna: "1.250.000" / "1250000,5" → Double. */
    fun parseAmount(input: String): Double? {
        val cleaned = input.trim().replace("Rp", "", ignoreCase = true).replace(" ", "")
        if (cleaned.isEmpty()) return null
        val normalized = if (cleaned.contains(',')) cleaned.replace(".", "").replace(',', '.') else cleaned.replace(".", "")
        return normalized.toDoubleOrNull()
    }
}
