<?php
/**
 * Recording Scanner
 * Scans the recordings directory and populates the database.
 */

require_once __DIR__ . '/../core/config.php';

function scanRecordings() {
    global $pdo, $db_settings;

    $recordingsDir = RECORDINGS_DIR;
    $storagePath = $db_settings['storage_path'] ?? 'recordings/';
    $storagePath = rtrim(str_replace('\\', '/', $storagePath), '/') . '/';

    $cameraFolders = glob($recordingsDir . 'cam_*', GLOB_ONLYDIR);

    foreach ($cameraFolders as $folder) {
        $camId = str_replace($recordingsDir . 'cam_', '', $folder);
        $files = glob($folder . '/*.mp4');

        foreach ($files as $file) {
            $filename = basename($file);
            // Path used by the web UI (assumes storage_path is web-accessible under the project root).
            // If you set storage_path to an absolute/non-web path, playback will need a download/proxy endpoint.
            $filePath = $storagePath . 'cam_' . $camId . '/' . $filename;
            
            // Extract date from filename (YYYY-MM-DD_HH-II-SS.mp4)
            $datePart = str_replace('.mp4', '', $filename);
            $startTime = str_replace('_', ' ', $datePart);

            // Check if already in DB
            $stmt = $pdo->prepare("SELECT id FROM recordings WHERE file_path = ?");
            $stmt->execute([$filePath]);
            
            if (!$stmt->fetch()) {
                // Generate Thumbnail
                $thumbName = str_replace('.mp4', '.jpg', $filename);
                $thumbPath = $storagePath . 'cam_' . $camId . '/' . $thumbName;
                $thumbDiskPath = $folder . '/' . $thumbName;
                
                $ffmpeg = FFMPEG_PATH;
                // Take a snapshot at 2 seconds mark
                if (!file_exists($thumbDiskPath)) {
                    $thumbCmd = "\"" . $ffmpeg . "\" -i \"" . $file . "\" -ss 00:00:02 -vframes 1 -q:v 2 \"" . $thumbDiskPath . "\" -y";
                    @exec($thumbCmd);
                }

                // Add to DB
                $fileSize = filesize($file);
                $stmt = $pdo->prepare("INSERT INTO recordings (camera_id, filename, file_path, thumbnail_path, start_time, file_size) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$camId, $filename, $filePath, $thumbPath, $startTime, $fileSize]);
                echo "Indexed new recording & thumbnail: $filename\n";
            }
        }
    }

    // --- DISK SPACE GUARD ---
    $freeDir = RECORDINGS_DIR;
    $minFreeSpace = 5 * 1024 * 1024 * 1024; // 5GB in bytes
    
    while (disk_free_space($freeDir) < $minFreeSpace) {
        // Find oldest recording
        $oldest = $pdo->query("SELECT id, file_path, thumbnail_path FROM recordings ORDER BY start_time ASC LIMIT 1")->fetch();
        if (!$oldest) break; // Nothing left to delete

        // Delete disk files (guarded: only within RECORDINGS_DIR)
        $base = realpath(RECORDINGS_DIR);
        foreach (['file_path', 'thumbnail_path'] as $k) {
            $webPath = str_replace('\\', '/', $oldest[$k] ?? '');
            if ($storagePath !== '' && $webPath !== '' && str_starts_with($webPath, $storagePath)) {
                $relative = ltrim(substr($webPath, strlen($storagePath)), '/');
                $disk = RECORDINGS_DIR . $relative;
                $diskReal = realpath($disk);
                if ($base && $diskReal && str_starts_with($diskReal, $base) && is_file($diskReal)) {
                    @unlink($diskReal);
                }
            }
        }

        // Delete DB Row
        $pdo->prepare("DELETE FROM recordings WHERE id = ?")->execute([$oldest['id']]);
        echo "Disk Guard: Deleted oldest recording to free space.\n";
    }

    // Auto-delete old recordings based on retention_days
    $retentionDays = (int)($db_settings['retention_days'] ?? 7);
    if ($retentionDays > 0) {
        $cutoff = (new DateTimeImmutable('now'))->modify("-{$retentionDays} days")->format('Y-m-d H:i:s');

        // Delete files on disk first (safe unlink limited to RECORDINGS_DIR)
        $old = $pdo->prepare("SELECT id, file_path, thumbnail_path FROM recordings WHERE start_time < ?");
        $old->execute([$cutoff]);
        $rows = $old->fetchAll();

        $base = realpath(RECORDINGS_DIR);
        foreach ($rows as $r) {
            foreach (['file_path', 'thumbnail_path'] as $k) {
                $webPath = str_replace('\\', '/', $r[$k] ?? '');
                if ($storagePath !== '' && $webPath !== '' && str_starts_with($webPath, $storagePath)) {
                    $relative = ltrim(substr($webPath, strlen($storagePath)), '/');
                    $disk = RECORDINGS_DIR . $relative;
                    $diskReal = realpath($disk);
                    if ($base && $diskReal && str_starts_with($diskReal, $base) && is_file($diskReal)) {
                        @unlink($diskReal);
                    }
                }
            }
        }

        // Then delete DB rows
        $pdo->prepare("DELETE FROM recordings WHERE start_time < ?")->execute([$cutoff]);
    }
}

if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    // Allow running manually from browser/CLI without breaking output expectations.
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (str_contains($accept, 'application/json')) {
        header('Content-Type: application/json; charset=utf-8');
        try {
            scanRecordings();
            echo json_encode(['ok' => true]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
    } else {
        scanRecordings();
        echo "Scan complete.";
    }
}
