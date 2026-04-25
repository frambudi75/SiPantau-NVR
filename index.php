<?php
require_once 'core/config.php';
require_once 'core/auth.php';

// Fetch cameras from DB
$cameras = $pdo->query("SELECT * FROM cameras ORDER BY name ASC")->fetchAll();

// Count stats
$active_cameras = count($cameras);
$online_cameras = count(array_filter($cameras, fn($c) => $c['status'] == 'online'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SiPantau NVR</title>
    <link rel="stylesheet" href="assets/css/index.css">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
</head>
<body>

    <?php require_once 'core/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <header>
            <div class="header-title">
                <h1>Live Monitoring</h1>
                <p>System is monitoring <?= $active_cameras ?> cameras.</p>
            </div>
            <div class="header-actions" style="display: flex; gap: 10px;">
                <button class="btn" style="background: rgba(255,255,255,0.05);" onclick="syncStreams()">
                    <i class="ph ph-arrows-counter-clockwise"></i> Sync Streams
                </button>
                <button class="btn btn-primary" onclick="showAddModal()">
                    <i class="ph ph-plus-circle"></i> Add Camera
                </button>
            </div>
        </header>

        <!-- Stats Overview -->
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
            <div class="camera-card" style="padding: 1.5rem; display: flex; align-items: center; gap: 1rem;">
                <div style="background: rgba(0, 210, 255, 0.1); padding: 1rem; border-radius: 12px; color: var(--primary);">
                    <i class="ph ph-video-camera" style="font-size: 2rem;"></i>
                </div>
                <div>
                    <h2 style="font-size: 1.5rem;"><?= $online_cameras ?> / <?= $active_cameras ?></h2>
                    <p style="color: var(--text-muted); font-size: 0.8rem;">Online Cameras</p>
                </div>
            </div>
            <div class="camera-card" style="padding: 1.5rem; display: flex; align-items: center; gap: 1rem;">
                <div style="background: rgba(239, 68, 68, 0.1); padding: 1rem; border-radius: 12px; color: var(--danger);">
                    <i class="ph ph-record" style="font-size: 2rem;"></i>
                </div>
                <div>
                    <h2 style="font-size: 1.5rem;"><?= count(array_filter($cameras, fn($c) => $c['is_recording'])) ?></h2>
                    <p style="color: var(--text-muted); font-size: 0.8rem;">Active Recordings</p>
                </div>
            </div>
            <div class="camera-card" style="padding: 1.5rem; display: flex; align-items: center; gap: 1rem;">
                <div style="background: rgba(16, 185, 129, 0.1); padding: 1rem; border-radius: 12px; color: var(--success);">
                    <i class="ph ph-hard-drive" style="font-size: 2rem;"></i>
                </div>
                <div>
                    <h2 style="font-size: 1.5rem;">85%</h2>
                    <p style="color: var(--text-muted); font-size: 0.8rem;">Disk Availability</p>
                </div>
            </div>
        </div>

        <!-- Camera Grid -->
        <div class="camera-grid">
            <?php if (empty($cameras)): ?>
                <div class="camera-card" style="grid-column: 1 / -1; padding: 4rem; text-align: center;">
                    <i class="ph ph-plus-circle" style="font-size: 4rem; color: var(--text-muted); margin-bottom: 1rem;"></i>
                    <h3>No cameras connected</h3>
                    <p style="color: var(--text-muted);">Add your first RTSP stream to start monitoring.</p>
                    <button class="btn btn-primary" style="margin-top: 1rem;" onclick="showAddModal()">Add Now</button>
                </div>
            <?php else: ?>
                <?php foreach ($cameras as $cam): ?>
                    <div class="camera-card" id="cam-<?= $cam['id'] ?>">
                        <div class="video-placeholder">
                            <div class="camera-overlay">
                                <span class="badge badge-live">Live</span>
                                <?php if ($cam['is_recording']): ?>
                                    <span class="badge badge-rec">REC</span>
                                <?php endif; ?>
                            </div>
                            <video id="video-<?= $cam['id'] ?>" style="width: 100%; height: 100%; object-fit: cover;" autoplay muted playsinline></video>
                            <div class="video-fallback" style="position: absolute; display: flex; flex-direction: column; align-items: center;">
                                <i class="ph ph-prohibit" style="font-size: 3rem; margin-bottom: 0.5rem;"></i>
                                <span>No Signal</span>
                            </div>
                        </div>
                        <div class="camera-info">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <h3><?= htmlspecialchars($cam['name']) ?></h3>
                                    <p><?= htmlspecialchars($cam['location'] ?: 'Unassigned') ?></p>
                                </div>
                                <div style="display: flex; gap: 10px;">
                                    <button class="btn btn-action <?= $cam['is_recording'] ? 'btn-delete' : '' ?>" 
                                            title="<?= $cam['is_recording'] ? 'Stop Recording' : 'Start Recording' ?>" 
                                            onclick="toggleRecord(<?= (int)$cam['id'] ?>)">
                                        <i class="ph-bold ph-record"></i>
                                    </button>
                                    <button class="btn btn-action" title="Full Screen">
                                        <i class="ph-bold ph-corners-out"></i>
                                    </button>
                                    <button class="btn btn-action btn-edit" title="Restart Stream" onclick="restartStream(<?= (int)$cam['id'] ?>)">
                                        <i class="ph-bold ph-arrow-counter-clockwise"></i>
                                    </button>
                                    <button class="btn btn-action btn-delete" title="Stop Stream" onclick="stopStream(<?= (int)$cam['id'] ?>)">
                                        <i class="ph-bold ph-stop"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal Add Camera -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <h2 style="margin-bottom: 1.5rem;">Add New Camera</h2>
            <form id="addCameraForm" action="api/add_camera.php" method="POST">
                <div class="form-group">
                    <label>Camera Name</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Front Door" required>
                </div>
                
                <div class="form-group">
                    <label>Camera Brand (Template)</label>
                    <select id="brandSelector" class="form-control" onchange="updateRtspPreview()">
                        <option value="custom">-- Custom RTSP URL --</option>
                        <option value="hikvision" data-template="rtsp://{user}:{pass}@{ip}:554/Streaming/Channels/101">Hikvision</option>
                        <option value="dahua" data-template="rtsp://{user}:{pass}@{ip}:554/cam/realmonitor?channel=1&subtype=0">Dahua</option>
                        <option value="tplink" data-template="rtsp://{user}:{pass}@{ip}:554/stream1">TP-Link / Tapo / Vigi</option>
                        <option value="onvif" data-template="rtsp://{user}:{pass}@{ip}:554/live/ch0">Generic ONVIF / Bardi</option>
                    </select>
                </div>

                <div id="smartInputs" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 1rem;">
                    <div class="form-group" style="grid-column: span 2;">
                        <label>IP Address</label>
                        <input type="text" id="cam_ip" class="form-control" placeholder="192.168.1.100" oninput="updateRtspPreview()">
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" id="cam_user" class="form-control" placeholder="admin" oninput="updateRtspPreview()">
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" id="cam_pass" class="form-control" placeholder="****" oninput="updateRtspPreview()">
                    </div>
                </div>

                <div class="form-group">
                    <label>Final RTSP URL</label>
                    <input type="text" name="rtsp_url" id="rtsp_url" class="form-control" placeholder="rtsp://..." required>
                    <small style="color: var(--text-muted); font-size: 0.7rem;">You can edit this manually if needed.</small>
                </div>

                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" class="form-control" placeholder="e.g. Warehouse 1">
                </div>
                <div style="display: flex; gap: 10px; margin-top: 2rem;">
                    <button type="button" class="btn" onclick="hideAddModal()" style="flex: 1; background: rgba(255,255,255,0.05);">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="flex: 2;">Save Camera</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showAddModal() {
            document.getElementById('addModal').style.display = 'flex';
        }

        function hideAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }

        function updateRtspPreview() {
            const selector = document.getElementById('brandSelector');
            const selected = selector.options[selector.selectedIndex];
            const template = selected.getAttribute('data-template');
            
            const ip = document.getElementById('cam_ip').value || 'IP_ADDRESS';
            const user = document.getElementById('cam_user').value || 'USER';
            const pass = document.getElementById('cam_pass').value || 'PASS';
            
            if (selector.value === 'custom') {
                document.getElementById('smartInputs').style.opacity = '0.3';
                document.getElementById('smartInputs').style.pointerEvents = 'none';
                return;
            } else {
                document.getElementById('smartInputs').style.opacity = '1';
                document.getElementById('smartInputs').style.pointerEvents = 'all';
            }

            let url = template.replace('{ip}', ip).replace('{user}', user).replace('{pass}', pass);
            document.getElementById('rtsp_url').value = url;
        }

        function syncStreams() {
            const btn = event.currentTarget;
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="ph ph-circle-notch ph-spin"></i> Syncing...';
            btn.disabled = true;

            fetch('api/stream_manager.php')
                .then(response => response.text())
                .then(data => {
                    console.log(data);
                    setTimeout(() => {
                        btn.innerHTML = originalHtml;
                        btn.disabled = false;
                        location.reload(); // Reload to update status
                    }, 1000);
                })
                .catch(err => {
                    alert('Sync failed. Check console for details.');
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                });
        }

        // Initialize streams
        document.addEventListener('DOMContentLoaded', () => {
            <?php foreach ($cameras as $cam): 
                $stream_file = "streams/cam_" . $cam['id'] . "/index.m3u8";
            ?>
                initializeStream(<?= $cam['id'] ?>, '<?= $stream_file ?>?t=' + Date.now());
            <?php endforeach; ?>
        });

        function initializeStream(id, url) {
            const video = document.getElementById('video-' + id);
            const card = document.getElementById('cam-' + id);
            const fallback = card.querySelector('.video-fallback');

            if (Hls.isSupported()) {
                const hls = new Hls({
                    enableWorker: true,
                    lowLatencyMode: true,
                    // Reduce live buffer delay/stutter
                    liveSyncDurationCount: 2,
                    liveMaxLatencyDurationCount: 4,
                    maxBufferLength: 10,
                    backBufferLength: 30,
                    maxLiveSyncPlaybackRate: 1.2,
                    // Retry settings for Docker startup delay
                    manifestLoadingMaxRetry: 10,
                    manifestLoadingRetryDelay: 2000,
                    levelLoadingMaxRetry: 10,
                    levelLoadingRetryDelay: 2000,
                    fragLoadingMaxRetry: 10,
                    fragLoadingRetryDelay: 2000
                });
                hls.loadSource(url);
                hls.attachMedia(video);
                hls.on(Hls.Events.MANIFEST_PARSED, () => {
                    video.play();
                });
                hls.on(Hls.Events.ERROR, (event, data) => {
                    if (data.fatal) {
                        console.warn(`[Camera ${id}] Fatal HLS error, retrying in 5s...`, data.type);
                        fallback.style.display = 'flex';
                        fallback.querySelector('span').textContent = 'Reconnecting...';
                        hls.destroy();
                        setTimeout(() => {
                            initializeStream(id, url.split('?')[0] + '?t=' + Date.now());
                        }, 5000);
                    }
                });
            } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                video.src = url;
            }

            video.onplaying = () => {
                fallback.style.display = 'none';
            };
        }

        function stopStream(cameraId) {
            if (!confirm('Stop stream for this camera?')) return;
            fetch('api/stream_control.php?action=stop&camera_id=' + encodeURIComponent(cameraId), { method: 'POST' })
                .then(r => r.json())
                .then(res => {
                    if (!res.ok) throw new Error(res.error || 'Stop failed');
                    location.reload();
                })
                .catch(err => alert(err.message || 'Stop failed'));
        }

        function restartStream(cameraId) {
            fetch('api/stream_control.php?action=restart&camera_id=' + encodeURIComponent(cameraId), { method: 'POST' })
                .then(r => r.json())
                .then(res => {
                    if (!res.ok) throw new Error(res.error || 'Restart failed');
                    setTimeout(() => location.reload(), 800);
                })
                .catch(err => alert(err.message || 'Restart failed'));
        }

        function toggleRecord(cameraId) {
            fetch('api/stream_control.php?action=toggle_record&camera_id=' + encodeURIComponent(cameraId), { method: 'POST' })
                .then(r => r.json())
                .then(res => {
                    if (!res.ok) throw new Error(res.error || 'Toggle failed');
                    location.reload();
                })
                .catch(err => alert(err.message || 'Toggle failed'));
        }
    </script>
</body>
</html>
