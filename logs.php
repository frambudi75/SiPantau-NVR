<?php
require_once 'core/config.php';
require_once 'core/auth.php';

$page_title = "System Logs";

// Helper to read logs
function readLog($file) {
    $path = __DIR__ . '/' . $file;
    
    // In Docker, also check the configured error_log path
    if (!file_exists($path) && getenv('DB_HOST')) {
        $altPath = '/var/www/html/' . $file;
        if (file_exists($altPath)) {
            $path = $altPath;
        } else {
            // Create the file so it exists for future use
            @touch($altPath);
            @chmod($altPath, 0666);
            return "Log file initialized. No entries yet.";
        }
    }
    
    if (!file_exists($path)) return "Log file not found: $file (path: $path)";
    if (filesize($path) === 0) return "Log file is empty. No entries yet.";
    
    // Read last 100 lines
    $content = @shell_exec("tail -n 100 " . escapeshellarg($path));
    if (!$content) {
        // Fallback for Windows or empty file
        $lines = file($path);
        if (!$lines) return "Log is empty.";
        $content = implode("", array_slice($lines, -100));
    }
    return htmlspecialchars($content);
}

$php_log = readLog('error.log');
$ffmpeg_log = readLog('ffmpeg_error.log');

include 'core/header.php';
?>

<div class="main-content">
    <div class="content-header">
        <div>
            <h1>System Logs</h1>
            <p>Monitor system activities and debug technical issues.</p>
        </div>
        <div class="header-actions">
            <button onclick="location.reload()" class="btn-action" title="Refresh Logs">
                <i class="ph ph-arrows-clockwise"></i> Refresh
            </button>
        </div>
    </div>

    <div class="grid" style="grid-template-columns: 1fr; gap: 2rem;">
        <!-- PHP Error Log -->
        <div class="card">
            <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                <h3 style="margin:0;"><i class="ph ph-php-file" style="color: #777BB4;"></i> PHP Error Log</h3>
                <span class="badge" style="background: rgba(119, 123, 180, 0.1); color: #777BB4;">error.log</span>
            </div>
            <div style="background: #0f172a; color: #94a3b8; padding: 1.5rem; border-radius: 8px; font-family: 'Fira Code', monospace; font-size: 0.85rem; overflow-x: auto; max-height: 400px; line-height: 1.6; border: 1px solid #1e293b;">
                <pre style="margin:0;"><?= $php_log ?: 'No errors recorded.' ?></pre>
            </div>
        </div>

        <!-- FFmpeg Log -->
        <div class="card">
            <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                <h3 style="margin:0;"><i class="ph ph-file-video" style="color: #ef4444;"></i> FFmpeg Stream Log</h3>
                <span class="badge" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">ffmpeg_error.log</span>
            </div>
            <div style="background: #0f172a; color: #94a3b8; padding: 1.5rem; border-radius: 8px; font-family: 'Fira Code', monospace; font-size: 0.85rem; overflow-x: auto; max-height: 400px; line-height: 1.6; border: 1px solid #1e293b;">
                <pre style="margin:0;"><?= $ffmpeg_log ?: 'No activity recorded.' ?></pre>
            </div>
        </div>
    </div>
</div>

<style>
pre {
    white-space: pre-wrap;
    word-wrap: break-word;
}
/* Custom Scrollbar for Logs */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}
::-webkit-scrollbar-track {
    background: #1e293b;
}
::-webkit-scrollbar-thumb {
    background: #334155;
    border-radius: 4px;
}
::-webkit-scrollbar-thumb:hover {
    background: #475569;
}
</style>

<?php include 'core/footer.php'; ?>
