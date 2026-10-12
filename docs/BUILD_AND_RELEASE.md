# PANDUAN BUILD & RILIS APLIKASI SISINDEN ANDROID

Panduan ini menjelaskan tata cara kompilasi, pengujian, penandatanganan (*signing*), dan otomatisasi rilis APK Android Native SISINDEN ke **GitHub Releases**.

---

## 1. Prasyarat Lingkungan Build

- **JDK:** OpenJDK 17 (Temurin atau Corretto)
- **Android SDK:**
  - `compileSdk`: 34 (Android 14)
  - `minSdk`: 26 (Android 8.0 Oreo)
  - `targetSdk`: 34
- **Build System:** Gradle 8.7+ dengan Gradle Kotlin DSL (`build.gradle.kts`)
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
│       │   │   │   └── ui/theme/
│       │   │   ├── data/
│       │   │   │   ├── model/
│       │   │   │   └── repository/
│       │   │   ├── ui/
│       │   │   │   ├── auth/
│       │   │   │   ├── dashboard/
│       │   │   │   ├── letters/
│       │   │   │   ├── skhpp/
│       │   │   │   ├── cash/
│       │   │   │   ├── mitra/
│       │   │   │   ├── signature/
│       │   │   │   └── settings/
│       │   │   ├── MainActivity.kt
│       │   │   └── SindenApplication.kt
│       │   └── res/
│       └── test/
├── gradle/
│   └── wrapper/
│       ├── gradle-wrapper.jar
│       └── gradle-wrapper.properties
├── build.gradle.kts
├── settings.gradle.kts
├── gradle.properties
├── gradlew
└── gradlew.bat
```

---

## 3. Perintah Kompilasi Lokal (CLI)

Jalankan perintah berikut di direktori `android/`:

### 3.1 Kompilasi Debug APK
```bash
cd android
./gradlew assembleDebug
```
Lokasi berkas hasil: `android/app/build/outputs/apk/debug/app-debug.apk`

### 3.2 Menjalankan Unit Test
```bash
cd android
./gradlew testDebugUnitTest
```

### 3.3 Kompilasi Release APK
```bash
cd android
./gradlew assembleRelease
```
Lokasi berkas hasil: `android/app/build/outputs/apk/release/app-release-unsigned.apk` (atau ditandatangani jika keystore telah dikonfigurasi).

---

## 4. Konfigurasi Penandatanganan (Signing Keystore)

Untuk rilis produksi terverifikasi, siapkan GitHub Actions Secrets berikut pada repositori:
1. `ANDROID_KEYSTORE_BASE64`: Berkas keystore (`.jks`) yang di-encode ke format Base64.
2. `KEYSTORE_PASSWORD`: Kata sandi keystore.
3. `KEY_ALIAS`: Alias kunci di dalam keystore.
4. `KEY_PASSWORD`: Kata sandi untuk alias kunci.

*Catatan:* Bila secrets belum dikonfigurasi, pipeline CI/CD otomatis menghasilkan build berlabel debug/release yang ditandatangani secara aman dengan kunci build standar untuk kebutuhan evaluasi langsung.

---

## 5. Otomatisasi GitHub Releases (`.github/workflows/android-release.yml`)

Pipeline rilis berjalan secara otomatis melalui GitHub Actions:

### 5.1 Pemicu (Triggers)
1. **Push Git Tag:** Ketika tag berformat `v*` (contoh: `v1.0.0`) didorong ke repositori GitHub.
2. **Manual Dispatch:** Dapat dijalankan sewaktu-waktu melalui menu **Actions -> Run workflow** di GitHub.

### 5.2 Tahapan Kerja Pipeline
1. Checkout kode sumber repositori.
2. Setup Java 17 dan Android SDK secara otomatis di runner Ubuntu.
3. Pemberian izin eksekusi pada `gradlew`.
4. Menjalankan pemeriksaan unit test.
5. Membangun APK Release (`./gradlew assembleRelease` atau fallback signed build).
6. Menamai berkas APK secara dinamis: `SISINDEN-v1.0.0.apk`.
7. Mengunggah APK ke GitHub Releases beserta ringkasan pembaruan (*release notes*).
