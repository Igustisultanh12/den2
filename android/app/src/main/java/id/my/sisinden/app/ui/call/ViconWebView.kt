package id.my.sisinden.app.ui.call

import android.annotation.SuppressLint
import android.net.Uri
import android.os.Handler
import android.os.Looper
import android.webkit.JavascriptInterface
import android.webkit.PermissionRequest
import android.webkit.WebChromeClient
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.ui.Modifier
import androidx.compose.ui.viewinterop.AndroidView
import id.my.sisinden.app.core.data.SessionStore
import kotlinx.coroutines.flow.Flow
import org.json.JSONObject

/** Perintah media ke halaman vicon (Agora Web SDK). */
sealed interface ViconCommand {
    data class Join(val appId: String, val channel: String, val token: String?, val uid: Long, val video: Boolean) : ViconCommand
    data object Leave : ViconCommand
    data class Mic(val on: Boolean) : ViconCommand
    data class Cam(val on: Boolean) : ViconCommand
    data object Flip : ViconCommand
}

/** Jembatan WebView: antre perintah sampai halaman siap ("ready"). */
private class ViconBridge {
    var webView: WebView? = null
    var ready = false
    private val pending = ArrayList<String>()

    fun send(command: ViconCommand) {
        val js = when (command) {
            is ViconCommand.Join -> "viconJoin(${JSONObject.quote(command.appId)}, ${JSONObject.quote(command.channel)}, " +
                "${command.token?.let { JSONObject.quote(it) } ?: "null"}, ${command.uid}, ${command.video})"
            ViconCommand.Leave -> "viconLeave()"
            is ViconCommand.Mic -> "viconMic(${command.on})"
            is ViconCommand.Cam -> "viconCam(${command.on})"
            ViconCommand.Flip -> "viconFlip()"
        }
        val view = webView
        if (ready && view != null) view.evaluateJavascript(js, null) else pending += js
    }

    fun onReady() {
        ready = true
        val view = webView ?: return
        pending.forEach { view.evaluateJavascript(it, null) }
        pending.clear()
    }
}

/**
 * Media panggilan memakai halaman `/mobile/vicon` milik server — Agora Web
 * SDK dan kanal yang sama dengan modul Vicon web, sehingga panggilan antara
 * aplikasi dan web saling tersambung. Sinyal panggilan tetap ditangani
 * aplikasi lewat API; halaman ini hanya mengirim/menerima audio-video.
 *
 * Izin kamera/mikrofon hanya diberikan ke halaman dari host Sinden 2.
 */
@SuppressLint("SetJavaScriptEnabled")
@Composable
fun ViconWebView(
    commands: Flow<ViconCommand>,
    onEvent: (String, JSONObject) -> Unit,
    modifier: Modifier = Modifier,
) {
    val eventCb by rememberUpdatedState(onEvent)
    val bridge = remember { ViconBridge() }
    val baseHost = remember { Uri.parse(SessionStore.DEFAULT_BASE_URL).host }
    val main = remember { Handler(Looper.getMainLooper()) }

    LaunchedEffect(commands) {
        commands.collect { bridge.send(it) }
    }

    AndroidView(
        modifier = modifier,
        factory = { ctx ->
            WebView(ctx).apply {
                setBackgroundColor(android.graphics.Color.BLACK)
                keepScreenOn = true
                settings.javaScriptEnabled = true
                settings.domStorageEnabled = true
                settings.mediaPlaybackRequiresUserGesture = false
                settings.allowFileAccess = false
                settings.allowContentAccess = false
                settings.setSupportMultipleWindows(false)
                webViewClient = object : WebViewClient() {
                    override fun shouldOverrideUrlLoading(view: WebView, request: WebResourceRequest): Boolean =
                        request.url.host != baseHost

                    override fun onReceivedError(view: WebView, request: WebResourceRequest, error: WebResourceError) {
                        if (request.isForMainFrame) {
                            main.post { eventCb("error", JSONObject().put("message", "Halaman vicon tidak dapat dimuat. Periksa koneksi internet.")) }
                        }
                    }
                }
                webChromeClient = object : WebChromeClient() {
                    override fun onPermissionRequest(request: PermissionRequest) {
                        main.post {
                            val allowed = setOf(PermissionRequest.RESOURCE_AUDIO_CAPTURE, PermissionRequest.RESOURCE_VIDEO_CAPTURE)
                            val wanted = request.resources.filter { it in allowed }.toTypedArray()
                            if (request.origin.host == baseHost && wanted.isNotEmpty()) request.grant(wanted) else request.deny()
                        }
                    }
                }
                addJavascriptInterface(
                    object {
                        @JavascriptInterface
                        fun onEvent(type: String, json: String) {
                            main.post {
                                if (type == "ready") bridge.onReady()
                                val data = runCatching { JSONObject(json) }.getOrElse { JSONObject() }
                                eventCb(type, data)
                            }
                        }
                    },
                    "SisindenVicon",
                )
                bridge.webView = this
                loadUrl("${SessionStore.DEFAULT_BASE_URL}/mobile/vicon")
            }
        },
    )

    DisposableEffect(Unit) {
        onDispose {
            val view = bridge.webView
            bridge.webView = null
            if (view != null) {
                // Lepas kamera/mikrofon lebih dulu, lalu hancurkan WebView.
                view.evaluateJavascript("viconLeave()", null)
                main.postDelayed({
                    view.stopLoading()
                    view.loadUrl("about:blank")
                    view.removeJavascriptInterface("SisindenVicon")
                    view.destroy()
                }, 400)
            }
        }
    }
}
