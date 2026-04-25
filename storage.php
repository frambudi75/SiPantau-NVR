<?php
require_once 'core/config.php';
require_once 'core/auth.php';

$page_title = "Storage Management";

// Handle delete actions via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? '';
    
    try {
        $storagePath = $db_settings['storage_path'] ?? 'recordings/';
        $storagePath = rtrim(str_replace('\\', '/', $storagePath), '/') . '/';
        $base = realpath(RECORDINGS_DIR);

        if ($action === 'delete_selected') {
            $ids = json_decode($_POST['ids'] ?? '[]', true);
            if (empty($ids)) {
                echo json_encode(['ok' => false, 'error' => 'No recordings selected']);
                exit;
            }
            
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT id, file_path, thumbnail_path FROM recordings WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $rows = $stmt->fetchAll();
            
            $deleted = 0;
            foreach ($rows as $r) {
                // Delete files from disk
                foreach (['file_path', 'thumbnail_path'] as $k) {
                    $webPath = str_replace('\\', '/', $r[$k] ?? '');
                    if ($storagePath !== '' && $webPath !== '' && str_starts_with($webPath, $storagePath)) {
                        $relative = ltrim(substr($webPath, strlen($storagePath)), '/');
                        $disk = RECORDINGS_DIR . $relative;
                        $diskReal = realpath($disk);
                        if ($base && $diskReal && str_starts_with($diskReal, $base) && is_file($diskReal)) {
                            @unlink($diskReal);
                        }
                    }
                }
                $deleted++;
            }
            
            // Delete from DB
            $pdo->prepare("DELETE FROM recordings WHERE id IN ($placeholders)")->execute($ids);
            echo json_encode(['ok' => true, 'deleted' => $deleted]);
            exit;
        }
        
        if ($action === 'delete_by_camera_date') {
            $cameraId = (int)($_POST['camera_id'] ?? 0);
            $date = $_POST['date'] ?? '';
            if (!$cameraId || !$date) {
                echo json_encode(['ok' => false, 'error' => 'Camera and date required']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT id, file_path, thumbnail_path FROM recordings WHERE camera_id = ? AND DATE(start_time) = ?");
            $stmt->execute([$cameraId, $date]);
            $rows = $stmt->fetchAll();
            
            $deleted = 0;
            foreach ($rows as $r) {
                foreach (['file_path', 'thumbnail_path'] as $k) {
                    $webPath = str_replace('\\', '/', $r[$k] ?? '');
                    if ($storagePath !== '' && $webPath !== '' && str_starts_with($webPath, $storagePath)) {
                        $relative = ltrim(substr($webPath, strlen($storagePath)), '/');
                        $disk = RECORDINGS_DIR . $relative;
                        $diskReal = realpath($disk);
                        if ($base && $diskReal && str_starts_with($diskReal, $base) && is_file($diskReal)) {
                            @unlink($diskReal);
                        }
                    }
                }
                $deleted++;
            }
            
            $pdo->prepare("DELETE FROM recordings WHERE camera_id = ? AND DATE(start_time) = ?")->execute([$cameraId, $date]);
            echo json_encode(['ok' => true, 'deleted' => $deleted]);
            exit;
        }

        if ($action === 'delete_all_camera') {
            $cameraId = (int)($_POST['camera_id'] ?? 0);
            if (!$cameraId) {
                echo json_encode(['ok' => false, 'error' => 'Camera ID required']);
                exit;
            }
            
            // Delete all files for this camera
            $camDir = RECORDINGS_DIR . "cam_$cameraId/";
            if (is_dir($camDir)) {
                array_map('unlink', glob($camDir . "*.mp4") ?: []);
                array_map('unlink', glob($camDir . "*.jpg") ?: []);
            }
            
            $pdo->prepare("DELETE FROM recordings WHERE camera_id = ?")->execute([$cameraId]);
            echo json_encode(['ok' => true]);
            exit;
        }

        echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Fetch data for display
$cameras = $pdo->query("SELECT * FROM cameras ORDER BY name ASC")->fetchAll();

// Get recordings grouped by camera and date
$recordings = $pdo->query("
    SELECT r.*, c.name as camera_name 
    FROM recordings r 
    JOIN cameras c ON r.camera_id = c.id 
    ORDER BY r.start_time DESC
")->fetchAll();

// Group by camera -> date
$grouped = [];
$totalSize = 0;
foreach ($recordings as $rec) {
    $camName = $rec['camera_name'];
    $date = date('Y-m-d', strtotime($rec['start_time']));
    $grouped[$camName][$date][] = $rec;
    $totalSize += $rec['file_size'];
}

// Disk usage
$diskTotal = @disk_total_space(RECORDINGS_DIR) ?: 0;
$diskFree = @disk_free_space(RECORDINGS_DIR) ?: 0;
$diskUsed = $diskTotal - $diskFree;

include 'core/header.php';
?>

<main class="main-content">
    <header>
        <div class="header-title">
            <h1>Storage Management</h1>
            <p>Manage recordings and free up disk space.</p>
        </div>
    </header>

    <!-- Storage Overview -->
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
        <div class="camera-card" style="padding: 1.5rem; display: flex; align-items: center; gap: 1rem;">
            <div style="background: rgba(0, 210, 255, 0.1); padding: 1rem; border-radius: 12px; color: var(--primary);">
                <i class="ph ph-database" style="font-size: 2rem;"></i>
            </div>
            <div>
                <h2 style="font-size: 1.5rem;"><?= number_format($totalSize / 1024 / 1024, 1) ?> MB</h2>
                <p style="color: var(--text-muted); font-size: 0.8rem;">Total Recordings</p>
            </div>
        </div>
        <div class="camera-card" style="padding: 1.5rem; display: flex; align-items: center; gap: 1rem;">
            <div style="background: rgba(16, 185, 129, 0.1); padding: 1rem; border-radius: 12px; color: var(--success);">
                <i class="ph ph-hard-drive" style="font-size: 2rem;"></i>
            </div>
            <div>
                <h2 style="font-size: 1.5rem;"><?= number_format($diskFree / 1024 / 1024 / 1024, 1) ?> GB</h2>
                <p style="color: var(--text-muted); font-size: 0.8rem;">Free Disk Space</p>
            </div>
        </div>
        <div class="camera-card" style="padding: 1.5rem; display: flex; align-items: center; gap: 1rem;">
            <div style="background: rgba(239, 68, 68, 0.1); padding: 1rem; border-radius: 12px; color: var(--danger);">
                <i class="ph ph-files" style="font-size: 2rem;"></i>
            </div>
            <div>
                <h2 style="font-size: 1.5rem;"><?= count($recordings) ?></h2>
                <p style="color: var(--text-muted); font-size: 0.8rem;">Total Files</p>
            </div>
        </div>
    </div>

    <!-- Recordings Table -->
    <?php if (empty($grouped)): ?>
        <div class="camera-card" style="padding: 3rem; text-align: center;">
            <i class="ph ph-folder-open" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem;"></i>
            <h3>No recordings found</h3>
            <p style="color: var(--text-muted);">Recordings will appear here once cameras start recording.</p>
        </div>
    <?php else: ?>
        <?php foreach ($grouped as $camName => $dates): ?>
            <div class="camera-card" style="margin-bottom: 1.5rem; overflow: hidden;">
                <div style="padding: 1.2rem 1.5rem; background: rgba(255,255,255,0.03); display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--glass-border);">
                    <h3 style="margin: 0; display: flex; align-items: center; gap: 8px;">
                        <i class="ph ph-video-camera" style="color: var(--primary);"></i>
                        <?= htmlspecialchars($camName) ?>
                    </h3>
                    <button class="btn btn-action btn-delete" style="padding: 0.4rem 1rem; width: auto; border-radius: 8px; font-size: 0.8rem;"
                            onclick="deleteAllCamera(<?= (int)$dates[array_key_first($dates)][0]['camera_id'] ?>, '<?= htmlspecialchars($camName) ?>')">
                        <i class="ph ph-trash"></i> Delete All
                    </button>
                </div>
                
                <?php foreach ($dates as $date => $recs): 
                    $dateSize = array_sum(array_column($recs, 'file_size'));
                ?>
                    <div style="border-bottom: 1px solid var(--glass-border);">
                        <div style="padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center; background: rgba(255,255,255,0.02);">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <input type="checkbox" class="date-checkbox" onchange="toggleDateCheckboxes(this, '<?= $date ?>')" 
                                       style="width: 18px; height: 18px; cursor: pointer;">
                                <span style="font-weight: 600;"><i class="ph ph-calendar" style="color: var(--text-muted);"></i> <?= $date ?></span>
                                <span style="color: var(--text-muted); font-size: 0.8rem;">
                                    <?= count($recs) ?> files · <?= number_format($dateSize / 1024 / 1024, 1) ?> MB
                                </span>
                            </div>
                            <button class="btn btn-action" style="padding: 0.3rem 0.8rem; width: auto; border-radius: 8px; font-size: 0.75rem; color: var(--danger); border-color: var(--danger);"
                                    onclick="deleteByDate(<?= (int)$recs[0]['camera_id'] ?>, '<?= $date ?>')">
                                <i class="ph ph-trash"></i> Delete Date
                            </button>
                        </div>
                        
                        <table style="width: 100%; border-collapse: collapse;">
                            <?php foreach ($recs as $rec): ?>
                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.03);">
                                    <td style="padding: 0.6rem 1.5rem; width: 40px;">
                                        <input type="checkbox" class="rec-checkbox" value="<?= $rec['id'] ?>" data-date="<?= $date ?>"
                                               style="width: 16px; height: 16px; cursor: pointer;">
                                    </td>
                                    <td style="padding: 0.6rem 0.5rem;">
                                        <span style="font-family: monospace; font-size: 0.85rem;">
                                            <?= date('H:i:s', strtotime($rec['start_time'])) ?>
                                        </span>
                                    </td>
                                    <td style="padding: 0.6rem 0.5rem; color: var(--text-muted); font-size: 0.85rem;">
                                        <?= $rec['file_size'] > 0 ? number_format($rec['file_size'] / 1024 / 1024, 2) . ' MB' : '<span style="color: var(--danger);">0 bytes</span>' ?>
                                    </td>
                                    <td style="padding: 0.6rem 0.5rem; font-family: monospace; font-size: 0.75rem; color: var(--text-muted);">
                                        <?= htmlspecialchars($rec['filename']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <!-- Bulk Action Bar -->
        <div id="bulkBar" style="position: fixed; bottom: -80px; left: 260px; right: 0; background: rgba(15,23,42,0.95); backdrop-filter: blur(12px); 
             border-top: 1px solid var(--glass-border); padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center;
             transition: bottom 0.3s ease; z-index: 50;">
            <span id="bulkCount" style="color: var(--text-muted);"></span>
            <button class="btn" style="background: var(--danger); color: white;" onclick="deleteSelected()">
                <i class="ph ph-trash"></i> Delete Selected
            </button>
        </div>
    <?php endif; ?>
</main>

<style>
    input[type="checkbox"] {
        accent-color: var(--primary);
    }
    table tr:hover {
        background: rgba(255,255,255,0.02);
    }
</style>

<script>
    // Toggle all checkboxes for a date
    function toggleDateCheckboxes(masterCheckbox, date) {
        const checkboxes = document.querySelectorAll(`.rec-checkbox[data-date="${date}"]`);
        checkboxes.forEach(cb => cb.checked = masterCheckbox.checked);
        updateBulkBar();
    }

    // Watch all checkboxes
    document.addEventListener('change', (e) => {
        if (e.target.classList.contains('rec-checkbox')) updateBulkBar();
    });

    function updateBulkBar() {
        const checked = document.querySelectorAll('.rec-checkbox:checked');
        const bar = document.getElementById('bulkBar');
        if (checked.length > 0) {
            bar.style.bottom = '0';
            document.getElementById('bulkCount').textContent = checked.length + ' recording(s) selected';
        } else {
            bar.style.bottom = '-80px';
        }
    }

    function deleteSelected() {
        const checked = document.querySelectorAll('.rec-checkbox:checked');
        const ids = Array.from(checked).map(cb => cb.value);
        if (ids.length === 0) return;
        if (!confirm(`Delete ${ids.length} recording(s)? This cannot be undone.`)) return;

        const formData = new FormData();
        formData.append('action', 'delete_selected');
        formData.append('ids', JSON.stringify(ids));

        fetch('storage.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                if (!res.ok) throw new Error(res.error);
                location.reload();
            })
            .catch(err => alert('Delete failed: ' + err.message));
    }

    function deleteByDate(cameraId, date) {
        if (!confirm(`Delete ALL recordings for ${date}? This cannot be undone.`)) return;

        const formData = new FormData();
        formData.append('action', 'delete_by_camera_date');
        formData.append('camera_id', cameraId);
        formData.append('date', date);

        fetch('storage.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                if (!res.ok) throw new Error(res.error);
                location.reload();
            })
            .catch(err => alert('Delete failed: ' + err.message));
    }

    function deleteAllCamera(cameraId, cameraName) {
        if (!confirm(`Delete ALL recordings for "${cameraName}"? This will remove all files and cannot be undone.`)) return;

        const formData = new FormData();
        formData.append('action', 'delete_all_camera');
        formData.append('camera_id', cameraId);

        fetch('storage.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                if (!res.ok) throw new Error(res.error);
                location.reload();
            })
            .catch(err => alert('Delete failed: ' + err.message));
    }
</script>

<?php include 'core/footer.php'; ?>
