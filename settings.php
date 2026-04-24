<?php
require_once 'core/config.php';
require_once 'core/auth.php';

$message = '';

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        foreach ($_POST['settings'] as $key => $value) {
            $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?");
            $stmt->execute([$key, $value, $value]);
        }
        
        $pdo->commit();
        $message = '<div class="badge badge-rec" style="background: var(--success); padding: 10px; margin-bottom: 1rem; display: block; text-align: center;">Settings updated successfully!</div>';
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = '<div class="badge badge-rec" style="background: var(--danger); padding: 10px; margin-bottom: 1rem; display: block; text-align: center;">Error: ' . $e->getMessage() . '</div>';
    }
}

// Fetch current settings
$settings_raw = $pdo->query("SELECT * FROM settings")->fetchAll();
$settings = [];
foreach ($settings_raw as $s) {
    $settings[$s['key']] = $s['value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - NVR System</title>
    <link rel="stylesheet" href="assets/css/index.css">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        .settings-card {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid var(--glass-border);
            padding: 2rem;
            max-width: 800px;
        }
        .settings-info {
            background: rgba(0, 210, 255, 0.05);
            padding: 1rem;
            border-radius: 12px;
            color: var(--primary);
            font-size: 0.85rem;
            margin-bottom: 2rem;
            display: flex;
            gap: 10px;
            align-items: center;
        }
    </style>
</head>
<body>

    <?php require_once 'core/sidebar.php'; ?>

    <main class="main-content">
        <header>
            <div class="header-title">
                <h1>System Settings</h1>
                <p>Configure recording engine and storage policies.</p>
            </div>
        </header>

        <?= $message ?>

        <div class="settings-card">
            <div class="settings-info">
                <i class="ph ph-info" style="font-size: 1.5rem;"></i>
                <div>
                   Changing storage paths or FFmpeg settings will require a "Sync Streams" from the dashboard to apply to active recordings.
                </div>
            </div>

            <form action="settings.php" method="POST">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div>
                        <h3 style="margin-bottom: 1.5rem; color: var(--primary);">Storage & Archive</h3>
                        
                        <div class="form-group">
                            <label>Storage Path</label>
                            <input type="text" name="settings[storage_path]" class="form-control" 
                                   value="<?= htmlspecialchars($settings['storage_path'] ?? 'recordings/') ?>">
                            <small style="color: var(--text-muted);">Relative or absolute path to save MP4 files.</small>
                        </div>

                        <div class="form-group">
                            <label>Recording Retention (Days)</label>
                            <input type="number" name="settings[retention_days]" class="form-control" 
                                   value="<?= htmlspecialchars($settings['retention_days'] ?? '7') ?>">
                            <small style="color: var(--text-muted);">Files older than this will be automatically deleted.</small>
                        </div>
                    </div>

                    <div>
                        <h3 style="margin-bottom: 1.5rem; color: var(--primary);">Video Engine</h3>
                        
                        <div class="form-group">
                            <label>FFmpeg Path</label>
                            <input type="text" name="settings[ffmpeg_path]" class="form-control" 
                                   value="<?= htmlspecialchars($settings['ffmpeg_path'] ?? 'ffmpeg') ?>">
                            <small style="color: var(--text-muted);">Executable name or full path (e.g. /usr/bin/ffmpeg).</small>
                        </div>

                        <div class="form-group">
                            <label>Recording Segment Time (Seconds)</label>
                            <input type="number" name="settings[segment_time]" class="form-control" 
                                   value="<?= htmlspecialchars($settings['segment_time'] ?? '600') ?>">
                            <small style="color: var(--text-muted);">How many seconds per video clip (Default: 600 = 10 min).</small>
                        </div>

                        <h3 style="margin-top: 2rem; margin-bottom: 1.5rem; color: var(--accent);">
                            <i class="ph ph-paper-plane-tilt"></i> Telegram Alerts
                        </h3>
                        <div class="form-group">
                            <label>Bot Token</label>
                            <input type="text" name="settings[telegram_bot_token]" class="form-control" 
                                   value="<?= htmlspecialchars($settings['telegram_bot_token'] ?? '') ?>" placeholder="123456:ABC-DEF...">
                        </div>
                        <div class="form-group">
                            <label>Chat ID</label>
                            <input type="text" name="settings[telegram_chat_id]" class="form-control" 
                                   value="<?= htmlspecialchars($settings['telegram_chat_id'] ?? '') ?>" placeholder="-100123456789">
                        </div>
                    </div>
                </div>

                <div style="margin-top: 2rem; border-top: 1px solid var(--glass-border); padding-top: 2rem; text-align: right;">
                    <button type="submit" class="btn btn-primary">
                        <i class="ph ph-floppy-disk"></i> Save System Settings
                    </button>
                </div>
            </form>
        </div>

        <div class="camera-card" style="margin-top: 2rem; padding: 2rem; max-width: 800px; border-color: var(--glass-border);">
            <h3 style="margin-bottom: 1rem;"><i class="ph ph-activity"></i> System Environment</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.9rem;">
                <div style="color: var(--text-muted);">OS Family:</div>
                <div><?= PHP_OS_FAMILY ?></div>
                
                <div style="color: var(--text-muted);">PHP Version:</div>
                <div><?= PHP_VERSION ?></div>
                
                <div style="color: var(--text-muted);">Disk Space (Free):</div>
                <div>
                    <?php 
                        $bytes = disk_free_space(".");
                        $units = array('B', 'KB', 'MB', 'GB', 'TB');
                        for ($i = 0; $bytes >= 1024 && $i < 4; $i++) $bytes /= 1024;
                        echo round($bytes, 2) . " " . $units[$i];
                    ?>
                </div>

                <div style="color: var(--text-muted);">FFmpeg Status:</div>
                <div>
                    <?php 
                        $ffmpeg = $settings['ffmpeg_path'] ?? 'ffmpeg';
                        $cmd = escapeshellcmd($ffmpeg) . " -version";
                        exec($cmd, $output, $return_var);
                        if ($return_var === 0) {
                            echo '<span style="color: var(--success);">Installed</span>';
                        } else {
                            echo '<span style="color: var(--danger);">Not Found</span>';
                        }
                    ?>
                </div>
            </div>
        </div>
    </main>

</body>
</html>
