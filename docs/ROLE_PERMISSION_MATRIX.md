# Matriks Role & Permission — SISINDEN Mobile v1.1.0

Sumber kebenaran: `app/Support/MobileAccess.php` (server). Aplikasi Android hanya menampilkan menu/aksi berdasarkan daftar permission yang dikirim server, dan **setiap endpoint menegakkan permission yang sama** lewat middleware `mobile.auth:<permission>`.

Tabel di bawah dihasilkan langsung dari kode `MobileAccess` untuk personel tanpa flag khusus (bukan diketik manual).

## 1. Permission per role (tanpa flag tambahan)

| Permission | admin | komandan | wadan | pasops | staf | personel | anggotasintel | danunitteknis | kaurmintel | paurset | user |
|---|---|---|---|---|---|---|---|---|---|---|---|
| `dashboard.view` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `profile.view` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `notifications.view` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `attendance.view` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `attendance.create` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `attendance.view_all` | ✅ | ✅ | — | ✅ | — | — | — | — | — | — | — |
| `duty_roster.view` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `duty_roster.view_unpublished` | ✅ | ✅ | — | ✅ | — | — | — | — | — | — | — |
| `duty_roster.pdf` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `letter_logs.view` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| `letter_logs.create` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| `letter_logs.update` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| `letter_logs.delete` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| `letter_logs.pdf` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| `letters.view` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| `letters.create` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| `skhpp.view` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| `skhpp.create` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| `skhpp.approve` | ✅ | ✅ | — | — | — | — | — | — | — | — | — |
| `skhpp.delete` | ✅ | — | — | — | — | — | — | — | — | — | — |
| `skhpp.pdf` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| `signature.view` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| `signature.approve` | ✅ | ✅ | — | — | — | — | — | — | — | — | — |
| `cash.view` | ✅ | — | — | — | — | — | — | — | — | — | — |
| `cash.manage` | ✅ | — | — | — | — | — | — | — | — | — | — |
| `cash.pdf` | ✅ | — | — | — | — | — | — | — | — | — | — |
| `commander_account.view` | ✅ | ✅ | — | — | — | — | — | — | — | — | ✅ |
| `commander_account.manage` | ✅ | — | — | — | — | — | — | — | — | — | ✅ |
| `commander_account.pdf` | ✅ | ✅ | — | — | — | — | — | — | — | — | ✅ |
| `mitra.view` | ✅ | — | — | — | — | — | — | — | — | — | — |
| `mitra.manage` | ✅ | — | — | — | — | — | — | — | — | — | — |
| `technical_cash.view` | ✅ | — | — | — | — | — | — | ✅ | — | — | — |
| `technical_cash.manage` | ✅ | — | — | — | — | — | — | ✅ | — | — | — |
| `violations.view` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| `violations.manage` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| `activities.view` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| `activities.manage` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| `categories.view` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| `users.view` | ✅ | — | — | — | — | — | — | — | — | — | — |
| `users.manage` | ✅ | — | — | — | — | — | — | — | — | — | — |
| `audit_logs.view` | ✅ | — | — | — | — | — | — | — | — | — | — |
| `backup.view` | ✅ | — | — | — | — | — | — | — | — | — | — |

## 2. Pengubah hak akses di luar role (diatur Admin di web)

| Sumber | Efek di mobile | Asal aturan di web |
|---|---|---|
| `can_access_mitra = true` | `mitra.view`, `mitra.manage` | `MitraPaymentController@index` |
| `can_access_technical_cash = true` | `technical_cash.view`, `technical_cash.manage` | `TechnicalUnitCashController@index` |
| `can_access_ibu_beti = true` | flag kompatibilitas `can_access_ibu_beti` (modul belum ada di mobile) | `IbuBetiAccountController` |
| Jabatan mengandung "anggota sintel" | diperlakukan sebagai Anggota Sintel (menu umum disembunyikan) | `AuthenticatedLayout.vue` (`isAnggotaSintel`) |
| `is_active = false` | login ditolak (403), token aktif ditolak & dicabut saat admin menonaktifkan | `UserController` |
| Pengecualian berbasis nama (sudah ada di web) | Kas pangkalan: 3 nama tertentu; Mitra/Kas Unit Teknis/Rekening Komandan/SKHPP-approve: developer; SP Jaga admin: 2 nama | `MobileAccess::CASH_EXTRA_NAMES`, `DEVELOPER_NAMES`, `SP_JAGA_ADMIN_NAMES` |

Catatan:
- Rekening Komandan: web mengizinkan role `admin` **dan `user`** untuk melihat & mengubah; komandan hanya melihat. Aturan ini dipertahankan apa adanya untuk paritas — mohon ditinjau pemilik sistem.
- Hapus SKHPP di web tidak memiliki pembatasan role; di mobile dibatasi **admin** (lebih ketat, karena mencabut validitas dokumen).
- Draf/pengajuan SP Jaga (status selain `published`) hanya terlihat oleh admin, Pasops, Komandan, dan nama yang sama seperti di web.

## 3. Matriks pengujian akses

Status: ✅ otomatis = diuji di `tests/Feature/MobileApiSecurityTest.php` dan lulus; 🔲 manual = perlu diuji di perangkat setelah APK dibangun.

| # | Skenario | Diharapkan | Status |
|---|---|---|---|
| R-1 | Request tanpa token ke endpoint data | 401 `unauthenticated` | ✅ otomatis |
| R-2 | Token palsu / dicabut (logout) | 401 | ✅ otomatis |
| R-3 | Header `X-User-Id` palsu (tanpa token) | 401, identitas tidak dipakai | ✅ otomatis |
| R-4 | Personel biasa → `GET /cash`, `GET/POST /users` | 403 `forbidden` + `required_permission`; tidak ada data tertulis | ✅ otomatis |
| R-5 | Admin → kas: validasi input & saldo berjalan presisi Rp0,5 | 422 untuk input tidak valid; saldo benar | ✅ otomatis |
| R-6 | Admin memberi `can_access_technical_cash` → permission muncul pada request berikutnya | ya | ✅ otomatis |
| R-7 | Anggota Sintel → hanya Daftar Jaga, Presensi, Notifikasi, Profil | permission terbatas | ✅ otomatis (server) · 🔲 manual (menu) |
| R-8 | Akun dinonaktifkan saat sesi aktif | 403 `account_inactive` → aplikasi kembali ke login | ✅ otomatis (server) · 🔲 manual (UI) |
| R-9 | Presensi dikirim dengan `user_id` orang lain | tercatat untuk pemilik token saja | ✅ otomatis |
| R-10 | Notifikasi milik/role orang lain | tidak terlihat | ✅ otomatis |
| R-11 | Personel membuka PDF SP Jaga berstatus draf | 403 | ✅ otomatis |
| R-12 | Dashboard personel tidak memuat saldo kas | field tidak dikirim | ✅ otomatis |
| R-13 | Deep link `sisinden://app/cash` oleh personel tanpa izin | halaman "Akses ditolak" | 🔲 manual |
| R-14 | Komandan: setujui/kembalikan SKHPP & TTE dari aplikasi; hasil & nomor sama dengan web | sukses, tercatat di web | 🔲 manual (butuh data produksi/staging) |
| R-15 | Role `user`, `komandan`, `danunitteknis`, `pasops`: menu sesuai tabel bagian 1 | sesuai | 🔲 manual per akun uji |
| R-16 | Token tidak dipakai > 30 hari | 401 `token_expired` | 🔲 manual (logika di `MobileApiAuth`) |
