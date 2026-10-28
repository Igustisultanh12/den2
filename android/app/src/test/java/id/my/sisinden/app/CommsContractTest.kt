package id.my.sisinden.app

import com.google.gson.Gson
import id.my.sisinden.app.core.notification.NotificationHelper
import id.my.sisinden.app.data.model.AgoraTokenResponse
import id.my.sisinden.app.data.model.CallResponse
import id.my.sisinden.app.data.model.ChatAttachmentDto
import id.my.sisinden.app.data.model.ChatHomeResponse
import id.my.sisinden.app.data.model.ChatSyncResponse
import id.my.sisinden.app.data.model.ChatUnreadResponse
import id.my.sisinden.app.data.model.NotificationResponse
import id.my.sisinden.app.data.model.PendingSpJagaResponse
import id.my.sisinden.app.ui.chat.ChatThreadViewModel
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * Kontrak Chat Dinas, panggilan (Vicon), TTE SP Jaga, dan notifikasi
 * terhadap respons ASLI backend (fixture dari feature test PHP).
 */
class CommsContractTest {

    private val gson = Gson()

    private fun fixture(name: String): String =
        requireNotNull(javaClass.classLoader?.getResource("fixtures/$name.json")) { "fixture $name hilang" }.readText()

    @Test
    fun `daftar percakapan berisi chat pribadi dan entri operator dinas`() {
        val res = gson.fromJson(fixture("chat_home"), ChatHomeResponse::class.java)
        assertFalse(res.isOperator)
        assertEquals(2, res.totalUnread)
        val (direct, support) = requireNotNull(res.conversations)
        assertFalse(direct.isSupport)
        assertEquals("Letkol Laut (P) Danden", direct.displayTitle)
        assertEquals("Letkol Laut (P) Danden", direct.partner?.fullName)
        assertEquals(2, direct.unread)
        assertTrue(direct.isOnline && direct.isOpen)
        assertEquals("🎤 Pesan suara", direct.latestMessage?.preview)
        // Operator Dinas yang belum pernah dipakai: uuid null, dibuat saat dibuka.
        assertTrue(support.isSupport)
        assertNull(support.uuid)
        assertEquals("Operator Dinas", support.displayTitle)
    }

    @Test
    fun `sync percakapan aktif berisi pesan suara dan foto`() {
        val res = gson.fromJson(fixture("chat_sync_active"), ChatSyncResponse::class.java)
        val active = requireNotNull(res.active)
        assertTrue(active.isTyping)
        val msgs = requireNotNull(active.messages)
        assertEquals(listOf(7, 3, 3), msgs.map { it.senderId })
        val voice = msgs[1].attachments!!.single()
        assertTrue(voice.isAudio && voice.isVoice)
        assertEquals(4200L, voice.durationMs)
        assertFalse(voice.isImage)
        val photo = msgs[2].attachments!!.single()
        assertTrue(photo.isImage)
        assertFalse(photo.isAudio)
        assertEquals(
            "https://sinden2.my.id/storage/chat_attachments/4f1c2a9e-1b2c-4d5e-8f90-1234567890ab/20261028_foto.png",
            photo.publicUrl("https://sinden2.my.id"),
        )
    }

    @Test
    fun `durasi pesan suara diformat menit detik`() {
        assertEquals("0:04", id.my.sisinden.app.ui.chat.formatVoiceDuration(4_200))
        assertEquals("1:05", id.my.sisinden.app.ui.chat.formatVoiceDuration(65_000))
        assertEquals("0:00", id.my.sisinden.app.ui.chat.formatVoiceDuration(-5))
    }

    @Test
    fun `unread chat berisi pesan terakhir untuk banner`() {
        val res = gson.fromJson(fixture("chat_unread"), ChatUnreadResponse::class.java)
        assertEquals(1, res.unread)
        assertEquals(2L, res.latest?.id)
        assertEquals("Admin Dinas", res.latest?.senderName)
        assertNotNull(res.threadUuid)
    }

