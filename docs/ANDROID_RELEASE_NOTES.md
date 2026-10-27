# SISINDEN Android v1.3.1 (versionCode 12)

Rilis pertama yang ditandatangani **kunci rilis resmi SISINDEN Denintel Koarmada 2** (SHA-256 sertifikat `f90157b7e13639cafa1352209f8e4fc5a4d4d9fdf8aa2af2ac8c891dede973ee`). Isi fitur sama dengan v1.3.0.

> ⚠️ **Pasang sekali dengan uninstall:** versi sebelumnya (v1.0–v1.3.0) ditandatangani kunci debug, sehingga Android menolak pembaruan langsung. Uninstall aplikasi SISINDEN lama, lalu pasang APK ini. Setelah itu pembaruan berikutnya (termasuk dari **Pengaturan web → Unggah APK**) terpasang tanpa uninstall.

---
# SISINDEN Android v1.3.0 (versionCode 11)

Notifikasi push Firebase dan pembaruan aplikasi dari Pengaturan web (diambil dari SISINDEN Kodaeral V).

> ⚠️ **Wajib di server:** `git pull`, `php artisan migrate --force` (tabel `device_tokens`, kolom `settings.value` menjadi TEXT), `npm run build`. Untuk unggah APK, naikkan `upload_max_filesize` & `post_max_size` PHP serta `client_max_body_size` Nginx (mis. 100M) di aaPanel.

## Yang baru

- **Notifikasi push (Firebase Cloud Messaging)** — warta dinas & Chat Dinas tetap muncul walau aplikasi ditutup. Admin cukup mengunggah `google-services.json` dan service account Firebase di **Pengaturan web → Notifikasi Push Aplikasi**; aplikasi mengambil konfigurasinya dari server tanpa perlu build ulang APK. Kartu **Notifikasi push** di Pengaturan aplikasi untuk uji kirim ke HP sendiri.
- **Pembaruan aplikasi dari Pengaturan web** — Super Admin mengunggah APK di **Pengaturan → Pembaruan Aplikasi Android**. Versi dibaca otomatis dari APK, APK aplikasi lain/versi lebih lama ditolak, dan personel menerima pemberitahuan. Di HP: dialog "Pembaruan tersedia", unduh dengan verifikasi SHA-256 + tanda tangan, pasang satu ketukan; opsi **Perbarui otomatis** (Wi-Fi).

## Keamanan

- Pengaturan yang dibagikan ke setiap halaman web kini disaring: secret Google OAuth, sertifikat Agora, dan kredensial Firebase tidak lagi terkirim ke browser (sebelumnya terlihat di sumber halaman login).
- Service account Firebase disimpan terenkripsi; APK pembaruan disimpan di penyimpanan privat (tidak dapat diakses lewat /storage).

---

# SISINDEN Android v1.2.0 (versionCode 10)

Rilis fitur pelacakan lokasi realtime dan presensi foto senyum.

> ⚠️ **Wajib sebelum dipakai:** perbarui server SISINDEN ke `main` terbaru lalu jalankan `php artisan migrate --force` (tabel `personnel_locations`, `personnel_location_histories`, kolom `attendances.smile_score`). Pastikan scheduler Laravel (`php artisan schedule:run` tiap menit) aktif agar jejak lokasi > 30 hari dibersihkan otomatis.

## Yang baru

- **Akses lokasi wajib** — aplikasi tidak dapat dipakai sebelum izin lokasi presisi, izin **"Izinkan sepanjang waktu"**, dan GPS perangkat aktif. Layar panduan menunjukkan langkah yang belum terpenuhi.
- **Pelacakan lokasi realtime** — selama login, posisi personel dikirim ke server sekitar tiap 30 detik (tetap berjalan saat aplikasi di latar, ditandai notifikasi permanen "Pelacakan lokasi dinas aktif"). Titik yang gagal terkirim saat sinyal buruk dikirim ulang otomatis. Pelacakan berhenti saat logout.
- **Pantau Personel** (Admin, Super Admin, Komandan) — daftar posisi terakhir seluruh personel dengan status online/offline, waktu pembaruan, akurasi, dan baterai; ketuk untuk membuka di aplikasi peta. Di web tersedia peta realtime (menu **Sistem → Pantau Personel**) lengkap dengan jejak 1–24 jam.
- **Presensi foto senyum** — sama seperti di web: kamera depan memotret otomatis setelah senyum ≥ 50% terdeteksi (deteksi on-device, tanpa internet). Lokasi GPS dan foto senyum **wajib**; skor senyum tersimpan di riwayat.

