# ANALISIS KOMPREHENSIF SISTEM INFORMASI SISINDEN
**Platform Sumber:** https://sisinden.my.id  
**Repositori:** https://github.com/Igustisultanh12/kantor.git  
**Target Aplikasi:** SISINDEN Android Native (Kotlin + Jetpack Compose Material 3)  
**Tanggal Analisis:** Oktober 2026  

---

## 1. Profil & Karakteristik Platform Sumber

### 1.1 Identitas Instansi
- **Nama Sistem:** SI SINDEN (Sistem Informasi Detasemen Intelijen)
- **Instansi Pemilik:** Detasemen Intelijen Komando Armada II (Denintel Koarmada 2)
- **Karakteristik Operasional:** Kedinasan militer dan intelijen matra laut yang membutuhkan kerahasiaan data berjenjang, kecepatan pelaporan, ketertiban administrasi surat dinas, serta pemantauan operasional lapangan.

### 1.2 Lingkungan Teknologi Web
- **Backend Framework:** Laravel 11 (PHP 8.2+) dengan arsitektur REST API lengkap di port 80/443 (HTTPS).
- **Frontend Web:** Inertia.js dengan Vue 3 dan Tailwind CSS.
- **Gateway Komunikasi:** Node.js Multi-Session WhatsApp Gateway (Baileys v6.7.x) di port 3000.
- **Basis Data:** MariaDB / MySQL dengan migrasi terstruktur (`users`, `settings`, `cash_logs`, `letters`, `letter_logs`, `skhpp_requests`, `commander_accounts`, `mitras`, `violations`, `signature_requests`, `wa_sessions`).

---

## 2. Struktur Navigasi & Modul Fungsional Website

Berdasarkan pembedahan kode sumber backend dan rute sistem (`routes/web.php`), berikut adalah pemetaan seluruh modul fungsional:

| Modul Kedinasan | Deskripsi Fungsional | Hak Akses | Status di Android Native |
|---|---|---|---|
| **Autentikasi & Profil** | Login NRP/Email/Password, sesi token Sanctum, manajemen hak akses personel | Semua Personel | Native Compose Screen |
| **Dashboard Operasional** | Rekap kas, surat masuk/keluar, SKHPP tertunda, notifikasi resmi, status server | Semua Personel | Native Compose Dashboard |
| **Agenda & Log Surat** | Pencatatan surat masuk, keluar, dan disposisi dinas beserta nomor agenda | Semua Personel | Native Compose Feature |
| **Penerbitan SKHPP** | Pengajuan dan verifikasi Surat Keterangan Hasil Penelitian Personel | Operasional & Komandan | Native Compose Feature |
| **Kas Denintel** | Pembukuan kas dinas, pencatatan kas masuk/keluar, laporan saldo berjalan | Petugas Keuangan & Admin | Native Compose Feature |
| **Kas Taktis Operasi** | Pengelolaan dana taktis operasi intelijen dan laporan peruntukan dana | Intel Operasi & Komandan | Native Compose Feature |
| **Rekening Komandan** | Pengawasan pergerakan mutasi rekening kedinasan komandan | Komandan & Admin | Native Compose Feature |
| **Mitra Intelijen** | Monitoring agen/jaringan mitra lapangan, status pembinaan dan pembayaran | Intelijen Lapangan | Native Compose Feature |
| **Tanda Tangan Digital** | Verifikasi dokumen dan persetujuan tanda tangan elektronik dinas | Komandan Denintel | Native Compose Feature |
| **Pelanggaran Personel** | Pencatatan rekam jejak pelanggaran disiplin dan kode etik personel | Provost & Pimpinan | Native Compose Feature |
| **Direktori Personel** | Kontak dinas, daftar anggota, korps, pangkat, NRP, dan status tugas | Semua Personel | Native Compose Feature |
| **Notifikasi Kedinasan** | Peringatan tugas baru, instruksi pimpinan, dan pengumuman kedinasan | Semua Personel | Native Compose Feature |
| **Pengaturan & Jaringan** | Pemilihan alamat server backend (Base URL), tema gelap/terang, info lisensi | Semua Personel | Native Compose Feature |

---

## 3. Analisis Mekanisme Autentikasi & Keamanan

### 3.1 Alur Autentikasi Resmi
1. **Endpoint:** `POST /api/mobile/login`
2. **Format Masukan:**
   ```json
   {
     "username": "NRP_ATAU_EMAIL",
     "password": "KATA_SANDI"
   }
   ```
3. **Mekanisme Verifikasi:**
   - Pencarian pengguna berdasarkan kolom `email` atau `nrp`.
   - Pengecekan kecocokan hash `password` menggunakan `Hash::check`.
   - Validasi status aktif akun (`is_active == 1`). Akun yang ditangguhkan ditolak dengan kode status 403.
4. **Pemberian Token:**
   - Laravel Sanctum mengeluarkan Bearer Token teks biasa (`plainTextToken`).
   - Token dikembalikan dalam payload respons JSON beserta objek `user` lengkap yang memuat profil dan hak akses (`can_access_*`).
