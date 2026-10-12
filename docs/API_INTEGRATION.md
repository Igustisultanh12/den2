# DOKUMENTASI INTEGRASI API RESMI SISINDEN

**Base URL Standar:** `https://sisinden.my.id`  
**Protokol:** HTTPS / TLS 1.3  
**Header Wajib:**
```http
Accept: application/json
Content-Type: application/json
User-Agent: SISINDEN-Android/1.0 (Kedinasan Android Native)
Authorization: Bearer <TOKEN_SANCTUM>
```

---

## 1. Modul Autentikasi

### 1.1 Konfigurasi Awal Sistem
- **Method & Path:** `GET /api/mobile/config`
- **Autentikasi:** Publik (Tanpa Token)
- **Contoh Respons Sukses (HTTP 200):**
```json
{
  "status": "success",
  "app_name": "DENINTEL KODAERAL V",
  "agency_name": "DENINTEL KODAERAL V",
  "agency_logo": "https://sisinden.my.id/storage/agency/ROLwRbabZHoAbqiAi3H0VA430SxxYG3idzs7xrlV.png",
  "login_background": "https://sisinden.my.id/storage/backgrounds/wptQOfJpPHnyK9HJ6mb3XnUEUyQW746QYGXvLZ90.jpg",
  "api_base_url": "https://sisinden.my.id",
  "api_login_endpoint": "/api/mobile/login",
  "apk_download_url": "https://sisinden.my.id/download/sinden-mobile.apk",
  "api_status": "AKTIF",
  "min_version": "1.0.0",
  "server_time": "2026-10-12T14:14:06+07:00"
}
```

### 1.2 Login Personel
- **Method & Path:** `POST /api/mobile/login`
- **Autentikasi:** Publik
- **Format Body:**
```json
{
  "username": "12000018012200216",
  "password": "kata_sandi_rahasia"
}
```
- **Contoh Respons Sukses (HTTP 200):**
```json
{
  "status": "success",
  "token": "1|sinden_auth_token_hash...",
  "user": {
    "id": 1,
    "name": "I Gusti Sultan H.A., A.Md.Kom.",
    "email": "sultanhardiansyah12@gmail.com",
    "username": "12000018012200216",
    "nrp": "12000018012200216",
    "role": "admin",
    "pangkat": "Sersan Satu",
    "korps": "KOM",
    "jabatan": "Operator Komunikasi",
    "is_active": true,
    "can_access_agenda": true,
    "can_access_letters": true,
    "can_access_skhpp": true,
    "can_access_categories": true,
    "can_access_signature": true,
    "can_access_cash": true,
    "can_access_commander": true,
    "can_access_mitra": true,
    "can_access_technical_cash": true,
    "can_access_violations": true,
    "can_access_activities": true,
    "can_access_users": true,
    "can_access_settings": true
  }
}
```

---

## 2. Modul Operasional & Agenda

### 2.1 Log Agenda Surat (Masuk, Keluar, Disposisi)
- **Method & Path:** `GET /api/mobile/letter-logs`
- **Query Params:** `type` (masuk, keluar, disposisi), `search` (kata kunci)
- **Autentikasi:** Bearer Token
- **Respons (HTTP 200):**
```json
{
  "status": "success",
  "data": [
    {
      "id": 12,
      "type": "masuk",
      "letter_number": "B/142/X/2026",
      "sender": "Lantamal V",
      "recipient": "Dan Denintel Kodaeral V",
      "subject": "Permohonan Dukungan Pengamanan VVIP",
      "date": "2026-10-10",
      "status": "diproses",
      "notes": "Segera koordinasikan dengan tim lapangan."
    }
  ]
}
```

### 2.2 Input Log Surat Baru
- **Method & Path:** `POST /api/mobile/letter-logs`
- **Autentikasi:** Bearer Token
- **Format Body:**
```json
{
  "type": "masuk",
  "letter_number": "B/145/X/2026",
  "sender": "Koarmada II",
  "recipient": "Dan Denintel",
  "subject": "Laporan Intelijen Situasi Maritim",
  "date": "2026-10-12",
  "notes": "Penting dan rahasia"
}
```

