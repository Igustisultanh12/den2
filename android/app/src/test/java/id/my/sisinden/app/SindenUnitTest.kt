package id.my.sisinden.app

import com.google.gson.Gson
import id.my.sisinden.app.data.model.*
import id.my.sisinden.app.ui.navigation.NavRoute
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Test

class SindenUnitTest {

    private val gson = Gson()

    @Test
    fun testUserDtoParsing() {
        val json = """
            {
                "id": 1,
                "name": "Letkol Intelijen",
                "nrp": "12345/P",
                "role": "admin",
                "jabatan": "Komandan Detasemen"
            }
        """.trimIndent()

        val user = gson.fromJson(json, UserDto::class.java)
        assertEquals(1, user.id)
        assertEquals("Letkol Intelijen", user.name)
        assertEquals("12345/P", user.nrp)
        assertEquals("admin", user.role)
        assertEquals("Komandan Detasemen", user.jabatan)
        assertTrue(user.isAdminOrOfficer)
    }

    @Test
    fun testConfigResponseParsing() {
        val json = """
            {
                "status": "success",
                "app_name": "SI SINDEN",
                "agency_name": "Detasemen Intelijen Kodaeral V",
                "api_status": "ONLINE",
                "mobile_theme_mode": "dark",
                "mobile_primary_color": "#1E3A8A",
                "mobile_card_radius": 20
            }
        """.trimIndent()

        val config = gson.fromJson(json, ConfigResponse::class.java)
        assertEquals("success", config.status)
        assertEquals("SI SINDEN", config.appName)
        assertEquals("Detasemen Intelijen Kodaeral V", config.agencyName)
        assertEquals("ONLINE", config.apiStatus)
        assertEquals("dark", config.mobileThemeMode)
        assertEquals("#1E3A8A", config.mobilePrimaryColor)
        assertEquals(20, config.mobileCardRadius)
    }

    @Test
    fun testCashResponseParsingFromLiveBackend() {
        val liveJson = """
            {
                "status": "success",
                "balance": 505300,
                "totalSaldo": 505300,
                "total_debit": 35492000,
                "total_credit": 34986700,
                "cashes": [
                    {
                        "id": 58,
                        "date": "2026-05-26",
                        "description": "senar + Krupuk Komandan",
                        "debit": 0,
                        "credit": 37000,
                        "balance": 1020100
                    },
                    {
                        "id": 54,
                        "date": "2026-05-23",
                        "description": "Masuk dr komandan",
                        "debit": 1000000,
                        "credit": 0,
                        "balance": 1420500
                    }
                ]
            }
        """.trimIndent()

        val response = gson.fromJson(liveJson, CashResponse::class.java)
        assertEquals("success", response.status)
        assertEquals(505300.0, response.totalBalance, 0.001)
        assertEquals(35492000.0, response.totalIncome, 0.001)
        assertEquals(34986700.0, response.totalExpense, 0.001)
        assertEquals(2, response.transactions.size)

        val txExpense = response.transactions[0]
        assertEquals(37000.0, txExpense.effectiveAmount, 0.001)
        assertEquals(false, txExpense.isIncome)

        val txIncome = response.transactions[1]
        assertEquals(1000000.0, txIncome.effectiveAmount, 0.001)
        assertEquals(true, txIncome.isIncome)
    }

    @Test
    fun testTechnicalCashParsingFromLiveBackend() {
        val liveJson = """
            {
                "status": "success",
                "balance": 1720056,
                "totalSaldo": 1720056,
                "total_debit": 1720056,
                "total_credit": 0,
                "cashes": [
                    {
                        "id": 3,
                        "date": "2026-08-04",
                        "description": "Dana Sisa Terakhir",
                        "debit": 1720056,
                        "credit": 0,
                        "balance": 1720056
                    }
                ]
            }
        """.trimIndent()

        val response = gson.fromJson(liveJson, TechnicalCashResponse::class.java)
        assertEquals(1720056.0, response.balance, 0.001)
        assertEquals(1, response.transactions.size)
        assertEquals(1720056.0, response.transactions[0].effectiveAmount, 0.001)
        assertTrue(response.transactions[0].isIncome)
    }

    @Test
    fun testMitraParsingFromLiveBackend() {
        val liveJson = """
            {
                "id": 27,
                "nama": "Pak oni",
                "pt": "spob pantai lamong",
                "no_tlp": "085692180301",
                "payment_matrix": {
                    "1": true,
                    "2": true,
                    "10": true
                }
            }
        """.trimIndent()

        val mitra = gson.fromJson(liveJson, MitraDto::class.java)
        assertEquals("Pak oni", mitra.name)
        assertEquals("spob pantai lamong", mitra.sector)
        assertEquals("085692180301", mitra.phone)
        assertNotNull(mitra.paymentMatrix)
    }

