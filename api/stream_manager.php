<?php
/**
 * Stream Manager Script (Legacy Wrapper)
 * Streaming is now managed by the high-precision Python Daemon (engine/daemon.py).
 * This file exists for backwards compatibility with UI buttons.
 */

require_once __DIR__ . '/../core/config.php';

function killStreamByCameraId($cameraId) {
    $marker = "cam_$cameraId";
    if (PHP_OS_FAMILY === 'Windows') {
        return; 
    }
    exec("pkill -f " . escapeshellarg("ffmpeg.*$marker") . " 2>/dev/null");
    usleep(500000); 
}

function startStream($cameraId, $rtspUrl) {
    // The Python daemon automatically detects missing streams and starts them.
    // We just ensure any dead/stuck stream is killed, and the daemon will respawn it instantly.
    killStreamByCameraId($cameraId);
}

// Logic for manual "Sync Streams" button
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'message' => 'Streams synced via NVR Daemon']);
}
