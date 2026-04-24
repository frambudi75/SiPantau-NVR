# SiPantau-NVR

Web-based NVR (Network Video Recorder) untuk monitoring kamera RTSP, live view via HLS, dan playback rekaman MP4 tersegment.

## Fitur

- **Live monitoring**: tiap kamera menghasilkan HLS stream untuk live view.
- **Recording**: FFmpeg membuat rekaman MP4 tersegment (default 10 menit per file).
- **Playback**: list rekaman per kamera & tanggal, timeline sederhana, preview + **download** MP4.
- **Indexing**: scanner mengindeks file rekaman ke database + generate thumbnail.
- **Retention**: auto-delete berdasarkan hari retensi + disk space guard (hapus yang paling lama saat disk menipis).
- **Watchdog**: healthcheck m3u8 + auto restart FFmpeg, opsional notifikasi Telegram.
- **Auth**: login session (default admin).

## Teknologi

- **PHP** (XAMPP/LAMP)
- **MySQL/MariaDB**
- **FFmpeg** (wajib)
- **HLS.js** (untuk playback HLS di browser)

## Quick Start (Windows + XAMPP)

Lihat panduan lengkap di `docs/SETUP_WINDOWS_XAMPP.md`.

Ringkasnya:

1. Install **XAMPP** dan jalankan Apache + MySQL.
2. Install **FFmpeg** dan pastikan bisa dipanggil dari command line.
3. Import schema database:
   - Buka phpMyAdmin → import `database.sql`
4. Buka aplikasi:
   - `http://localhost/nvr/`
5. Login:
   - **username**: `admin`
   - **password**: `admin`

## Konsep Folder

- `streams/`: output HLS per kamera (dibuat otomatis).
- `recordings/`: output rekaman MP4 per kamera (dibuat otomatis).
- `api/`: endpoint dan job (scanner/watchdog/stream manager).

## Job yang Disarankan (agar headless)

Supaya recording tetap jalan walau web tidak dibuka, jalankan `api/watchdog.php` via **Windows Task Scheduler**.

Panduan: `docs/SCHEDULER_WINDOWS.md`.

## Dokumentasi

- `docs/SETUP_WINDOWS_XAMPP.md`
- `docs/DB_SCHEMA_AND_MIGRATIONS.md`
- `docs/ARCHITECTURE.md`
- `docs/API.md`
- `docs/SCHEDULER_WINDOWS.md`
- `docs/TROUBLESHOOTING.md`

## Lisensi

TBD
