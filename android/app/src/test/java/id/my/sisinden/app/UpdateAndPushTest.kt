package id.my.sisinden.app

import com.google.gson.Gson
import id.my.sisinden.app.core.update.AppUpdater
import id.my.sisinden.app.data.model.ConfigResponse
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class UpdateAndPushTest {

    @Test
    fun `perbandingan versi numerik`() {
        assertTrue(AppUpdater.compareVersions("1.10.0", "1.9.2") > 0)
        assertTrue(AppUpdater.compareVersions("v1.5.0", "1.4.0") > 0)
        assertEquals(0, AppUpdater.compareVersions("1.5", "1.5.0"))
        assertEquals(0, AppUpdater.compareVersions("1.5.0-debug", "1.5.0"))
        assertTrue(AppUpdater.compareVersions("1.4.9", "1.5.0") < 0)
    }

    private fun fixture(name: String): String =
        requireNotNull(javaClass.classLoader?.getResource("fixtures/$name.json")) { "fixture $name" }.readText()

    @Test
    fun `info pembaruan dari Pengaturan web`() {
        val r = AppUpdater.parseServer(fixture("app_update"), "https://sinden2.my.id")
        assertNotNull(r)
        assertEquals("1.7.0", r!!.version)
        assertEquals(16, r.versionCode)
        assertEquals("Sinden2-1.7.0.apk", r.apkName)
        assertEquals(14_800_000L, r.apkSize)
        assertEquals("ab".repeat(32), r.sha256)
        assertEquals("https://sinden2.my.id/api/mobile/app-update/download?v=1.7.0", r.apkUrl)
        assertTrue(r.notes.contains("Ibu Beti"))

        // Belum ada APK di server.
        assertNull(AppUpdater.parseServer(fixture("app_update_none")))
    }

    @Test
    fun `unduhan hanya dari server Sinden 2 dan wajib SHA-256`() {
        val base = "https://sinden2.my.id"
        val sha = "c".repeat(64)
        // URL server lain diabaikan → dipaksa ke endpoint server sendiri.
        val foreign = AppUpdater.parseServer("""{"available":true,"version":"1.8.0","sha256":"$sha","download_url":"https://contoh.lain/x.apk"}""", base)
        assertEquals("$base/api/mobile/app-update/download?v=1.8.0", foreign!!.apkUrl)
        val relative = AppUpdater.parseServer("""{"available":true,"version":"1.8.0","sha256":"$sha","download_url":"/api/mobile/app-update/download?v=1.8.0"}""", base)
        assertEquals("$base/api/mobile/app-update/download?v=1.8.0", relative!!.apkUrl)
        // Tanpa SHA-256 / versi tidak valid → ditolak.
        assertNull(AppUpdater.parseServer("""{"available":true,"version":"1.8.0","sha256":""}""", base))
        assertNull(AppUpdater.parseServer("""{"available":true,"version":"terbaru","sha256":"$sha"}""", base))
        assertTrue(AppUpdater.reason(java.net.UnknownHostException("sinden2.my.id")).contains("internet"))
    }

    @Test
    fun `konfigurasi push dari server`() {
        val cfg = Gson().fromJson(
            """{"status":"success","push":{"api_key":"AIza","app_id":"1:1:android:a","project_id":"p","sender_id":"1"},"push_enabled":true}""",
            ConfigResponse::class.java,
        )
        assertTrue(cfg.push!!.isComplete)
        assertEquals(true, cfg.pushEnabled)
        val none = Gson().fromJson("""{"status":"success","push":null,"push_enabled":false}""", ConfigResponse::class.java)
        assertNull(none.push)
        assertFalse(none.pushEnabled!!)
    }
}
