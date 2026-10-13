# IMPLEMENTASI REDESIGN UI/UX SISINDEN ANDROID

Dokumen ini menjelaskan penerapan sistem desain antarmuka pengguna (UI) dan pengalaman pengguna (UX) terkini pada aplikasi SISINDEN Android dengan mengadopsi prinsip eCommerce UI Kit modern, bersih, dan berwibawa khas aplikasi kedinasan militer.

---

## 1. Palet Warna dan Tipografi Kedinasan
Sistem warna diatur di `id.my.sisinden.app.core.ui.theme.Color.kt`:
- **Deep Navy Base**:
  - `SindenNavyDark`: `#0B132B` (Warna utama gradient kartu eksekutif)
  - `SindenNavyMedium`: `#1C2541` (Warna transisi mid-tone)
  - `SindenNavyLight`: `#263252` (Warna aksen batas kontras)
  - `SindenNavyAccent`: `#3A506B` (Warna chip status terverifikasi)
- **Latar Belakang Modern**:
  - `SindenIceBlueBg`: `#F4F8FB` (Memberikan kesan sejuk, bersih, dan kontras tinggi)
- **Aksen Kedinasan**:
  - `SindenOceanPrimary`: `#0284C7` (Biru laut kedinasan maritim)
  - `SindenTealPrimary`: `#0D9488` (Teal navigasi dan aksi simpan)
  - `SindenGold`: `#D97706` (Emas lencana kedinasan)
- **Pastel Container Squircles (Aksi Cepat)**:
  - `SindenPastelGreen`: `#DCFCE7` (Kas Denintel)
  - `SindenPastelBlue`: `#E0F2FE` (Kas Taktis Operasi & Agenda Surat)
  - `SindenPastelOrange`: `#FFEDD5` (Mitra Intelijen)
  - `SindenPastelPurple`: `#F3E8FF` (Layanan SKHPP)
  - `SindenPastelRed`: `#FFE4E6` (Tanda Tangan Elektronik & Pelanggaran)
  - `SindenPastelYellow`: `#FEF3C7` (Direktori Personel)

---

## 2. Implementasi Layar Utama (Beranda Eksekutif)
1. **Header Kedinasan**:
   - Memuat identitas satuan "Denintel Kodaeral V", sapaan prajurit terpersonalisasi, lencana dinas, dan tombol notifikasi aktif.
2. **Kartu Saldo Utama Eksekutif**:
   - Tampilan gradient linear 3-layer deep navy dengan kelengkungan sudut 24dp.
   - Dilengkapi badge "Terverifikasi" dan tombol toggle privasi mata (`Icons.Default.Visibility` / `VisibilityOff`) untuk menyembunyikan nominal saldo saat berada di ruang publik.
3. **Trio Ringkasan Keuangan**:
   - Tiga kartu sejajar dengan visualisasi pemasukan (hijau), pengeluaran (oranye hangat), dan dana taktis (slate blue).
4. **Grid Modul Utama 8-Aksi**:
   - Penataan grid 2 baris x 4 kolom yang responsif dengan kontainer ikon squircle 46dp bergradasi pastel, menghilangkan kesan monoton aplikasi administrasi konvensional.
5. **Daftar Agenda Terkini**:
   - Kartu dinas bertingkat dengan label jenis surat, nomor naskah dinas, dan pengirim.

---

## 3. Implementasi Modul Keuangan (Kas Denintel & Kas Taktis)
- **Visualisasi Saldo Berjalan**:
  - Kartu ringkasan total saldo berjalan dilengkapi sub-rekapitulasi total penerimaan dan total pengeluaran.
- **Penyaringan Kategori**:
  - Filter chips modern untuk beralih antara "Semua Transaksi", "Penerimaan (Masuk)", dan "Pengeluaran (Keluar)".
