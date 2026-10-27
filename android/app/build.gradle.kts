plugins {
    alias(libs.plugins.android.application)
    alias(libs.plugins.kotlin.android)
    alias(libs.plugins.kotlin.compose)
}

// ---------------------------------------------------------------------------
// Signing rilis: kredensial TIDAK pernah disimpan di repository.
// Sediakan lewat environment variable (lokal) atau GitHub Secrets (CI):
//   SISINDEN_KEYSTORE_PATH, SISINDEN_KEYSTORE_PASSWORD,
//   SISINDEN_KEY_ALIAS, SISINDEN_KEY_PASSWORD
// Tanpa variabel tersebut, build release menghasilkan APK *unsigned* yang
// jujur (app-release-unsigned.apk), bukan APK bertanda tangan debug.
// ---------------------------------------------------------------------------
val releaseKeystorePath: String? = System.getenv("SISINDEN_KEYSTORE_PATH")
val hasReleaseKey: Boolean = !releaseKeystorePath.isNullOrBlank() &&
    file(releaseKeystorePath).exists() &&
    !System.getenv("SISINDEN_KEYSTORE_PASSWORD").isNullOrBlank() &&
    !System.getenv("SISINDEN_KEY_ALIAS").isNullOrBlank()

android {
    namespace = "id.my.sisinden.app"
    compileSdk = 34

    defaultConfig {
        applicationId = "id.my.sinden2.app"
        minSdk = 26
        targetSdk = 34
        versionCode = 18
        versionName = "1.5.0"

        testInstrumentationRunner = "androidx.test.runner.AndroidJUnitRunner"

        // Library native ML Kit (deteksi senyum) hanya untuk HP ARM agar APK tetap ramping.
        ndk {
            abiFilters += listOf("arm64-v8a", "armeabi-v7a")
        }
        vectorDrawables {
            useSupportLibrary = true
        }
    }

    signingConfigs {
        if (hasReleaseKey) {
            create("release") {
                storeFile = file(releaseKeystorePath!!)
                storePassword = System.getenv("SISINDEN_KEYSTORE_PASSWORD")
                keyAlias = System.getenv("SISINDEN_KEY_ALIAS")
                keyPassword = System.getenv("SISINDEN_KEY_PASSWORD") ?: System.getenv("SISINDEN_KEYSTORE_PASSWORD")
            }
        }
    }

    buildTypes {
        release {
            isMinifyEnabled = false
            proguardFiles(
                getDefaultProguardFile("proguard-android-optimize.txt"),
                "proguard-rules.pro"
            )
            // Jika keystore rilis tidak dikonfigurasi, gunakan signing debug sebagai fallback agar APK dapat langsung diinstal
            signingConfig = if (hasReleaseKey) signingConfigs.getByName("release") else signingConfigs.findByName("debug")
        }
        debug {
            applicationIdSuffix = ".debug"
            versionNameSuffix = "-debug"
            isDebuggable = true
        }
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    kotlinOptions {
        jvmTarget = "17"
    }

    buildFeatures {
        compose = true
        buildConfig = true
    }

    packaging {
        resources {
            excludes += "/META-INF/{AL2.0,LGPL2.1}"
        }
    }
}

dependencies {
    implementation(libs.androidx.core.ktx)
    implementation(libs.androidx.lifecycle.runtime.ktx)
    implementation(libs.androidx.lifecycle.runtime.compose)
    implementation(libs.androidx.lifecycle.viewmodel.compose)
    implementation(libs.androidx.activity.compose)

    implementation(platform(libs.androidx.compose.bom))
    implementation(libs.androidx.ui)
    implementation(libs.androidx.ui.graphics)
    implementation(libs.androidx.ui.tooling.preview)
    implementation(libs.androidx.material3)
    implementation(libs.androidx.material.icons.extended)

    implementation(libs.androidx.navigation.compose)
    implementation(libs.androidx.datastore.preferences)

    // Networking
    implementation(libs.retrofit)
    implementation(libs.retrofit.converter.gson)
    implementation(libs.okhttp)
    implementation(libs.okhttp.logging)

    // Image Loading
    implementation(libs.coil.compose)

    // Asynchronous Coroutines
    implementation(libs.kotlinx.coroutines.android)

    // Kamera presensi + deteksi senyum on-device (model ML Kit dibundel, tanpa Play Services)
    implementation(libs.androidx.camera.core)
    implementation(libs.androidx.camera.camera2)
    implementation(libs.androidx.camera.lifecycle)
    implementation(libs.androidx.camera.view)
    implementation(libs.mlkit.face.detection)
    // Sinkron notifikasi bersuara di latar belakang
    implementation(libs.androidx.work.runtime.ktx)
    // Notifikasi push (FCM). Konfigurasi Firebase diambil dari server saat runtime,
    // jadi plugin google-services & google-services.json tidak diperlukan di repo.
    implementation(platform(libs.firebase.bom))
    implementation(libs.firebase.messaging)

    // Testing
    testImplementation(libs.junit)
    androidTestImplementation(libs.androidx.junit)
    androidTestImplementation(libs.androidx.espresso.core)
    androidTestImplementation(platform(libs.androidx.compose.bom))
    debugImplementation(libs.androidx.ui.tooling)
}