## Perubahan server

- Presensi web & aplikasi kini menolak pengiriman tanpa koordinat GPS atau tanpa foto senyum yang valid.
- Endpoint baru: `POST /api/mobile/location`, `GET /api/mobile/tracking`, `GET /api/mobile/tracking/{id}/history`.

## Catatan

- APK hanya untuk HP berprosesor ARM (hampir semua ponsel Android).
- Ukuran APK ±32 MB karena model deteksi wajah dibundel agar berjalan tanpa Google Play Services.

---

# SISINDEN Android v1.1.2 (versionCode 9)

Rilis penyelarasan identitas satuan.

- Identitas satuan diseragamkan menjadi **Denintel Koarmada 2**; kop surat PDF dari server kini "KOMANDO ARMADA II — DETASEMEN INTELIJEN" (sebelumnya sebagian masih "Komando Daerah TNI Angkatan Laut V").
- Keterangan hak cipta ditampilkan di layar Masuk dan Pengaturan: © Denintel Kodaeral V cc Letda (KC) I Gusti Sultan, A.Md.Kom.

---

# SISINDEN Android v1.1.1 (versionCode 8)

Rilis pemeliharaan untuk Denintel Koarmada 2.

> ⚠️ **Wajib sebelum dipakai:** perbarui server SISINDEN ke `main` terbaru lalu jalankan `php artisan migrate --force`. Migrasi baru memperbaiki kolom `letter_logs.sequence` (string) dan kolom lama `visitor_logs` yang membuat login gagal pada instalasi baru.

## Yang baru

- **Logo baru** — ikon aplikasi Square Kufic Hanoman dan identitas SISINDEN v1.1.1.
- **Android App Bundle (.aab)** untuk Play Store disertakan di samping APK.
- **Modul Buku Kas & Kas Unit Teknis dihapus** dari server; menu terkait otomatis tidak tampil di aplikasi.

## Perbaikan server (diperlukan aplikasi)

- Seluruh migrasi database kini berjalan bersih dari nol maupun rollback, di MySQL/MariaDB dan SQLite.
- Login web pada instalasi baru tidak lagi error 500 (`visitor_logs.device_type`).
- Pencarian global tidak lagi error karena tautan ke modul kas yang sudah dihapus.

## Status pengujian

- Backend: 43 feature test lulus (termasuk kontrak & keamanan API mobile).
- Android: unit test, build debug/release APK dan AAB dijalankan oleh GitHub Actions.

Checksum SHA-256 tersedia di berkas `SHA256SUMS.txt` pada rilis ini.

---

# SISINDEN Android v1.1.0 (versionCode 7)

Rilis desain ulang total aplikasi Android SISINDEN, dengan hak akses mengikuti pengaturan Admin, **Daftar Jaga** di beranda, dan pratinjau/unduh PDF resmi.

> ⚠️ **Wajib sebelum dipakai:** server SISINDEN harus diperbarui ke branch yang sama dan menjalankan `php artisan migrate`. Aplikasi ini memakai token Sanctum dan endpoint baru (`/api/mobile/me`, `/dashboard`, `/duty-roster`, `/pdf/*`).
>
> Pemasangan di atas v1.0.x: versi lama ditandatangani kunci debug CI yang berganti-ganti, sehingga Android dapat menolak pembaruan. Uninstall versi lama terlebih dahulu bila muncul pesan "aplikasi tidak terpasang".

## Yang baru

