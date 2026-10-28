# R8 (rilis): hanya MEMBUANG kode pustaka yang tidak dipakai agar APK kecil (unggah &
# unduh pembaruan lebih cepat). Nama tidak disamarkan dan seluruh kode aplikasi
# dipertahankan utuh, sehingga perilaku identik dengan build debug.
-dontobfuscate
-keep class id.my.sisinden.app.** { *; }

# Retrofit & Gson: metadata generik/anotasi dibaca lewat refleksi.
-keepattributes Signature, *Annotation*, InnerClasses, EnclosingMethod, Exceptions, RuntimeVisibleAnnotations, RuntimeVisibleParameterAnnotations, AnnotationDefault
-keep class com.google.gson.** { *; }
-keep class * extends com.google.gson.TypeAdapter
-keep class * implements com.google.gson.TypeAdapterFactory
-keep class * implements com.google.gson.JsonSerializer
-keep class * implements com.google.gson.JsonDeserializer
-keep class * extends com.google.gson.reflect.TypeToken
-keepclassmembers,allowobfuscation class * {
    @com.google.gson.annotations.SerializedName <fields>;
}
-keep,allowobfuscation,allowshrinking interface retrofit2.Call
-keep,allowobfuscation,allowshrinking class retrofit2.Response
-keep,allowobfuscation,allowshrinking class kotlin.coroutines.Continuation
-dontwarn okio.**
-dontwarn retrofit2.**
-dontwarn org.codehaus.mojo.animal_sniffer.**

# Jembatan JavaScript WebView (Turnstile & Vicon) — nama metode dipanggil dari JS.
-keepclassmembers class * {
    @android.webkit.JavascriptInterface <methods>;
}
-keepattributes JavascriptInterface

# Peta osmdroid memuat sumber tile & kelas pendukung secara refleksi.
-keep class org.osmdroid.** { *; }
-dontwarn org.osmdroid.**
