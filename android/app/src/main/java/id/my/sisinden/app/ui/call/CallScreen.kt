package id.my.sisinden.app.ui.call

import android.Manifest
import android.content.pm.PackageManager
import androidx.activity.compose.BackHandler
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Call
import androidx.compose.material.icons.filled.CallEnd
import androidx.compose.material.icons.filled.Cameraswitch
import androidx.compose.material.icons.filled.Mic
import androidx.compose.material.icons.filled.MicOff
import androidx.compose.material.icons.filled.Videocam
import androidx.compose.material.icons.filled.VideocamOff
import androidx.compose.material.icons.automirrored.filled.VolumeUp
import androidx.compose.material.icons.filled.Hearing
import androidx.compose.material3.FilledIconButton
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButtonDefaults
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.scale
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.core.content.ContextCompat
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import id.my.sisinden.app.core.notification.CommsState
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.data.repository.SindenRepository
import kotlinx.coroutines.delay

private val CallGreen = Color(0xFF16A34A)
private val CallRed = Color(0xFFDC2626)

/**
 * Layar panggilan video/suara Chat Dinas. Tersambung dua arah dengan modul
 * Vicon web (kanal Agora & sinyal yang sama).
 */
@Composable
fun CallScreen(
    repository: SindenRepository,
    threadUuid: String,
    video: Boolean,
    incoming: Boolean,
    peerName: String,
    myUserId: Int,
    onClose: () -> Unit,
) {
    val context = LocalContext.current
    val vm: CallViewModel = viewModel(key = "call-$threadUuid") {
        CallViewModel(repository, threadUuid, video, incoming, myUserId)
    }
    val state by vm.state.collectAsStateWithLifecycle()
    val audio = remember { CallAudio(context) }
    // Tutup layar tepat sekali (selesai otomatis vs tombol kembali).
    val closeOnce = remember {
        var done = false
        { if (!done) { done = true; onClose() } }
    }

    // Izin kamera/mikrofon diminta sebelum media dimulai.
    val needed = remember(video) {
        buildList {
            add(Manifest.permission.RECORD_AUDIO)
            if (video) add(Manifest.permission.CAMERA)
        }.toTypedArray()
    }
    fun granted() = needed.all { ContextCompat.checkSelfPermission(context, it) == PackageManager.PERMISSION_GRANTED }
    val permissionLauncher = rememberLauncherForActivityResult(ActivityResultContracts.RequestMultiplePermissions()) {
        // Tetap lanjut walau ditolak: halaman vicon akan memberi peringatan mikrofon/kamera tidak tersedia.
        if (incoming) vm.accept() else vm.startOutgoing()
    }

    LaunchedEffect(Unit) {
        if (!incoming) {
            if (granted()) vm.startOutgoing() else permissionLauncher.launch(needed)
        }
    }

    DisposableEffect(threadUuid) {
        CommsState.activeCallThread = threadUuid
        // Layar panggilan sudah tampil → hentikan dering notifikasi.
        id.my.sisinden.app.core.notification.CallNotifications.cancel(context)
        onDispose {
            CommsState.activeCallThread = null
            audio.release()
        }
    }

    // Suara & jalur audio mengikuti fase panggilan.
    LaunchedEffect(state.phase) {
        when (state.phase) {
            CallPhase.INCOMING -> audio.startRinging()
            CallPhase.OUTGOING -> { audio.enterCall(state.speakerOn); audio.startRingback() }
            CallPhase.CONNECTING -> { audio.stopSounds(); audio.enterCall(state.speakerOn) }
            CallPhase.CONNECTED -> audio.stopSounds()
            CallPhase.ENDED -> {
                audio.stopSounds()
                audio.beepEnded()
                delay(1_400)
                closeOnce()
            }
            CallPhase.PREPARING -> Unit
        }
    }
    LaunchedEffect(state.speakerOn) {
        if (state.phase != CallPhase.INCOMING && state.phase != CallPhase.PREPARING) audio.setSpeaker(state.speakerOn)
    }

    BackHandler {
        if (state.phase == CallPhase.ENDED) closeOnce() else vm.hangUp()
    }

    Box(Modifier.fillMaxSize().background(Color(0xFF020617))) {
        ViconWebView(
            commands = vm.commands,
            onEvent = vm::onViconEvent,
            modifier = Modifier.fillMaxSize(),
        )

        // Setelah tersambung, video tidak lagi ditutupi kartu: halaman vicon sendiri menampilkan
        // keterangan bila video lawan bicara belum masuk. Sebelumnya kartu baru terbuka setelah
        // laporan "video tampil" — bila pemutaran di WebView gagal diam-diam, video tak pernah terlihat.
        val showPeerCard = !state.video || state.phase != CallPhase.CONNECTED
        if (showPeerCard) {
            Box(
                Modifier
                    .fillMaxSize()
                    .background(Brush.verticalGradient(listOf(Color(0xFF0B2A55), Color(0xFF020617)))),
            )
            PeerCard(
                name = peerName,
                status = statusText(state),
                ringing = state.phase == CallPhase.INCOMING || state.phase == CallPhase.OUTGOING,
                modifier = Modifier.align(Alignment.Center),
            )
        } else {
            Column(
                Modifier.align(Alignment.TopStart).statusBarsPadding().padding(Spacing.lg),
            ) {
                Text(peerName, color = Color.White, style = MaterialTheme.typography.titleMedium)
                Text(statusText(state), color = Color.White.copy(alpha = 0.85f), style = MaterialTheme.typography.bodySmall)
            }
        }

        state.info?.let {
            Text(
                it,
                color = Color.White,
                style = MaterialTheme.typography.bodySmall,
                textAlign = TextAlign.Center,
                modifier = Modifier
                    .align(Alignment.BottomCenter)
                    .navigationBarsPadding()
                    .padding(bottom = 140.dp, start = Spacing.xl, end = Spacing.xl)
                    .clip(MaterialTheme.shapes.small)
                    .background(Color.Black.copy(alpha = 0.55f))
                    .padding(horizontal = Spacing.md, vertical = Spacing.xs),
            )
        }

        Box(
            Modifier
                .align(Alignment.BottomCenter)
                .fillMaxWidth()
                .navigationBarsPadding()
                .padding(bottom = Spacing.xxl),
            contentAlignment = Alignment.Center,
        ) {
            when (state.phase) {
                CallPhase.INCOMING -> Row(horizontalArrangement = Arrangement.spacedBy(72.dp)) {
                    RoundAction(Icons.Filled.CallEnd, "Tolak", CallRed) { vm.reject() }
                    RoundAction(if (state.video) Icons.Filled.Videocam else Icons.Filled.Call, "Terima", CallGreen) {
                        if (granted()) vm.accept() else permissionLauncher.launch(needed)
                    }
                }
                CallPhase.ENDED -> Unit
                else -> Row(horizontalArrangement = Arrangement.spacedBy(Spacing.md), verticalAlignment = Alignment.CenterVertically) {
                    ToggleAction(if (state.micOn) Icons.Filled.Mic else Icons.Filled.MicOff, if (state.micOn) "Bisukan" else "Aktifkan mikrofon", state.micOn) { vm.toggleMic() }
                    if (state.video) {
                        ToggleAction(if (state.camOn) Icons.Filled.Videocam else Icons.Filled.VideocamOff, if (state.camOn) "Matikan kamera" else "Nyalakan kamera", state.camOn) { vm.toggleCam() }
                        ToggleAction(Icons.Filled.Cameraswitch, "Ganti kamera", true) { vm.flipCamera() }
                    }
                    ToggleAction(
                        if (state.speakerOn) Icons.AutoMirrored.Filled.VolumeUp else Icons.Filled.Hearing,
                        if (state.speakerOn) "Pengeras suara" else "Earpiece",
                        state.speakerOn,
                    ) { vm.setSpeaker(!state.speakerOn) }
                    RoundAction(Icons.Filled.CallEnd, "Tutup", CallRed, size = 64) { vm.hangUp() }
                }
            }
        }
    }
}

