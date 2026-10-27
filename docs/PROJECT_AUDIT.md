# AUDIT ARSITEKTUR DAN STRUKTUR PROYEK SISINDEN ANDROID

## 1. Identitas Sistem dan Repository
- Nama Proyek: SISINDEN (Sistem Informasi dan Surat Intelijen Denintel Koarmada 2)
- Jenis Aplikasi: Aplikasi Administrasi dan Operasional Kantor Kedinasan
- Repository GitHub: [https://github.com/Igustisultanh12/kantor.git](https://github.com/Igustisultanh12/kantor.git)
- Backend & Server Web: Laravel 11 (PHP 8.2), Inertia.js, Vue.js, Tailwind CSS di [https://sisinden.my.id](https://sisinden.my.id)
- Klien Mobile: Native Android (Kotlin, Jetpack Compose Material 3)

## 2. Struktur Proyek dan Framework
Aplikasi Android SISINDEN dikembangkan menggunakan native Android dengan stack teknologi:
- Bahasa Pemrograman: Kotlin 1.9.0
- UI Toolkit: Jetpack Compose (BOM 2024.06.00) dengan Material 3
- Asynchronous Engine: Kotlin Coroutines & StateFlow / SharedFlow
- Jaringan & REST Client: Retrofit 2.11.0 + OkHttp 4.12.0 + Gson Converter
- Penyimpanan Lokal: Jetpack DataStore Preferences & Android SharedPreferences
- Arsitektur: Clean Architecture MVVM (Model-View-ViewModel) dengan unidirectional data flow (UDF)
- Target SDK: Android 14 (API level 34)
- Minimum SDK: Android 8.0 Oreo (API level 26)

## 3. Direktori Kode Sumber Android
- Core DataStore & Prefs: `id.my.sisinden.app.core.data`
- Core Network & Endpoints: `id.my.sisinden.app.core.network`
- Core Notification Service: `id.my.sisinden.app.core.notification`
- Core UI Themes & Atoms: `id.my.sisinden.app.core.ui`
- Data Model & DTOs: `id.my.sisinden.app.data.model`
- Repository Layer: `id.my.sisinden.app.data.repository`
- Fitur UI Screens & ViewModels:
  - Autentikasi: `id.my.sisinden.app.ui.auth`
  - Beranda Eksekutif: `id.my.sisinden.app.ui.dashboard`
  - Kas Satuan & Kas Taktis: `id.my.sisinden.app.ui.cash`
  - Agenda Surat Dinas: `id.my.sisinden.app.ui.letters`
  - Layanan SKHPP: `id.my.sisinden.app.ui.skhpp`
  - Mitra Intelijen: `id.my.sisinden.app.ui.mitra`
  - TTE Komandan: `id.my.sisinden.app.ui.signature`
  - Disiplin & Pelanggaran: `id.my.sisinden.app.ui.violations`
  - Direktori Personel: `id.my.sisinden.app.ui.personnel`
  - Presensi Kehadiran Kedinasan: `id.my.sisinden.app.ui.attendance`
  - Pengaturan Akun: `id.my.sisinden.app.ui.settings`

## 4. Mekanisme Otentikasi dan Otoritas
- Otentikasi mobile menggunakan token Bearer kedinasan yang disimpan pada DataStore terenkripsi.
- Otoritas akses modul dikelola berbasis peran kedinasan:
  - Admin / Superadmin
  - Komandan Detasemen (Danden)
  - Bendahara / Perwira Keuangan
  - Dan Unit Teknis
  - Anggota Personel Reguler

## 5. Ringkasan Temuan Audit Awal
1. Anomali nilai Rp1 yang tersimpan sebagai Rp0: Disebabkan oleh ketidaksesuaian kontrak data antara klien Android (mengirimkan properti amount dan type) dan controller backend (hanya membaca kolom debit dan credit secara spesifik).
2. Keterbatasan operasi CRUD: Pada versi awal, aksi Edit dan Hapus pada modul kas, kas teknis, dan mitra belum terhubung ke endpoint backend.
3. Keterkaitan hak akses menu: Tombol aksi cepat Kas Denintel dan Kas Teknis sempat terkendala validasi izin lokal yang terlalu restriktif sebelum data profil personel selesai dimuat secara asynchronous.
4. Perilaku notifikasi berulang: Notifikasi kedinasan memicu nada dering dan getaran setiap kali aplikasi dibuka karena ketiadaan pencatatan ID notifikasi yang persisten di penyimpanan perangkat.
5. Ketiadaan Fitur Absensi di Android: Pada versi awal, endpoint `/api/mobile/attendances` belum didaftarkan di backend dan belum ada komponen UI Presensi di Android. Telah dipulihkan secara penuh di v1.0.2 dengan desain Livin' Elder-Friendly.
