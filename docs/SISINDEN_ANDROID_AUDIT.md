# Audit Aplikasi Android SISINDEN & Paritas Fitur Web — v1.1.0

Tanggal audit: 13 Oktober 2026 · Branch: `feature/android-sisinden-redesign`

> **Cakupan & batasan audit (jujur):**
> - Diaudit: seluruh source code di repository ini — aplikasi Android native (`android/`), backend Laravel + web Inertia/Vue (`app/`, `routes/`, `resources/js/`), aplikasi Flutter (`sinden_mobile/`), dan workflow CI.
> - **Tidak** diaudit secara langsung: situs produksi `https://sisinden.my.id` (diblokir dari lingkungan kerja, dan tidak ada akun uji yang diberikan), database produksi, serta perilaku server yang tidak tercermin di repository. Audit fitur web dilakukan dari kode sumber (rute, controller, middleware, dan menu `AuthenticatedLayout.vue`), bukan dengan menjelajah halaman yang berjalan.
> - Aplikasi Android **belum dikompilasi di lingkungan audit** (Maven/Google Maven diblokir). Verifikasi build dilakukan oleh workflow GitHub Actions setelah branch di-push — lihat bagian 8.

---

## 1. Teknologi

| Komponen | Temuan |
|---|---|
| Android native | Kotlin 2.0.21, Jetpack Compose (BOM 2024.10.00 → Material 3 1.3.0, Compose 1.7), Navigation Compose 2.8.3, Lifecycle 2.8.6, Retrofit 2.11 + OkHttp 4.12 + Gson, DataStore Preferences, Coil. AGP 8.5.2, Gradle 8.9, minSdk 26, targetSdk 34. Arsitektur MVVM tanpa DI framework. |
| Backend & web | Laravel 12 (PHP 8.2+), Inertia + Vue 3, Sanctum 4, DomPDF 3 (semua laporan PDF resmi dibuat di server), WhatsApp gateway (`wa-gateway/`). |
| API mobile | Seluruhnya didefinisikan sebagai closure di `routes/web.php` (±1.550 baris) — kini dipindahkan ke `routes/mobile_api.php`. |
| Aplikasi lain | `sinden_mobile/` (Flutter) memakai API mobile yang sama. |
| CI | `.github/workflows/android-release.yml` (lihat temuan B-14). |

## 2. Menu web vs Android

Sumber menu web: `resources/js/Layouts/AuthenticatedLayout.vue` + guard di controller. Kolom "Android v1.0.4" adalah kondisi sebelum perbaikan; "v1.1.0" adalah hasil branch ini.

| Menu web | Gate di web | Android v1.0.4 | Android v1.1.0 |
|---|---|---|---|
| Dashboard | login | Ada (memakai saldo kas untuk SEMUA pengguna, angka 0 bila gagal) | Didesain ulang; ringkasan hanya modul yang diizinkan server; **Daftar Jaga** jadi seksi utama |
| SP Jaga (Daftar Jaga) | login; draf hanya admin/Pasops | **Tidak ada** | **Baru**: dashboard + tab Jaga + detail tanggal + PDF resmi |
| Presensi | login | Ada (koordinat GPS palsu/hard-coded) | Lokasi nyata opsional (izin runtime), riwayat 30 hari |
| Agenda Surat (letter-logs) | non-Anggota Sintel | Ada, field tidak cocok dengan API (nomor/pengirim kosong) | Diperbaiki: daftar, cari, filter kategori, tambah, ubah, hapus, **rekap PDF** |
| Naskah Dinas (letters) | non-Anggota Sintel | Tidak ada | Endpoint tersedia & diamankan; UI belum (alur web berbasis unggah dokumen) — *gap* |
| SKHPP | non-Anggota Sintel; setuju = admin/komandan | Ada, **form tidak pernah berhasil** (kolom wajib tidak dikirim) | Form lengkap sesuai web (foto, pengikut), setujui/kembalikan via logika web, PDF resmi |
| Tracking SC (sc-submissions) | Anggota Sintel | Tidak ada | *Gap* — belum ada API mobile |
| Tanda Tangan (TTE) | admin/komandan menyetujui; lainnya melihat miliknya | Ada, persetujuan **tidak membubuhkan stempel QR** | Persetujuan memakai `SignatureRequestController@update` (stempel QR sama dengan web); tolak dengan alasan |
| Otoritas Stempel, Layanan Printing | login | Tidak ada | *Gap* (butuh perangkat/printer kantor) |
| Simpan Pinjam / Kelola Koperasi | non-Anggota Sintel / pengurus | Tidak ada | *Gap* — belum ada API mobile |
| Buku Kas | admin + nama tertentu | Ada (terlihat semua orang di bottom bar) | Diperbaiki & dijaga permission; laporan PDF resmi |
| Rekening Komandan | komandan / admin / role `user` | Tidak ada di Android | **Baru**: mutasi per bulan, tambah/hapus (admin/user), rekening koran PDF |
| Rekening Ibu Beti | flag `can_access_ibu_beti` | Tidak ada | *Gap* — belum ada API mobile (flag kompatibilitas dikirim) |
| Mitra | admin / flag `can_access_mitra` | Ada | Matriks 12 bulan per tahun, tambah/ubah/hapus |
| Kas Unit Teknis | admin / Dan Unit Teknis / flag | Ada | Diperbaiki & dijaga permission |
| Backup PC | jaringan kantor (`office.only`) | Tidak ada | Tidak ditambahkan — dibatasi IP kantor oleh desain web |
| Pelanggaran Prajurit | non-Anggota Sintel | Ada, **kolom salah** (data tersimpan "-") | Diperbaiki: form sesuai web, riwayat perkembangan, ubah status, hapus |
| Radar Kegiatan | non-Anggota Sintel | Tidak ada | Endpoint diamankan; UI belum (peta) — *gap* |
| Kelola Pengguna | admin | "Direktori Personel" terbuka untuk semua (bocor email/role) | Khusus admin: cari, aktif/nonaktif, tambah, hapus |
| Log Pengunjung, System Check, Pengaturan sistem | admin | Tidak ada | *Gap* (administrasi server — tetap di web) |
| Live Chat, WhatsApp Web | login | Tidak ada | *Gap* (butuh WebRTC/WA session) |
| Notifikasi | login | Ada (semua notifikasi semua orang) | Dibatasi milik pengguna/role-nya, tandai dibaca |
| Profil & Pengaturan | login | Pengaturan saja | Profil (data + hak akses), Pengaturan (tema, sembunyikan saldo, suara, server) |

