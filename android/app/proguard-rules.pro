# Aturan Proguard untuk Retrofit dan Gson
-keepattributes Signature
-keepattributes *Annotation*
-keep class com.google.gson.** { *; }
-keep class id.my.sisinden.app.data.model.** { *; }
-dontwarn okio.**
-dontwarn retrofit2.**
