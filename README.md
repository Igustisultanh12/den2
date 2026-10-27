# SISINDEN - Sistem Informasi & Surat Intelijen
### Detasemen Intelijen Koarmada 2 (Denintel Koarmada 2)

Repositori resmi sistem kedinasan **SISINDEN** untuk **Detasemen Intelijen Koarmada 2** (Komando Armada II TNI AL). Repositori ini memuat backend web dinas terintegrasi (Laravel + Inertia Vue 3), daemon WhatsApp Gateway berbasis Node.js & Baileys, serta aplikasi mobile (Android Native Kotlin Compose & Flutter).

---

## 📋 Daftar Isi
1. [Arsitektur Sistem & Spesifikasi](#-arsitektur-sistem--spesifikasi)
2. [Hierarki Hak Akses (Role)](#-hierarki-hak-akses-role)
3. [Akun Bawaan (Default Credentials)](#-akun-bawaan-default-credentials)
4. [Perubahan & Pembersihan Fitur (Perbedaan dari Repo Lama)](#-perubahan--pembersihan-fitur)
5. [Panduan Lengkap Deployment ke aaPanel](#-panduan-lengkap-deployment-ke-aapanel)
   - [Langkah 1: Prasyarat Server & Instalasi aaPanel](#langkah-1-prasyarat-server--instalasi-aapanel)
   - [Langkah 2: Instalasi Komponen & Ekstensi di aaPanel](#langkah-2-instalasi-komponen--ekstensi-di-aapanel)
   - [Langkah 3: Konfigurasi PHP 8.2 (Disable Functions & Limits)](#langkah-3-konfigurasi-php-82)
   - [Langkah 4: Tambah Website & Unduh Kode Sumber](#langkah-4-tambah-website--unduh-kode-sumber)
   - [Langkah 5: Konfigurasi Environment (.env)](#langkah-5-konfigurasi-environment-env)
   - [Langkah 6: Instalasi Dependensi PHP & Kompilasi Aset Frontend](#langkah-6-instalasi-dependensi-php--kompilasi-aset-frontend)
   - [Langkah 7: Migrasi Database & Seeder](#langkah-7-migrasi-database--seeder)
   - [Langkah 8: Konfigurasi Nginx Virtual Host & SSL](#langkah-8-konfigurasi-nginx-virtual-host--ssl)
   - [Langkah 9: Konfigurasi WhatsApp Gateway (PM2 / Supervisor)](#langkah-9-konfigurasi-whatsapp-gateway)
   - [Langkah 10: Konfigurasi Cron Job Laravel Scheduler](#langkah-10-konfigurasi-cron-job-laravel-scheduler)
   - [Langkah 11: Hak Akses File & Direktori (Permissions)](#langkah-11-hak-akses-file--direktori)
6. [Aplikasi Android Native](#-aplikasi-android-native)
7. [Troubleshooting & Solusi Kendala](#-troubleshooting--solusi-kendala)

---

## 🏗 Arsitektur Sistem & Spesifikasi

- **Backend:** Laravel 11 (PHP 8.2+)
- **Frontend Web:** Vue 3, Inertia.js v2, Tailwind CSS, Vite
- **Database:** MySQL 8.0+ / MariaDB 10.11+
- **Cache & Realtime:** Redis / Ably / Pusher
- **WhatsApp Gateway Daemon:** Node.js (v20+ LTS) + Baileys Multi-Device Socket
- **Mobile Native:** Android Native (Kotlin Jetpack Compose, Material Design 3)
- **Mobile Hybrid:** Flutter (Dart)

---

## 👥 Hierarki Hak Akses (Role)

Sistem SISINDEN Denintel Koarmada 2 memiliki 4 tingkatan hak akses utama yang terisolasi dengan ketat:

| Role | Slug Sistem | Hak Akses & Wewenang | Batasan |
| :--- | :--- | :--- | :--- |
| **Super Admin** | `super_admin` | **Akses Total (Unrestricted Master).** Mengakses semua modul operasional dinas, pengaturan identitas sistem (`/settings`), konfigurasi API Gateway, cek performa & kesehatan server (`/admin/system-check`), pemantauan Laravel logs (`/admin/laravel-logs`), serta manajemen WhatsApp Gateway. | Tidak ada batasan. |
| **Admin** | `admin` | **Operasional Kedinasan Penuh.** Mengelola surat dinas, buku agenda surat, pengajuan & cetak SKHPP, penjadwalan SP Jaga Siaga Sintel, absensi/presensi dinas, log kegiatan personel, catatan pelanggaran prajurit, dan manajemen akun pengguna. | **DIBATASI (403 Forbidden):** Tidak dapat mengakses Pengaturan Sistem (`/settings`), Konfigurasi API, Cek Sistem & Server, maupun Log Teknis Server. |
| **Personel** | `personel` | **Prajurit / Operator Reguler.** Melakukan presensi harian berbasis geolokasi & swafoto, melihat jadwal dinas jaga jaga siaga, melihat agenda surat, dan mengajukan permohonan dinas pribadi. | Terbatas pada modul operasional perorangan. |
| **Personel Sintel** | `personel_sintel` | **Operator Staf Intelijen.** Memiliki akses operasional intelijen, monitoring pelacakan Security Clearance (SC), validasi data intel, dan verifikasi berkas SKHPP. | Tidak dapat mengakses konfigurasi sistem root. |

---

## 🔑 Akun Bawaan (Default Credentials)

Setelah menjalankan `php artisan db:seed`, akun bawaan berikut siap digunakan:

| Role | Email Login | Password Default | Akses Spesifik |
| :--- | :--- | :--- | :--- |
| **Super Admin** | `superadmin@mail.com` | `password123` | Master Web + Konfigurasi Server + WhatsApp Gateway + API |
| **Admin** | `admin@mail.com` | `password123` | Operasional Surat, SKHPP, SP Jaga, Personel, Users |
| **Personel** | `personel@mail.com` | `password123` | Presensi, Tugas Jaga, Agenda Diri |
| **Personel Sintel** | `sintel@mail.com` | `password123` | Pengolahan Data Intelijen, Tracking SC, SKHPP |

> **PERINGATAN KEAMANAN:** Segera ubah kata sandi akun default di atas setelah sistem terpasang pada lingkungan produksi publik!

---

## ✂ Perubahan & Pembersihan Fitur

Dibandingkan dengan repositori lama, repositori `den2` ini telah disesuaikan dan dibersihkan dari modul yang tidak lagi digunakan:
1. **Perubahan Identitas Satuan:** Seluruh identitas satuan telah diselaraskan dari *Denintel Kodaeral V* menjadi **Denintel Koarmada 2** (*Detasemen Intelijen Koarmada 2* / *Komando Armada II*), termasuk kop surat resmi PDF (SKHPP & SP Jaga Siaga).
2. **Pembersihan Fitur WA Web:** Modul multi-user WhatsApp Web (`/whatsapp-web`) telah dihapus secara menyeluruh. Layanan pesan otomatis kedinasan & OTP dialihkan melalui daemon mandiri WhatsApp Gateway (Baileys) yang statusnya dikontrol langsung oleh Super Admin.
3. **Pembersihan Fitur Keuangan Tidak Terpakai:** 
   - Modul Buku Kas (`/cash`) telah dihapus.
   - Modul Kas Unit Teknis (`/technical-cash`) telah dihapus.
   - Modul Rekening Ibu Beti (`/rekening-ibu-beti`) telah dihapus.
   - Kolom basis data `can_access_technical_cash` dan `can_access_ibu_beti` telah dieliminasi.

---

## 🚀 Panduan Lengkap Deployment ke aaPanel

### Langkah 1: Prasyarat Server & Instalasi aaPanel

1. Gunakan VPS atau Dedicated Server bersih dengan OS **Ubuntu 22.04 LTS / 24.04 LTS** (direkomendasikan) atau **Debian 12**.
2. Masuk ke terminal VPS via SSH sebagai `root`:
   ```bash
   ssh root@ip_server_anda
   ```
3. Pasang aaPanel versi terbaru:
   ```bash
   # Untuk Ubuntu / Deepin:
   wget -O install.sh http://www.aapanel.com/script/install-ubuntu_6.0_en.sh && sudo bash install.sh aapanel
   ```
4. Catat URL panel, username, dan password yang tampil di terminal, lalu buka di browser.

---

### Langkah 2: Instalasi Komponen & Ekstensi di aaPanel

Buka menu **App Store** di aaPanel, lalu pasang paket berikut:
1. **Nginx** (versi 1.24 atau 1.26 - pilih metode *Fast*)
2. **MySQL** (versi 8.0 - pilih metode *Fast*)
3. **PHP 8.2** (atau PHP 8.3)
4. **Node.js Version Manager** (pilih dan pasang Node.js LTS v20.x atau v22.x)
5. **PM2 Manager** (atau **Supervisor Manager**) untuk menjalankan daemon WhatsApp Gateway
6. **Redis** (opsional tapi disarankan untuk cache antrean)

#### Memasang Ekstensi PHP 8.2:
Masuk ke menu **App Store** > cari **PHP 8.2** > klik **Settings** > tab **Install extensions**:
- Centang dan instal:
  - `fileinfo` (Wajib untuk upload berkas dokumen dinas & scan PDF)
  - `redis` (Untuk session & antrean cepat)
  - `opcache` (Untuk performa eksekusi PHP)
  - `exif` (Untuk pembacaan metadata foto presensi lapangan)
  - `zip` (Untuk ekstraksi dan backup data)
  - `intl` (Untuk penanggalan format Indonesia baku)
  - `pdo_mysql` (Sudah terpasang secara default)
  - `gd` atau `imagick` (Untuk rendering stempel & QR code)

---

### Langkah 3: Konfigurasi PHP 8.2

Masih di jendela pengaturan **PHP 8.2**:

#### A. Hapus Fungsi yang Diblokir (Disable Functions):
Masuk ke tab **Disabled functions**. Beberapa fungsi penting berikut wajib **DIHAPUS** dari daftar disable agar Laravel dapat menjalankan artisan, symlink, dan process background:
- `putenv`
- `proc_open`
- `pcntl_signal`
- `pcntl_alarm`
- `symlink`

#### B. Optimasi Nilai `php.ini` (Configuration File):
Masuk ke tab **Configuration file** atau **Performance tuning**:
```ini
upload_max_filesize = 100M
post_max_size = 100M
memory_limit = 512M
max_execution_time = 300
max_input_time = 300
default_socket_timeout = 60
```
Klik **Save**, lalu masuk ke tab **Service** dan klik **Restart** PHP-8.2.

---

### Langkah 4: Tambah Website & Unduh Kode Sumber

1. Di menu utama aaPanel, klik menu **Website** > klik tombol **Add site**.
2. Isi formulir pembuatan domain:
   - **Domain name:** Isi dengan domain Anda (misal: `sisinden.koarmada2.tni.mil.id` atau `den2.domainanda.com`).
   - **Description:** `SISINDEN Denintel Koarmada 2`
   - **Root directory:** `/www/wwwroot/den2`
   - **Database:** Pilih *MySQL*, Set karakter `utf8mb4`, catat nama database, user, dan password.
   - **PHP Version:** Pilih `PHP-82`.
3. Buka terminal SSH VPS Anda dan arahkan ke direktori website:
   ```bash
   cd /www/wwwroot
   # Jika folder /www/wwwroot/den2 sudah terisi file default index.html, hapus terlebih dahulu:
   rm -rf /www/wwwroot/den2
   
   # Clone repositori den2 langsung dari GitHub:
   git clone https://github.com/Igustisultanh12/den2.git /www/wwwroot/den2
   cd /www/wwwroot/den2
   ```

---

### Langkah 5: Konfigurasi Environment (.env)

1. Salin berkas konfigurasi template:
   ```bash
   cp .env.example .env
   ```
2. Buka berkas `.env` menggunakan nano atau editor aaPanel:
   ```bash
   nano .env
   ```
3. Sesuaikan baris-baris kunci berikut:
   ```env
   APP_NAME="SI SINDEN - DENINTEL KOARMADA 2"
   APP_ENV=production
   APP_KEY=
   APP_DEBUG=false
   APP_URL=https://domain-anda.com

   LOG_CHANNEL=stack
   LOG_DEPRECATIONS_CHANNEL=null
   LOG_LEVEL=debug

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=nama_database_anda
   DB_USERNAME=user_database_anda
   DB_PASSWORD=password_database_anda

   BROADCAST_DRIVER=log
   CACHE_DRIVER=file
   FILESYSTEM_DISK=public
   QUEUE_CONNECTION=sync
   SESSION_DRIVER=file
   SESSION_LIFETIME=120

   # WhatsApp Gateway Node.js Daemon (Local Port 3000)
   WA_GATEWAY_URL=http://localhost:3000
   WA_GATEWAY_API_KEY=denintel-koarmada2-secure-key
   ```
4. Simpan perubahan (`Ctrl + O`, `Enter`, lalu `Ctrl + X`).

---

### Langkah 6: Instalasi Dependensi PHP & Kompilasi Aset Frontend

Jalankan perintah berikut di folder `/www/wwwroot/den2`:

```bash
# 1. Pasang dependensi PHP melalui Composer (Mode Production)
composer install --no-dev --optimize-autoloader

# 2. Generate Application Key Laravel
php artisan key:generate

# 3. Buat Symbolic Link Direktori Storage (Wajib untuk akses foto & dokumen PDF)
php artisan storage:link

# 4. Instal dependensi Node.js & kompilasi aset Frontend Vue Inertia
npm install
npm run build
```

---

### Langkah 7: Migrasi Database & Seeder

Eksekusi migrasi tabel dan seeding data inisial:

```bash
# Jalankan migrasi database
php artisan migrate --force

# Jalankan seeder akun & konfigurasi awal dinas
php artisan db:seed --force
```

Perintah di atas akan membentuk seluruh tabel kedinasan dan membuat akun default:
- **Super Admin:** `superadmin@mail.com` (`password123`)
- **Admin:** `admin@mail.com` (`password123`)
- **Personel:** `personel@mail.com` (`password123`)
- **Personel Sintel:** `sintel@mail.com` (`password123`)

---

### Langkah 8: Konfigurasi Nginx Virtual Host & SSL

Buka menu **Website** di aaPanel > klik nama website Anda > atur pengaturan berikut:

#### A. Atur Running Directory (Site Directory):
- Masuk ke tab **Site directory**.
- **Running directory:** Pilih `/public` (Sangat krusial untuk Laravel).
- Klik tombol **Save**.

#### B. Atur URL Rewrite (Routing Laravel):
- Masuk ke tab **URL rewrite**.
- Pilih template bawaan **Laravel 5** atau tempel konfigurasi berikut:
  ```nginx
  location / {
      try_files $uri $uri/ /index.php?$query_string;
  }
  ```
- Klik tombol **Save**.

#### C. Pasang Sertifikat SSL Gratis (Let's Encrypt):
- Masuk ke tab **SSL** > pilih **Let's Encrypt**.
- Centang nama domain Anda.
- Klik **Apply** untuk mengunduh dan memasang sertifikat otomatis.
- Aktifkan toggle **Force HTTPS** agar semua koneksi otomatis dialihkan ke HTTPS.

---

### Langkah 9: Konfigurasi WhatsApp Gateway (PM2 / Supervisor)

WhatsApp Gateway bertindak sebagai daemon latar belakang (background service) untuk pengiriman notifikasi surat perintah dinas, jadwal jaga siaga, warta intelijen, dan kode OTP.

#### A. Siapkan Dependensi WhatsApp Gateway:
```bash
cd /www/wwwroot/den2/wa-gateway
npm install
```

#### B. Jalankan Menggunakan PM2 (Rekomendasi):
1. Buka menu **App Store** di aaPanel > buka **PM2 Manager**.
2. Klik **Add Project**:
   - **Startup file:** `/www/wwwroot/den2/wa-gateway/server.js`
   - **Run directory:** `/www/wwwroot/den2/wa-gateway`
   - **Project name:** `sinden-wa-gateway`
   - **Port:** `3000`
3. Klik **Submit**. Service akan aktif dan berjalan di port `3000`.

*Alternatif via Terminal CLI:*
```bash
cd /www/wwwroot/den2/wa-gateway
pm2 start server.js --name "sinden-wa-gateway"
pm2 save
pm2 startup
```

Super Admin dapat memeriksa status koneksi barcode QR WhatsApp melalui menu **Cek Sistem / Status WA Gateway** di dalam web portal SINDEN.

---

### Langkah 10: Konfigurasi Cron Job Laravel Scheduler

Sistem presensi dinas, pengingat jadwal jaga siaga, dan pembersihan log otomatis membutuhkan scheduler Laravel yang berjalan setiap menit.

1. Buka menu **Cron** di navigasi kiri aaPanel.
2. Tambahkan tugas terjadwal baru:
   - **Type of Task:** Shell Script
   - **Name of Task:** `Laravel Scheduler SINDEN`
   - **Period:** `N Minutes` > isi `1` Minute (atau format `* * * * *`)
   - **Script content:**
     ```bash
     php /www/wwwroot/den2/artisan schedule:run >> /dev/null 2>&1
     ```
3. Klik **Add task**.

---

### Langkah 11: Hak Akses File & Direktori (Permissions)

Pastikan seluruh file dimiliki oleh pengguna web server Nginx (`www:www`) agar unggah file dan logging berjalan tanpa kendala permission:

```bash
cd /www/wwwroot/den2
chown -R www:www /www/wwwroot/den2
chmod -R 755 /www/wwwroot/den2
chmod -R 775 /www/wwwroot/den2/storage
chmod -R 775 /www/wwwroot/den2/bootstrap/cache
```

Jika terjadi perubahan konfigurasi di masa mendatang, bersihkan cache aplikasi:
```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 📱 Aplikasi Android Native

Sistem terhubung langsung dengan aplikasi Android resmi berbasis **Jetpack Compose** yang terletak di folder `android/`:
- **Paket:** `id.my.sisinden.app`
- **Minimum SDK:** Android 8.0 (Oreo / API 26)
- **Target SDK:** Android 14+ (API 34/35)

### Mengompilasi Berkas APK Release:
```bash
cd /www/wwwroot/den2/android
chmod +x gradlew
./gradlew assembleRelease
```
Berkas APK output akan berada di: `android/app/build/outputs/apk/release/app-release.apk`.

Endpoint API mobile terdaftar secara aman di `/api/mobile/*` dengan otentikasi Sanctum Bearer Token dan pengamanan hak akses berbasis `MobileAccess.php`.

---

## 🛠 Troubleshooting & Solusi Kendala

| Gejala Kendala | Penyebab | Solusi |
| :--- | :--- | :--- |
| **Error 500 saat pertama kali dibuka** | Direktori storage belum writable atau APP_KEY kosong | Jalankan `php artisan key:generate` dan `chmod -R 775 storage bootstrap/cache`. |
| **Halaman 404 pada rute selain `/`** | Nginx URL Rewrite belum dipasang | Buka aaPanel > Website > URL rewrite > Masukkan konfigurasi `try_files $uri $uri/ /index.php?$query_string;`. |
| **Gambar logo atau lampiran tidak muncul** | Symlink storage belum dibuat | Jalankan `php artisan storage:link` dari terminal. |
| **Admin menerima error 403 saat akses Setting** | Normal / Sesuai Aturan Sistem | Menu Pengaturan Sistem & Cek Sistem eksklusif untuk **Super Admin**. Gunakan akun Super Admin. |
| **WhatsApp Gateway tidak mengirim pesan** | Port 3000 belum menyala atau belum pairing QR | Cek log PM2: `pm2 logs sinden-wa-gateway` dan periksa status di menu Super Admin. |
| **Composer error: `proc_open has been disabled`** | Eksekusi fungsi ditahan oleh konfigurasi PHP aaPanel | Buka aaPanel > PHP 8.2 Settings > Disabled functions > Hapus `proc_open` dan `putenv`. |

---

## 📄 Hak Cipta & Lisensi
Sistem Informasi dan Surat Intelijen (SISINDEN) dikembangkan secara khusus untuk operasional kedinasan **Detasemen Intelijen Koarmada 2 (Denintel Koarmada 2)**. Seluruh hak cipta dan kepemilikan kode dilindungi undang-undang kedinasan TNI AL.

© 2026 Denintel Kodaeral V cc Letda (KC) I Gusti Sultan, A.Md.Kom
