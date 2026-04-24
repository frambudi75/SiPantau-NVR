## Troubleshooting

### Live view “No Signal”

- Klik **Sync Streams** di dashboard.
- Pastikan FFmpeg ter-install:
  - Settings → FFmpeg Status harus “Installed”
- Cek file HLS ada:
  - `streams/cam_{id}/index.m3u8`

### Recording tidak muncul di Playback

- Klik **Sync Recordings** di Playback.
- Pastikan folder `recordings/cam_{id}/` berisi file `.mp4`.
- Pastikan schema DB sudah update:
  - kolom `recordings.thumbnail_path` ada (lihat `docs/DB_SCHEMA_AND_MIGRATIONS.md`)

### Playback tidak bisa seek / lama load

- Pastikan playback menggunakan endpoint:
  - `api/recording.php?id=...`
Endpoint ini sudah support HTTP Range.

### Storage path absolute dan file tidak bisa diputar

Kalau `settings.storage_path` kamu mengarah ke lokasi non-webroot:
- playback harus lewat `api/recording.php` (sudah diterapkan)

### Watchdog tidak mengubah status kamera

- Jalankan manual:
  - `http://localhost/nvr/api/watchdog.php`
- Pastikan `streams/cam_{id}/index.m3u8` terus ter-update.

### Telegram tidak kirim alert

- Settings → isi:
  - `telegram_bot_token`
  - `telegram_chat_id`
- Jalankan `api/watchdog.php` dan matikan salah satu stream untuk tes.

