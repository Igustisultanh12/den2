package id.my.sisinden.app

import com.google.gson.Gson
import id.my.sisinden.app.data.model.CashResponse
import id.my.sisinden.app.data.model.CashTransactionDto
import id.my.sisinden.app.data.model.ConfigResponse
import id.my.sisinden.app.data.model.LetterLogDto
import id.my.sisinden.app.data.model.LetterLogsResponse
import id.my.sisinden.app.data.model.MitraDto
import id.my.sisinden.app.data.model.MitraResponse
import id.my.sisinden.app.data.model.UserDto
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
    }

    @Test
    fun testConfigResponseParsing() {
        val json = """
            {
                "status": "success",
                "app_name": "SI SINDEN",
                "agency_name": "Detasemen Intelijen Kodaeral V",
                "api_status": "ONLINE"
            }
        """.trimIndent()

        val config = gson.fromJson(json, ConfigResponse::class.java)
        assertEquals("success", config.status)
        assertEquals("SI SINDEN", config.appName)
        assertEquals("Detasemen Intelijen Kodaeral V", config.agencyName)
        assertEquals("ONLINE", config.apiStatus)
    }

    @Test
    fun testCashCalculation() {
        val transactions = listOf(
            CashTransactionDto(id = 1, type = "masuk", amount = 1000000.0, description = "Dana Operasional"),
            CashTransactionDto(id = 2, type = "keluar", amount = 250000.0, description = "Konsumsi Piket"),
            CashTransactionDto(id = 3, type = "masuk", amount = 500000.0, description = "Dukungan Logistik")
        )

        val totalMasuk = transactions.filter { it.type == "masuk" }.sumOf { it.amount }
        val totalKeluar = transactions.filter { it.type == "keluar" }.sumOf { it.amount }
        val sisaSaldo = totalMasuk - totalKeluar

        assertEquals(1500000.0, totalMasuk, 0.001)
        assertEquals(250000.0, totalKeluar, 0.001)
        assertEquals(1250000.0, sisaSaldo, 0.001)
    }

    @Test
    fun testNavRoutesUniqueness() {
        val routes = listOf(
            NavRoute.Login.route,
            NavRoute.Main.route,
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
}