private fun statusText(s: CallUiState): String = when (s.phase) {
    CallPhase.PREPARING -> "Menyiapkan panggilan…"
    CallPhase.OUTGOING -> if (s.video) "Memanggil (video)…" else "Memanggil…"
    CallPhase.INCOMING -> if (s.video) "Panggilan video masuk" else "Panggilan suara masuk"
    CallPhase.CONNECTING -> "Menyambungkan…"
    CallPhase.CONNECTED -> "%02d:%02d".format(s.seconds / 60, s.seconds % 60)
    CallPhase.ENDED -> s.endedMessage ?: "Panggilan selesai"
}

@Composable
private fun PeerCard(name: String, status: String, ringing: Boolean, modifier: Modifier = Modifier) {
    val pulse = rememberInfiniteTransition(label = "pulse")
    val scale by pulse.animateFloat(1f, if (ringing) 1.18f else 1f, infiniteRepeatable(tween(900), RepeatMode.Reverse), label = "scale")
    val glow by pulse.animateFloat(0.35f, if (ringing) 0.05f else 0.35f, infiniteRepeatable(tween(900), RepeatMode.Reverse), label = "glow")
    Column(modifier.padding(horizontal = Spacing.xl), horizontalAlignment = Alignment.CenterHorizontally) {
        Box(contentAlignment = Alignment.Center) {
            Box(Modifier.size(150.dp).scale(scale).alpha(glow).clip(CircleShape).background(Color(0xFF2B93FE)))
            Box(
                Modifier.size(112.dp).clip(CircleShape).background(Brush.linearGradient(listOf(Color(0xFF5AB0FF), Color(0xFF1F7FE6)))),
                contentAlignment = Alignment.Center,
            ) {
                Text(initialsOf(name), color = Color.White, style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold)
            }
        }
        Spacer(Modifier.height(Spacing.xl))
        Text(name, color = Color.White, style = MaterialTheme.typography.titleLarge, textAlign = TextAlign.Center)
        Spacer(Modifier.height(Spacing.xs))
        Text(status, color = Color.White.copy(alpha = 0.8f), style = MaterialTheme.typography.bodyMedium, textAlign = TextAlign.Center)
    }
}