---

## 3. Modul Penerbitan SKHPP

### 3.1 Daftar Pengajuan SKHPP
- **Method & Path:** `GET /api/mobile/skhpp`
- **Query Params:** `status` (semua, pending, verified, rejected)
- **Autentikasi:** Bearer Token
- **Respons (HTTP 200):**
```json
{
  "status": "success",
  "data": [
    {
      "id": 4,
      "reference_number": "SKHPP/10/X/2026",
      "name": "Budi Santoso",
      "nrp": "1098234",
      "pangkat": "Sersan Mayor",
      "jabatan": "Bintara Intel",
      "purpose": "Persyaratan Kenaikan Pangkat Reguler",
      "status": "verified",
      "created_at": "2026-10-11T09:00:00Z"
    }
  ]
}
```

### 3.2 Verifikasi SKHPP (Pejabat Berwenang)
- **Method & Path:** `POST /api/mobile/skhpp/{id}/verify`
- **Autentikasi:** Bearer Token
- **Respons (HTTP 200):**
```json
{
  "status": "success",
  "message": "Pengajuan SKHPP berhasil diverifikasi."
}
```

---

## 4. Modul Keuangan & Kas

### 4.1 Kas Denintel
- **Method & Path:** `GET /api/mobile/cash`
- **Autentikasi:** Bearer Token
- **Respons (HTTP 200):**
```json
{
  "status": "success",
  "total_balance": 45200000,
  "total_income": 80000000,
  "total_expense": 34800000,
  "transactions": [
    {
      "id": 8,
      "type": "masuk",
      "amount": 10000000,
      "category": "Dukungan Operasi",
      "description": "Dropping dana operasional triwulan IV",
      "date": "2026-10-01"
    }
  ]
}
```

### 4.2 Kas Taktis Operasi
- **Method & Path:** `GET /api/mobile/technical-cash`
- **Autentikasi:** Bearer Token
- **Respons (HTTP 200):**
```json
{
  "status": "success",
  "balance": 18500000,
  "transactions": [
    {
      "id": 3,
      "type": "keluar",
      "amount": 2500000,
      "description": "Dukungan logistik operasi monitoring pelabuhan",
      "date": "2026-10-08"
    }
  ]
}
```

---

## 5. Modul Khusus Intelijen

### 5.1 Mitra Intelijen
- **Method & Path:** `GET /api/mobile/mitra`
- **Autentikasi:** Bearer Token
- **Respons (HTTP 200):**
```json
{
  "status": "success",
  "data": [
    {
      "id": 2,
      "name": "Mitra A-1",
      "code_name": "Garuda Laut",
      "phone": "081234567890",
      "sector": "Pelabuhan Tanjung Perak",
      "payment_status": "sudah_dibayar",
      "last_contact": "2026-10-11"
    }
  ]
}
```

### 5.2 Tanda Tangan Digital Dokumen
- **Method & Path:** `GET /api/mobile/signature-requests`
- **Autentikasi:** Bearer Token
- **Respons (HTTP 200):**
```json
{
  "status": "success",
  "requests": [
    {
      "id": 5,
      "document_title": "Surat Perintah Pengamanan Tertutup",
      "document_number": "Sprin/201/X/2026",
      "requester_name": "Lettu Laut (P) Andi",
      "status": "menunggu",
      "created_at": "2026-10-12T07:30:00Z"
    }
  ]
}
```

---

## 6. Penanganan Galat (Error Handling) Standar

Setiap respons gagal mengikuti format JSON konsisten:
```json
{
  "status": "error",
  "message": "Pesan deskripsi kesalahan yang mudah dipahami oleh pengguna"
}
```

- **401 Unauthorized:** Token tidak valid atau sesi berakhir. Aplikasi otomatis mengarahkan ke layar Login.
- **403 Forbidden:** Akun tidak memiliki hak akses modul terkait. Aplikasi menampilkan peringatan izin kedinasan.
- **422 Unprocessable Entity:** Validasi formulir gagal.
- **500 Internal Server Error:** Terjadi kendala di backend server.
