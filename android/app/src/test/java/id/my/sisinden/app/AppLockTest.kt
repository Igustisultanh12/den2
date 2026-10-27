package id.my.sisinden.app

import id.my.sisinden.app.core.security.AppLockManager
import id.my.sisinden.app.core.security.LockStore
import id.my.sisinden.app.core.security.PinResult
import id.my.sisinden.app.core.security.PinRules
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/** PIN & kunci otomatis 5 menit (gaya M-Banking). */
class AppLockTest {

    private class MemoryStore : LockStore {
        var user = Int.MIN_VALUE
        var pin: String? = null
        override fun hasPin(userId: Int) = pin != null && user == userId
        override fun setPin(userId: Int, pin: String) { user = userId; this.pin = pin; failedAttempts = 0 }
        override fun matches(pin: String) = this.pin == pin
        override var failedAttempts = 0
        override var biometricEnabled = false
        override fun clear() { pin = null; user = Int.MIN_VALUE; biometricEnabled = false; failedAttempts = 0 }
    }

    private var clock = 1_000_000L
    private fun manager(store: LockStore = MemoryStore().apply { setPin(7, "250914") }) = AppLockManager(store) { clock }

    @Test
    fun `pin lemah ditolak`() {
        assertNotNull(PinRules.weakness("12345"))
        assertNotNull(PinRules.weakness("1234a6"))
        assertNotNull(PinRules.weakness("111111"))
        assertNotNull(PinRules.weakness("123456"))
        assertNotNull(PinRules.weakness("987654"))
        assertNull(PinRules.weakness("250914"))
        assertNull(PinRules.weakness("135790"))
    }

    @Test
    fun `aplikasi terkunci saat dibuka dan terbuka dengan pin benar`() {
        val m = manager()
        assertTrue(m.locked.value)
        assertEquals(PinResult.Wrong(4), m.verifyPin("000000"))
        assertTrue(m.locked.value)
        assertEquals(PinResult.Ok, m.verifyPin("250914"))
        assertFalse(m.locked.value)
        assertEquals(0, m.store.failedAttempts)
    }

    @Test
    fun `lima kali salah memaksa login ulang`() {
        val m = manager()
        repeat(4) { assertTrue(m.verifyPin("999999") is PinResult.Wrong) }
        assertEquals(PinResult.Locked, m.verifyPin("999999"))
    }

    @Test
    fun `terkunci setelah lima menit diam`() {
        val m = manager()
        m.unlock()
        clock += AppLockManager.TIMEOUT_MS - 1_000
        m.checkIdle()
        assertFalse(m.locked.value)
        m.touch()
        clock += AppLockManager.TIMEOUT_MS - 1_000
        m.checkIdle()
        assertFalse("sentuhan layar mengulang hitungan", m.locked.value)
        clock += 2_000
        m.checkIdle()
        assertTrue(m.locked.value)
    }

    @Test
    fun `keluar aplikasi lalu kembali wajib pin`() {
        val m = manager()
        m.unlock()
        m.onBackground()
        clock += 3_000
        m.onForeground()
        assertTrue("kembali dari Home/aplikasi lain → terkunci", m.locked.value)
    }

    @Test
    fun `pemilih berkas dari dalam aplikasi tidak mengunci`() {
        val m = manager()
        m.unlock()
        m.expectExternal()
        m.onBackground()
        clock += 30_000
        m.onForeground()
        assertFalse(m.locked.value)
        // Pengecualian hanya sekali: keluar berikutnya tetap terkunci.
        m.onBackground()
        clock += 1_000
        m.onForeground()
        assertTrue(m.locked.value)
    }

    @Test
    fun `pengecualian tidak berlaku bila pergi lebih dari lima menit`() {
        val m = manager()
        m.unlock()
        m.expectExternal()
        m.onBackground()
        clock += AppLockManager.TIMEOUT_MS
        m.onForeground()
        assertTrue(m.locked.value)
    }

    @Test
    fun `panggilan video tidak ikut terkunci`() {
        val m = manager()
        m.unlock()
        m.keepAlive = { true }
        clock += AppLockManager.TIMEOUT_MS * 3
        m.checkIdle()
        assertFalse(m.locked.value)
    }

    @Test
    fun `hash pin konsisten dan bergantung salt`() {
        val salt = ByteArray(16) { it.toByte() }
        val a = PinRules.hash("250914", salt, 1_000)
        val b = PinRules.hash("250914", salt, 1_000)
        val c = PinRules.hash("250914", ByteArray(16), 1_000)
        assertTrue(a.contentEquals(b))
        assertFalse(a.contentEquals(c))
        assertEquals(32, a.size)
    }
}
