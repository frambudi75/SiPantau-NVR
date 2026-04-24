<?php
require_once __DIR__ . '/../core/config.php';

/**
 * Watchdog Script
 * Run this every 1-5 minutes via Task Scheduler or Cron.
 */

function sendTelegram($msg) {
    global $db_settings;
    $token = $db_settings['telegram_bot_token'] ?? '';
    $chatId = $db_settings['telegram_chat_id'] ?? '';
    
    if ($token && $chatId) {
        $url = "https://api.telegram.org/bot$token/sendMessage?chat_id=$chatId&text=" . urlencode($msg);
        @file_get_contents($url);
    }
}

// 1. Database Updates (One-time)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) UNIQUE, password VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    // Default pass: admin (bcrypt)
    $pdo->exec("INSERT IGNORE INTO users (username, password) VALUES ('admin', '$2y$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi')");
    $pdo->exec("INSERT IGNORE INTO settings (`key`, `value`) VALUES ('telegram_bot_token', ''), ('telegram_chat_id', '')");
} catch (Exception $e) {}

// 2. Watchdog Logic
require_once __DIR__ . '/stream_manager.php';

$cameras = $pdo->query("SELECT * FROM cameras")->fetchAll();
foreach ($cameras as $cam) {
    $camId = (int)$cam['id'];
    $marker = "cam_" . $camId;
    $isRunning = isProcessRunning('ffmpeg', $marker);

    $m3u8 = STREAMS_DIR . "cam_$camId/index.m3u8";
    $m3u8Fresh = false;
    if (is_file($m3u8)) {
        $age = time() - filemtime($m3u8);
        $m3u8Fresh = ($age >= 0 && $age <= 12); // should update often with 2s segments
    }

    // Determine effective online/offline
    $isHealthy = $isRunning && $m3u8Fresh;
    $newStatus = $isHealthy ? 'online' : 'offline';
    if ($cam['status'] !== $newStatus) {
        $pdo->prepare("UPDATE cameras SET status=? WHERE id=?")->execute([$newStatus, $camId]);
    }

    if (!$isHealthy) {
        if ($cam['status'] === 'online') {
            sendTelegram("⚠️ WARNING: Camera [{$cam['name']}] went OFFLINE. Attempting auto-recovery...");
        }
        echo "Restarting Camera {$camId}...\n";
        startStream($camId, $cam['rtsp_url']);
    }
}

echo "Watchdog run completed.\n";