    @Test
    fun testNavRoutesUniqueness() {
        val routes = listOf(
            NavRoute.Login.route,
            NavRoute.Main.route,
            NavRoute.Attendance.route,
            NavRoute.LetterLogs.route,
            NavRoute.CreateLetter.route,
            NavRoute.Skhpp.route,
            NavRoute.CreateSkhpp.route,
            NavRoute.Cash.route,
            NavRoute.TechnicalCash.route,
            NavRoute.Mitra.route,
            NavRoute.Signature.route,
            NavRoute.Violations.route,
            NavRoute.Personnel.route,
            NavRoute.Notifications.route,
            NavRoute.Settings.route
        )

        val uniqueRoutes = routes.toSet()
        assertEquals("Setiap route navigasi harus unik", routes.size, uniqueRoutes.size)
    }

    @Test
    fun testRp1TransactionIntegrity() {
        val incomeReq = CreateCashRequest(type = "masuk", amount = 1.0, description = "Setoran Kas Rp1")
        assertEquals(1.0, incomeReq.amount, 0.001)
        assertEquals(1.0, incomeReq.debit, 0.001)
        assertEquals(0.0, incomeReq.credit, 0.001)

        val expenseReq = CreateCashRequest(type = "keluar", amount = 1.0, description = "Pengeluaran Kas Rp1")
        assertEquals(1.0, expenseReq.amount, 0.001)
        assertEquals(0.0, expenseReq.debit, 0.001)
        assertEquals(1.0, expenseReq.credit, 0.001)

        val techIncomeReq = CreateTechnicalCashRequest(type = "masuk", amount = 1.0, description = "Masuk Taktis Rp1")
        assertEquals(1.0, techIncomeReq.amount, 0.001)
        assertEquals(1.0, techIncomeReq.debit, 0.001)
        assertEquals(0.0, techIncomeReq.credit, 0.001)

        val techExpenseReq = CreateTechnicalCashRequest(type = "keluar", amount = 1.0, description = "Keluar Taktis Rp1")
        assertEquals(1.0, techExpenseReq.amount, 0.001)
        assertEquals(0.0, techExpenseReq.debit, 0.001)
        assertEquals(1.0, techExpenseReq.credit, 0.001)
    }

    @Test
    fun testUserModulePermissions() {
        val officerUser = UserDto(role = "danunitteknis", canAccessCash = false)
        assertTrue(officerUser.canOpenTechnicalCash)

        val adminUser = UserDto(role = "admin")
        assertTrue(adminUser.canOpenCash)
        assertTrue(adminUser.canOpenTechnicalCash)
        assertTrue(adminUser.canOpenMitra)
    }

    @Test
    fun testAttendanceResponseParsing() {
        val json = """
            {
                "status": "success",
                "today_attendance": {
                    "id": 10,
                    "user_id": 1,
                    "date": "2026-10-13",
                    "time_in": "07:30:00",
                    "status": "hadir",
                    "location_name": "Mako Denintel Kodaeral V, Surabaya",
                    "latitude": -7.2053,
                    "longitude": 112.7358,
                    "device_model": "Android Device",
                    "notes": "Apel pagi tepat waktu"
                },
                "officer_stats": {
                    "total_attended_today": 25,
                    "total_active_personnel": 30
                },
                "attendances": [
                    {
                        "id": 10,
                        "user_id": 1,
                        "date": "2026-10-13",
                        "time_in": "07:30:00",
                        "status": "hadir",
                        "location_name": "Mako Denintel Kodaeral V, Surabaya",
                        "latitude": -7.2053,
                        "longitude": 112.7358,
                        "device_model": "Android Device"
                    }
                ]
            }
        """.trimIndent()

        val response = gson.fromJson(json, AttendanceResponse::class.java)
        assertEquals("success", response.status)
        assertNotNull(response.todayAttendance)
        assertEquals("hadir", response.todayAttendance?.status)
        assertEquals(-7.2053, response.todayAttendance?.latitude ?: 0.0, 0.0001)
        assertEquals(25, response.officerStats?.totalAttendedToday)
        assertEquals(30, response.officerStats?.totalActivePersonnel)
        assertEquals(1, response.attendances.size)
    }

    @Test
    fun testCreateAttendanceRequest() {
        val req = CreateAttendanceRequest(
            status = "dinas_luar",
            latitude = -7.2575,
            longitude = 112.7521,
            locationName = "Kantor Gubernur Jawa Timur",
            deviceModel = "Samsung Galaxy S23",
            notes = "Koordinasi pengamanan VVIP"
        )
        assertEquals("dinas_luar", req.status)
        assertEquals(-7.2575, req.latitude ?: 0.0, 0.0001)
        assertEquals("Samsung Galaxy S23", req.deviceModel)
    }
}
