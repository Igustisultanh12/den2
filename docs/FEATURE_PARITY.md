# MATRIKS KESETARAAN FITUR: SISTEM WEB VS APLIKASI ANDROID SISINDEN

Dokumen ini memetakan kesetaraan fitur operasional kantor antara versi Web ([https://sisinden.my.id](https://sisinden.my.id)) dan aplikasi Android native.

| Nama Modul / Fitur | Hak Akses Kedinasan | Status Web | Status Android | Catatan Implementasi |
| :--- | :--- | :--- | :--- | :--- |
| Login & Autentikasi | Semua Personel | Tersedia | Tersedia | Token Bearer persisten di DataStore Preferences |
| Dashboard Eksekutif | Semua Personel | Tersedia | Tersedia | Ringkasan saldo kas, trio kartu, warta dinas, agenda surat, aksi cepat |
| Presensi Kehadiran Dinas (Absensi) | Semua Personel | Tersedia | Tersedia | Status hari ini, 4 status dinas (Hadir, Dinas Luar, Piket, Izin), koordinat GPS, catatan, riwayat 30 hari |
| Kas Denintel (Pangkalan) - Lihat | Admin, Danden, Bendahara | Tersedia | Tersedia | Rekalkulasi saldo kronologis dan penyajian format Rupiah |
| Kas Denintel - Tambah | Admin, Bendahara | Tersedia | Tersedia | Input debit/kredit dan nominal dengan validasi otomatis |
| Kas Denintel - Edit | Admin, Bendahara | Tersedia | Tersedia | Modal BottomSheet edit transaksi dan rekalkulasi saldo |
| Kas Denintel - Hapus | Admin, Bendahara | Tersedia | Tersedia | Dialog konfirmasi hapus dan penyesuaian saldo berjalan |
| Kas Taktis (Dan Unit Teknis) - Lihat | Admin, Dan Unit Teknis | Tersedia | Tersedia | Monitoring saldo dana taktis operasional lapangan |
| Kas Taktis - Tambah | Admin, Dan Unit Teknis | Tersedia | Tersedia | Pencatatan transaksi masuk dan keluar operasional |
| Kas Taktis - Edit | Admin, Dan Unit Teknis | Tersedia | Tersedia | Modal BottomSheet edit catatan kas taktis operasional |
| Kas Taktis - Hapus | Admin, Dan Unit Teknis | Tersedia | Tersedia | Konfirmasi hapus transaksi kas taktis operasional |
| Mitra Intelijen - Direktori | Admin, Komandan, Pembina | Tersedia | Tersedia | Pencarian nama mitra, nomor kontak, sektor/perusahaan |
| Mitra Intelijen - Pendaftaran | Admin, Komandan, Pembina | Tersedia | Tersedia | Pendaftaran mitra jaringan lapangan baru |
| Mitra Intelijen - Edit Data | Admin, Komandan, Pembina | Tersedia | Tersedia | Pembaruan informasi profil mitra dan nomor kontak |
| Mitra Intelijen - Hapus Mitra | Admin, Komandan | Tersedia | Tersedia | Penghapusan data mitra beserta relasi pembayarannya |
| Mitra Intelijen - Matriks 12 Bulan | Admin, Pembina Mitra | Tersedia | Tersedia | Lembar matriks 12 bulan interaktif dengan toggle status |
| Agenda & Log Surat Dinas | Semua Personel | Tersedia | Tersedia | Daftar surat masuk/keluar, filter kategori, dan nomor surat |
| Pencatatan Surat Baru | Admin, Tata Usaha/Suma | Tersedia | Tersedia | Penomoran otomatis dinas dan penentuan perihal surat |
| Layanan SKHPP | Personel Terdaftar | Tersedia | Tersedia | Monitoring pengajuan SKHPP dan status verifikasi |
| Pengajuan SKHPP Baru | Personel Terdaftar | Tersedia | Tersedia | Form digital pengajuan penelitian personel |
| Verifikasi SKHPP | Danden / Perwira Intel | Tersedia | Tersedia | Persetujuan resmi dokumen keterangan hasil penelitian |
| Tanda Tangan Elektronik Komandan | Danden / Komandan | Tersedia | Tersedia | Verifikasi dokumen kedinasan berbasis kode pengesahan |
| Catatan Pelanggaran Prajurit | Provos / Pimpinan | Tersedia | Tersedia | Rekapitulasi pelanggaran disiplin dan tindakan perbaikan |
| Tambah Catatan Pelanggaran | Provos / Pimpinan | Tersedia | Tersedia | Pencatatan rekam disiplin prajurit satuan |
| Direktori Personel Satuan | Semua Personel | Tersedia | Tersedia | Daftar kontak kedinasan, pangkat, korps, dan jabatan |
| Warta Notifikasi Kedinasan | Semua Personel | Tersedia | Tersedia | Notifikasi sistem dengan deduplikasi persisten |
| Setelan Akun & Tema Gelap | Semua Personel | Tersedia | Tersedia | Pemilihan tema terang/gelap dan logout sesi kedinasan |

Seluruh fitur inti operasional kedinasan pada portal web kini telah memiliki padanan fungsional pada aplikasi Android dengan hak akses yang selaras.
