package id.my.sisinden.app.ui.attendance

import android.Manifest
import android.content.pm.PackageManager
import android.graphics.Bitmap
import android.graphics.Matrix
import android.util.Base64
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.annotation.OptIn
import androidx.camera.core.CameraSelector
import androidx.camera.core.ExperimentalGetImage
import androidx.camera.core.ImageAnalysis
import androidx.camera.core.ImageCapture
import androidx.camera.core.ImageCaptureException
import androidx.camera.core.ImageProxy
import androidx.camera.core.Preview
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.camera.view.PreviewView
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.safeDrawingPadding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Close
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import androidx.core.content.ContextCompat
import androidx.lifecycle.compose.LocalLifecycleOwner
import com.google.mlkit.vision.common.InputImage
import com.google.mlkit.vision.face.FaceDetection
import com.google.mlkit.vision.face.FaceDetectorOptions
import id.my.sisinden.app.core.ui.components.PrimaryButton
import id.my.sisinden.app.core.ui.theme.Spacing
import kotlinx.coroutines.delay
import java.io.ByteArrayOutputStream
import java.util.concurrent.Executors
import kotlin.math.roundToInt

/** Ambang senyum & jumlah frame berturut-turut — sama dengan presensi web (Dashboard.vue). */
const val SMILE_THRESHOLD = 50
private const val SMILE_FRAMES_REQUIRED = 2
private const val COUNTDOWN_SECONDS = 3
private const val MAX_PHOTO_EDGE = 720

/** Hasil foto verifikasi senyum yang siap dikirim ke server. */
class SmilePhoto(val dataUrl: String, val smileScore: Int, val preview: Bitmap)

private enum class FaceHint(val text: String) {
    SEARCHING("Arahkan wajah ke kamera"),
    NO_FACE("Wajah tidak terdeteksi"),
    MULTIPLE("Pastikan hanya satu wajah di kamera"),
    NOT_SMILING("Tersenyumlah untuk verifikasi"),
    SMILING("Senyum terdeteksi, tahan…"),
}

/**
 * Layar penuh kamera depan. Foto diambil otomatis setelah senyum ≥ [SMILE_THRESHOLD]%
 * bertahan [SMILE_FRAMES_REQUIRED] frame dan hitung mundur selesai.
 */
@Composable
fun SmileCameraDialog(onCaptured: (SmilePhoto) -> Unit, onDismiss: () -> Unit) {
    Dialog(
        onDismissRequest = onDismiss,
        properties = DialogProperties(usePlatformDefaultWidth = false, decorFitsSystemWindows = false),
    ) {
        val context = LocalContext.current
        var hasCamera by remember {
            mutableStateOf(ContextCompat.checkSelfPermission(context, Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED)
        }
        val permission = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) { hasCamera = it }
        LaunchedEffect(Unit) { if (!hasCamera) permission.launch(Manifest.permission.CAMERA) }

        Box(Modifier.fillMaxSize().background(Color.Black)) {
            if (hasCamera) {
                SmileCameraContent(onCaptured)
            } else {
                Column(
                    Modifier.align(Alignment.Center).padding(Spacing.screen),
                    horizontalAlignment = Alignment.CenterHorizontally,
                ) {
                    Text(
                        "Izin kamera diperlukan untuk foto verifikasi senyum.",
                        color = Color.White,
                        textAlign = TextAlign.Center,
                    )
                    Spacer(Modifier.height(Spacing.lg))
                    PrimaryButton("Izinkan kamera", onClick = { permission.launch(Manifest.permission.CAMERA) })
                }
            }
            IconButton(onClick = onDismiss, modifier = Modifier.safeDrawingPadding().padding(Spacing.sm).align(Alignment.TopStart)) {
                Icon(Icons.Outlined.Close, contentDescription = "Tutup kamera", tint = Color.White)
            }
        }
    }
}

