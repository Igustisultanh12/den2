# PANDUAN BUILD & RILIS APLIKASI SISINDEN ANDROID

Panduan ini menjelaskan tata cara kompilasi, pengujian, penandatanganan (*signing*), dan otomatisasi rilis APK Android Native SISINDEN ke **GitHub Releases**.

---

## 1. Prasyarat Lingkungan Build

- **JDK:** OpenJDK 17 (Temurin atau Corretto)
- **Android SDK:**
  - `compileSdk`: 34 (Android 14)
  - `minSdk`: 26 (Android 8.0 Oreo)
  - `targetSdk`: 34
- **Versi Rilis Saat Ini:**
  - `versionCode`: 3
  - `versionName`: "1.0.2"
- **Build System:** Gradle 8.9 dengan Gradle Kotlin DSL (`build.gradle.kts`)
- **Android Gradle Plugin (AGP):** 8.5.2
- **Kotlin:** 2.0.21

---

## 2. Struktur Direktori Android Native

Proyek Android Native terletak di folder `android/` dalam repositori ini:

```
android/
├── app/
│   ├── build.gradle.kts
│   ├── proguard-rules.pro
│   └── src/
│       ├── main/
│       │   ├── AndroidManifest.xml
│       │   ├── java/id/my/sisinden/app/
│       │   │   ├── core/
│       │   │   │   ├── data/
│       │   │   │   ├── network/
│       │   │   │   ├── notification/
│       │   │   │   └── ui/theme/
│       │   │   ├── data/
│       │   │   │   ├── model/
│       │   │   │   └── repository/
│       │   │   │   ├── auth/
│       │   │   │   ├── attendance/
│       │   │   │   ├── dashboard/
│       │   │   │   ├── letters/
│       │   │   │   ├── skhpp/
│       │   │   │   ├── cash/
│       │   │   │   ├── mitra/
│       │   │   │   ├── signature/
│       │   │   │   ├── violations/
│       │   │   │   ├── personnel/
│       │   │   │   ├── notifications/
│       │   │   │   └── settings/
│       │   │   ├── MainActivity.kt
│       │   │   └── SindenApplication.kt
│       │   └── res/
│       └── test/
├── build.gradle.kts
├── settings.gradle.kts
├── gradle.properties
├── gradlew
└── gradlew.bat
```

---

## 3. Perintah Kompilasi Lokal (CLI)

Jalankan perintah berikut di direktori `android/`:

### 3.1 Menjalankan Unit Test
```bash
cd android
./gradlew testDebugUnitTest
```

### 3.2 Kompilasi Debug APK
```bash
cd android
./gradlew assembleDebug
```
Lokasi berkas hasil: `android/app/build/outputs/apk/debug/app-debug.apk`

### 3.3 Kompilasi Release APK
```bash
cd android
./gradlew assembleRelease
```
Lokasi berkas hasil: `android/app/build/outputs/apk/release/app-release.apk`

---

## 4. Alur Kerja Otomatisasi GitHub Actions (CI/CD)

Repositori dilengkapi alur kerja `.github/workflows/android-release.yml` yang terpicu secara otomatis pada setiap push ke branch `main` atau pembuatan tag `v*`:

1. **Checkout & JDK Setup**: Mempersiapkan lingkungan Ubuntu runner dengan Temurin JDK 17 dan Gradle 8.9.
2. **Eksekusi Unit Test**: Menjalankan `:app:testDebugUnitTest` untuk memverifikasi integritas model dan parser.
3. **Kompilasi APK**: Menjalankan `:app:assembleDebug` dan `:app:assembleRelease`.
4. **Penyiapan Berkas Rilis**: Mengumpulkan berkas output menjadi `SISINDEN-v1.0.1.apk`, `app-release.apk`, dan `app-debug.apk`.
5. **Publikasi ke GitHub Releases**: Membuat rilis publik dengan tag versi otomatis dan menyertakan seluruh aset berkas APK siap pasang.

---

## 5. Status dan Tautan Rilis Terverifikasi

- **Tag Rilis:** `v1.0.24`
- **Tautan Rilis:** [SISINDEN Android Native v1.0.24](https://github.com/Igustisultanh12/kantor/releases/tag/v1.0.24)
- **Aset Rilis Utama:** [SISINDEN-v1.0.2.apk](https://github.com/Igustisultanh12/kantor/releases/download/v1.0.24/SISINDEN-v1.0.2.apk)
- **Aset Standar:** [app-release.apk](https://github.com/Igustisultanh12/kantor/releases/download/v1.0.24/app-release.apk)
- **Aset Debug:** [app-debug.apk](https://github.com/Igustisultanh12/kantor/releases/download/v1.0.24/app-debug.apk)
