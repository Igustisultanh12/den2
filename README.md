# SISINDEN (Sistem Informasi dan Surat Intelijen Denintel Kodaeral V)

Repositori resmi sistem kedinasan SISINDEN untuk Detasemen Intelijen Kodaeral V (Komando Daerah Angkatan Laut V). Repositori ini memuat backend web dinas serta aplikasi Android Native resmi.

---

## 1. Ikhtisar Aplikasi Android Native

Aplikasi Android Native SISINDEN dirancang khusus untuk memenuhi standar operasional kedinasan intelijen dengan tampilan modern berbasis Jetpack Compose dan Material 3.

- **Platform:** Android Native (Min SDK: 26 / Android 8.0 Oreo, Target SDK: 34 / Android 14)
- **Bahasa:** Kotlin
- **UI Framework:** Jetpack Compose dengan Material 3
- **Arsitektur:** Clean Architecture dengan pola MVVM (Model-View-ViewModel) dan Unidirectional Data Flow (UDF)
- **Networking:** Retrofit 2 + OkHttp 4 + Gson Converter
- **Penyimpanan Lokal:** Jetpack DataStore Preferences (penyimpanan token sesi dan konfigurasi server)
- **Navigasi:** Jetpack Navigation Compose
- **Build System:** Gradle Kotlin DSL (`build.gradle.kts`)
- **Backend Produksi:** https://sisinden.my.id

---

## 2. Modul dan Fitur Aplikasi

1. **Otentikasi & Keamanan Kedinasan:**
   - Login resmi menggunakan NRP / Username dan Sandi.
   - Manajemen sesi aman dengan Bearer Token via Jetpack DataStore Preferences.
   - Konfigurasi alamat server API dinamis (dapat diarahkan ke mirror internal).

2. **Dashboard & Pemantauan Cepat:**
   - Ringkasan profil dan hierarki dinas pengguna (NRP, Korps, Jabatan, Role).
   - Indikator saldo Kas Denintel dan Kas Taktis Satuan.
   - Metrik statistik volume surat masuk, surat keluar, dan disposisi.
   - Menu aksi cepat ke seluruh modul operasional.

3. **Agenda & Log Surat Dinas:**
   - Filter tab surat (Semua, Surat Masuk, Surat Keluar, Disposisi).
   - Pencarian dinamis berdasarkan nomor agenda, perihal, atau pengirim.
   - Formulir pencatatan agenda surat dinas baru.

4. **Layanan SKHPP (Surat Keterangan Hasil Penelitian Personel):**
   - Pemantauan status pengajuan (Pending, Terverifikasi).
   - Validasi nama personel, pangkat, dan tujuan permohonan dinas.
   - Formulir pengajuan permohonan SKHPP baru.

5. **Manajemen Kas Denintel & Kas Taktis:**
   - Rekapitulasi transaksi masuk dan keluar serta saldo akhir.
   - Pencatatan transaksi baru dengan validasi nominal dan keterangan.

6. **Mitra Intelijen (Jaringan Kerja Lapangan):**
   - Direktori mitra intelijen terenkripsi (nama samaran/code name, nomor kontak, sektor wilayah).
   - Status dukungan operasional dan pencatatan mitra baru.

7. **Tanda Tangan Digital Komandan:**
   - Daftar permohonan persetujuan dokumen kedinasan.
   - Verifikasi status persetujuan resmi.

8. **Pelanggaran Personel Satuan:**
   - Catatan rekap kedisiplinan dan kepatuhan etika dinas.

9. **Direktori Personel Satuan:**
   - Pencarian kontak personel, NRP, korps, pangkat, dan peranan.

10. **Notifikasi Kedinasan:**
    - Saluran pengumuman resmi dan instruksi komando.

11. **Preferensi & Personalisasi:**
    - Pengaturan tema gelap (Dark Mode) dan informasi markas satuan.

---

## 3. Struktur Direktori Proyek Android

Proyek Android Native terletak pada subdirektori `android/`:

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
│       │   │   │   ├── data/DataStoreManager.kt
│       │   │   │   ├── network/
│       │   │   │   │   ├── ApiClient.kt
│       │   │   │   │   └── SindenApiService.kt
│       │   │   │   └── ui/
│       │   │   │       ├── components/Components.kt
│       │   │   │       └── theme/
│       │   │   ├── data/
│       │   │   │   ├── model/Models.kt
│       │   │   │   └── repository/SindenRepository.kt
│       │   │   ├── ui/
│       │   │   │   ├── auth/
│       │   │   │   ├── cash/
│       │   │   │   ├── dashboard/
│       │   │   │   ├── letters/
│       │   │   │   ├── mitra/
│       │   │   │   ├── navigation/
│       │   │   │   ├── notifications/
│       │   │   │   ├── personnel/
│       │   │   │   ├── settings/
│       │   │   │   ├── signature/
│       │   │   │   ├── skhpp/
│       │   │   │   └── violations/
│       │   │   ├── MainActivity.kt
│       │   │   └── SindenApplication.kt
│       │   └── res/
│       └── test/
│           └── java/id/my/sisinden/app/SindenUnitTest.kt
├── gradle/
│   ├── libs.versions.toml
│   └── wrapper/
├── build.gradle.kts
├── settings.gradle.kts
├── gradle.properties
├── gradlew
└── gradlew.bat
```

---

## 4. Panduan Kompilasi & Build

### 4.1 Menjalankan Pengujian Unit
```bash
cd android
./gradlew testReleaseUnitTest
```

### 4.2 Membangun APK Release
```bash
cd android
./gradlew assembleRelease
```
Berkas APK yang dihasilkan akan berada di: `android/app/build/outputs/apk/release/app-release.apk`.

---

## 5. Otomatisasi CI/CD & GitHub Releases

Pipeline GitHub Actions telah dikonfigurasi di `.github/workflows/android-release.yml`.

Setiap kali pembaruan didorong ke cabang `main` atau tag rilis `v*` dibuat:
1. Kode sumber diperiksa dan dikompilasi pada lingkungan Ubuntu runner.
2. Seluruh unit test dieksekusi secara otomatis.
3. APK Release (`SISINDEN-v1.0.0.apk`) dibangun dan ditandatangani.
4. APK diterbitkan ke menu **GitHub Releases** dengan catatan rilis resmi.

---

## 6. Dokumentasi Teknis Terkait

- [Analisis Sistem & Backend](file:///C:/Users/Hackersxx12/.gemini/antigravity/scratch/kantor/docs/ANALYSIS.md)
- [Spesifikasi Integrasi API](file:///C:/Users/Hackersxx12/.gemini/antigravity/scratch/kantor/docs/API_INTEGRATION.md)
- [Arsitektur Perangkat Lunak](file:///C:/Users/Hackersxx12/.gemini/antigravity/scratch/kantor/docs/ARCHITECTURE.md)
- [Panduan Kompilasi dan Rilis](file:///C:/Users/Hackersxx12/.gemini/antigravity/scratch/kantor/docs/BUILD_AND_RELEASE.md)