private fun initialsOf(name: String): String =
    name.split(" ").filter { it.isNotBlank() && it.first().isLetter() }.take(2).joinToString("") { it.first().uppercase() }.ifEmpty { "SD" }

@Composable
private fun RoundAction(icon: ImageVector, label: String, color: Color, size: Int = 72, onClick: () -> Unit) {
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        FilledIconButton(
            onClick = onClick,
            modifier = Modifier.size(size.dp),
            colors = IconButtonDefaults.filledIconButtonColors(containerColor = color, contentColor = Color.White),
        ) { Icon(icon, contentDescription = label, modifier = Modifier.size((size / 2.4).dp)) }
        Spacer(Modifier.height(Spacing.xs))
        Text(label, color = Color.White, style = MaterialTheme.typography.labelMedium)
    }
}

@Composable
private fun ToggleAction(icon: ImageVector, label: String, active: Boolean, onClick: () -> Unit) {
    FilledIconButton(
        onClick = onClick,
        modifier = Modifier.size(52.dp),
        colors = IconButtonDefaults.filledIconButtonColors(
            containerColor = if (active) Color.White.copy(alpha = 0.18f) else Color.White,
            contentColor = if (active) Color.White else Color(0xFF0F172A),
        ),
    ) { Icon(icon, contentDescription = label) }
}
