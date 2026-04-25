<?php
/**
 * Configuration file for NVR system
 */

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'nvr_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// Redis Session Handler (Only for Docker)
if (getenv('DB_HOST')) {
    ini_set('session.save_handler', 'redis');
    ini_set('session.save_path', 'tcp://redis:6379');
    
    // Docker Error Logging (Prevent HTML errors from breaking JSON)
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', '/var/www/html/error.log');
    
    // Set timezone to match local server time
    date_default_timezone_set(getenv('TZ') ?: 'Asia/Jakarta');
}

// PDO Database Connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Fetch dynamic settings from DB
    $db_settings = $pdo->query("SELECT `key`, `value` FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Auto-detect if we are in Docker
    if (getenv('DB_HOST')) {
        define('FFMPEG_PATH', 'ffmpeg'); // Use standard linux command in Docker
    } else {
        define('FFMPEG_PATH', $db_settings['ffmpeg_path'] ?? 'ffmpeg');
    }

    define('RECORDINGS_DIR', __DIR__ . '/../' . ($db_settings['storage_path'] ?? 'recordings/'));
    define('STREAMS_DIR', __DIR__ . '/../streams/');
    define('SEGMENT_TIME', $db_settings['segment_time'] ?? 600);

} catch (PDOException $e) {
    // Check if it's an API request
    $isJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || 
              str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/') ||
              (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest');

    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'ok' => false, 
            'error' => 'Database Connection Failed',
            'details' => $e->getMessage()
        ]);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo "<div style='background: #fee2e2; color: #991b1b; padding: 20px; border-radius: 8px; margin: 20px; font-family: sans-serif; border: 1px solid #ef4444;'>";
        echo "<h3 style='margin-top:0'>Database Connection Failed</h3>";
        echo "<p>Details: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p>If you are using Docker, please wait a few seconds for the database to initialize.</p>";
        echo "</div>";
    }
    exit;
}

// Ensure directories exist
if (!is_dir(RECORDINGS_DIR)) mkdir(RECORDINGS_DIR, 0777, true);
if (!is_dir(STREAMS_DIR)) mkdir(STREAMS_DIR, 0777, true);
