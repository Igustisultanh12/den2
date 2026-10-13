package id.my.sisinden.app.ui.pdf

import android.graphics.Bitmap
import android.graphics.Color
import android.graphics.pdf.PdfRenderer
import android.os.ParcelFileDescriptor
import android.util.LruCache
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import kotlinx.coroutines.withContext
import java.io.Closeable
import java.io.File

/**
 * Pembungkus [PdfRenderer] bawaan Android (tanpa pustaka pihak ketiga).
 * PdfRenderer tidak thread-safe dan hanya boleh membuka satu halaman sekaligus,
 * sehingga semua akses diserialkan dengan [Mutex]. Bitmap disimpan di LruCache
 * berbasis ukuran memori agar dokumen multi-halaman tidak menyebabkan OOM.
 */
class PdfDocument private constructor(
    private val descriptor: ParcelFileDescriptor,
    private val renderer: PdfRenderer,
    /** Rasio tinggi/lebar tiap halaman (mendukung orientasi campuran). */
    val pageAspectRatios: List<Float>,
) : Closeable {

    val pageCount: Int get() = pageAspectRatios.size
    private val mutex = Mutex()
    @Volatile private var closed = false

    private val cache = object : LruCache<String, Bitmap>(cacheSizeKb()) {
        override fun sizeOf(key: String, value: Bitmap): Int = value.byteCount / 1024
    }

    /** Render halaman pada lebar piksel tertentu (dibulatkan agar cache efektif). */
    suspend fun render(pageIndex: Int, widthPx: Int): Bitmap? = withContext(Dispatchers.IO) {
        if (closed || pageIndex !in 0 until pageCount || widthPx <= 0) return@withContext null
        val width = ((widthPx + 63) / 64 * 64).coerceAtMost(2400)
        val key = "$pageIndex@$width"
        cache.get(key)?.let { return@withContext it }
        mutex.withLock {
            if (closed) return@withLock null
            cache.get(key)?.let { return@withLock it }
            val height = (width * pageAspectRatios[pageIndex]).toInt().coerceAtLeast(1)
            val bitmap = Bitmap.createBitmap(width, height, Bitmap.Config.ARGB_8888)
            bitmap.eraseColor(Color.WHITE) // halaman PDF bisa transparan
            renderer.openPage(pageIndex).use { page ->
                page.render(bitmap, null, null, PdfRenderer.Page.RENDER_MODE_FOR_DISPLAY)
            }
            cache.put(key, bitmap)
            bitmap
        }
    }

    override fun close() {
        if (closed) return
        closed = true
        // Tutup di bawah mutex agar tidak bentrok dengan render yang sedang berjalan.
        kotlinx.coroutines.runBlocking {
            mutex.withLock {
                runCatching { renderer.close() }
                runCatching { descriptor.close() }
            }
        }
        cache.evictAll()
    }

    companion object {
        private fun cacheSizeKb(): Int {
            val maxKb = (Runtime.getRuntime().maxMemory() / 1024).toInt()
            return (maxKb / 6).coerceAtLeast(8 * 1024)
        }

        /** @throws java.io.IOException bila berkas rusak / bukan PDF. */
        suspend fun open(file: File): PdfDocument = withContext(Dispatchers.IO) {
            val pfd = ParcelFileDescriptor.open(file, ParcelFileDescriptor.MODE_READ_ONLY)
            try {
                val renderer = PdfRenderer(pfd)
                val ratios = (0 until renderer.pageCount).map { i ->
                    renderer.openPage(i).use { p -> if (p.width > 0) p.height.toFloat() / p.width else 1.414f }
                }
                if (ratios.isEmpty()) {
                    renderer.close()
                    throw java.io.IOException("Dokumen PDF tidak memiliki halaman.")
                }
                PdfDocument(pfd, renderer, ratios)
            } catch (t: Throwable) {
                runCatching { pfd.close() }
                throw t
            }
        }
    }
}