- **Tampilan baru menyeluruh** — palet "Samudra" (biru angkatan laut + teal + amber), tipografi berjenjang, kartu konsisten, mode terang/gelap/ikut sistem, animasi ringan, skeleton loading, empty/error state yang jelas, dan halaman "Akses ditolak".
- **Beranda sebagai pusat aktivitas** — sapaan sesuai waktu, tanggal, pangkat/jabatan, status presensi hari ini, ringkasan modul yang diizinkan, dan akses cepat.
- **Daftar Jaga** — jaga hari ini (penanda bila Anda bertugas), jadwal Anda berikutnya, jadwal mendatang, tab Jaga lengkap per bulan dengan filter "Jadwal saya"/"Mendatang", pencarian personel, detail per tanggal, dan PDF SP Jaga resmi. Data diambil langsung dari SP Jaga yang diterbitkan di web.
- **Menu dinamis sesuai hak akses** — tab, menu, tombol tambah/ubah/hapus/setujui, dan PDF hanya muncul bila server memberi izin. Perubahan hak akses oleh Admin tersinkron saat aplikasi dibuka atau beranda di-refresh.
- **PDF lengkap** — buat → pratinjau (zoom cubit, ketuk ganda, navigasi halaman) → simpan ke perangkat → buka/bagikan. Tersedia untuk SP Jaga, Buku Kas, Rekap Agenda Surat (filter kategori & tanggal), SKHPP, dan Rekening Koran Komandan — memakai template resmi server.
- **Modul baru di Android:** Rekening Komandan, Kelola Pengguna (admin), Profil.
- **SKHPP** — formulir lengkap seperti web (foto, pengikut), persetujuan Komandan dengan derajat surat, pengembalian dengan catatan revisi.
- **Tanda Tangan** — pengesahan membubuhkan stempel QR seperti di web; pengembalian dengan alasan.
- **Pelanggaran** — formulir sesuai web, riwayat perkembangan kasus, pembaruan status.
- **Presensi** — lokasi GPS nyata (opsional, izin diminta saat dibutuhkan), riwayat 30 hari.

## Perbaikan bug

- Agenda surat, SKHPP, dan pelanggaran tidak lagi menampilkan/menyimpan data kosong akibat kolom yang tidak cocok dengan server.
- Pengajuan SKHPP dari aplikasi kini benar-benar tersimpan (sebelumnya selalu gagal di server).
- Presensi tidak lagi mengirim koordinat palsu; tanggal riwayat tidak lagi mundur satu hari.
- Menu Kas tidak lagi tampil untuk semua personel; beranda tidak lagi memuat saldo kas bagi yang tidak berhak.
- Error jaringan tidak lagi ditampilkan sebagai angka 0; ada tombol coba lagi.
- Sesi kedaluwarsa/akun dinonaktifkan kini mengembalikan pengguna ke halaman masuk dengan pesan yang jelas.
- Permintaan ganda (ketuk berulang) dicegah pada semua aksi simpan.

## Keamanan

- Seluruh API mobile kini mewajibkan token dan memeriksa hak akses di server (sebelumnya terbuka tanpa autentikasi).
- Aktivasi akun wajib token dari Admin; log server tidak lagi publik; login tidak membocorkan keberadaan akun.
- Token tidak lagi tercatat di log; backup aplikasi dimatikan; token hanya dikirim ke host server SISINDEN; alamat server wajib HTTPS.

## Kompatibilitas

- Android 8.0 (API 26) – Android 14 (API 34).
- Ukuran layar ponsel hingga tablet; mendukung mode gelap dan ukuran huruf sistem.
- Izin: Internet, Notifikasi (Android 13+), Lokasi (opsional, hanya saat presensi).

## Status pengujian

- Backend: 19 feature test keamanan & kontrak API mobile lulus (81 asersi).
- Android: unit test kontrak API (fixture dari backend asli) & utilitas, build debug/release, dan lint dijalankan oleh GitHub Actions. Uji manual di perangkat mengikuti `docs/ROLE_PERMISSION_MATRIX.md` bagian 3.

Checksum SHA-256 APK tersedia di berkas `SHA256SUMS.txt` pada rilis ini.
