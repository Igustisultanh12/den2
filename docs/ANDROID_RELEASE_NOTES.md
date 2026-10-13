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
