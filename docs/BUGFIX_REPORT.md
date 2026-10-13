# LAPORAN PENYELESAIAN BUG DAN PERBAIKAN SISTEM

Dokumen ini mendokumentasikan akar masalah teknis, langkah analisis, dan solusi kode yang diterapkan untuk menyelesaikan kendala pada aplikasi SISINDEN Android.

---

## 1. Bug Nominal Transaksi Rp1 Tercatat Sebagai Rp0

### Akar Masalah:
Pada implementasi sebelumnya, DTO klien Android (`CreateCashRequest` dan `CreateTechnicalCashRequest`) mengirimkan payload JSON berformat:
```json
{
  "type": "masuk",
  "amount": 1.0,
  "category": "Operasional",
  "description": "Setoran Kas"
}
```
Sedangkan pada endpoint backend Laravel (`/api/mobile/cash` dan `/api/mobile/technical-cash`), controller hanya mengekstrak properti `$data['debit']` dan `$data['credit']`:
```php
$debit = (float)($data['debit'] ?? 0);
$credit = (float)($data['credit'] ?? 0);
```
Karena properti `debit` dan `credit` tidak dikirimkan oleh klien, kedua variabel tersebut mengevaluasi ke angka `0.0`. Akibatnya, transaksi dengan nominal berapa pun termasuk Rp1 tersimpan di database sebagai debit 0 dan kredit 0.

### Solusi Kode:
1. **Perbaikan Backend (`routes/web.php`)**:
   Backend kini memeriksa keberadaan `amount` dan `type` secara fleksibel:
   ```php
   $type = strtolower($data['type'] ?? 'masuk');
   $amount = isset($data['amount']) ? (float)$data['amount'] : 0.0;

   if (isset($data['debit']) || isset($data['credit'])) {
       $debit = (float)($data['debit'] ?? 0);
       $credit = (float)($data['credit'] ?? 0);
   } else {
       if ($type === 'masuk' || $type === 'debit') {
           $debit = $amount;
           $credit = 0.0;
       } else {
           $debit = 0.0;
           $credit = $amount;
       }
   }
   ```
   Setelah penyimpanan atau pembaruan, backend menjalankan kalkulasi ulang berurutan secara kronologis untuk memastikan kolom `balance` setiap transaksi akurat.

2. **Perbaikan Klien Android (`Models.kt`)**:
   DTO `CreateCashRequest` dan `CreateTechnicalCashRequest` ditambahkan properti `debit` dan `credit` dengan default serializer:
   ```kotlin
   data class CreateCashRequest(
       @SerializedName("type") val type: String,
       @SerializedName("amount") val amount: Double,
       @SerializedName("category") val category: String = "Operasional",
       @SerializedName("description") val description: String,
       @SerializedName("date") val date: String? = null,
       @SerializedName("debit") val debit: Double = if (type.lowercase() == "masuk" || type.lowercase() == "debit") amount else 0.0,
       @SerializedName("credit") val credit: Double = if (type.lowercase() == "keluar" || type.lowercase() == "kredit") amount else 0.0
   )
   ```
   Dengan pendekatan proteksi dua arah ini, data nominal tersimpan presisi baik melalui pembacaan `amount` maupun `debit`/`credit`.

---

## 2. Fitur Edit dan Hapus Belum Berfungsi

### Akar Masalah:
- Endpoint `PUT /api/mobile/cash/{id}` sebelumnya belum melakukan rekalkulasi saldo kronologis.
- Endpoint `PUT /api/mobile/technical-cash/{id}` belum tersedia di backend.
- Endpoint `PUT /api/mobile/mitra/{id}` belum tersedia di backend.
- Antarmuka pengguna Android belum menyediakan aksi Edit dan Hapus pada kartu item transaksi.

### Solusi Kode:
1. Menambahkan dan melengkapi endpoint `PUT` dan `DELETE` di `routes/web.php` untuk Kas Pangkalan, Kas Taktis Unit Teknis, dan Mitra Intelijen.
2. Menambahkan deklarasi metode Retrofit di `SindenApiService.kt` dan implementasi pemanggilannya di `SindenRepository.kt`.
3. Menambahkan fungsi ViewModel (`editCashTransaction`, `deleteCashTransaction`, `editTechnicalCashTransaction`, `deleteTechnicalCashTransaction`, `updateMitra`, `deleteMitra`).
4. Menyediakan ModalBottomSheet untuk form edit yang langsung memuat data awal, serta AlertDialog untuk konfirmasi penghapusan data secara aman.

---

## 3. Menu Kas Denintel dan Kas Teknis Tidak Merespons Klik

### Akar Masalah:
Pada `DashboardScreen.kt`, validasi izin akses lokal menggunakan evaluasi boolean kaku:
```kotlin
ActionItem("Kas Denintel", ..., isAdmin || user?.canAccessCash == true, onNavigateToCash)
```
Ketika state pengguna (`currentUser`) belum selesai dimuat dari DataStore asynchronous saat pertama kali beranda dirender, nilai tersebut menjadi `false`. Kondisi ini memicu pemblokiran klik atau memunculkan dialog pembatasan akses secara prematur bagi pengguna yang sah. Selain itu, penanganan tombol kembali di `SindenNavGraph.kt` menggunakan `navController.popBackStack()` tanpa pemeriksaan fallback rute utama jika back stack kosong.

### Solusi Kode:
1. Memperbarui logika otorisasi di `Models.kt` dengan accessor komprehensif (`canOpenCash`, `canOpenTechnicalCash`, `canOpenMitra`).
2. Menyesuaikan pemetaan izin di `DashboardScreen.kt` agar tidak memblokir render saat state profil sedang dalam proses sinkronisasi (`user == null || user.canOpenCash`).
3. Menambahkan pengaman navigasi balik di `SindenNavGraph.kt` sehingga tombol kembali pada header Kas Denintel, Kas Teknis, dan Mitra selalu memindahkan pengguna kembali ke Beranda jika tidak ada riwayat sebelumnya.

---

## 4. Notifikasi Berulang Setiap Kali Aplikasi Dibuka

### Akar Masalah:
Variabel `lastNotifiedNotifId` di `DashboardViewModel.kt` disimpan hanya di memori runtime (`private var lastNotifiedNotifId = 0`). Setiap kali aktivitas aplikasi dimulai ulang atau pengguna berganti layar, ViewModel diinisialisasi kembali dan nilai variabel tersebut kembali menjadi `0`. Akibatnya, notifikasi lama yang belum dibaca akan selalu memicu `NotificationHelper.showNotification()` berulang kali disertai bunyi dan getaran.

### Solusi Kode:
1. Menerapkan penyimpanan persisten berbasis `SharedPreferences` di dalam `NotificationHelper.kt` dengan kunci `last_notified_id` dan `is_initialized`.
2. Menambahkan fungsi `processNewNotification()` yang melakukan sinkronisasi senyap (silent sync) saat peluncuran awal aplikasi.
3. Notifikasi sistem kini hanya berbunyi dan memunculkan banner apabila ID notifikasi yang diterima dari server benar-benar lebih besar dari ID notifikasi terakhir yang tersimpan dan berstatus belum dibaca.
