## Setup (Windows + XAMPP)

Dokumen ini menjelaskan cara menjalankan SiPantau-NVR di Windows menggunakan XAMPP.

### Prasyarat

- **Windows 10/11**
- **XAMPP** (Apache + MySQL/MariaDB)
- **FFmpeg** (wajib)

### 1) Taruh project di htdocs

- Copy folder project ke:
  - `C:\xampp\htdocs\nvr`

Pastikan bisa diakses:
- `http://localhost/nvr/`

### 2) Setup database

1. Jalankan **MySQL** dari XAMPP Control Panel.
2. Buka phpMyAdmin:
   - `http://localhost/phpmyadmin`
3. Import file schema:
   - `database.sql`

Jika kamu sudah punya database sebelumnya, lihat `docs/DB_SCHEMA_AND_MIGRATIONS.md`.

### 3) Konfigurasi koneksi database

File: `core/config.php`

Default:
- host: `localhost`
- db: `nvr_db`
- user: `root`
- pass: *(kosong)*

Jika MySQL kamu beda, edit konstanta:
- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`

### 4) Install FFmpeg

Opsi A (disarankan): install FFmpeg dan tambahkan ke PATH.

Tes:

```bash
ffmpeg -version
```

Atau set path di aplikasi:
- Menu **Settings** → **FFmpeg Path**
  - contoh: `C:\ffmpeg\bin\ffmpeg.exe`

### 5) Login

Default user dibuat oleh `api/watchdog.php` (saat job itu dijalankan).

Jika belum ada user:
- Jalankan sekali:
  - `http://localhost/nvr/api/watchdog.php`

Default:
- username: `admin`
- password: `admin`

### 6) Tambah kamera

Dashboard → **Add Camera**

Isi:
- Camera Name
- RTSP URL
- Location (optional)

### 7) Mulai stream & recording

Dashboard → klik **Sync Streams**

Output akan dibuat otomatis:
- `streams/cam_{id}/index.m3u8`
- `recordings/cam_{id}/*.mp4`

### 8) Playback & download

Menu **Playback**:
- pilih camera + tanggal
- klik clip untuk play
- tombol **Download MP4** akan download file via endpoint aman