- **Tampilan Transaksi Transparan**:
  - Setiap kartu item memuat ikon penanda arah dana, uraian transaksi, kategori, tanggal, nominal tercatat, serta sisa saldo berjalan pada saat transaksi tersebut dicatat.
- **Aksi Cepat Edit & Hapus**:
  - Tombol aksi terintegrasi di bagian bawah setiap kartu dengan warna pembeda yang jelas untuk mencegah salah tekan.

---

## 4. Implementasi Modul Mitra Intelijen
- **Pencarian Cepat**:
  - Kotak pencarian dengan ikon kaca pembesar dan penataan sudut 14dp untuk mencari mitra berdasarkan nama, perusahaan, atau nomor kontak.
- **Status Pembayaran Terkini**:
  - Badge status "Lunas" (hijau) atau "Belum" (kuning-oranye) pada bulan berjalan.
- **Lembar Matriks 12 Bulan Interaktif**:
  - Mengadaptasi matriks web ke dalam form modal mobile dengan tata letak grid 3x4 (Januari hingga Desember) yang dapat disentuh untuk langsung mengubah status pembayaran.

---

## 5. Implementasi Redesign Livin' by Mandiri Elder-Friendly

Sesuai arahan desain terbaru yang terinspirasi dari referensi Livin' by Mandiri Elder-Friendly:
1. **Tipografi & Hirarki Kontras Tinggi**:
   - Ukuran font judul dan nominal diperbesar (headlineLarge 28-32sp dengan weight Black).
   - Teks sublabel dan keterangan menggunakan kontras warna tegas untuk kenyamanan pembacaan pengguna dari berbagai latar belakang usia.
2. **Komponen Taktil Berukuran Nyaman (Large Touch Targets)**:
   - Tombol eksekusi utama, tab kuadran status, dan tombol aksi dirancang dengan tinggi 48dp hingga 56dp untuk mencegah terjadinya kesalahan sentuh (misclick).
3. **Kartu Status Bergradien Deep Navy - Royal Blue**:
   - Menerapkan palet warna Royal Blue (`#003D79`), Navy Deep (`#002244`), dan aksen Golden Amber (`#F9A825`).
4. **Layar Presensi Kehadiran Terdedikasi**:
   - Hero banner status hari ini (menampilkan jam tercatat dan badge TERVERIFIKASI/BELUM ABSEN).
   - Kuadran pilihan 4 status dinas: Hadir Kantor, Dinas Luar, Piket Jaga, dan Izin Dinas.
   - Deteksi visual titik koordinat kedinasan.
   - Input catatan penugasan kedinasan yang lapang.
   - Kartu riwayat presensi 30 hari terakhir dengan visualisasi badge status dinas.
5. **Navigasi Terintegrasi & Center Elevated Floating Button**:
   - Tab navigasi bawah (NavigationBar) 5 menu dengan tombol mengambang terangkat di tengah khusus untuk **PRESENSI** (lingkaran 50dp elevated circle, `LivinSkyBlue`, garis batas putih 2.5dp, ikon `Icons.Default.HowToReg`).
   - 4 tombol tab pendukung: Beranda (dengan titik indikator biru aktif), Kas, Surat, dan Akun.

---

## 6. Detail Arsitektur Desain Figma Elder-Friendly (1:1 Livin' Sky Blue)

Berdasarkan referensi resmi Figma Elder-Friendly Livin' by Mandiri:
1. **Header Sky Blue Bergradien**:
   - Latar belakang gradien biru cerah (`LivinSkyBlue` `#0084F4` ke `LivinSkyBlueDark` `#0066CC`).
   - Penataan identitas satuan SISINDEN Kodaeral V di kiri atas.
   - Kolom aksi kanan: Notifikasi kedinasan ("Warta") dan menu "Keluar".
2. **Floating Executive White Card**:
   - Kartu melayang putih bersih dengan radius lengkungan 26dp dan elevasi halus.
   - Menampilkan sapaan prajurit, foto profil avatar prajurit, kartu tanda pengenal militer, saldo kas dinas dengan toggle privasi mata, serta indikator pagination titik bulat.
