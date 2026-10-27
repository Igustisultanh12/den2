package id.my.sisinden.app

import id.my.sisinden.app.core.data.SessionStore
import id.my.sisinden.app.core.security.Perm
import id.my.sisinden.app.core.security.PermissionSet
import id.my.sisinden.app.core.util.Fmt
import id.my.sisinden.app.data.model.SkhppDto
import id.my.sisinden.app.data.model.ViolationDto
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import java.time.LocalDate
import java.time.LocalTime

class UtilitiesTest {

    @Test
    fun rupiahFormattingIsIndonesian() {
        assertEquals("Rp 1.250.000", Fmt.rupiah(1_250_000.0))
        assertEquals("Rp 1", Fmt.rupiah(1.0))
        assertEquals("-Rp 5.000", Fmt.rupiah(-5_000.0))
        assertEquals("Rp ••••••", Fmt.rupiah(999.0, hidden = true))
    }

    @Test
    fun amountParsingAcceptsIndonesianSeparators() {
        assertEquals(1_250_000.0, Fmt.parseAmount("1.250.000")!!, 0.0)
        assertEquals(1250.5, Fmt.parseAmount("1.250,5")!!, 0.0001)
        assertEquals(1.0, Fmt.parseAmount("Rp 1")!!, 0.0)
        assertNull(Fmt.parseAmount(""))
        assertNull(Fmt.parseAmount("abc"))
    }

    @Test
    fun datesAreParsedAndFormattedInIndonesian() {
        assertEquals(LocalDate.of(2026, 10, 13), Fmt.parseDate("2026-10-13T00:00:00.000000Z"))
        assertEquals("13 Oktober 2026", Fmt.longDate("2026-10-13"))
        assertEquals("Selasa", Fmt.weekday(LocalDate.of(2026, 10, 13)))
        assertEquals("-", Fmt.longDate(null as String?))
        assertEquals("Hari ini", Fmt.relativeDay(LocalDate.now()))
        assertEquals("Besok", Fmt.relativeDay(LocalDate.now().plusDays(1)))
    }

    @Test
    fun greetingFollowsTimeOfDay() {
        assertEquals("Selamat pagi", Fmt.greeting(LocalTime.of(7, 0)))
        assertEquals("Selamat siang", Fmt.greeting(LocalTime.of(12, 30)))
        assertEquals("Selamat sore", Fmt.greeting(LocalTime.of(16, 0)))
        assertEquals("Selamat malam", Fmt.greeting(LocalTime.of(21, 0)))
    }

    @Test
    fun serverUrlMustBeHttps() {
        assertEquals("https://sinden2.my.id", SessionStore.normalizeBaseUrl("sinden2.my.id/"))
        assertEquals("https://mirror.example.go.id/sinden", SessionStore.normalizeBaseUrl(" https://mirror.example.go.id/sinden/ "))
        assertNull("HTTP polos ditolak", SessionStore.normalizeBaseUrl("http://sinden2.my.id"))
        assertNull(SessionStore.normalizeBaseUrl("bukan url"))
        assertNull(SessionStore.normalizeBaseUrl(""))
    }

    @Test
    fun permissionSetChecks() {
        val set = PermissionSet(setOf(Perm.CASH_VIEW, Perm.DUTY_VIEW))
        assertTrue(set.has(Perm.CASH_VIEW))
        assertFalse(set.has(Perm.CASH_MANAGE))
        assertTrue(set.hasAny(Perm.USERS_VIEW, Perm.DUTY_VIEW))
        assertFalse(PermissionSet.EMPTY.has(Perm.DASHBOARD_VIEW))
    }

    @Test
    fun skhppStatusIsCaseInsensitive() {
        assertEquals("Menunggu", SkhppDto(status = "PENDING").statusLabel)
        assertEquals("Disetujui", SkhppDto(status = "approved").statusLabel)
        assertEquals("Dikembalikan", SkhppDto(status = "REJECTED").statusLabel)
    }

    @Test
    fun violationHistorySplitsLines() {
        val v = ViolationDto(caseDevelopment = "[01/10/2026 08:00] AWAL: diperiksa\n\n[05/10/2026 10:00] sidang")
        assertEquals(2, v.history.size)
    }
}
