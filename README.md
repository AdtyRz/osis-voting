# 🗳️ Sistem Pemilihan Ketua OSIS — Backend PHP

Sistem backend lengkap untuk pemilihan Ketua OSIS berbasis PHP + MySQL.

---

## 📁 Struktur Folder

```
osis-voting/
├── admin/
│   └── index.html          ← Panel Admin (Beranda, Paslon, User, Pengaturan)
├── api/
│   ├── admin/
│   │   ├── login.php       ← Login admin
│   │   ├── logout.php      ← Logout admin
│   │   ├── dashboard.php   ← Data statistik & grafik beranda
│   │   ├── paslon.php      ← CRUD pasangan calon
│   │   ├── users.php       ← CRUD data pemilih (siswa)
│   │   ├── pengaturan.php  ← Pengaturan sistem voting
│   │   └── export.php      ← Export CSV hasil/pemilih
│   └── frontend/
│       ├── login.php       ← Login siswa (NISN + password)
│       ├── logout.php      ← Logout siswa
│       ├── paslon.php      ← Daftar paslon (publik)
│       ├── vote.php        ← Kirim suara
│       └── hasil.php       ← Hasil sementara
├── includes/
│   ├── config.php          ← Konfigurasi database & konstan
│   └── helpers.php         ← Fungsi-fungsi pembantu
├── uploads/
│   └── foto/               ← Folder foto paslon (auto-created)
├── database.sql            ← Script buat database
├── template_import_siswa.csv ← Template import massal
├── .htaccess               ← Keamanan server
└── README.md               ← Dokumentasi ini
```

---

## 🚀 Cara Install

### 1. Persyaratan
- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- Web server: Apache / Nginx
- Extension PHP: `pdo_mysql`, `fileinfo`, `mbstring`

### 2. Setup Database
```sql
-- Buka phpMyAdmin atau MySQL CLI, lalu jalankan:
SOURCE /path/to/osis-voting/database.sql;
```

### 3. Konfigurasi
Edit file `includes/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // username MySQL kamu
define('DB_PASS', '');             // password MySQL kamu
define('DB_NAME', 'db_osis_voting');
define('BASE_URL', 'http://localhost/osis-voting');
```

### 4. Upload ke Server
- Taruh folder `osis-voting/` di dalam `htdocs/` (XAMPP) atau `www/` (WAMP)
- Pastikan folder `uploads/foto/` bisa ditulis (permission 755)

### 5. Akses Panel Admin
```
http://localhost/osis-voting/admin/index.html
Username: admin
Password: password   ← GANTI SEGERA!
```

---

## 🔑 Akun Default

| Role  | Username/NISN | Password |
|-------|---------------|----------|
| Admin | admin         | password |
| Siswa | 0012345678    | password |
| Siswa | 0012345679    | password |

> ⚠️ **Ganti semua password default sebelum digunakan!**

---

## 📡 API Endpoints

### Admin (butuh login admin)

| Method | Endpoint | Keterangan |
|--------|----------|------------|
| POST | `/api/admin/login.php` | Login admin |
| POST | `/api/admin/logout.php` | Logout |
| GET | `/api/admin/dashboard.php` | Data beranda & grafik |
| GET/POST/PUT/DELETE | `/api/admin/paslon.php` | CRUD paslon |
| GET/POST/PUT/DELETE | `/api/admin/users.php` | CRUD pemilih |
| GET/POST | `/api/admin/pengaturan.php` | Pengaturan sistem |
| GET | `/api/admin/export.php?type=users` | Export pemilih CSV |
| GET | `/api/admin/export.php?type=hasil` | Export hasil CSV |

### Frontend Siswa

| Method | Endpoint | Keterangan |
|--------|----------|------------|
| POST | `/api/frontend/login.php` | Login siswa (NISN + password) |
| POST | `/api/frontend/logout.php` | Logout |
| GET | `/api/frontend/paslon.php` | Daftar paslon |
| POST | `/api/frontend/vote.php` | Kirim suara |
| GET | `/api/frontend/hasil.php` | Hasil sementara |

---

## 📤 Import Siswa Massal (CSV)

Format file CSV (lihat `template_import_siswa.csv`):
```
nama,nisn,kelas,password
Ahmad Fauzi,0012345678,XI IPA 1,password123
```

Upload via: Panel Admin → Data Pemilih → Import CSV

---

## 🔒 Fitur Keamanan

- Password di-hash dengan `bcrypt` (PASSWORD_BCRYPT)
- Query menggunakan **PDO Prepared Statement** (anti SQL Injection)
- Satu user hanya bisa memilih **satu kali**
- Voting log mencatat IP & User-Agent
- Upload foto dibatasi tipe & ukuran (JPG/PNG/WEBP, maks 5MB)
- Folder uploads diblok dari eksekusi PHP
- Session-based authentication

---

## 🎨 Integrasi Frontend Siswa

Kamu bisa buat halaman voting untuk siswa menggunakan API:

```javascript
// 1. Login siswa
const res = await fetch('/osis-voting/api/frontend/login.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ nisn: '0012345678', password: 'password' })
});

// 2. Ambil daftar paslon
const paslon = await fetch('/osis-voting/api/frontend/paslon.php');

// 3. Kirim suara
const vote = await fetch('/osis-voting/api/frontend/vote.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ paslon_id: 1 })
});
```

---

## 📞 Troubleshooting

**"Koneksi database gagal"**
→ Cek setting di `includes/config.php`, pastikan MySQL berjalan

**Foto tidak terupload**
→ Pastikan folder `uploads/foto/` ada & permission 755

**Session tidak tersimpan**
→ Pastikan PHP session extension aktif & `session.save_path` valid

**CORS Error dari frontend**
→ Tambahkan domain frontend ke `setCorsHeaders()` di `helpers.php`
# Voting-osis