3. **4 Tombol Aksi Taktil Melingkar (Fitur Utama Anda)**:
   - Presensi Kehadiran: Tombol bulat 60dp warna Teal (`#00C2A8`).
   - Kas Denintel: Tombol bulat 60dp warna Oranye Mandiri (`#FF7A00`).
   - Kas Taktis: Tombol bulat 60dp warna Biru Mandiri (`#00A3FF`).
   - Mitra Intelijen: Tombol bulat 60dp warna Hijau Lime (`#87C038`).
4. **Pill Filter Chips & Kartu Aksi Vertikal (Fitur Lainnya)**:
   - Filter chips horizontal: "Sering Dipakai", "Surat Dinas", "SKHPP".
   - Kartu baris aksi berbingkai 18dp dengan kontainer ikon bergradasi dan tombol arah "Buka >".
5. **Form Autentikasi Pill-Shaped**:
   - Kolom isian NRP dan Kata Sandi dengan lengkungan 22dp pill radius.
   - Tombol masuk berdimensi tinggi 54dp dengan lengkungan 26dp berlatar belakang `LivinSkyBlue`.

---

## 7. Desain Sistem Modern SaaS & Pengaturan Mode Gelap/Terang (v1.0.3)

Sesuai kebutuhan penyederhanaan antarmuka yang bersih, modern, dan interaktif khas SaaS (Software-as-a-Service) selaras dengan portal web SISINDEN:

1. **Prinsip Estetika Modern SaaS Enterprise**:
   - Layout bersih tanpa elemen berlebihan, menggunakan batas kontur halus (`BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant)`).
   - Kartu metrik operasional terstruktur (`SaaSStatCard`) dengan squircle ikon bergradasi dan badge status.
   - Tipografi hierarki tegas dengan tracking konsisten dan pembacaan yang nyaman.

2. **Micro-Interactions & Animasi Taktil (Tactile Bounce Click)**:
   - Setiap elemen tombol, kartu, dan chip filter dilengkapi modifier `Modifier.bounceClick()` yang memanfaatkan kurva fisika pegas (`Spring.DampingRatioMediumBouncy`, `Spring.StiffnessMedium`).
   - Elemen menyusut halus ke skala `0.95f` saat ditekan dan membal kembali saat dilepas, memberikan umpan balik fisik yang sangat memuaskan bagi pengguna.

3. **Komponen SaaS Terpusat**:
   - `SaaSCard`: Kartu kontainer adaptif terang/gelap dengan sudut lengkung 16dp dan batas kontur halus.
   - `SaaSStatCard`: Kartu ringkasan metrik KPI (Presensi, Kas Denintel, Kas Taktis, Agenda Surat).
   - `SaaSTabChip`: Tombol filter pill dengan animasi transisi warna latar belakang dan teks.
   - `ThemeModeSegmentedControl`: Pengendali pilihan tema tersegmen (Terang, Gelap, Sistem).

4. **Sistem Mode Gelap dan Terang Terintegrasi (3-Pilihan)**:
   - Pengguna dapat memilih mode tampilan: **Mode Terang**, **Mode Gelap**, atau **Mengikuti Sistem**.
   - Pilihan dapat diganti langsung via tombol toggle instan di bilah atas Beranda maupun melalui kontrol tersegmen di menu Pengaturan.
   - Preferensi tersimpan persisten di `DataStoreManager` (`KEY_DARK_MODE`).
   - Seluruh warna komponen pada semua modul (Beranda, Presensi, Kas, Surat, SKHPP, Mitra, Tanda Tangan, Personel, Pelanggaran, Notifikasi, dan Autentikasi) mengacu pada token `MaterialTheme.colorScheme` sehingga beradaptasi secara harmonis.



