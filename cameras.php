<?php
require_once 'core/config.php';
require_once 'core/auth.php';

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $pdo->prepare("DELETE FROM cameras WHERE id = ?")->execute([$id]);
    header("Location: cameras.php");
    exit;
}

// Fetch all cameras
$cameras = $pdo->query("SELECT * FROM cameras ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Camera Management - NVR System</title>
    <link rel="stylesheet" href="assets/css/index.css">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        .camera-list-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid var(--glass-border);
        }
        .camera-list-table th, .camera-list-table td {
            padding: 1.2rem;
            text-align: left;
            border-bottom: 1px solid var(--glass-border);
        }
        .camera-list-table th {
            background: rgba(255,255,255,0.05);
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
        }
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .status-online { background: var(--success); box-shadow: 0 0 10px var(--success); }
        .status-offline { background: var(--text-muted); }
    </style>
</head>
<body>

    <?php require_once 'core/sidebar.php'; ?>

    <main class="main-content">
        <header>
            <div class="header-title">
                <h1>Camera Management</h1>
                <p>Manage your linked CCTV hardware.</p>
            </div>
            <button class="btn btn-primary" onclick="showAddModal()">
                <i class="ph ph-plus-circle"></i> Add New Camera
            </button>
        </header>

        <table class="camera-list-table">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Camera Name</th>
                    <th>Location</th>
                    <th>RTSP URL</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($cameras)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 4rem;">No cameras added yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($cameras as $cam): ?>
                        <tr>
                            <td>
                                <span class="status-dot <?= $cam['status'] == 'online' ? 'status-online' : 'status-offline' ?>"></span>
                                <?= ucfirst($cam['status']) ?>
                            </td>
                            <td><strong><?= htmlspecialchars($cam['name']) ?></strong></td>
                            <td><?= htmlspecialchars($cam['location'] ?: '-') ?></td>
                            <td style="font-family: monospace; font-size: 0.8rem; color: var(--text-muted);">
                                <?= htmlspecialchars(substr($cam['rtsp_url'], 0, 40)) ?>...
                            </td>
                            <td>
                                <div style="display: flex; gap: 10px;">
                                    <button class="btn btn-action" style="color: var(--success); border-color: var(--success);" 
                                            title="Test Connection" 
                                            onclick="testCamera(<?= (int)$cam['id'] ?>, '<?= htmlspecialchars($cam['name']) ?>')">
                                        <i class="ph-bold ph-plugs-connected"></i>
                                    </button>
                                    <button class="btn btn-action btn-edit" title="Edit Camera">
                                        <i class="ph-bold ph-pencil-simple"></i>
                                    </button>
                                    <a href="?delete=<?= $cam['id'] ?>" class="btn btn-action btn-delete" 
                                       onclick="return confirm('Delete this camera?')"
                                       title="Delete Camera">
                                        <i class="ph-bold ph-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </main>

    <!-- Modal Add Camera (Copied from index.php) -->
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
                </div>

                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" class="form-control" placeholder="e.g. Warehouse">
                </div>
                <div style="display: flex; gap: 10px; margin-top: 2rem;">
                    <button type="button" class="btn" onclick="hideAddModal()" style="flex: 1; background: rgba(255,255,255,0.05);">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="flex: 2;">Save Camera</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Test Camera Modal -->
    <div id="testModal" class="modal">
        <div class="modal-content" style="max-width: 600px; max-height: 80vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 id="testModalTitle" style="margin: 0;"><i class="ph ph-plugs-connected" style="color: var(--primary);"></i> Test Connection</h2>
                <button class="btn btn-action" onclick="hideTestModal()" style="border: none;"><i class="ph ph-x"></i></button>
            </div>
            <div id="testResult">
                <div style="text-align: center; padding: 3rem;">
                    <i class="ph ph-circle-notch ph-spin" style="font-size: 2rem; color: var(--primary);"></i>
                    <p style="color: var(--text-muted); margin-top: 1rem;">Running diagnostics...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showAddModal() { document.getElementById('addModal').style.display = 'flex'; }
        function hideAddModal() { document.getElementById('addModal').style.display = 'none'; }
        function hideTestModal() { document.getElementById('testModal').style.display = 'none'; }

        function testCamera(cameraId, cameraName) {
            document.getElementById('testModalTitle').innerHTML = 
                '<i class="ph ph-plugs-connected" style="color: var(--primary);"></i> Test: ' + cameraName;
            document.getElementById('testResult').innerHTML = 
                '<div style="text-align: center; padding: 3rem;">' +
                '<i class="ph ph-circle-notch ph-spin" style="font-size: 2rem; color: var(--primary);"></i>' +
                '<p style="color: var(--text-muted); margin-top: 1rem;">Running diagnostics... (may take up to 15s)</p></div>';
            document.getElementById('testModal').style.display = 'flex';

            fetch('api/test_camera.php?camera_id=' + cameraId)
                .then(r => r.json())
                .then(data => {
                    let html = '<div style="display: flex; flex-direction: column; gap: 1rem;">';
                    
                    // Status Banner
                    const isOk = data.stream_status === 'OK';
                    const bannerColor = isOk ? 'var(--success)' : 'var(--danger)';
                    const bannerIcon = isOk ? 'ph-check-circle' : 'ph-warning';
                    html += `<div style="background: ${isOk ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)'}; ` +
                            `border: 1px solid ${bannerColor}; border-radius: 12px; padding: 1.2rem; text-align: center;">` +
                            `<i class="ph-bold ${bannerIcon}" style="font-size: 2rem; color: ${bannerColor};"></i>` +
                            `<p style="color: ${bannerColor}; font-weight: 600; margin-top: 0.5rem;">${data.stream_message || 'Unknown'}</p>` +
                            `<p style="color: var(--text-muted); font-size: 0.8rem;">${data.rtsp_url_tested || ''}</p></div>`;

                    // Checklist
                    html += '<div style="background: rgba(15,23,42,0.5); border-radius: 12px; padding: 1.2rem; border: 1px solid var(--glass-border);">';
                    html += '<h4 style="margin-bottom: 0.8rem; color: var(--text-muted);">Diagnostics</h4>';
                    
                    const checks = [
                        { label: 'FFmpeg Available', ok: data.ffmpeg_available, detail: data.ffmpeg_version },
                        { label: 'Camera Reachable (Ping)', ok: data.ping_reachable, detail: data.camera_ip },
                        { label: 'RTSP Port 554 Open', ok: data.port_554_open, detail: data.port_554_error || '' },
                        { label: 'Stream Connection', ok: isOk, detail: data.stream_status }
                    ];
                    
                    checks.forEach(c => {
                        const icon = c.ok ? '✅' : (c.ok === false ? '❌' : '⚪');
                        const color = c.ok ? 'var(--success)' : (c.ok === false ? 'var(--danger)' : 'var(--text-muted)');
                        html += `<div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid var(--glass-border);">` +
                                `<span>${icon} ${c.label}</span>` +
                                `<span style="color: ${color}; font-size: 0.8rem; font-family: monospace;">${c.detail || (c.ok ? 'OK' : 'FAIL')}</span></div>`;
                    });
                    html += '</div>';

                    // Video/Audio info
                    if (data.video_info) {
                        html += `<div style="background: rgba(0,210,255,0.05); border: 1px solid rgba(0,210,255,0.2); border-radius: 12px; padding: 1rem;">` +
                                `<p style="font-size: 0.8rem; font-family: monospace; color: var(--primary);">${data.video_info}</p>` +
                                (data.audio_info ? `<p style="font-size: 0.8rem; font-family: monospace; color: var(--text-muted); margin-top: 0.3rem;">${data.audio_info}</p>` : '') +
                                `</div>`;
                    }

                    // Raw output (collapsible)
                    if (data.probe_raw_output) {
                        html += `<details style="cursor: pointer;">` +
                                `<summary style="color: var(--text-muted); font-size: 0.85rem;">Raw FFmpeg Output (${data.probe_time_seconds || '?'}s)</summary>` +
                                `<pre style="background: #0f172a; color: #94a3b8; padding: 1rem; border-radius: 8px; font-size: 0.75rem; ` +
                                `overflow-x: auto; max-height: 200px; margin-top: 0.5rem; white-space: pre-wrap; word-break: break-all;">${data.probe_raw_output}</pre></details>`;
                    }

                    html += '</div>';
                    document.getElementById('testResult').innerHTML = html;
                })
                .catch(err => {
                    document.getElementById('testResult').innerHTML = 
                        '<div style="text-align: center; padding: 2rem; color: var(--danger);">' +
                        '<i class="ph ph-warning" style="font-size: 2rem;"></i>' +
                        '<p>Test failed: ' + err.message + '</p></div>';
                });
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
    </script>
</body>
</html>
