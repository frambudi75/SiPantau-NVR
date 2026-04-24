<?php
/**
 * Configuration file for NVR system
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'nvr_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// PDO Database Connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Fetch dynamic settings from DB
    $db_settings = $pdo->query("SELECT `key`, `value` FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    
    define('FFMPEG_PATH', $db_settings['ffmpeg_path'] ?? 'ffmpeg');
    define('RECORDINGS_DIR', __DIR__ . '/../' . ($db_settings['storage_path'] ?? 'recordings/'));
    define('STREAMS_DIR', __DIR__ . '/../streams/');
    define('SEGMENT_TIME', $db_settings['segment_time'] ?? 600);

} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

// Ensure directories exist
if (!is_dir(RECORDINGS_DIR)) mkdir(RECORDINGS_DIR, 0777, true);
if (!is_dir(STREAMS_DIR)) mkdir(STREAMS_DIR, 0777, true);
