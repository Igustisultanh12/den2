package id.my.sisinden.app.ui.call

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import id.my.sisinden.app.core.network.ApiResult
import id.my.sisinden.app.data.repository.SindenRepository
import kotlinx.coroutines.Job
import kotlinx.coroutines.channels.Channel
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.receiveAsFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import org.json.JSONObject

enum class CallPhase { PREPARING, OUTGOING, INCOMING, CONNECTING, CONNECTED, ENDED }

data class CallUiState(
    val phase: CallPhase,
    val video: Boolean,
    val micOn: Boolean = true,
    val camOn: Boolean = true,
    val speakerOn: Boolean = true,
    val remoteVideo: Boolean = false,
    val seconds: Int = 0,
    val info: String? = null,
    val endedMessage: String? = null,
)

/**
 * Mesin status panggilan — mengikuti VideoCallModal.vue milik web:
 * RINGING → ACCEPTED → CONNECTED → ENDED/REJECTED, sinyal dipoll tiap 1 detik,
 * pemanggil berhenti setelah 40 detik tak dijawab, penyambungan dibatasi 35 detik.
 */
class CallViewModel(
    private val repository: SindenRepository,
    private val uuid: String,
    video: Boolean,
    private val incoming: Boolean,
    private val myUserId: Int,
) : ViewModel() {

    private val _state = MutableStateFlow(
        CallUiState(
            phase = if (incoming) CallPhase.INCOMING else CallPhase.PREPARING,
            video = video,
            camOn = video,
            speakerOn = video,
        )
    )
    val state: StateFlow<CallUiState> = _state.asStateFlow()

    private val _commands = Channel<ViconCommand>(Channel.BUFFERED)
    val commands: Flow<ViconCommand> = _commands.receiveAsFlow()

    private var pollJob: Job? = null
    private var timeoutJob: Job? = null
    private var durationJob: Job? = null
    private var reconnectJob: Job? = null
    private var joining = false

    init {
        if (incoming) startPolling()
    }

    private val phase get() = _state.value.phase

    /** Panggilan keluar dimulai setelah izin kamera/mikrofon diputuskan pengguna. */
    fun startOutgoing() {
        if (incoming || phase != CallPhase.PREPARING) return
        viewModelScope.launch {
            when (val res = repository.initiateCall(uuid, _state.value.video)) {
                is ApiResult.Success -> {
                    _state.update { it.copy(phase = CallPhase.OUTGOING) }
                    startPolling()
                    startTimeout(OUTGOING_TIMEOUT_S) {
                        endWithServer("missed", "Tidak dijawab. Silakan kirim pesan tertulis.")
                    }
                }
                is ApiResult.Failure -> finish(res.error.message)
            }
        }
    }

    fun accept() {
        if (phase != CallPhase.INCOMING) return
        _state.update { it.copy(phase = CallPhase.CONNECTING) }
        startTimeout(CONNECT_TIMEOUT_S) { hangUp("Gagal tersambung. Coba lagi.") }
        viewModelScope.launch {
            repository.sendCallSignal(uuid, "accept", "ACCEPTED")
            joinRoom()
        }
    }

    fun reject() {
        if (phase != CallPhase.INCOMING) return
        endWithServer("rejected", "Panggilan ditolak")
    }

    /** Tutup panggilan dari sisi ini (tombol merah / kembali). */
    fun hangUp(message: String = "Panggilan selesai") {
        when (phase) {
            CallPhase.ENDED -> Unit
            CallPhase.PREPARING -> finish(message)
            CallPhase.INCOMING -> endWithServer("rejected", "Panggilan ditolak")
            CallPhase.OUTGOING -> endWithServer("canceled", "Panggilan dibatalkan")
            CallPhase.CONNECTING -> endWithServer("ended", message)
            CallPhase.CONNECTED -> endWithServer("ended", message, _state.value.seconds)
        }
    }

    fun toggleMic() {
        val on = !_state.value.micOn
        _state.update { it.copy(micOn = on) }
        _commands.trySend(ViconCommand.Mic(on))
    }

    fun toggleCam() {
        val on = !_state.value.camOn
        _state.update { it.copy(camOn = on) }
        _commands.trySend(ViconCommand.Cam(on))
    }

    fun flipCamera() {
        _commands.trySend(ViconCommand.Flip)
    }

    fun setSpeaker(on: Boolean) {
        _state.update { it.copy(speakerOn = on) }
    }

    /** Peristiwa dari halaman vicon (lihat resources/views/mobile/vicon.blade.php). */
    fun onViconEvent(type: String, data: JSONObject) {
        when (type) {
            "remote-joined" -> {
                reconnectJob?.cancel()
                _state.update { it.copy(info = null) }
                onConnected(notifyServer = true)
            }
            "remote-video" -> _state.update { it.copy(remoteVideo = data.optBoolean("on")) }
            // "Quit" = lawan bicara menutup; selain itu sinyalnya putus sebentar → tunggu 20 dtk.
            "remote-left" -> if (phase == CallPhase.CONNECTED) {
                if (data.optString("reason") == "Quit") {
                    hangUp("Lawan bicara menutup panggilan")
                } else {
                    _state.update { it.copy(remoteVideo = false, info = "Sinyal lawan bicara terputus, menunggu tersambung kembali…") }
                    reconnectJob?.cancel()
                    reconnectJob = viewModelScope.launch {
                        delay(REMOTE_RECONNECT_S * 1_000L)
                        if (phase == CallPhase.CONNECTED) hangUp("Sambungan lawan bicara terputus")
                    }
                }
            }
            "state" -> when (data.optString("state")) {
                "RECONNECTING" -> _state.update { it.copy(info = "Jaringan Anda terputus, menyambungkan kembali…") }
                "CONNECTED" -> if (_state.value.info?.startsWith("Jaringan") == true) _state.update { it.copy(info = null) }
                "PROXY_3", "PROXY_5" -> _state.update { it.copy(info = "Mencoba jalur cadangan…") }
            }
            "warn" -> _state.update { it.copy(info = data.optString("message").ifBlank { null }) }
            "error" -> {
                val msg = data.optString("message").ifBlank { "Media panggilan gagal dimulai." }
                if (phase == CallPhase.CONNECTING || phase == CallPhase.CONNECTED) hangUp(msg)
                else _state.update { it.copy(info = msg) }
            }
        }
    }

    // ------------------------------------------------------------------ internal

    private fun startPolling() {
        pollJob?.cancel()
        pollJob = viewModelScope.launch {
            var missed = 0
            while (isActive && phase != CallPhase.ENDED) {
                val res = repository.callSignal(uuid)
                if (res is ApiResult.Success) {
                    val call = res.data.call
                    if (call == null) {
                        missed++
                        if (missed >= 10) remoteEnded("Panggilan terputus")
                    } else {
                        missed = 0
                        when (call.normalizedStatus) {
                            "ENDED" -> remoteEnded(if (phase == CallPhase.INCOMING) "Panggilan dibatalkan pemanggil" else "Panggilan selesai")
                            "REJECTED" -> remoteEnded("Panggilan ditolak")
                            "ACCEPTED", "CONNECTING" ->
                                if (!incoming && phase == CallPhase.OUTGOING) onRemoteAccepted()
                            "CONNECTED" -> {
                                if (!incoming && phase == CallPhase.OUTGOING) onRemoteAccepted()
                                if (phase == CallPhase.CONNECTING) onConnected(notifyServer = false)
                            }
                        }
                    }
                }
                delay(1_000)
            }
        }
    }

    private fun onRemoteAccepted() {
        _state.update { it.copy(phase = CallPhase.CONNECTING) }
        startTimeout(CONNECT_TIMEOUT_S) { hangUp("Gagal tersambung. Coba lagi.") }
        viewModelScope.launch { joinRoom() }
    }

    private suspend fun joinRoom() {
        if (joining) return
        joining = true
        when (val res = repository.agoraToken(uuid, myUserId)) {
            is ApiResult.Success -> {
                val d = res.data
                val appId = d.appId.orEmpty()
                val channel = d.channel.orEmpty()
                if (appId.isBlank() || channel.isBlank()) {
                    hangUp("Konfigurasi vicon (Agora) di server belum lengkap.")
                } else {
                    _commands.trySend(ViconCommand.Join(appId, channel, d.token, if (d.uid > 0) d.uid else myUserId.toLong(), _state.value.video))
                }
            }
            is ApiResult.Failure -> hangUp(res.error.message)
        }
    }

    private fun onConnected(notifyServer: Boolean) {
        if (phase == CallPhase.CONNECTED || phase == CallPhase.ENDED) return
        timeoutJob?.cancel()
        _state.update { it.copy(phase = CallPhase.CONNECTED, info = null) }
        durationJob?.cancel()
        durationJob = viewModelScope.launch {
            while (isActive) {
                delay(1_000)
                _state.update { it.copy(seconds = it.seconds + 1) }
            }
        }
        if (notifyServer) viewModelScope.launch { repository.sendCallSignal(uuid, "connected", "CONNECTED") }
    }

    private fun startTimeout(seconds: Int, onTimeout: () -> Unit) {
        timeoutJob?.cancel()
        timeoutJob = viewModelScope.launch {
            delay(seconds * 1_000L)
            if (phase != CallPhase.CONNECTED && phase != CallPhase.ENDED) onTimeout()
        }
    }

    /** Akhiri dan beri tahu server. Permintaan tetap dikirim walau layar sudah ditutup. */
    private fun endWithServer(reason: String, message: String, duration: Int = 0) {
        if (phase == CallPhase.ENDED) return
        finish(message)
        repository.appScopeLaunch { repository.endCall(uuid, reason, duration) }
    }

    private fun remoteEnded(message: String) {
        if (phase == CallPhase.ENDED) return
        finish(message)
    }

    private fun finish(message: String) {
        pollJob?.cancel()
        timeoutJob?.cancel()
        durationJob?.cancel()
        reconnectJob?.cancel()
        _commands.trySend(ViconCommand.Leave)
        _state.update { it.copy(phase = CallPhase.ENDED, endedMessage = message) }
    }

    override fun onCleared() {
        // Layar ditutup paksa (mis. tombol kembali) tanpa menekan tutup panggilan.
        hangUp()
        super.onCleared()
    }

    companion object {
        const val OUTGOING_TIMEOUT_S = 40
        // Termasuk waktu mencoba jalur cadangan (cloud proxy) bila jaringan memblokir UDP.
        const val CONNECT_TIMEOUT_S = 50
        const val REMOTE_RECONNECT_S = 20
    }
}