5. **Penyimpanan Sisi Klien:**
   - Token disimpan secara terenkripsi/aman menggunakan Android Jetpack DataStore Preferences.
   - Tidak pernah disimpan dalam bentuk plaintext di SharedPreferences lama.

### 3.2 Keamanan Jaringan
- Menggunakan HTTPS dengan TLS 1.3 standar pada domain `sisinden.my.id`.
- Setiap permintaan HTTP yang memerlukan otentikasi wajib menyertakan header:
  ```http
  Authorization: Bearer <TOKEN>
  Accept: application/json
  User-Agent: SISINDEN-Android/1.0 (Kedinasan Android Native)
  ```
- Tidak melakukan bypass sertifikat SSL.

---

## 4. Pemetaan Endpoint API Resmi SINDEN

Semua endpoint berikut telah diverifikasi pada `routes/web.php` dan aktif di `https://sisinden.my.id`:

| Metode | Endpoint | Fungsi | Parameter / Payload |
|---|---|---|---|
| `GET` | `/api/mobile/config` | Konfigurasi server, logo, background, min version | Tidak ada |
| `POST` | `/api/mobile/login` | Login personel dan perolehan token | `username`, `password` |
| `GET` | `/api/mobile/cash` | Riwayat transaksi dan saldo kas Denintel | Header Token |
| `POST` | `/api/mobile/cash` | Tambah transaksi kas masuk / keluar | `type`, `amount`, `category`, `description` |
| `GET` | `/api/mobile/letter-logs` | Daftar log surat masuk, keluar, disposisi | `type`, `search` |
| `POST` | `/api/mobile/letter-logs` | Tambah catatan surat baru | `type`, `letter_number`, `sender`, `recipient`, `subject`, `date` |
| `GET` | `/api/mobile/skhpp` | Daftar pengajuan SKHPP | `status`, `search` |
| `POST` | `/api/mobile/skhpp` | Pengajuan SKHPP baru | `name`, `nrp`, `pangkat`, `keperluan` |
| `POST` | `/api/mobile/skhpp/{id}/verify` | Verifikasi SKHPP oleh pejabat berwenang | ID SKHPP |
| `GET` | `/api/mobile/commander-account` | Daftar transaksi rekening komandan | Header Token |
| `POST` | `/api/mobile/commander-account` | Tambah mutasi rekening komandan | `type`, `amount`, `description` |
| `GET` | `/api/mobile/mitra` | Daftar mitra intelijen dan status pembinaan | `search` |
| `POST` | `/api/mobile/mitra` | Tambah mitra baru | `name`, `phone`, `code_name`, `target_area` |
| `POST` | `/api/mobile/mitra/{id}/toggle-payment` | Toggle status pembayaran mitra | `status_payment` |
| `GET` | `/api/mobile/violations` | Rekapitulasi pelanggaran disiplin | `search` |
| `POST` | `/api/mobile/violations` | Catat pelanggaran personel | `user_id`, `violation_type`, `description`, `date` |
| `GET` | `/api/mobile/users` | Direktori personel terdaftar | Header Token |
| `GET` | `/api/mobile/signature-requests` | Daftar permohonan tanda tangan komandan | Header Token |
| `POST` | `/api/mobile/signature-requests/{id}/sign`| Tanda tangani dokumen | ID Request |
| `GET` | `/api/mobile/categories` | Daftar klasifikasi naskah kedinasan | Header Token |
| `GET` | `/api/mobile/technical-cash` | Riwayat kas operasional taktis | Header Token |
| `POST` | `/api/mobile/technical-cash` | Tambah kas taktis | `type`, `amount`, `description` |
| `GET` | `/api/mobile/notifications` | Notifikasi kedinasan terbaru | Header Token |
| `POST` | `/api/mobile/activities` | Pelaporan aktivitas harian personel | `title`, `description`, `date` |

---

## 5. Keputusan Desain UI/UX Android Native

### 5.1 Karakter Visual
- **Gaya Desain:** Modern Minimalism dengan Google Material 3 dan tipografi editorial militer bersih.
- **Palet Warna:**
  - **Primary:** Deep Teal / Emerald `#00A884` (melambangkan warna resmi SISINDEN dan kepresisian matra laut).
  - **Secondary:** Ocean Blue `#0284C7` (melambangkan kedalaman maritim).
  - **Surface Light:** Pure White `#FFFFFF` & Clean Neutral `#F8FAFC`.
  - **Surface Dark:** Obsidian Navy `#0B1120` & Midnight Slate `#0F172A`.
  - **Border / Outline:** Subtle Slate `#E2E8F0` (Light) / `#1E293B` (Dark).
  - **Success / Warning / Error:** Semantics Material 3 (`#10B981`, `#F59E0B`, `#EF4444`).
- **Komponen Interaktif:**
  - Bottom Navigation Bar dengan transisi halus antar tab utama.
  - Kartu dengan sudut melengkung 16dp (rounded-2xl) dan bayangan mikro (subtle elevation).
  - Indikator status berbentuk Pill Badge yang kontras dan jelas.
  - Skeleton loading berupa Shimmer Animation pada seluruh kartu daftar data.
  - Tombol aksi mengambang (Floating Action Button) untuk input cepat.
  - Halaman detail yang menyajikan data secara terstruktur dan mudah dibaca.
