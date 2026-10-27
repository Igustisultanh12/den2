package id.my.sisinden.app.ui.splash

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import id.my.sisinden.app.ui.common.BrandBackdrop
import id.my.sisinden.app.ui.common.FloatingLogo
import id.my.sisinden.app.ui.common.Slate300

@Composable
fun SplashScreen() {
    val scale = remember { Animatable(0.9f) }
    val alpha = remember { Animatable(0f) }
    LaunchedEffect(Unit) {
        alpha.animateTo(1f, tween(450))
    }
    LaunchedEffect(Unit) {
        scale.animateTo(1f, tween(600, easing = FastOutSlowInEasing))
    }

    BrandBackdrop(backgroundUrl = null) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            modifier = Modifier
                .align(Alignment.Center)
                .graphicsLayer {
                    scaleX = scale.value
                    scaleY = scale.value
                    this.alpha = alpha.value
                },
        ) {
            FloatingLogo(size = 120.dp)
            Text("SINDEN 2", color = Color.White, style = MaterialTheme.typography.headlineMedium, letterSpacing = 3.sp)
            Spacer(Modifier.height(4.dp))
            Text("Denintel Koarmada 2", color = Slate300, style = MaterialTheme.typography.bodyMedium)
        }
        Column(
            Modifier.align(Alignment.BottomCenter).navigationBarsPadding().padding(bottom = 48.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            LinearProgressIndicator(
                modifier = Modifier.width(120.dp).clip(RoundedCornerShape(50)),
                color = Color(0xFFFB923C),
                trackColor = Color.White.copy(alpha = 0.15f),
            )
            Text("Memuat…", color = Slate300, style = MaterialTheme.typography.bodySmall)
        }
    }
}