    @Test
    fun `panggilan masuk dan token agora terbaca`() {
        val initiate = gson.fromJson(fixture("call_initiate"), CallResponse::class.java)
        assertTrue(initiate.success)
        val incoming = gson.fromJson(fixture("call_incoming"), CallResponse::class.java).call
        assertNotNull(incoming)
        assertEquals("RINGING", incoming!!.normalizedStatus)
        assertTrue(incoming.isVideo)
        assertEquals("Bambang", incoming.caller?.name)
        assertEquals(initiate.call?.callId, incoming.callId)

        val agora = gson.fromJson(fixture("agora_token"), AgoraTokenResponse::class.java)
        assertTrue(agora.channel!!.startsWith("SINDEN_"))
        assertFalse(agora.channel!!.contains("-"))
        assertEquals(1L, agora.uid)
    }

    @Test
    fun `antrean ttd sp jaga untuk pasops`() {
        val res = gson.fromJson(fixture("duty_pending"), PendingSpJagaResponse::class.java)
        val sp = res.data!!.single()
        assertEquals(11, sp.bulan)
        assertEquals(2026, sp.tahun)
        assertEquals(24, sp.totalPersonel)
        assertEquals("pending_signature", sp.status)
    }

    @Test
    fun `notifikasi membawa link yang dipetakan ke rute aplikasi`() {
        val res = gson.fromJson(fixture("notifications_pasops"), NotificationResponse::class.java)
        val n = res.notifications!!.first()
        assertEquals("/sp-jaga", n.link)
        assertEquals("duty", NotificationHelper.deepLinkFor(n.link))
        assertEquals("signature", NotificationHelper.deepLinkFor("/signature-requests?open_skhpp=3&tab=skhpp"))
        assertEquals("skhpp", NotificationHelper.deepLinkFor("/skhpp"))
        assertEquals("chat", NotificationHelper.deepLinkFor("/live-chat"))
        assertEquals("notifications", NotificationHelper.deepLinkFor(null))
    }

    @Test
    fun `semua warta baru dibunyikan bukan hanya yang terbaru`() {
        val items = listOf(
            NotificationHelper.Item(12, "C", "c", null, isRead = false),
            NotificationHelper.Item(11, "B", "b", null, isRead = true),
            NotificationHelper.Item(10, "A", "a", null, isRead = false),
            NotificationHelper.Item(9, "Lama", "x", null, isRead = false),
        )
        assertEquals(listOf(12, 10), NotificationHelper.newUnread(items, lastNotifiedId = 9).map { it.id })
        assertTrue(NotificationHelper.newUnread(items, lastNotifiedId = 12).isEmpty())
    }

    @Test
    fun `nama lampiran mengikuti ekstensi yang diizinkan server`() {
        assertEquals("Laporan_Harian.pdf", ChatThreadViewModel.attachmentName("Laporan Harian.pdf", "application/pdf"))
        assertEquals("foto.jpg", ChatThreadViewModel.attachmentName("foto.JPG", "image/jpeg"))
        assertEquals("data.xlsx", ChatThreadViewModel.attachmentName("data.xlsx", "application/octet-stream"))
        assertEquals("2.5 MB".replace('.', java.text.DecimalFormatSymbols.getInstance().decimalSeparator), ChatThreadViewModel.sizeLabel(2_621_440))
    }

    @Test
    fun `url lampiran dibangun dari file_path dengan https`() {
        val a = ChatAttachmentDto(filePath = "chat_attachments/abc/x.png", url = "http://localhost/storage/chat_attachments/abc/x.png", isImage = true)
        assertEquals("https://sinden2.my.id/storage/chat_attachments/abc/x.png", a.publicUrl("https://sinden2.my.id"))
        val traversal = ChatAttachmentDto(filePath = "../.env", url = "http://evil")
        assertNull(traversal.publicUrl("https://sinden2.my.id"))
        val relative = ChatAttachmentDto(url = "/storage/y.pdf")
        assertEquals("https://sinden2.my.id/storage/y.pdf", relative.publicUrl("https://sinden2.my.id/"))
    }
}