@OptIn(ExperimentalGetImage::class)
@Composable
private fun SmileCameraContent(onCaptured: (SmilePhoto) -> Unit) {
    val context = LocalContext.current
    val lifecycleOwner = LocalLifecycleOwner.current

    var smileScore by remember { mutableIntStateOf(0) }
    var hint by remember { mutableStateOf(FaceHint.SEARCHING) }
    var consecutiveSmiles by remember { mutableIntStateOf(0) }
    var countdown by remember { mutableStateOf<Int?>(null) }
    var triggerScore by remember { mutableIntStateOf(0) }
    var capturing by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }

    val imageCapture = remember {
        ImageCapture.Builder().setCaptureMode(ImageCapture.CAPTURE_MODE_MINIMIZE_LATENCY).build()
    }
    val analysisExecutor = remember { Executors.newSingleThreadExecutor() }
    val detector = remember {
        FaceDetection.getClient(
            FaceDetectorOptions.Builder()
                .setPerformanceMode(FaceDetectorOptions.PERFORMANCE_MODE_FAST)
                .setClassificationMode(FaceDetectorOptions.CLASSIFICATION_MODE_ALL)
                .setMinFaceSize(0.2f)
                .build()
        )
    }
    val previewView = remember {
        PreviewView(context).apply {
            scaleType = PreviewView.ScaleType.FILL_CENTER
            implementationMode = PreviewView.ImplementationMode.COMPATIBLE
        }
    }

    DisposableEffect(lifecycleOwner) {
        val providerFuture = ProcessCameraProvider.getInstance(context)
        providerFuture.addListener({
            val provider = providerFuture.get()
            val preview = Preview.Builder().build().also { it.setSurfaceProvider(previewView.surfaceProvider) }
            val analysis = ImageAnalysis.Builder()
                .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
                .build()
            analysis.setAnalyzer(analysisExecutor) { proxy: ImageProxy ->
                val media = proxy.image
                if (media == null) {
                    proxy.close()
                    return@setAnalyzer
                }
                detector.process(InputImage.fromMediaImage(media, proxy.imageInfo.rotationDegrees))
                    .addOnSuccessListener { faces ->
                        when {
                            faces.isEmpty() -> { hint = FaceHint.NO_FACE; smileScore = 0; consecutiveSmiles = 0 }
                            faces.size > 1 -> { hint = FaceHint.MULTIPLE; smileScore = 0; consecutiveSmiles = 0 }
                            else -> {
                                smileScore = ((faces[0].smilingProbability ?: 0f) * 100).roundToInt().coerceIn(0, 100)
                                if (smileScore >= SMILE_THRESHOLD) {
                                    hint = FaceHint.SMILING
                                    consecutiveSmiles++
                                    if (consecutiveSmiles >= SMILE_FRAMES_REQUIRED && countdown == null && !capturing) {
                                        triggerScore = smileScore
                                        countdown = COUNTDOWN_SECONDS
                                    }
                                } else {
                                    hint = FaceHint.NOT_SMILING
                                    consecutiveSmiles = 0
                                }
                            }
                        }
                    }
                    .addOnCompleteListener { proxy.close() }
            }
            runCatching {
                provider.unbindAll()
                provider.bindToLifecycle(lifecycleOwner, CameraSelector.DEFAULT_FRONT_CAMERA, preview, analysis, imageCapture)
            }.onFailure { error = "Kamera depan tidak dapat dibuka: ${it.message}" }
        }, ContextCompat.getMainExecutor(context))

        onDispose {
            runCatching { providerFuture.get().unbindAll() }
            detector.close()
            analysisExecutor.shutdown()
        }
    }

    LaunchedEffect(countdown) {
        val c = countdown ?: return@LaunchedEffect
        if (c > 0) {
            delay(1000)
            countdown = c - 1
        } else if (!capturing) {
            capturing = true
            imageCapture.takePicture(ContextCompat.getMainExecutor(context), object : ImageCapture.OnImageCapturedCallback() {
                override fun onCaptureSuccess(image: ImageProxy) {
                    val photo = runCatching { image.toSmilePhoto(triggerScore) }
                    image.close()
                    photo.onSuccess(onCaptured).onFailure {
                        error = "Gagal memproses foto. Silakan coba lagi."
                        capturing = false
                        countdown = null
                        consecutiveSmiles = 0
                    }
                }

                override fun onError(exception: ImageCaptureException) {
                    error = "Gagal mengambil foto: ${exception.message}"
                    capturing = false
                    countdown = null
                    consecutiveSmiles = 0
                }
            })
        }
    }

    Box(Modifier.fillMaxSize()) {
        AndroidView(factory = { previewView }, modifier = Modifier.fillMaxSize())

        // Bingkai panduan wajah
        Box(
            Modifier
                .align(Alignment.Center)
                .size(260.dp)
                .border(4.dp, if (smileScore >= SMILE_THRESHOLD) Color(0xFF10B981) else Color.White.copy(alpha = 0.7f), CircleShape)
        )

        countdown?.takeIf { it > 0 }?.let {
            Text(
                "$it",
                color = Color.White,
                fontSize = 96.sp,
                fontWeight = FontWeight.Black,
                modifier = Modifier.align(Alignment.Center),
            )
        }

        Column(
            Modifier
                .align(Alignment.BottomCenter)
                .fillMaxWidth()
                .background(Color.Black.copy(alpha = 0.55f))
                .safeDrawingPadding()
                .padding(Spacing.screen),
            verticalArrangement = Arrangement.spacedBy(Spacing.sm),
        ) {
            Text(
                error ?: when {
                    capturing -> "Mengambil foto…"
                    countdown != null -> "Tahan senyum Anda…"
                    else -> hint.text
                },
                color = if (error != null) Color(0xFFFCA5A5) else Color.White,
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Bold,
            )
            Text(
                "Skor senyum $smileScore% (minimal $SMILE_THRESHOLD%)",
                color = Color.White.copy(alpha = 0.85f),
                style = MaterialTheme.typography.bodyMedium,
            )
            LinearProgressIndicator(
                progress = { smileScore / 100f },
                modifier = Modifier.fillMaxWidth().height(8.dp),
                color = if (smileScore >= SMILE_THRESHOLD) Color(0xFF10B981) else Color(0xFFF59E0B),
                trackColor = Color.White.copy(alpha = 0.25f),
            )
        }
    }
}

/** Putar sesuai sensor, cerminkan (selfie), perkecil, lalu JPEG base64 data URL. */
private fun ImageProxy.toSmilePhoto(score: Int): SmilePhoto {
    val raw = toBitmap()
    val matrix = Matrix().apply {
        postRotate(imageInfo.rotationDegrees.toFloat())
        postScale(-1f, 1f)
    }
    val oriented = Bitmap.createBitmap(raw, 0, 0, raw.width, raw.height, matrix, true)
    val scale = MAX_PHOTO_EDGE.toFloat() / maxOf(oriented.width, oriented.height)
    val scaled = if (scale < 1f) {
        Bitmap.createScaledBitmap(oriented, (oriented.width * scale).roundToInt(), (oriented.height * scale).roundToInt(), true)
    } else oriented
    val bytes = ByteArrayOutputStream().use { out ->
        scaled.compress(Bitmap.CompressFormat.JPEG, 82, out)
        out.toByteArray()
    }
    return SmilePhoto(
        dataUrl = "data:image/jpeg;base64," + Base64.encodeToString(bytes, Base64.NO_WRAP),
        smileScore = score,
        preview = scaled,
    )
}