## 3. Temuan keamanan backend (KRITIS — sudah diperbaiki di branch ini)

| # | Temuan | Dampak | Perbaikan |
|---|---|---|---|
| S-1 | Semua `/api/mobile/*` **tanpa autentikasi** dan dikecualikan dari CSRF. Token login tidak pernah divalidasi. | Siapa pun bisa membaca/mengubah/menghapus kas, mitra, pelanggaran, SKHPP, pengguna. | Middleware `mobile.auth` (token Sanctum) + permission per endpoint. Uji: `test_data_endpoints_reject_requests_without_token`, `test_permissions_are_enforced_by_backend`. |
| S-2 | `POST /api/mobile/activate` mengganti kata sandi akun mana pun hanya dengan NRP/email. | Pengambilalihan akun termasuk admin. | Wajib token aktivasi (sama dengan web `/aktivasi`), throttle 5/menit. Uji: `test_activation_requires_valid_token`. |
| S-3 | `POST /api/mobile/users` publik, role diambil dari payload. | Siapa pun bisa membuat akun admin. | Khusus `users.manage` (admin), validasi role & email/NRP unik. |
| S-4 | `/admin/laravel-logs` publik; log mencatat input login. | Kebocoran data. | `auth` + `role:admin`. Uji: `test_laravel_log_viewer_is_no_longer_public`. |
| S-5 | Presensi memakai header `X-User-Id` (bisa dipalsukan); fallback ke "personel aktif pertama". | Presensi atas nama orang lain. | Identitas hanya dari token. Uji: `test_attendance_is_recorded_for_token_owner_only`, `test_forged_identity_headers_are_ignored`. |
| S-6 | Login membedakan "personel tidak ditemukan" vs "sandi salah" & menyebut nama. | Enumerasi akun. | Pesan generik + throttle 10/menit. Uji: `test_login_error_does_not_reveal_whether_user_exists`. |
| S-7 | Token cadangan acak `sinden_token_…` bila Sanctum gagal — tidak pernah tersimpan. | Sesi palsu. | Dihapus; 503 jelas bila tabel token belum dimigrasikan. |
| S-8 | Notifikasi mobile mengembalikan semua notifikasi semua pengguna. | Kebocoran instruksi dinas. | Dibatasi `user_id` = pengguna, atau broadcast role/umum. Uji: `test_notifications_are_scoped_to_user`. |
| S-9 | Pesan error mengembalikan `$e->getMessage()` (SQL dsb.) ke klien. | Kebocoran detail internal. | Pesan generik + log server. |
| S-10 | Keystore rilis Flutter **beserta passwordnya** ter-commit (`sinden_mobile/android/app/sinden_release.jks`). | Siapa pun dapat menandatangani APK atas nama aplikasi Flutter. | File dihapus dari tree, password pindah ke `key.properties` (di-gitignore). **Kunci harus diganti** karena tetap ada di riwayat git. |
| S-11 | APK Android "release" ditandatangani **debug key** milik runner CI (berganti tiap build). | Pembaruan aplikasi gagal (signature berbeda) & tidak sah untuk distribusi. | Signing dari secrets; tanpa secrets build menjadi *unsigned* dan rilis ditolak. |
| S-12 | Aplikasi Android mencatat body HTTP lengkap (token, sandi) ke Logcat di build rilis; `allowBackup=true`. | Kebocoran kredensial. | Logging hanya build debug, level BASIC, header sensitif disamarkan; backup dimatikan. |

