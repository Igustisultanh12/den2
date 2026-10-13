# DOKUMENTASI PEMULIHAN FITUR PRESENSI & ABSENSI KEDINASAN SISINDEN ANDROID

## 1. Ringkasan Eksekutif
Fitur **Presensi Kehadiran Kedinasan (Absensi)** telah dipulihkan secara penuh pada aplikasi Android native SISINDEN. Pemulihan ini mencakup integrasi backend REST API Laravel, model data DTO terstandarisasi, repository asinkronus berbasis Coroutine Flow, ViewModel reaktif MVI/MVVM, layar interaktif Jetpack Compose berstandar **Elder-Friendly Livin' by Mandiri**, serta penempatan navigasi terdepan pada NavigationBar utama dan kartu aksi cepat di Beranda.

---

## 2. Investigasi & Akar Masalah (Root Cause Analysis)
Berdasarkan audit komparasi antara versi website (https://sisinden.my.id) dan repositori Android:
1. **Ketersediaan di Web**: 
   - Model Eloquent `App\Models\Attendance` dan tabel basis data `attendances` telah aktif di backend.
   - Kolom basis data mencakup: `user_id`, `date`, `time_in`, `time_out`, `status`, `notes`, `latitude`, `longitude`, `location_name`, `device_model`, dan `photo`.
2. **Ketiadaan di REST API Mobile**:
   - Saat modul REST API seluler (`/api/mobile/*`) diimplementasikan di `routes/web.php`, endpoint untuk presensi (`/api/mobile/attendances`) tidak didaftarkan.
3. **Ketiadaan di Android**:
   - Akibat endpoint backend tidak tersedia, repositori Android sebelumnya sama sekali tidak memiliki DTO `AttendanceDto`, deklarasi Retrofit API, modul layar `AttendanceScreen`, maupun rute pada `NavRoutes.kt`.
   - Modul ini tidak tampak di antarmuka bukan karena kesalahan sembunyi peran (*hidden by role*), melainkan karena belum pernah dihubungkan ke arsitektur Android.

---

## 3. Implementasi Backend REST API (`routes/web.php`)

### Endpoint 1: Ambil Status & Riwayat Presensi
- **Metode & URL**: `GET /api/mobile/attendances`
- **Autentikasi**: Sanctum Token / Header `Authorization: Bearer <token>`
- **Payload Respons**:
```json
{
  "status": "success",
  "today_attendance": {
    "id": 14,
    "user_id": 1,
    "date": "2026-10-13",
    "time_in": "07:30:00",
    "status": "hadir",
    "location_name": "Mako Denintel Kodaeral V, Surabaya",
    "latitude": -7.2053,
    "longitude": 112.7358,
    "device_model": "Android Device",
    "notes": "Apel pagi dinas"
  },
  "officer_stats": {
    "total_attended_today": 25,
    "total_active_personnel": 30
  },
  "attendances": [ ... ]
}
```

### Endpoint 2: Kirim / Perbarui Presensi Kehadiran
- **Metode & URL**: `POST /api/mobile/attendances`
- **Validasi**:
  - `status`: wajib, salah satu dari `hadir`, `dinas_luar`, `piket`, `izin`.
  - `latitude`, `longitude`: opsional (desimal koordinat GPS).
  - `location_name`: opsional (string lokasi kedinasan).
  - `device_model`: opsional (identifikasi perangkat).
  - `notes`: opsional (keterangan penugasan dinas).
  - `photo`: opsional (format base64 string jika melampirkan foto dinas).
- **Logika Penyimpanan**: Menggunakan `Attendance::updateOrCreate` berdasar `['user_id' => $user->id, 'date' => $today]` untuk memastikan integritas data satu rekam presensi dinas per hari per personel tanpa duplikasi.

---

## 4. Implementasi Arsitektur Android Native

### A. Data Layer (`Models.kt`, `SindenApiService.kt`, `SindenRepository.kt`)
- `AttendanceDto`: Pemetaan JSON dengan `@SerializedName` lengkap, mencakup properti terkomputasi `displayStatus` (Hadir Kantor, Dinas Luar, Piket Jaga, Izin Dinas) dan `displayTime` (format `HH:mm WIB`).
- `AttendanceResponse`: Pembungkus status, presensi hari ini, statistik komandan, dan daftar riwayat 30 hari.
- `CreateAttendanceRequest`: Objek permintaan presensi mandiri.
- `SindenApiService`: Deklarasi `@GET("/api/mobile/attendances")` dan `@POST("/api/mobile/attendances")`.
- `SindenRepository`: Penanganan IO Dispatchers dengan `NetworkResult` tertutup (*sealed class*).

### B. Presentation Layer (`AttendanceViewModel.kt`, `AttendanceScreen.kt`)
- **Desain Terinspirasi Livin' by Mandiri Elder-Friendly**:
  - **Palet Warna Taktil**: Latar belakang kontras tinggi, kartu bergradien Deep Navy ke Royal Blue (`#002244` -> `#003D79` -> `#0056B3`), aksen Golden Amber (`#F9A825`).
  - **Hero Status Banner**: Menampilkan status kehadiran hari ini secara jelas dengan jam masuk berukuran besar (headline large), lokasi terdeteksi, dan badge status terverifikasi.
  - **Tombol Pilihan Status 4 Kuadran**: Tombol sentuh taktil ukuran 48-52dp dengan umpan balik visual instan untuk `Hadir Kantor`, `Dinas Luar`, `Piket Jaga`, dan `Izin Dinas`.
  - **Deteksi Koordinat Lokasi**: Kartu visual dengan ikon pangkalan kedinasan.
  - **Tombol Aksi Utama**: Tombol eksekusi selebar layar (54dp) yang mudah ditekan dengan status progres pemuatan.
  - **Daftar Riwayat 30 Hari**: Kartu riwayat presensi dengan ikon kode warna status kedinasan.

### C. Navigasi Terpadu (`NavRoutes.kt`, `SindenNavGraph.kt`, `DashboardScreen.kt`)
- `NavRoute.Attendance` didaftarkan secara permanen.
- **Bilah Navigasi Bawah**: Menu **Presensi** ditempatkan sebagai tab permanen ke-2 di samping Beranda, Kas, Surat, dan Setelan.
- **Aksi Cepat Beranda**: Kartu banner presensi cepat dan tombol modul kedinasan di Beranda langsung membuka modul absensi.

---

## 5. Verifikasi & Pengujian
- Unit Test `testAttendanceResponseParsing()`: Berhasil memvalidasi parsing JSON respons presensi.
- Unit Test `testCreateAttendanceRequest()`: Berhasil memvalidasi serialisasi permintaan kirim presensi.
- Unit Test `testNavRoutesUniqueness()`: Berhasil memvalidasi rute navigasi unik tanpa konflik.
- PHP Syntax Check: File `routes/web.php` lolos tanpa kesalahan sintaks.
