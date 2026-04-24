<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/auth.php';

/**
 * Secure recording streamer with HTTP Range support.
 * This allows playback even when storage_path is outside webroot.
 */

function resolveRecordingDiskPath(array $rec): ?string {
    global $db_settings;

    $storagePath = $db_settings['storage_path'] ?? 'recordings/';
    $storagePath = rtrim(str_replace('\\', '/', $storagePath), '/') . '/';
    $webPath = str_replace('\\', '/', $rec['file_path'] ?? '');

    if ($storagePath === '' || $webPath === '' || !str_starts_with($webPath, $storagePath)) {
        return null;
    }

    $relative = ltrim(substr($webPath, strlen($storagePath)), '/');
    $candidate = RECORDINGS_DIR . $relative;

    $base = realpath(RECORDINGS_DIR);
    $real = realpath($candidate);
    if (!$base || !$real) return null;
    if (!str_starts_with($real, $base)) return null;
    if (!is_file($real) || !is_readable($real)) return null;
    return $real;
}

function sendFileWithRange(string $path, string $downloadName): void {
    $size = filesize($path);
    $fh = fopen($path, 'rb');
    if ($fh === false) {
        http_response_code(500);
        exit;
    }

    $mime = 'video/mp4';
    header('Content-Type: ' . $mime);
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');

    $disposition = 'inline';
    if (isset($_GET['download']) && $_GET['download'] === '1') {
        $disposition = 'attachment';
    }
    header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '', $downloadName) . '"');

    $range = $_SERVER['HTTP_RANGE'] ?? '';
    if ($range && preg_match('/bytes=(\d*)-(\d*)/', $range, $m)) {
        $start = ($m[1] !== '') ? (int)$m[1] : 0;
        $end = ($m[2] !== '') ? (int)$m[2] : ($size - 1);
        if ($start < 0) $start = 0;
        if ($end >= $size) $end = $size - 1;
        if ($end < $start) {
            header("Content-Range: bytes */$size");
            http_response_code(416);
            fclose($fh);
            exit;
        }

        $length = $end - $start + 1;
        http_response_code(206);
        header("Content-Range: bytes $start-$end/$size");
        header('Content-Length: ' . $length);

        fseek($fh, $start);
        $buf = 8192;
        $remaining = $length;
        while ($remaining > 0 && !feof($fh)) {
            $read = ($remaining > $buf) ? $buf : $remaining;
            $data = fread($fh, $read);
            if ($data === false) break;
            echo $data;
            $remaining -= strlen($data);
            if (connection_aborted()) break;
        }
        fclose($fh);
        exit;
    }

    header('Content-Length: ' . $size);
    fpassthru($fh);
    fclose($fh);
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    exit('Missing id');
}

$stmt = $pdo->prepare("SELECT * FROM recordings WHERE id = ?");
$stmt->execute([$id]);
$rec = $stmt->fetch();
if (!$rec) {
    http_response_code(404);
    exit('Not found');
}

$disk = resolveRecordingDiskPath($rec);
if (!$disk) {
    http_response_code(404);
    exit('File missing or not accessible');
}

$downloadName = $rec['filename'] ?? ('recording-' . $id . '.mp4');
sendFileWithRange($disk, $downloadName);

