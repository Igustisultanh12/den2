# SINDEN Multi-Session WhatsApp Gateway

Gateway WhatsApp Multi-Device berbasis Node.js & Baileys untuk sistem SINDEN Denintel Koarmada 2.

## Fitur Utama
1. **Multi-Akun / Multi-Sesi**: Dapat menjalankan banyak nomor WA sekaligus (Dinas, Komandan, Piket, dll.).
2. **Scan QR Code Langsung**: Menghasilkan QR Code dinamis untuk dipindai via menu Perangkat Tertaut WhatsApp.
3. **Pembersihan Otomatis Saat Logout**: Menghapus folder kredensial secara aman saat sesi diputuskan.
4. **Auto-Restore**: Otomatis menyambungkan kembali sesi aktif ketika server dinyalakan ulang.
5. **Kompatibilitas Penuh**: Tetap mendukung endpoint lama `GET /send?number=...&msg=...` untuk notifikasi otomatis SINDEN.

## Panduan Instalasi di Server VPS

```bash
# 1. Masuk ke folder gateway
cd /www/wwwroot/Kantor.site/wa-gateway

# 2. Pasang dependensi
npm install

# 3. Jalankan dengan PM2 (agar selalu aktif di background)
pm2 start server.js --name "sinden-wa-gateway"

# 4. Simpan konfigurasi PM2
pm2 save
pm2 startup
```

## Memeriksa Status Layanan
```bash
pm2 status sinden-wa-gateway
pm2 logs sinden-wa-gateway
```
