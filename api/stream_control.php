<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/auth.php';

header('Content-Type: application/json; charset=utf-8');

function stopStreamByCameraId(int $cameraId): int {
    $marker = "cam_$cameraId";

    if (PHP_OS_FAMILY === 'Windows') {
        // Use WMIC to find processes and terminate them.
        $cmd = 'wmic process get ProcessId,Commandline';
        exec($cmd, $out);
        $killed = 0;
        foreach ($out as $line) {
            if (!str_contains($line, 'ffmpeg')) continue;
            if (!str_contains($line, $marker)) continue;
            // PID is usually last token
            if (preg_match('/(\d+)\s*$/', trim($line), $m)) {
                $pid = (int)$m[1];
                if ($pid > 0) {
                    exec("taskkill /PID $pid /T /F");
                    $killed++;
                }
            }
        }
        return $killed;
    }

    // Linux/macOS
    $cmd = "pkill -f " . escapeshellarg("ffmpeg.*$marker");
    exec($cmd, $out, $code);
    return ($code === 0) ? 1 : 0;
}

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    $cameraId = (int)($_POST['camera_id'] ?? $_GET['camera_id'] ?? 0);
    if (!in_array($action, ['stop', 'restart', 'toggle_record'], true) || $cameraId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid action or camera_id']);
        exit;
    }

    if ($action === 'stop') {
        $killed = stopStreamByCameraId($cameraId);
        $pdo->prepare("UPDATE cameras SET status='offline' WHERE id=?")->execute([$cameraId]);
        echo json_encode(['ok' => true, 'killed' => $killed]);
        exit;
    }

    if ($action === 'toggle_record') {
        require_once __DIR__ . '/stream_manager.php';
        stopStreamByCameraId($cameraId);
        
        // Toggle the flag in DB
        $pdo->prepare("UPDATE cameras SET is_recording = 1 - is_recording WHERE id = ?")->execute([$cameraId]);
        
        // Restart stream with new settings
        $stmt = $pdo->prepare("SELECT * FROM cameras WHERE id = ?");
        $stmt->execute([$cameraId]);
        $cam = $stmt->fetch();
        if (!$cam) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Camera not found']);
            exit;
        }
        startStream($cam['id'], $cam['rtsp_url']);
        
        echo json_encode(['ok' => true, 'is_recording' => (bool)$cam['is_recording']]);
        exit;
    }

    // restart
    require_once __DIR__ . '/stream_manager.php';
    stopStreamByCameraId($cameraId);
    $stmt = $pdo->prepare("SELECT * FROM cameras WHERE id=?");
    $stmt->execute([$cameraId]);
    $cam = $stmt->fetch();
    if (!$cam) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Camera not found']);
        exit;
    }
    startStream($cam['id'], $cam['rtsp_url']);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

