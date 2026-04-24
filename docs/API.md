## API / Job endpoints

Semua endpoint di bawah folder `api/`.

Catatan: endpoint yang dipakai dari UI membutuhkan session login (lihat `core/auth.php`).

### Stream / Live

- **Start/check all streams**
  - `GET /nvr/api/stream_manager.php`
  - Dipakai tombol **Sync Streams** di dashboard.

- **Stop / Restart stream per kamera**
  - `POST /nvr/api/stream_control.php?action=stop&camera_id={id}`
  - `POST /nvr/api/stream_control.php?action=restart&camera_id={id}`

### Recordings

- **Scan & sync recordings (indexing)**
  - `GET /nvr/api/sync_recordings.php`
  - Dipakai tombol **Sync Recordings** di halaman Playback.

- **Stream video recording (Range support)**
  - `GET /nvr/api/recording.php?id={recording_id}`

- **Download video recording**
  - `GET /nvr/api/recording.php?id={recording_id}&download=1`

### Watchdog

- **Healthcheck + autorestart + Telegram**
  - `GET /nvr/api/watchdog.php`
  - Jalankan via scheduler setiap 1–5 menit.

