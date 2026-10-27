# Aturan Proguard untuk Retrofit dan Gson
-keepattributes Signature
-keepattributes *Annotation*
-keep class com.google.gson.** { *; }
-keep class id.my.sisinden.app.data.model.** { *; }
-dontwarn okio.**
-dontwarn retrofit2.**

# Jembatan JavaScript widget Cloudflare Turnstile (WebView) — nama metode
# dipanggil dari JS, jangan di-rename/dihapus R8.
-keepclassmembers class * {
    @android.webkit.JavascriptInterface <methods>;
}
-keepattributes JavascriptInterface
