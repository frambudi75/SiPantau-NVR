## Windows Task Scheduler (recommended)

Tujuan: supaya NVR tetap jalan **tanpa perlu membuka web**, dan bisa auto-recovery saat FFmpeg mati.

### Opsi paling simpel (HTTP call)

Ini cara cepat: scheduler memanggil URL watchdog.

1. Buka **Task Scheduler**
2. Create Task…
3. **Triggers**
   - Begin the task: *On a schedule*
   - Repeat task every: **1 minute**
4. **Actions**
   - Action: *Start a program*
   - Program/script: `powershell`
   - Arguments:

```powershell
-NoProfile -ExecutionPolicy Bypass -Command "Invoke-WebRequest -UseBasicParsing http://localhost/nvr/api/watchdog.php | Out-Null"
```

Catatan:
- Ini butuh Apache sudah running.

### Opsi lebih stabil (jalankan via PHP CLI)

Jika kamu punya `php.exe` (XAMPP), kamu bisa jalankan langsung file PHP (tanpa HTTP).

Program/script:
- `C:\xampp\php\php.exe`

Arguments:
- `-f C:\xampp\htdocs\nvr\api\watchdog.php`

Start in:
- `C:\xampp\htdocs\nvr`

### Saran interval

- Watchdog: **1 menit** (atau 2–5 menit)
- Sync recordings (`api/sync_recordings.php` / `api/scanner.php`): 5–10 menit (tergantung volume)

