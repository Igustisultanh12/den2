package id.my.sisinden.app.ui.auth

import android.annotation.SuppressLint
import android.net.Uri
import android.os.Handler
import android.os.Looper
import android.webkit.JavascriptInterface
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.key
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.ui.Modifier
import androidx.compose.ui.viewinterop.AndroidView
import id.my.sisinden.app.core.data.SessionStore

/** Aksi Turnstile yang divalidasi server (harus sama dengan routes/mobile_api.php). */
enum class TurnstileAction(val value: String) { LOGIN("login"), ACTIVATE("activate") }

/**
 * Widget Cloudflare Turnstile.
 *
 * Cloudflare belum menyediakan SDK Android native, sehingga widget dimuat di
 * WebView dari halaman `/mobile/turnstile` milik server Sinden 2 (origin cocok
 * dengan daftar hostname widget). Token dikirim balik lewat jembatan JavaScript
 * lalu diverifikasi server dengan secret key — aplikasi tidak pernah memegang
 * secret key. Navigasi dibatasi ke domain Sinden 2 dan Cloudflare.
 *
 * @param reloadKey ubah nilainya untuk memuat ulang widget (token sekali pakai).
 */
@SuppressLint("SetJavaScriptEnabled")
@Composable
fun TurnstileWidget(
    action: TurnstileAction,
    reloadKey: Int,
    onToken: (String) -> Unit,
    onError: (String) -> Unit,
    modifier: Modifier = Modifier,
    onInteractive: () -> Unit = {},
) {
    val tokenCb by rememberUpdatedState(onToken)
    val errorCb by rememberUpdatedState(onError)
    val interactiveCb by rememberUpdatedState(onInteractive)
    val base = Uri.parse(SessionStore.DEFAULT_BASE_URL)
    val url = "${SessionStore.DEFAULT_BASE_URL}/mobile/turnstile?action=${action.value}&theme=dark"

    key(reloadKey) {
        AndroidView(
            factory = { ctx ->
                val main = Handler(Looper.getMainLooper())
                WebView(ctx).apply {
                    setBackgroundColor(android.graphics.Color.TRANSPARENT)
                    settings.javaScriptEnabled = true
                    settings.domStorageEnabled = true
                    settings.allowFileAccess = false
                    settings.allowContentAccess = false
                    settings.setSupportMultipleWindows(false)
                    webViewClient = object : WebViewClient() {
                        override fun shouldOverrideUrlLoading(view: WebView, request: WebResourceRequest): Boolean {
                            val host = request.url.host.orEmpty()
                            val allowed = host == base.host || host == "challenges.cloudflare.com"
                            return !allowed
                        }

                        override fun onReceivedError(view: WebView, request: WebResourceRequest, error: WebResourceError) {
                            if (request.isForMainFrame) main.post { errorCb("network") }
                        }
                    }
                    addJavascriptInterface(
                        object {
                            @JavascriptInterface
                            fun onToken(token: String) {
                                main.post { tokenCb(token) }
                            }

                            @JavascriptInterface
                            fun onError(code: String) {
                                main.post { errorCb(code) }
                            }

                            @JavascriptInterface
                            fun onExpired() {
                                main.post { errorCb("expired") }
                            }

                            @JavascriptInterface
                            fun onInteractive() {
                                main.post { interactiveCb() }
                            }
                        },
                        "SisindenTurnstile",
                    )
                    loadUrl(url)
                }
            },
            onRelease = { web ->
                web.stopLoading()
                web.removeJavascriptInterface("SisindenTurnstile")
                web.destroy()
            },
            modifier = modifier,
        )
    }
}