## 4. Daftar bug yang ditemukan & status

| # | Bug | Lapisan | Status |
|---|---|---|---|
| B-1 | DTO agenda surat (`letter_number`, `sender`, `type`) tidak cocok dengan respons (`full_number`, `recipient`, `category_name`) → daftar kosong | Android | Diperbaiki |
| B-2 | Buat agenda mengirim `type/sender`; server mengabaikan dan memaksa kategori 1 | Android + API | Diperbaiki (kategori wajib, validasi) |
| B-3 | Form SKHPP mobile tidak mengirim kolom wajib (`surat_pengantar`, `tempat_lahir`, foto, kode verifikasi) → selalu error 500 | Android + API | Diperbaiki: API memakai `SkhppController@store` web |
| B-4 | "Verifikasi" SKHPP mobile hanya mengubah status tanpa nomor/TTE | API | Diperbaiki: memakai `SkhppController@approve` |
| B-5 | Pelanggaran mobile menulis kolom yang tidak ada (`pangkat`, `jabatan`, `tmt`, `case_progress`) → data "-" dan riwayat hilang | API | Diperbaiki (kolom `rank`, `position`, `incident_date`, `case_development`) |
| B-6 | DTO pelanggaran/SKHPP Android tidak cocok dengan respons | Android | Diperbaiki |
| B-7 | Presensi mengirim koordinat GPS hard-coded (-7.2053, …) | Android | Diperbaiki: lokasi nyata atau tanpa lokasi |
| B-8 | `attendance_date` diserialisasi sebagai ISO UTC → tanggal mundur sehari (WIB) | Backend | Diperbaiki (`date:Y-m-d`) + uji regresi |
| B-9 | `SpJagaController@approveTte` menyimpan `penandatangan_pangkat_nrp` = `"{\} {\}"` | Backend | Diperbaiki |
| B-10 | Persetujuan TTE mobile tidak membubuhkan stempel QR (beda dengan web) | API | Diperbaiki (delegasi ke controller web) |
| B-11 | Menu Kas tampil di bottom bar untuk semua pengguna; kas termuat di dashboard semua pengguna | Android | Diperbaiki (menu & data mengikuti permission) |
| B-12 | Error jaringan ditutupi angka 0 (dashboard) | Android | Diperbaiki: state error + coba lagi, data lama dipertahankan saat refresh gagal |
| B-13 | Token kedaluwarsa tidak ditangani (layar kosong/berulang gagal) | Android | Diperbaiki: 401 → sesi diakhiri, kembali ke login dengan pesan |
| B-14 | CI membuat GitHub Release otomatis di setiap push `main` dengan tag `v1.0.<run>` (283 tag) dan menyalin APK yang sama ke 5 nama versi palsu | CI | Diperbaiki: release hanya dari tag `v*` dan hanya APK bertanda tangan |
| B-15 | `runBlocking` di interceptor OkHttp untuk membaca DataStore di setiap request | Android | Diperbaiki (token di memori) |
| B-16 | Rute dapat dibuka tanpa cek hak akses (deep link/menu) | Android | Diperbaiki: `Guard` per rute + halaman Akses Ditolak |
| B-17 | Migrasi membuat tabel yang sama lebih dari sekali (`visitor_logs`, `audit_logs`, `personnels`, `sc_submissions`, `sc_submission_logs`) → `php artisan test` bawaan gagal 24/25 | Backend | **Dicatat, tidak diubah** — mengubah riwayat migrasi produksi berisiko; perlu keputusan pemilik sistem |
| B-18 | Hak akses web masih memakai pengecualian berbasis nama personel (mis. kas) | Backend | Dipertahankan agar paritas, tetapi dipusatkan di `App\Support\MobileAccess` — direkomendasikan pindah ke kolom DB |

