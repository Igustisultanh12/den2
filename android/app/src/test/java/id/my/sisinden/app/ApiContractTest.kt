package id.my.sisinden.app

import com.google.gson.Gson
import id.my.sisinden.app.core.network.ApiErrors
import id.my.sisinden.app.core.network.ErrorKind
import id.my.sisinden.app.core.security.Perm
import id.my.sisinden.app.data.model.AttendanceResponse
import id.my.sisinden.app.data.model.CashLedgerResponse
import id.my.sisinden.app.data.model.DashboardResponse
import id.my.sisinden.app.data.model.DutyRosterResponse
import id.my.sisinden.app.data.model.LoginResponse
import id.my.sisinden.app.data.model.MeResponse
import id.my.sisinden.app.data.model.MitraDto
import id.my.sisinden.app.data.model.NotificationResponse
import id.my.sisinden.app.data.model.UserProfile
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * Kontrak API diuji terhadap respons ASLI yang dihasilkan backend Laravel
 * (routes/mobile_api.php) — lihat src/test/resources/fixtures. Fixture dibuat
 * dari feature test PHP sehingga perubahan bentuk JSON di server akan ketahuan.
 */
class ApiContractTest {

    private val gson = Gson()

    private fun fixture(name: String): String =
        requireNotNull(javaClass.classLoader?.getResource("fixtures/$name.json")) { "fixture $name hilang" }.readText()

    @Test
    fun loginResponseCarriesServerPermissions() {
        val res = gson.fromJson(fixture("login_personel"), LoginResponse::class.java)
        assertEquals("success", res.status)
        assertNotNull(res.token)
        val user = requireNotNull(res.user)
        assertEquals("82068", user.nrp)
        assertEquals("Bambang", user.displayName)
        val perms = user.permissionSet()
        assertTrue(perms.has(Perm.DUTY_VIEW))
        assertTrue(perms.has(Perm.ATTENDANCE_CREATE))
        assertFalse("personel biasa tidak boleh melihat kas", perms.has(Perm.CASH_VIEW))
        assertFalse(perms.has(Perm.USERS_MANAGE))
    }

    @Test
    fun adminPermissionsIncludeAdministration() {
        val me = gson.fromJson(fixture("me_admin"), MeResponse::class.java)
        val perms = requireNotNull(me.user).permissionSet()
        assertTrue(perms.has(Perm.USERS_MANAGE))
        assertTrue(perms.has(Perm.CASH_MANAGE))
        assertTrue(perms.has(Perm.SKHPP_APPROVE))
    }

    @Test
    fun legacyServerFlagsAreMappedWhenPermissionsMissing() {
        val legacy = """{"id":7,"name":"Lama","role":"user","can_access_cash":true,"can_access_mitra":false,"can_access_agenda":true}"""
        val perms = gson.fromJson(legacy, UserProfile::class.java).permissionSet()
        assertTrue(perms.has(Perm.CASH_VIEW))
        assertTrue(perms.has(Perm.LETTER_LOGS_VIEW))
        assertFalse(perms.has(Perm.MITRA_VIEW))
        assertTrue(perms.has(Perm.ATTENDANCE_VIEW))
    }

    @Test
    fun dashboardOmitsModulesWithoutPermission() {
        val d = gson.fromJson(fixture("dashboard_personel"), DashboardResponse::class.java)
        val s = requireNotNull(d.summary)
        assertNull("saldo kas tidak dikirim untuk personel tanpa izin", s.cashBalance)
        assertEquals(1, s.notificationsUnread)
        assertEquals("2026-10-13", s.attendance?.today?.dateOnly)
        assertEquals("hadir", s.attendance?.today?.status)
        val duty = requireNotNull(d.duty)
        assertEquals("2026-10-13", duty.today?.date)
        assertTrue(duty.today!!.hasMe)
        assertEquals("2026-10-13", duty.myNextDate)
        assertEquals(listOf("2026-10-16", "2026-10-18"), duty.upcoming.orEmpty().map { it.date })
    }

    @Test
    fun dutyRosterIsParsedPerDayWithRoles() {
        val r = gson.fromJson(fixture("duty_roster"), DutyRosterResponse::class.java)
        val sp = requireNotNull(r.sp)
        assertTrue(sp.isPublished)
        assertEquals("Terbit", sp.statusLabel)
        assertEquals("Oktober 2026", sp.periodeLabel)
        val days = r.days.orEmpty().associateBy { it.date }
        assertEquals(setOf("2026-10-13", "2026-10-16", "2026-10-18"), days.keys)
        val d13 = days.getValue("2026-10-13")
        assertEquals(2, d13.safeEntries.size)
        assertTrue(d13.safeEntries.any { it.isMe && it.roleCode == "PERWIRA" })
        assertTrue(d13.safeEntries.any { it.roleCode == "BAGA" && it.divisiNo == 2 })
        assertEquals(listOf("2026-10-13", "2026-10-16"), r.myDates)
    }

    @Test
    fun attendanceDatesAreLocalDates() {
        val a = gson.fromJson(fixture("attendances"), AttendanceResponse::class.java)
        assertEquals("2026-10-13", a.todayAttendance?.attendanceDate)
        assertEquals("07:12", a.todayAttendance?.timeShort)
        assertEquals("Hadir Kantor", a.todayAttendance?.statusLabel)
    }

    @Test
    fun cashLedgerKeepsDecimalPrecision() {
        val c = gson.fromJson(fixture("cash_admin"), CashLedgerResponse::class.java)
        assertEquals(1249999.5, c.balance, 0.0001)
        val latest = c.data.orEmpty().first()
        assertFalse(latest.isIncome)
        assertEquals(250000.5, latest.amount, 0.0001)
    }

    @Test
    fun notificationsParse() {
        val n = gson.fromJson(fixture("notifications"), NotificationResponse::class.java)
        assertEquals(1, n.unreadCount)
        assertEquals("Jadwal jaga terbit", n.notifications.orEmpty().single().title)
    }

    @Test
    fun forbiddenResponseMapsToForbiddenErrorWithServerMessage() {
        val err = ApiErrors.fromHttp(403, fixture("forbidden_cash"))
        assertEquals(ErrorKind.FORBIDDEN, err.kind)
        assertTrue(err.message.contains("otoritas"))
    }

    @Test
    fun unauthorizedAndServerErrorsHaveFriendlyFallbacks() {
        assertEquals(ErrorKind.UNAUTHORIZED, ApiErrors.fromHttp(401, null).kind)
        val server = ApiErrors.fromHttp(500, "<html>Whoops</html>")
        assertEquals(ErrorKind.SERVER, server.kind)
        assertTrue(server.message.contains("500"))
        assertEquals("Data tidak valid", ApiErrors.fromHttp(422, """{"message":"Data tidak valid"}""").message)
    }

    @Test
    fun mitraPaymentMatrixUsesStringMonthKeys() {
        val json = """{"id":3,"nama":"Mitra A","payment_matrix":{"1":true,"2":false,"10":true,"11":false,"12":false}}"""
        val m = gson.fromJson(json, MitraDto::class.java)
        assertTrue(m.isPaid(1))
        assertFalse(m.isPaid(2))
        assertTrue(m.isPaid(10))
        assertEquals(2, m.paidCount)
    }
}
