package id.my.sisinden.app.core.notification

import android.app.AlarmManager
import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import id.my.sisinden.app.core.util.Fmt
import java.time.LocalDate
import java.time.LocalTime
import java.time.ZoneId
import java.time.ZonedDateTime

/**
 * Pengingat jaga H-1.
 *
 * Tanggal jaga pribadi (dari SP Jaga yang terbit) dijadwalkan sebagai alarm
 * lokal pukul [REMIND_AT] sehari sebelum tanggal jaga. Alarm memakai
 * `setAndAllowWhileIdle` (tidak butuh izin alarm presisi; sistem boleh
 * menunda beberapa menit saat hemat daya). Jadwal disimpan agar bisa dipasang
 * ulang setelah perangkat dinyalakan ulang, dan dihapus saat pengguna keluar.
 */
object DutyReminders {

    const val ACTION_REMIND = "id.my.sisinden.app.action.DUTY_REMINDER"
    const val EXTRA_DATE = "duty_date"
    const val EXTRA_ROLE = "duty_role"

    /** Jam pengingat pada hari H-1. */
    val REMIND_AT: LocalTime = LocalTime.of(7, 0)

    private const val PREFS = "sinden_duty_reminders"
    private const val KEY_ENTRIES = "entries" // "yyyy-MM-dd|peran"
    private const val KEY_NOTIFIED = "notified" // tanggal jaga yang pengingatnya sudah tampil

    /**
     * Samakan alarm dengan daftar tanggal jaga terbaru. Tanggal yang hilang
     * dari SP (mis. revisi) dibatalkan; tanggal yang sudah lewat diabaikan.
     */
    fun sync(context: Context, duties: Map<LocalDate, String?>) {
        val app = context.applicationContext
        val prefs = app.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
        val today = LocalDate.now()
        val upcoming = duties.filterKeys { it.isAfter(today) }

        val previous = decode(prefs.getStringSet(KEY_ENTRIES, emptySet()).orEmpty())
        (previous.keys - upcoming.keys).forEach { cancel(app, it) }

        val notified = prefs.getStringSet(KEY_NOTIFIED, emptySet()).orEmpty()
            .filter { iso -> Fmt.parseDate(iso)?.isBefore(today) == false }
            .toSet()
        prefs.edit()
            .putStringSet(KEY_ENTRIES, encode(upcoming))
            .putStringSet(KEY_NOTIFIED, notified)
            .apply()

        upcoming.forEach { (date, role) -> schedule(app, date, role, notified) }
    }

    /** Pasang ulang alarm dari data tersimpan (setelah reboot / pembaruan aplikasi). */
    fun restore(context: Context) {
        val prefs = context.applicationContext.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
        val stored = decode(prefs.getStringSet(KEY_ENTRIES, emptySet()).orEmpty())
        if (stored.isNotEmpty()) sync(context, stored)
    }

    /** Hapus semua pengingat (dipanggil saat keluar akun). */
    fun cancelAll(context: Context) {
        val app = context.applicationContext
        val prefs = app.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
        decode(prefs.getStringSet(KEY_ENTRIES, emptySet()).orEmpty()).keys.forEach { cancel(app, it) }
        prefs.edit().clear().apply()
    }

    /** Tampilkan notifikasi pengingat (sekali per tanggal jaga). */
    fun notify(context: Context, date: LocalDate, role: String?) {
        val app = context.applicationContext
        val prefs = app.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
        val stillScheduled = decode(prefs.getStringSet(KEY_ENTRIES, emptySet()).orEmpty()).containsKey(date)
        val notified = prefs.getStringSet(KEY_NOTIFIED, emptySet()).orEmpty()
        if (!stillScheduled || date.toString() in notified) return
        prefs.edit().putStringSet(KEY_NOTIFIED, notified + date.toString()).apply()

        val asRole = role?.takeIf { it.isNotBlank() }?.let { " sebagai $it" }.orEmpty()
        NotificationHelper.showNotification(
            context = app,
            notificationId = notificationId(date),
            title = "Pengingat: besok Anda jaga",
            message = "${Fmt.fullDate(date)} Anda terjadwal jaga$asRole. Mohon persiapkan diri. Detail dan PDF SP Jaga ada di menu Daftar Jaga.",
        )
    }

    private fun schedule(context: Context, date: LocalDate, role: String?, notified: Set<String>) {
        if (date.toString() in notified) return
        val zone = ZoneId.systemDefault()
        val trigger = date.minusDays(1).atTime(REMIND_AT).atZone(zone)
        val now = ZonedDateTime.now(zone)
        when {
            trigger.isAfter(now) -> {
                val am = context.getSystemService(AlarmManager::class.java) ?: return
                am.setAndAllowWhileIdle(AlarmManager.RTC_WAKEUP, trigger.toInstant().toEpochMilli(), pendingIntent(context, date, role))
            }
            // Jam pengingat H-1 sudah lewat (mis. SP baru terbit siang hari) → kirim sekarang.
            now.toLocalDate() == date.minusDays(1) -> notify(context, date, role)
        }
    }

    private fun cancel(context: Context, date: LocalDate) {
        val am = context.getSystemService(AlarmManager::class.java) ?: return
        val existing = PendingIntent.getBroadcast(
            context,
            requestCode(date),
            Intent(context, DutyReminderReceiver::class.java).setAction(ACTION_REMIND),
            PendingIntent.FLAG_NO_CREATE or PendingIntent.FLAG_IMMUTABLE,
        ) ?: return
        am.cancel(existing)
        existing.cancel()
    }

    private fun pendingIntent(context: Context, date: LocalDate, role: String?): PendingIntent =
        PendingIntent.getBroadcast(
            context,
            requestCode(date),
            Intent(context, DutyReminderReceiver::class.java)
                .setAction(ACTION_REMIND)
                .putExtra(EXTRA_DATE, date.toString())
                .putExtra(EXTRA_ROLE, role),
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
        )

    private fun requestCode(date: LocalDate): Int = (date.toEpochDay() % 1_000_000L).toInt() + 40_000_000

    private fun notificationId(date: LocalDate): Int = (date.toEpochDay() % 1_000_000L).toInt() + 900_000_000

    private fun encode(map: Map<LocalDate, String?>): Set<String> =
        map.map { (d, r) -> "$d|${r.orEmpty().replace("|", "/")}" }.toSet()

    private fun decode(set: Set<String>): Map<LocalDate, String?> =
        set.mapNotNull { raw ->
            val date = Fmt.parseDate(raw.substringBefore('|')) ?: return@mapNotNull null
            date to raw.substringAfter('|', "").takeIf { it.isNotBlank() }
        }.toMap()
}

/** Menerima alarm pengingat (intent eksplisit dari aplikasi sendiri). */
class DutyReminderReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action != DutyReminders.ACTION_REMIND) return
        val date = Fmt.parseDate(intent.getStringExtra(DutyReminders.EXTRA_DATE)) ?: return
        DutyReminders.notify(context, date, intent.getStringExtra(DutyReminders.EXTRA_ROLE))
    }
}

/** Memasang ulang pengingat setelah perangkat dinyalakan ulang / aplikasi diperbarui. */
class DutyReminderBootReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        when (intent.action) {
            Intent.ACTION_BOOT_COMPLETED -> DutyReminders.restore(context)
            Intent.ACTION_MY_PACKAGE_REPLACED -> {
                DutyReminders.restore(context)
                id.my.sisinden.app.core.update.AppUpdater.onPackageReplaced(context)
            }
        }
    }
}
