## Database schema & migrations

Schema utama ada di `database.sql`.

### Import baru

- Import `database.sql` via phpMyAdmin.

### Update schema (kalau sudah pernah install)

#### Tambah `thumbnail_path` pada tabel `recordings`

```sql
USE nvr_db;

ALTER TABLE recordings
  ADD COLUMN thumbnail_path VARCHAR(255) NULL AFTER file_path;
```

### Catatan

- Tabel `settings` menyimpan konfigurasi dinamis seperti:
  - `storage_path`, `retention_days`, `segment_time`, `ffmpeg_path`
  - `telegram_bot_token`, `telegram_chat_id`

