package id.my.sisinden.app.ui.chat

import android.content.Context
import android.media.AudioAttributes
import android.media.MediaPlayer
import android.media.MediaRecorder
import android.os.Build
import android.os.SystemClock
import android.util.Log
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Pause
import androidx.compose.material.icons.filled.PlayArrow
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableLongStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import java.io.File

/** Rekaman pesan suara selesai. */
class VoiceClip(val file: File, val durationMs: Long)

/**
 * Perekam pesan suara: AAC dalam wadah MPEG-4 (.m4a), mono 32 kbps — kecil
 * dan dapat diputar di aplikasi maupun browser (sama seperti pesan suara WhatsApp).
 */
class VoiceRecorder(private val context: Context) {
    private var recorder: MediaRecorder? = null
    private var file: File? = null
    private var startedAt = 0L

    val isRecording: Boolean get() = recorder != null
    val elapsedMs: Long get() = if (recorder != null) SystemClock.elapsedRealtime() - startedAt else 0

    /** @return false bila mikrofon tidak dapat dipakai (mis. sedang panggilan). */
    fun start(): Boolean {
        if (recorder != null) return true
        val out = File(context.cacheDir, "voice_${System.currentTimeMillis()}.m4a")
        val r = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) MediaRecorder(context) else @Suppress("DEPRECATION") MediaRecorder()
        return try {
            r.setAudioSource(MediaRecorder.AudioSource.MIC)
            r.setOutputFormat(MediaRecorder.OutputFormat.MPEG_4)
            r.setAudioEncoder(MediaRecorder.AudioEncoder.AAC)
            r.setAudioChannels(1)
            r.setAudioSamplingRate(44_100)
            r.setAudioEncodingBitRate(32_000)
            r.setOutputFile(out.absolutePath)
            r.prepare()
            r.start()
            recorder = r
            file = out
            startedAt = SystemClock.elapsedRealtime()
            true
        } catch (e: Exception) {
            Log.w("VoiceRecorder", "Gagal mulai merekam", e)
            runCatching { r.release() }
            out.delete()
            false
        }
    }

    /** Hentikan rekaman. Null bila terlalu singkat (< 0,7 dtk) atau gagal. */
    fun stop(): VoiceClip? {
        val r = recorder ?: return null
        val out = file
        val duration = elapsedMs
        recorder = null
        file = null
        val ok = runCatching { r.stop() }.isSuccess
        runCatching { r.release() }
        if (!ok || out == null || duration < 700 || out.length() == 0L) {
            out?.delete()
            return null
        }
        return VoiceClip(out, duration)
    }

    fun cancel() {
        stop()?.file?.delete()
    }
}

/** Status pemutar pesan suara bersama (hanya satu pesan berbunyi pada satu waktu). */
data class VoicePlaybackState(
    val url: String? = null,
    val playing: Boolean = false,
    val preparing: Boolean = false,
    val positionMs: Long = 0,
    val durationMs: Long = 0,
)

object VoicePlayback {
    private var player: MediaPlayer? = null
    private val _state = MutableStateFlow(VoicePlaybackState())
    val state: StateFlow<VoicePlaybackState> = _state.asStateFlow()

    fun toggle(url: String) {
        val current = _state.value
        val p = player
        if (current.url == url && p != null) {
            if (current.preparing) return
            if (p.isPlaying) {
                p.pause()
                _state.value = current.copy(playing = false)
            } else {
                p.start()
                _state.value = current.copy(playing = true)
            }
            return
        }
        stop()
        val mp = MediaPlayer()
        player = mp
        _state.value = VoicePlaybackState(url = url, preparing = true)
        try {
            mp.setAudioAttributes(
                AudioAttributes.Builder().setUsage(AudioAttributes.USAGE_MEDIA).setContentType(AudioAttributes.CONTENT_TYPE_SPEECH).build()
            )
            mp.setDataSource(url)
            mp.setOnPreparedListener {
                if (player !== it) return@setOnPreparedListener
                it.start()
                _state.value = _state.value.copy(preparing = false, playing = true, durationMs = it.duration.toLong().coerceAtLeast(0))
            }
            mp.setOnCompletionListener {
                if (player === it) _state.value = _state.value.copy(playing = false, positionMs = 0)
            }
            mp.setOnErrorListener { it, _, _ ->
                if (player === it) stop()
                true
            }
            mp.prepareAsync()
        } catch (e: Exception) {
            Log.w("VoicePlayback", "Gagal memutar pesan suara", e)
            stop()
        }
    }

    /** Dipanggil berkala oleh tampilan yang sedang diputar untuk memperbarui progres. */
    fun tick() {
        val p = player ?: return
        val s = _state.value
        if (s.playing) runCatching { _state.value = s.copy(positionMs = p.currentPosition.toLong()) }
    }

    fun stop() {
        player?.let { runCatching { it.stop() }; runCatching { it.release() } }
        player = null
        _state.value = VoicePlaybackState()
    }
}

fun formatVoiceDuration(ms: Long): String {
    val s = (ms / 1000).coerceAtLeast(0)
    return "%d:%02d".format(s / 60, s % 60)
}

/** Gelembung pesan suara: tombol putar/jeda, progres, durasi. */
@Composable
fun VoiceNoteView(url: String, durationMs: Long?, fg: Color, accent: Color) {
    val state by VoicePlayback.state.collectAsStateWithLifecycle()
    val isThis = state.url == url
    val playing = isThis && state.playing
    val total = (if (isThis && state.durationMs > 0) state.durationMs else durationMs ?: 0L).coerceAtLeast(1)
    val position = if (isThis) state.positionMs else 0L

    LaunchedEffect(playing) {
        while (playing) {
            VoicePlayback.tick()
            delay(200)
        }
    }

    Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.widthIn(min = 200.dp, max = 260.dp)) {
        IconButton(
            onClick = { VoicePlayback.toggle(url) },
            modifier = Modifier.size(40.dp).clip(CircleShape).background(accent),
        ) {
            if (isThis && state.preparing) {
                CircularProgressIndicator(Modifier.size(18.dp), strokeWidth = 2.dp, color = Color.White)
            } else {
                Icon(if (playing) Icons.Filled.Pause else Icons.Filled.PlayArrow, contentDescription = if (playing) "Jeda" else "Putar pesan suara", tint = Color.White)
            }
        }
        Spacer(Modifier.width(8.dp))
        LinearProgressIndicator(
            progress = { (position.toFloat() / total).coerceIn(0f, 1f) },
            modifier = Modifier.weight(1f),
            color = accent,
            trackColor = fg.copy(alpha = 0.2f),
        )
        Spacer(Modifier.width(8.dp))
        Text(
            formatVoiceDuration(if (playing || position > 0) position else total),
            style = MaterialTheme.typography.labelSmall,
            color = fg.copy(alpha = 0.8f),
            modifier = Modifier.padding(end = 4.dp),
        )
    }
}

/** Hentikan pemutaran saat layar percakapan ditutup. */
@Composable
fun StopVoicePlaybackOnDispose() {
    DisposableEffect(Unit) { onDispose { VoicePlayback.stop() } }
}

/** Detik berjalan selama merekam (untuk label waktu). */
@Composable
fun rememberRecordingClock(recording: Boolean, recorder: VoiceRecorder): Long {
    var elapsed by remember { mutableLongStateOf(0L) }
    LaunchedEffect(recording) {
        elapsed = 0
        while (recording) {
            elapsed = recorder.elapsedMs
            delay(200)
        }
    }
    return elapsed
}
