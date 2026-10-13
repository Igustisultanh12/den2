# ALUR DAN MEKANISME NOTIFIKASI KEDINASAN SISINDEN

Dokumen ini menjelaskan arsitektur pengiriman, deduplikasi persisten, dan penanganan audio notifikasi kedinasan pada aplikasi Android SISINDEN.

---

## 1. Arsitektur Saluran Notifikasi (Notification Channel)
Aplikasi mendaftarkan Notification Channel resmi sejak inisialisasi aplikasi (`SindenApplication`):
- **Channel ID**: `sinden_kedinasan_channel`
- **Nama Saluran**: Warta & Instruksi Kedinasan SINDEN
- **Tingkat Kepentingan**: `NotificationManager.IMPORTANCE_HIGH`
- **Pola Getar**: `0, 250, 150, 250` milidetik
- **Nada Dering**: Nada notifikasi default sistem Android (`TYPE_NOTIFICATION`)
- **Tipe Konten Audio**: `CONTENT_TYPE_SONIFICATION` dengan `USAGE_NOTIFICATION_COMMUNICATION_INSTANT`

---

## 2. Alur Deduplikasi Notifikasi Persisten

```
[Aplikasi Dibuka / Refresh Beranda]
                 |
                 v
   [GET /api/mobile/notifications]
                 |
                 v
     [Ambil Notifikasi Terkini]
                 |
                 v
   [Periksa SharedPreferences]
         (is_initialized?)
        /                 \
      TIDAK               YA
      /                     \
[Silent Initial Sync]    [Bandingkan ID Baru > ID Tersimpan?]
[Simpan latest.id]       /                             \
[Tanpa Bunyi/Getar]     YA                             TIDAK
                        /                                 \
           [Cek Status Unread?]                     [Abaikan / Tanpa Aksi]
           /                  \
         YA                   TIDAK
         /                      \
  [Periksa Preferensi Suara]  [Abaikan]
  /                        \
AKTIF                    SENYAP
  |                         |
[Bunyikan & Getar]    [Tampilkan Banner Senyap]
  \                        /
   [Update last_notified_id]
```

---

## 3. Spesifikasi Komponen

1. **`NotificationHelper.kt`**:
   - Berfungsi sebagai pengendali notifikasi terpusat.
   - Menyimpan status inisialisasi pada file preferensi `sinden_notification_prefs`.
   - Mengatur preferensi suara pengguna (`isNotificationSoundEnabled` dan `setNotificationSoundEnabled`).
   - Menyediakan metode `processNewNotification()` untuk menyaring pemanggilan notifikasi sistem.

2. **`DashboardViewModel.kt`**:
   - Memanggil `processNewNotification()` saat memuat data dasbor tanpa menyimpan state lokal yang rentan reset saat activity recreated.

3. **`NotificationsScreen.kt`**:
   - Menampilkan riwayat seluruh warta dinas dalam format visual terstruktur.
   - Menyediakan tombol pembaruan data langsung melalui top bar.
