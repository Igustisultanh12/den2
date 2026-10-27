package id.my.sisinden.app.ui.auth

import androidx.activity.compose.BackHandler
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.animation.expandVertically
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.shrinkVertically
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.outlined.Shield
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.semantics.LiveRegionMode
import androidx.compose.ui.semantics.liveRegion
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import id.my.sisinden.app.core.ui.theme.Spacing
import id.my.sisinden.app.ui.common.Slate300
import id.my.sisinden.app.ui.common.Slate400
import id.my.sisinden.app.ui.common.Slate900
import id.my.sisinden.app.ui.common.Slate950

/** Tahap proses masuk/aktivasi yang ditampilkan ke pengguna. */
enum class AuthStep { IDLE, SECURITY, SESSION, FINISHING }

private val Done = Color(0xFF34D399)
private val Active = Color(0xFF60A5FA)

/**
 * Lapisan animasi "pengecekan keamanan" saat menekan Masuk / Aktifkan:
 *  1. Memeriksa keamanan sistem — Cloudflare Turnstile (widget tampil bila
 *     Cloudflare meminta interaksi).
 *  2. Memeriksa sesi login / token aktivasi ke server.
 *  3. Masuk ke beranda / mengaktifkan akun.
 */
@Composable
fun SecurityCheckOverlay(
    step: AuthStep,
    labels: List<String>,
    action: TurnstileAction,
    widgetKey: Int,
    showWidget: Boolean,
    onToken: (String) -> Unit,
    onWidgetError: (String) -> Unit,
    onCancel: () -> Unit,
) {
    AnimatedVisibility(visible = step != AuthStep.IDLE, enter = fadeIn(tween(200)), exit = fadeOut(tween(200))) {
        BackHandler(enabled = step == AuthStep.SECURITY, onBack = onCancel)
        Box(
            Modifier
                .fillMaxSize()
                .background(Slate950.copy(alpha = 0.82f))
                // Menyerap sentuhan agar form di belakang tidak tersentuh.
                .clickable(interactionSource = remember { MutableInteractionSource() }, indication = null) {},
            contentAlignment = Alignment.Center,
        ) {
            Column(
                Modifier
                    .padding(Spacing.xxl)
                    .widthIn(max = 420.dp)
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(28.dp))
                    .background(Slate900.copy(alpha = 0.96f))
                    .border(1.dp, Color.White.copy(alpha = 0.12f), RoundedCornerShape(28.dp))
                    .padding(Spacing.xxl),
                horizontalAlignment = Alignment.CenterHorizontally,
            ) {
                PulsingShield(done = step == AuthStep.FINISHING)
                Spacer(Modifier.height(Spacing.lg))
                Text(
                    when (step) {
                        AuthStep.FINISHING -> "Verifikasi berhasil"
                        else -> "Mohon tunggu sebentar"
                    },
                    style = MaterialTheme.typography.titleLarge,
                    color = Color.White,
                )
                Spacer(Modifier.height(Spacing.xl))

                val current = when (step) {
                    AuthStep.SECURITY -> 0
                    AuthStep.SESSION -> 1
                    AuthStep.FINISHING -> 2
                    AuthStep.IDLE -> -1
                }
                Column(
                    Modifier.fillMaxWidth().semantics { liveRegion = LiveRegionMode.Polite },
                    verticalArrangement = Arrangement.spacedBy(Spacing.md),
                ) {
                    labels.forEachIndexed { i, label ->
                        StepRow(label = label, state = when {
                            i < current -> StepState.DONE
                            i == current -> if (step == AuthStep.FINISHING) StepState.DONE else StepState.ACTIVE
                            else -> StepState.PENDING
                        })
                        if (i == 0) {
                            AnimatedVisibility(
                                visible = showWidget && step == AuthStep.SECURITY,
                                enter = expandVertically(),
                                exit = shrinkVertically(),
                            ) {
                                Column(Modifier.fillMaxWidth().padding(top = Spacing.sm)) {
                                    TurnstileWidget(
                                        action = action,
                                        reloadKey = widgetKey,
                                        onToken = onToken,
                                        onError = onWidgetError,
                                        modifier = Modifier.fillMaxWidth().height(72.dp),
                                    )
                                    Text(
                                        "Dilindungi Cloudflare Turnstile",
                                        style = MaterialTheme.typography.labelSmall,
                                        color = Slate400,
                                        modifier = Modifier.align(Alignment.CenterHorizontally),
                                    )
                                }
                            }
                        }
                    }
                }

                if (step == AuthStep.SECURITY) {
                    Spacer(Modifier.height(Spacing.lg))
                    TextButton(onClick = onCancel) { Text("Batal", color = Slate300, style = MaterialTheme.typography.labelLarge) }
                }
            }
        }
    }
}

private enum class StepState { PENDING, ACTIVE, DONE }

@Composable
private fun StepRow(label: String, state: StepState) {
    val color by animateColorAsState(
        when (state) {
            StepState.DONE -> Done
            StepState.ACTIVE -> Active
            StepState.PENDING -> Color.White.copy(alpha = 0.25f)
        },
        label = "stepColor",
    )
    Row(verticalAlignment = Alignment.CenterVertically) {
        Box(
            Modifier.size(30.dp).clip(CircleShape).border(2.dp, color, CircleShape),
            contentAlignment = Alignment.Center,
        ) {
            when (state) {
                StepState.ACTIVE -> CircularProgressIndicator(Modifier.size(18.dp), strokeWidth = 2.dp, color = Active)
                StepState.DONE -> Icon(Icons.Filled.Check, contentDescription = null, tint = Done, modifier = Modifier.size(18.dp))
                StepState.PENDING -> Unit
            }
        }
        Spacer(Modifier.width(Spacing.md))
        Text(
            label,
            style = MaterialTheme.typography.bodyLarge,
            fontWeight = if (state == StepState.ACTIVE) FontWeight.Bold else FontWeight.Medium,
            color = if (state == StepState.PENDING) Slate400 else Color.White,
        )
    }
}

/** Ikon perisai dengan denyut cahaya; berubah hijau saat selesai. */
@Composable
private fun PulsingShield(done: Boolean) {
    val t = rememberInfiniteTransition(label = "shield")
    val pulse by t.animateFloat(
        initialValue = 0.85f,
        targetValue = 1.15f,
        animationSpec = infiniteRepeatable(tween(900, easing = FastOutSlowInEasing), RepeatMode.Reverse),
        label = "pulse",
    )
    val tint by animateColorAsState(if (done) Done else Active, label = "shieldTint")
    Box(
        Modifier
            .size(88.dp)
            .drawBehind {
                drawCircle(
                    Brush.radialGradient(listOf(tint.copy(alpha = 0.35f), Color.Transparent)),
                    radius = size.minDimension / 2f * pulse,
                )
            },
        contentAlignment = Alignment.Center,
    ) {
        Icon(
            if (done) Icons.Filled.Check else Icons.Outlined.Shield,
            contentDescription = null,
            tint = tint,
            modifier = Modifier.size(44.dp).graphicsLayer {
                val s = if (done) 1f else 0.95f + (pulse - 0.85f) * 0.2f
                scaleX = s; scaleY = s
            },
        )
    }
}
