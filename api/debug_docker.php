<?php
require_once __DIR__ . '/../core/config.php';
header('Content-Type: text/plain');

echo "--- SiPantau Docker Debug ---\n\n";

// 1. Check DB Connection
try {
    $pdo->query("SELECT 1");
    echo "[OK] Database connected.\n";
} catch (Exception $e) {
    echo "[ERROR] Database connection failed: " . $e->getMessage() . "\n";
}

// 2. Check Directories
$dirs = [
    'recordings' => RECORDINGS_DIR,
    'streams' => STREAMS_DIR
];

foreach ($dirs as $name => $path) {
    if (is_writable($path)) {
        echo "[OK] Directory '$name' is writable.\n";
    } else {
        echo "[ERROR] Directory '$name' is NOT writable. Path: $path\n";
    }
}

// 3. Check FFmpeg
$ffmpeg = FFMPEG_PATH;
$out = [];
exec("$ffmpeg -version", $out, $code);
if ($code === 0) {
    echo "[OK] FFmpeg is working. Version: " . ($out[0] ?? 'unknown') . "\n";
} else {
    echo "[ERROR] FFmpeg not found or not working. Code: $code\n";
}

// 4. Check Redis
if (ini_get('session.save_handler') === 'redis') {
    $path = ini_get('session.save_path');
    echo "Session handler is Redis: $path\n";
    $host = parse_url($path, PHP_URL_HOST);
    $port = parse_url($path, PHP_URL_PORT) ?: 6379;
    $fp = @fsockopen($host, $port, $errno, $errstr, 2);
    if ($fp) {
        echo "[OK] Redis server is reachable.\n";
        fclose($fp);
    } else {
        echo "[ERROR] Cannot connect to Redis: $errstr ($errno)\n";
    }
}
