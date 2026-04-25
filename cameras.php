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

    <script>
        function showAddModal() { document.getElementById('addModal').style.display = 'flex'; }
        function hideAddModal() { document.getElementById('addModal').style.display = 'none'; }

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
