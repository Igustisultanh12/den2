package id.my.sisinden.app.ui.call

import android.content.Context
import android.media.AudioAttributes
import android.media.AudioManager
import android.media.Ringtone
import android.media.RingtoneManager
import android.media.ToneGenerator
import android.os.Build
import android.os.VibrationEffect
import android.os.Vibrator
import android.os.VibratorManager

/**
 * Suara panggilan: nada dering (masuk), nada sambung (keluar), dan
 * pengaturan jalur audio (speaker/earpiece) selama panggilan.
 */
class CallAudio(context: Context) {
    private val appContext = context.applicationContext
    private val audio = appContext.getSystemService(Context.AUDIO_SERVICE) as AudioManager
    private var ringtone: Ringtone? = null
    private var ringback: ToneGenerator? = null
    private var vibrating = false
    private val previousMode = audio.mode

    private val vibrator: Vibrator? =
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            (appContext.getSystemService(Context.VIBRATOR_MANAGER_SERVICE) as? VibratorManager)?.defaultVibrator
        } else {
            @Suppress("DEPRECATION")
            appContext.getSystemService(Context.VIBRATOR_SERVICE) as? Vibrator
        }

    /** Dering panggilan masuk + getar (mengikuti mode senyap perangkat). */
    fun startRinging() {
        stopSounds()
        val uri = RingtoneManager.getActualDefaultRingtoneUri(appContext, RingtoneManager.TYPE_RINGTONE)
            ?: RingtoneManager.getDefaultUri(RingtoneManager.TYPE_RINGTONE)
        ringtone = runCatching { RingtoneManager.getRingtone(appContext, uri) }.getOrNull()?.apply {
            audioAttributes = AudioAttributes.Builder()
                .setUsage(AudioAttributes.USAGE_NOTIFICATION_RINGTONE)
                .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                .build()
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) isLooping = true
            runCatching { play() }
        }
        if (audio.ringerMode != AudioManager.RINGER_MODE_SILENT) {
            vibrator?.let {
                vibrating = true
                it.vibrate(VibrationEffect.createWaveform(longArrayOf(0, 800, 600), 0))
            }
        }
    }

    /** Nada sambung untuk pemanggil selama menunggu dijawab. */
    fun startRingback() {
        stopSounds()
        ringback = runCatching { ToneGenerator(AudioManager.STREAM_VOICE_CALL, 70) }.getOrNull()?.apply {
            startTone(ToneGenerator.TONE_SUP_RINGTONE)
        }
    }

    fun stopSounds() {
        ringtone?.let { runCatching { it.stop() } }
        ringtone = null
        ringback?.let { runCatching { it.stopTone(); it.release() } }
        ringback = null
        if (vibrating) {
            vibrator?.cancel()
            vibrating = false
        }
    }

    /** Masuk mode komunikasi; [speaker] true untuk video (pengeras suara). */
    @Suppress("DEPRECATION")
    fun enterCall(speaker: Boolean) {
        audio.mode = AudioManager.MODE_IN_COMMUNICATION
        audio.isSpeakerphoneOn = speaker
    }

    @Suppress("DEPRECATION")
    fun setSpeaker(on: Boolean) {
        audio.isSpeakerphoneOn = on
    }

    fun beepEnded() {
        runCatching {
            val tone = ToneGenerator(AudioManager.STREAM_VOICE_CALL, 60)
            tone.startTone(ToneGenerator.TONE_PROP_PROMPT, 300)
            android.os.Handler(android.os.Looper.getMainLooper()).postDelayed({ tone.release() }, 500)
        }
    }

    @Suppress("DEPRECATION")
    fun release() {
        stopSounds()
        audio.isSpeakerphoneOn = false
        audio.mode = previousMode
    }
}