## 5. Arsitektur hak akses (RBAC) yang diterapkan

1. Server menghitung permission per pengguna di **satu tempat**: `App\Support\MobileAccess::permissions()`, meniru aturan web yang sudah berlaku.
2. Login, `/api/mobile/me`, dan `/api/mobile/dashboard` mengirim daftar permission resmi.
3. Aplikasi merender tab, menu, aksi (tambah/ubah/hapus/setujui/PDF), dan rute **hanya** berdasarkan daftar tersebut (`Feature` hanya metadata UI; tidak ada daftar menu per nama personel di aplikasi).
4. Setiap endpoint dijaga `mobile.auth:<permission>` sehingga backend tetap otoritas akhir walau UI dimanipulasi.
5. Perubahan oleh admin berlaku di request berikutnya (permission dihitung ulang tiap request) dan tersinkron saat aplikasi dibuka/dashboard di-refresh.

Detail per role: `docs/ROLE_PERMISSION_MATRIX.md`.

## 6. Daftar jaga (SP Jaga)

Data SP Jaga web disimpan sebagai lampiran tabel (perwira `tgl_1..tgl_7` dan divisi anggota). Backend kini mengubahnya menjadi jadwal per tanggal (`App\Support\DutyRoster`) tanpa mengarang data: setiap entri berasal dari `sp_jaga_perwiras`/`sp_jaga_anggotas`. Pencocokan "jadwal saya" memakai aturan yang sama dengan web (user_id, NRP, atau nama). SP berstatus selain *published* hanya terlihat oleh admin/Pasops, sama dengan web.

## 7. PDF

Semua dokumen memakai **template resmi server** (DomPDF) melalui endpoint `GET /api/mobile/pdf/*` yang memanggil controller web: SP Jaga, Buku Kas, Rekap Agenda Surat (filter kategori & tanggal), SKHPP, Rekening Koran Komandan. Aplikasi memvalidasi status HTTP, `Content-Type`, signature berkas `%PDF`, lalu menampilkan dengan `PdfRenderer` bawaan Android (zoom cubit/ketuk ganda/tombol, navigasi halaman, multi-halaman, orientasi campuran), menyimpan lewat Storage Access Framework (tanpa izin penyimpanan luas), membagikan/membuka lewat `FileProvider`.

## 8. Rencana & status pengujian

| Lapisan | Pengujian | Status |
|---|---|---|
| Backend | `tests/Feature/MobileApiSecurityTest.php` — 19 tes, 81 asersi (autentikasi, permission, aktivasi, presensi, notifikasi, daftar jaga, dashboard, PDF kas `%PDF`) | **Dijalankan & lulus** di lingkungan audit (PHP 8.3, SQLite) |
| Backend | Suite bawaan `php artisan test` | Gagal 24/25 **sebelum** perubahan (B-17) |
| Android | Unit test JVM `ApiContractTest` (fixture JSON dihasilkan dari backend asli) & `UtilitiesTest` | Ditulis; **belum dijalankan** (Gradle/Maven diblokir) → dijalankan CI |
| Android | Pemeriksaan sintaks (tree-sitter-kotlin), validasi import & named-argument terhadap API signature AndroidX versi yang dipakai | Dijalankan, bersih |
| Android | Build debug/release, lint, uji instrumentasi/manual di emulator | **Belum** — menunggu CI / perangkat |
| Role | Matriks skenario di `ROLE_PERMISSION_MATRIX.md` | Skenario backend otomatis lulus; skenario UI perlu uji manual |

## 9. Kendala yang membutuhkan pemilik sistem

1. **Deploy backend** (wajib sebelum APK v1.1.0 dipakai): `php artisan migrate` (tabel `personal_access_tokens`), lalu deploy kode. Pengguna dengan token lama yang tidak valid akan diminta masuk ulang.
2. **Ganti keystore Flutter** yang bocor dan rotasi kredensial terkait.
3. Tambahkan GitHub Secrets signing Android (`SISINDEN_KEYSTORE_BASE64`, `SISINDEN_KEYSTORE_PASSWORD`, `SISINDEN_KEY_ALIAS`, `SISINDEN_KEY_PASSWORD`). Catatan: APK v1.0.x ditandatangani debug key CI yang berganti-ganti, sehingga pengguna **perlu uninstall** sekali sebelum memasang APK bertanda tangan resmi.
4. Keputusan atas gap fitur (bagian 2) dan migrasi duplikat (B-17).
5. Pertimbangkan memindahkan pengecualian berbasis nama personel ke kolom hak akses di database.
