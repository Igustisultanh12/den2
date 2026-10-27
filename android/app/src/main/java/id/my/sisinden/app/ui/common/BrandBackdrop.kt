package id.my.sisinden.app.ui.common

import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxScope
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.size
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import coil.compose.AsyncImage
import id.my.sisinden.app.R

/** Warna latar login web Sinden 2 (Tailwind slate-950 / slate-900). */
val Slate950 = Color(0xFF020617)
val Slate900 = Color(0xFF0F172A)
val Slate300 = Color(0xFFCBD5E1)
val Slate400 = Color(0xFF94A3B8)

/**
 * Latar seperti halaman login web Sinden 2: foto latar yang diatur Admin
 * (`login_background`) ditutup lapisan gelap slate-950/80. Bila belum ada
 * foto, dipakai gradien malam dengan cahaya biru/oranye lembut.
 */
@Composable
fun BrandBackdrop(
    backgroundUrl: String?,
    modifier: Modifier = Modifier,
    content: @Composable BoxScope.() -> Unit,
) {
    Box(modifier.fillMaxSize().background(Slate950)) {
        if (!backgroundUrl.isNullOrBlank()) {
            AsyncImage(
                model = backgroundUrl,
                contentDescription = null,
                contentScale = ContentScale.Crop,
                modifier = Modifier.fillMaxSize(),
            )
        } else {
            Box(
                Modifier
                    .fillMaxSize()
                    .background(Brush.verticalGradient(listOf(Color(0xFF0B1E42), Slate950)))
                    .drawBehind {
                        val r = size.maxDimension * 0.5f
                        drawCircle(
                            Brush.radialGradient(listOf(Color(0xFF2B93FE).copy(alpha = 0.35f), Color.Transparent), center = Offset(size.width * 0.85f, size.height * 0.12f), radius = r),
                            radius = r,
                            center = Offset(size.width * 0.85f, size.height * 0.12f),
                        )
                        drawCircle(
                            Brush.radialGradient(listOf(Color(0xFFF97316).copy(alpha = 0.18f), Color.Transparent), center = Offset(size.width * 0.1f, size.height * 0.9f), radius = r * 0.9f),
                            radius = r * 0.9f,
                            center = Offset(size.width * 0.1f, size.height * 0.9f),
                        )
                    },
            )
        }
        // Lapisan gelap seperti web (bg-slate-950/80) agar teks selalu terbaca.
        Box(Modifier.fillMaxSize().background(Slate950.copy(alpha = if (backgroundUrl.isNullOrBlank()) 0.35f else 0.80f)))
        content()
    }
}

/**
 * Logo Sinden 2 yang "mengambang" halus (naik-turun 10dp, 5 detik/siklus —
 * sama dengan animasi `float-logo` di web) dengan cahaya lembut berdenyut.
 */
@Composable
fun FloatingLogo(modifier: Modifier = Modifier, size: Dp = 132.dp) {
    val transition = rememberInfiniteTransition(label = "floatingLogo")
    val lift by transition.animateFloat(
        initialValue = 0f,
        targetValue = 1f,
        animationSpec = infiniteRepeatable(tween(2500, easing = FastOutSlowInEasing), RepeatMode.Reverse),
        label = "lift",
    )
    val glow by transition.animateFloat(
        initialValue = 0.25f,
        targetValue = 0.5f,
        animationSpec = infiniteRepeatable(tween(2500, easing = FastOutSlowInEasing), RepeatMode.Reverse),
        label = "glow",
    )
    val liftPx = with(LocalDensity.current) { 10.dp.toPx() }
    Box(
        modifier
            .size(size * 1.35f)
            .drawBehind {
                drawCircle(
                    Brush.radialGradient(listOf(Color(0xFF4BA3FE).copy(alpha = glow), Color.Transparent)),
                    radius = this.size.minDimension / 2f,
                )
            },
        contentAlignment = Alignment.Center,
    ) {
        Image(
            painterResource(R.drawable.ic_sinden_mark),
            contentDescription = "Logo Sinden 2",
            contentScale = ContentScale.Fit,
            modifier = Modifier
                .size(size)
                .graphicsLayer { translationY = -liftPx * lift },
        )
    }
}

/** Animasi kartu mengambang halus (6dp, 6 detik/siklus) seperti `float-card` di web. */
@Composable
fun rememberCardFloat(): Float {
    val transition = rememberInfiniteTransition(label = "floatingCard")
    val v by transition.animateFloat(
        initialValue = 0f,
        targetValue = 1f,
        animationSpec = infiniteRepeatable(tween(3000, easing = FastOutSlowInEasing), RepeatMode.Reverse),
        label = "cardLift",
    )
    return v
}
