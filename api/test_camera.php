<?php
/**
 * Test Camera API
 * Tests RTSP connection and returns diagnostic information.
 * Usage: POST/GET with ?rtsp_url=rtsp://... or ?camera_id=1
 */
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/auth.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $rtspUrl = $_REQUEST['rtsp_url'] ?? '';
    $cameraId = (int)($_REQUEST['camera_id'] ?? 0);

    // If camera_id is provided, look up the RTSP URL from DB
    if ($cameraId > 0 && empty($rtspUrl)) {
        $stmt = $pdo->prepare("SELECT rtsp_url, name FROM cameras WHERE id = ?");
        $stmt->execute([$cameraId]);
        $cam = $stmt->fetch();
        if (!$cam) {
            echo json_encode(['ok' => false, 'error' => 'Camera not found']);
            exit;
        }
        $rtspUrl = $cam['rtsp_url'];
    }

    if (empty($rtspUrl)) {
        echo json_encode(['ok' => false, 'error' => 'No RTSP URL provided. Use ?rtsp_url=... or ?camera_id=...']);
        exit;
    }

    $results = [];

    // 1. FFmpeg version
    $ffmpegPath = FFMPEG_PATH;
    $versionOutput = [];
    exec("$ffmpegPath -version 2>&1", $versionOutput, $versionCode);
    $results['ffmpeg_version'] = $versionOutput[0] ?? 'unknown';
    $results['ffmpeg_available'] = ($versionCode === 0);

    // 2. Network connectivity: try to parse IP from RTSP URL and ping it
    if (preg_match('/@([0-9.]+)/', $rtspUrl, $ipMatch)) {
        $cameraIp = $ipMatch[1];
        $results['camera_ip'] = $cameraIp;
        
        // Ping test
        $pingOut = [];
        exec("ping -c 2 -W 2 " . escapeshellarg($cameraIp) . " 2>&1", $pingOut, $pingCode);
        $results['ping_reachable'] = ($pingCode === 0);
        $results['ping_output'] = implode("\n", array_slice($pingOut, -3));
        
        // Port 554 test
        $fp = @fsockopen($cameraIp, 554, $errno, $errstr, 3);
        if ($fp) {
            $results['port_554_open'] = true;
            fclose($fp);
        } else {
            $results['port_554_open'] = false;
            $results['port_554_error'] = "$errstr ($errno)";
        }
    }

    // 3. FFmpeg probe test: try to read stream info (timeout after 10 seconds)
    $probeCmd = "$ffmpegPath -hide_banner -rtsp_transport tcp " .
                "-i " . escapeshellarg($rtspUrl) . " " .
                "-t 1 -f null - 2>&1";
    
    $probeOutput = [];
    $startTime = microtime(true);
    exec("timeout 15 $probeCmd", $probeOutput, $probeCode);
    $elapsed = round(microtime(true) - $startTime, 2);
    
    $probeText = implode("\n", $probeOutput);
    $results['probe_exit_code'] = $probeCode;
    $results['probe_time_seconds'] = $elapsed;
    
    // Parse the probe output for useful info
    if (str_contains($probeText, '401 Unauthorized') || str_contains($probeText, 'authorization failed')) {
        $results['stream_status'] = 'AUTH_FAILED';
        $results['stream_message'] = 'Username/password RTSP salah. Periksa kredensial kamera.';
    } elseif (str_contains($probeText, 'Connection refused')) {
        $results['stream_status'] = 'CONNECTION_REFUSED';
        $results['stream_message'] = 'Koneksi ditolak. Pastikan kamera aktif dan port 554 terbuka.';
    } elseif (str_contains($probeText, 'Connection timed out') || str_contains($probeText, 'Network is unreachable')) {
        $results['stream_status'] = 'TIMEOUT';
        $results['stream_message'] = 'Timeout. Kamera tidak bisa dijangkau dari server Docker.';
    } elseif (str_contains($probeText, 'Option not found') || str_contains($probeText, 'Unrecognized option')) {
        $results['stream_status'] = 'FFMPEG_ERROR';
        $results['stream_message'] = 'FFmpeg command error. Periksa opsi yang digunakan.';
    } elseif ($probeCode === 0 || str_contains($probeText, 'Video:') || str_contains($probeText, 'Stream #')) {
        $results['stream_status'] = 'OK';
        $results['stream_message'] = 'Stream berhasil terhubung!';
        
        // Extract stream info
        foreach ($probeOutput as $line) {
            if (str_contains($line, 'Video:')) {
                $results['video_info'] = trim($line);
            }
            if (str_contains($line, 'Audio:')) {
                $results['audio_info'] = trim($line);
            }
        }
    } else {
        $results['stream_status'] = 'UNKNOWN_ERROR';
        $results['stream_message'] = 'Error tidak diketahui. Lihat raw output.';
    }
    
    $results['probe_raw_output'] = $probeText;
    $results['rtsp_url_tested'] = preg_replace('/:([^:@]+)@/', ':****@', $rtspUrl); // mask password
    $results['ok'] = ($results['stream_status'] === 'OK');

    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
