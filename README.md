# SISINDEN (Sistem Informasi dan Surat Intelijen Denintel Kodaeral V)

Repositori resmi sistem kedinasan SISINDEN untuk Detasemen Intelijen Kodaeral V (Komando Daerah Angkatan Laut V). Repositori ini memuat backend web dinas serta aplikasi Android Native resmi.

---

## 1. Ikhtisar Aplikasi Android Native

- **Versi:** v1.1.0 (versionCode 7) — lihat [catatan rilis](docs/ANDROID_RELEASE_NOTES.md)
- **Platform:** Android 8.0 (API 26) – Android 14 (API 34)
- **Bahasa & UI:** Kotlin 2.0, Jetpack Compose Material 3 (design system "Samudra", mode terang/gelap)
- **Arsitektur:** MVVM + repository, state berbasis `StateFlow`, navigasi Compose dengan penjaga hak akses per rute
- **Jaringan:** Retrofit 2 + OkHttp 4 (token Sanctum, hanya HTTPS)
- **Penyimpanan lokal:** DataStore Preferences (sesi & preferensi; dikecualikan dari backup)
- **Backend:** [https://sisinden.my.id](https://sisinden.my.id) — API mobile di `routes/mobile_api.php`

> Aplikasi v1.1.0 membutuhkan backend dari branch yang sama (middleware `mobile.auth`, endpoint `/api/mobile/me`, `/dashboard`, `/duty-roster`, `/pdf/*`) dan `php artisan migrate`.

---

## 2. Modul dan Fitur Aplikasi

Menu, tombol aksi, dan rute ditampilkan **berdasarkan permission dari server** (`app/Support/MobileAccess.php`); backend menolak setiap operasi tanpa izin. Rincian per role: [ROLE_PERMISSION_MATRIX.md](docs/ROLE_PERMISSION_MATRIX.md).

1. **Beranda** — sapaan, tanggal, status presensi, Daftar Jaga hari ini & berikutnya, ringkasan modul yang diizinkan, akses cepat.
2. **Daftar Jaga (SP Jaga)** — jadwal per tanggal dari SP Jaga yang terbit, filter "Jadwal saya", detail tanggal, PDF resmi.
3. **Presensi** — Hadir / Dinas Luar / Piket / Izin, lokasi GPS opsional, riwayat 30 hari.
4. **Agenda Surat** — cari, filter kategori, tambah/ubah/hapus, rekap PDF.
5. **SKHPP** — pengajuan lengkap (foto & pengikut), persetujuan/pengembalian Komandan, PDF.
6. **Tanda Tangan (TTE)** — antrean pengesahan dengan stempel QR seperti web.
7. **Buku Kas**, **Kas Unit Teknis**, **Rekening Komandan** (rekening koran PDF), **Mitra** (matriks 12 bulan).
8. **Pelanggaran Personel** — catatan, riwayat perkembangan, status.
9. **Kelola Pengguna** (admin), **Notifikasi**, **Profil**, **Pengaturan** (tema, sembunyikan saldo, suara, alamat server).

Hasil audit paritas web ↔ Android, temuan keamanan, dan daftar bug: [SISINDEN_ANDROID_AUDIT.md](docs/SISINDEN_ANDROID_AUDIT.md).

---

## 3. Struktur Direktori Proyek Android

```
android/app/src/main/java/id/my/sisinden/app/
├── MainActivity.kt, SindenApplication.kt
├── core/
│   ├── data/            SessionStore (DataStore), SessionManager (status sesi)
│   ├── network/         ApiClient, SindenApiService, ApiResult & penanganan error
│   ├── security/        Perm (kunci permission server), PermissionSet
│   ├── notification/    NotificationHelper
│   ├── util/            Formatters (Rupiah, tanggal Indonesia)
│   └── ui/              theme/ (warna, tipografi, bentuk) · components/ (design system)
├── data/
│   ├── model/           DTO sesuai kontrak routes/mobile_api.php
│   └── repository/      SindenRepository (satu-satunya akses jaringan, unduh PDF)
└── ui/
    ├── navigation/      SindenRoot, MainNavigation (Guard per rute), Features (metadata menu)
    ├── auth/ splash/ dashboard/ duty/ attendance/ menu/ profile/ settings/
    ├── letters/ skhpp/ signature/ cash/ commander/ mitra/ violations/ users/ notifications/
    ├── pdf/             PdfDocument (PdfRenderer) & PdfPreviewScreen
    └── common/          LoadableViewModel, UiMessages, AccessDeniedScreen
android/app/src/test/    ApiContractTest (fixture JSON dari backend), UtilitiesTest
```

---

## 4. Dokumentasi Teknis

- [SISINDEN_ANDROID_AUDIT.md](docs/SISINDEN_ANDROID_AUDIT.md) — audit v1.1.0, paritas fitur, keamanan, bug, status pengujian.
- [ROLE_PERMISSION_MATRIX.md](docs/ROLE_PERMISSION_MATRIX.md) — matriks role/permission & skenario uji akses.
- [ANDROID_RELEASE_NOTES.md](docs/ANDROID_RELEASE_NOTES.md) — catatan rilis (juga dipakai sebagai isi GitHub Release).
- Dokumen lama (PROJECT_AUDIT, FEATURE_PARITY, BUGFIX_REPORT, dll.) menjelaskan v1.0.x.

---

## 5. Build & Rilis

- CI: `.github/workflows/android-release.yml` — tes backend mobile API, unit test Android, lint, build debug & release, checksum SHA-256.
- GitHub Release hanya dibuat dari tag `v*` **dan** hanya bila APK ditandatangani keystore resmi melalui secrets `SISINDEN_KEYSTORE_BASE64`, `SISINDEN_KEYSTORE_PASSWORD`, `SISINDEN_KEY_ALIAS`, `SISINDEN_KEY_PASSWORD`. Keystore tidak pernah disimpan di repository.
- Build lokal: `cd android && gradle :app:assembleDebug` (rilis bertanda tangan: set environment variable yang sama dengan nama secrets di atas, dengan `SISINDEN_KEYSTORE_PATH` menunjuk ke berkas keystore).
- Halaman rilis: https://github.com/Igustisultanh12/kantor/releases
