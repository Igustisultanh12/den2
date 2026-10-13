# SISINDEN (Sistem Informasi dan Surat Intelijen Denintel Kodaeral V)

Repositori resmi sistem kedinasan SISINDEN untuk Detasemen Intelijen Kodaeral V (Komando Daerah Angkatan Laut V). Repositori ini memuat backend web dinas serta aplikasi Android Native resmi.

---

## 1. Ikhtisar Aplikasi Android Native

Aplikasi Android Native SISINDEN dirancang khusus untuk memenuhi standar operasional kedinasan kantor intelijen dengan antarmuka modern berbasis Jetpack Compose Material 3 yang mengadopsi prinsip eCommerce UI Kit profesional.

- **Platform:** Android Native
- **Versi Rilis:** v1.0.1 (versionCode: 2)
- **Min SDK:** 26 (Android 8.0 Oreo)
- **Target SDK:** 34 (Android 14)
- **Bahasa:** Kotlin
- **UI Framework:** Jetpack Compose dengan Material 3
- **Arsitektur:** Clean Architecture MVVM (Model-View-ViewModel) dengan Unidirectional Data Flow (UDF)
- **Networking:** Retrofit 2 + OkHttp 4 + Gson Converter
- **Penyimpanan Lokal:** Jetpack DataStore Preferences & Android SharedPreferences
- **Navigasi:** Jetpack Navigation Compose
- **Build System:** Gradle 8.9 Kotlin DSL (`build.gradle.kts`)
- **Backend Produksi:** [https://sisinden.my.id](https://sisinden.my.id)

---

## 2. Modul dan Fitur Aplikasi

1. **Otentikasi & Keamanan Kedinasan:**
   - Login resmi menggunakan NRP / Username dan Sandi.
   - Manajemen sesi aman dengan Bearer Token via Jetpack DataStore Preferences.
   - Konfigurasi alamat server API dinamis (dapat diarahkan ke mirror internal).

2. **Dashboard Eksekutif Modern:**
   - Kartu saldo utama deep navy gradient dengan badge terverifikasi dan tombol privasi sensor saldo.
   - Trio ringkasan keuangan (Pemasukan, Pengeluaran, Kas Taktis).
   - Grid modul utama 8-aksi dengan kontainer squircle pastel elegan.
   - Daftar agenda naskah dinas terkini.

3. **Manajemen Kas Denintel (Pangkalan):**
   - Rekalkulasi kronologis otomatis yang menjamin ketepatan running balance.
   - Dukungan penuh pencatatan transaksi mulai dari Rp1 hingga nominal besar tanpa anomali.
   - Operasi lengkap: Tambah, Edit (ModalBottomSheet), dan Hapus (AlertDialog).
   - Filter tab: Semua, Penerimaan, dan Pengeluaran.

4. **Manajemen Kas Taktis Operasi (Dan Unit Teknis):**
   - Monitoring dana taktis khusus kegiatan intelijen lapangan.
   - Dukungan pencatatan dana masuk dan dana keluar.
   - Operasi lengkap: Tambah, Edit, dan Hapus data transaksi taktis.

5. **Mitra Intelijen (Jaringan Kerja Lapangan):**
   - Direktori mitra intelijen (nama mitra, perusahaan/sektor, nomor kontak, catatan).
   - Status pembayaran bulan berjalan ("Lunas" / "Belum Lunas").
   - Lembar Matriks 12 Bulan interaktif (Januari - Desember) untuk menandai pembayaran bulanan secara instan.
   - Operasi lengkap: Tambah, Edit, dan Hapus data mitra.

6. **Agenda & Log Surat Dinas:**
   - Filter tab surat (Semua, Surat Masuk, Surat Keluar, Disposisi).
   - Pencarian dinamis berdasarkan nomor agenda, perihal, atau pengirim.
   - Formulir pencatatan agenda surat dinas baru.

7. **Layanan SKHPP (Surat Keterangan Hasil Penelitian Personel):**
   - Pemantauan status pengajuan (Pending, Terverifikasi).
   - Validasi nama personel, pangkat, dan tujuan permohonan dinas.
   - Formulir pengajuan permohonan SKHPP baru.

8. **Tanda Tangan Elektronik Komandan:**
   - Daftar permohonan persetujuan dokumen kedinasan.
   - Verifikasi status persetujuan resmi dinas.

9. **Pelanggaran Personel Satuan:**
   - Rekap catatan disiplin internal dan kepatuhan etika dinas.

10. **Direktori Personel Satuan:**
    - Pencarian kontak dinas personel, NRP, korps, pangkat, dan jabatan.

11. **Warta Notifikasi Kedinasan:**
    - Sistem notifikasi pintar dengan deduplikasi persisten SharedPreferences.
    - Sinkronisasi senyap saat pertama kali aplikasi dibuka (mencegah spam bunyi/getar notifikasi lama).
    - Membunyikan nada dering hanya jika ada instruksi dinas yang benar-benar baru.

12. **Preferensi & Personalisasi:**
    - Pengaturan tema gelap (Dark Mode) dan sesi akun.

---

## 3. Struktur Direktori Proyek Android

Proyek Android Native terletak pada direktori `android/`:

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
│       │   │   │   ├── notification/
│       │   │   │   │   └── NotificationHelper.kt
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
```

---

## 4. Dokumentasi Teknis Tambahan

Dokumentasi terperinci tersedia di folder `docs/`:
- [PROJECT_AUDIT.md](file:///C:/Users/Hackersxx12/.gemini/antigravity/scratch/kantor/docs/PROJECT_AUDIT.md): Audit menyeluruh struktur kode dan teknologi.
- [FEATURE_PARITY.md](file:///C:/Users/Hackersxx12/.gemini/antigravity/scratch/kantor/docs/FEATURE_PARITY.md): Matriks perbandingan fitur web vs Android.
- [BUGFIX_REPORT.md](file:///C:/Users/Hackersxx12/.gemini/antigravity/scratch/kantor/docs/BUGFIX_REPORT.md): Analisis akar masalah teknis dan solusi perbaikan bug.
- [NOTIFICATION_FLOW.md](file:///C:/Users/Hackersxx12/.gemini/antigravity/scratch/kantor/docs/NOTIFICATION_FLOW.md): Alur deduplikasi dan siklus notifikasi kedinasan.
- [UI_UX_IMPLEMENTATION.md](file:///C:/Users/Hackersxx12/.gemini/antigravity/scratch/kantor/docs/UI_UX_IMPLEMENTATION.md): Rincian redesign antarmuka dan palet warna.
- [BUILD_AND_RELEASE.md](file:///C:/Users/Hackersxx12/.gemini/antigravity/scratch/kantor/docs/BUILD_AND_RELEASE.md): Panduan kompilasi dan CI/CD rilis APK.
