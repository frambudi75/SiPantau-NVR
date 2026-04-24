<?php
require_once 'core/config.php';
require_once 'core/auth.php';

// Fetch all cameras for the filter
$cameras = $pdo->query("SELECT * FROM cameras ORDER BY name ASC")->fetchAll();

// Get selected camera and date (default to today)
$selected_camera = $_GET['camera_id'] ?? ($cameras[0]['id'] ?? 0);
$selected_date = $_GET['date'] ?? date('Y-m-d');

// Fetch recordings for selected camera and date
$recordings = [];
if ($selected_camera) {
    $stmt = $pdo->prepare("SELECT * FROM recordings WHERE camera_id = ? AND DATE(start_time) = ? ORDER BY start_time DESC");
    $stmt->execute([$selected_camera, $selected_date]);
    $recordings = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Playback - NVR System</title>
    <link rel="stylesheet" href="assets/css/index.css">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        .playback-container {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 2rem;
        }
        .recording-list {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid var(--glass-border);
            padding: 1.5rem;
            max-height: 70vh;
            overflow-y: auto;
        }
        .recording-item {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 0.8rem;
            background: rgba(255,255,255,0.03);
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid transparent;
        }
        .recording-item:hover {
            background: rgba(0, 210, 255, 0.05);
            border-color: var(--primary);
        }
        .recording-item.active {
            background: rgba(0, 210, 255, 0.1);
            border-color: var(--primary);
        }
        .player-section {
            background: #000;
            border-radius: 20px;
            overflow: hidden;
            aspect-ratio: 16/9;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        .filter-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            background: var(--bg-card);
            padding: 1rem;
            border-radius: 15px;
            align-items: flex-end;
        }
    </style>
</head>
<body>

    <?php require_once 'core/sidebar.php'; ?>

    <main class="main-content">
        <header>
            <div class="header-title">
                <h1>Video Playback</h1>
                <p>Browse and review recorded footage.</p>
            </div>
            <div class="header-actions" style="display: flex; gap: 10px;">
                <button type="button" class="btn" style="background: rgba(255,255,255,0.05);" onclick="syncRecordings()">
                    <i class="ph ph-arrows-counter-clockwise"></i> Sync Recordings
                </button>
            </div>
        </header>

        <!-- Filters -->
        <form class="filter-bar" method="GET">
            <div class="form-group" style="margin-bottom: 0;">
                <label>Select Camera</label>
                <select name="camera_id" class="form-control" style="width: 200px;" onchange="this.form.submit()">
                    <?php foreach ($cameras as $cam): ?>
                        <option value="<?= $cam['id'] ?>" <?= $selected_camera == $cam['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cam['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label>Date</label>
                <input type="date" name="date" class="form-control" value="<?= $selected_date ?>" onchange="this.form.submit()">
            </div>
        </form>

        <!-- Visual Timeline -->
        <div class="camera-card" style="margin-bottom: 2rem; padding: 1.5rem;">
            <h3 style="margin-bottom: 1rem; font-size: 0.9rem; color: var(--text-muted);">
                24-Hour Coverage Timeline (<?= $selected_date ?>)
            </h3>
            <div id="timeline-wrapper" style="position: relative; height: 40px; background: rgba(255,255,255,0.05); border-radius: 8px; overflow: hidden; display: flex;">
                <?php 
                // Simple logic to show 24h blocks
                for ($h = 0; $h < 24; $h++) {
                    echo "<div style='flex: 1; border-right: 1px solid rgba(255,255,255,0.05); font-size: 0.6rem; padding: 2px; color: #444;'>$h</div>";
                }
                
                // Overlay recordings
                foreach ($recordings as $rec) {
                    $start = strtotime($rec['start_time']);
                    $day_start = strtotime($selected_date . ' 00:00:00');
                    $offset_percent = (($start - $day_start) / 86400) * 100;
                    $duration_percent = (SEGMENT_TIME / 86400) * 100;
                    
                    echo "<div class='timeline-block' 
                         style='position: absolute; left: {$offset_percent}%; width: {$duration_percent}%; height: 100%; background: var(--primary); opacity: 0.6; cursor: pointer;'
                         onclick=\"document.getElementById('rec-{$rec['id']}').click()\"></div>";
                }
                ?>
            </div>
        </div>

        <div class="playback-container">
            <!-- Sidebar: List of clips -->
            <div class="recording-list">
                <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 8px;">
                    <i class="ph ph-list"></i> Recordings
                </h3>
                <?php if (empty($recordings)): ?>
                    <p style="color: var(--text-muted); text-align: center; padding: 2rem;">No recordings found for this date.</p>
                <?php else: ?>
                    <?php foreach ($recordings as $rec): 
                        $thumb = $rec['thumbnail_path'] ?: "data:image/svg+xml;utf8," . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="320" height="180"><rect width="100%" height="100%" fill="#0b0f16"/><path d="M140 70h40v40h-40z" fill="#1f2a3a"/><path d="M150 80l22 10-22 10z" fill="#5b6b84"/><text x="50%" y="85%" text-anchor="middle" fill="#5b6b84" font-family="Arial" font-size="14">No thumbnail</text></svg>'); ?>
                        <div class="recording-item" id="rec-<?= $rec['id'] ?>" onclick="playVideo(<?= (int)$rec['id'] ?>, this)" style="display: flex; gap: 12px; align-items: center;">
                            <img src="<?= $thumb ?>" style="width: 80px; aspect-ratio: 16/9; object-fit: cover; border-radius: 8px; background: #000;">
                            <div>
                                <div style="font-weight: 600;"><?= date('H:i:s', strtotime($rec['start_time'])) ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">
                                    Size: <?= round($rec['file_size'] / 1024 / 1024, 2) ?> MB
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Main: Player -->
            <div class="main-player">
                <div class="player-section">
                    <video id="playbackPlayer" controls style="width: 100%; height: 100%;">
                        Your browser does not support the video tag.
                    </video>
                    <div id="playerPlaceholder" style="position: absolute; color: var(--text-muted); text-align: center;">
                        <i class="ph ph-play-circle" style="font-size: 4rem; opacity: 0.5;"></i>
                        <p>Select a clip to start playback</p>
                    </div>
                </div>
                
                <div style="margin-top: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
                    <div id="clipInfo">
                        <h3 id="currentClipTime">---</h3>
                        <p id="currentClipStatus" style="color: var(--text-muted);">No clip selected</p>
                    </div>
                    <div class="actions">
                        <button class="btn" onclick="downloadCurrent()" style="background: rgba(255,255,255,0.05);">
                            <i class="ph ph-download-simple"></i> Download MP4
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        let currentRecordingId = 0;

        function playVideo(id, element) {
            const player = document.getElementById('playbackPlayer');
            const placeholder = document.getElementById('playerPlaceholder');
            const items = document.querySelectorAll('.recording-item');
            
            // UI Updates
            items.forEach(item => item.classList.remove('active'));
            element.classList.add('active');
            placeholder.style.display = 'none';
            
            // Set source and play
            currentRecordingId = id;
            player.src = 'api/recording.php?id=' + encodeURIComponent(id);
            player.play();
            
            // Update info
            document.getElementById('currentClipTime').innerText = element.querySelector('div').innerText;
            document.getElementById('currentClipStatus').innerText = 'Recording ID: ' + id;
        }

        function downloadCurrent() {
            if (!currentRecordingId) {
                alert('Please select a clip first.');
                return;
            }
            window.open('api/recording.php?id=' + encodeURIComponent(currentRecordingId) + '&download=1', '_blank');
        }

        function syncRecordings() {
            const btn = event.currentTarget;
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="ph ph-circle-notch ph-spin"></i> Syncing...';
            btn.disabled = true;
            fetch('api/sync_recordings.php')
                .then(r => r.json())
                .then(res => {
                    if (!res.ok) throw new Error(res.error || 'Sync failed');
                    location.reload();
                })
                .catch(err => {
                    alert(err.message || 'Sync failed');
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                });
        }
    </script>
</body>
</html>
