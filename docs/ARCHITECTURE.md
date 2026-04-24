## Architecture overview

### Komponen utama

- **UI pages**
  - `index.php`: dashboard live monitoring (HLS.js)
  - `cameras.php`: manajemen kamera
  - `playback.php`: playback rekaman (timeline + list + download)
  - `settings.php`: konfigurasi sistem

- **Core**
  - `core/config.php`: koneksi DB + load settings + define path
  - `core/auth.php`: session auth guard

- **API / Jobs**
  - `api/stream_manager.php`: start FFmpeg untuk semua kamera (HLS + segment recording)
  - `api/stream_control.php`: stop/restart per kamera (kill FFmpeg berdasarkan marker cam_ID)
  - `api/scanner.php`: index file MP4 → DB, thumbnail, retention, disk guard
  - `api/sync_recordings.php`: endpoint untuk menjalankan scanner via tombol UI
  - `api/recording.php`: stream MP4 dari disk (Range support) + download
  - `api/watchdog.php`: job periodic untuk healthcheck + auto-restart + Telegram alert

### Flow Live View (HLS)

1. Dashboard memanggil `api/stream_manager.php` via tombol **Sync Streams**.
2. Script menjalankan FFmpeg background untuk tiap kamera.
3. FFmpeg menulis `streams/cam_{id}/index.m3u8` + segmen `.ts`.
4. Browser memutar HLS melalui HLS.js.

### Flow Recording

1. Proses FFmpeg yang sama juga menulis rekaman MP4 segment ke:
   - `recordings/cam_{id}/%Y-%m-%d_%H-%M-%S.mp4`
2. `api/scanner.php` mengindeks rekaman ke DB + generate thumbnail.
3. Playback mengambil daftar rekaman dari DB.

### Playback & Download

- Browser memutar video melalui:
  - `api/recording.php?id={recording_id}`
- Download melalui:
  - `api/recording.php?id={recording_id}&download=1`

Endpoint ini penting supaya playback tetap jalan walau `storage_path` bukan folder publik web.

