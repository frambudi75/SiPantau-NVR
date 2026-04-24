<?php
require_once 'core/config.php';
require_once 'core/auth.php';

$message = '';
$user_id = $_SESSION['user_id'];

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $username = $_POST['username'] ?? '';
        if (!empty($username)) {
            $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
            $stmt->execute([$username, $user_id]);
            $_SESSION['username'] = $username;
            $message = '<div class="badge badge-rec" style="background: var(--success); padding: 10px; margin-bottom: 1rem; display: block; text-align: center;">Profile updated!</div>';
        }
    } 
    
    elseif ($action === 'change_password') {
        $old_pass = $_POST['old_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';
        
        // Fetch current user
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (password_verify($old_pass, $user['password'])) {
            if ($new_pass === $confirm_pass) {
                $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed, $user_id]);
                $message = '<div class="badge badge-rec" style="background: var(--success); padding: 10px; margin-bottom: 1rem; display: block; text-align: center;">Password changed successfully!</div>';
            } else {
                $message = '<div class="badge badge-rec" style="background: var(--danger); padding: 10px; margin-bottom: 1rem; display: block; text-align: center;">New passwords do not match.</div>';
            }
        } else {
            $message = '<div class="badge badge-rec" style="background: var(--danger); padding: 10px; margin-bottom: 1rem; display: block; text-align: center;">Incorrect current password.</div>';
        }
    }
}

// Fetch current data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();

if (!$user_data) {
    header("Location: logout.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Profile - VISION PRO</title>
    <link rel="stylesheet" href="assets/css/index.css">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        .profile-card {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid var(--glass-border);
            padding: 2.5rem;
            max-width: 600px;
        }
    </style>
</head>
<body>

    <?php require_once 'core/sidebar.php'; ?>

    <main class="main-content">
        <header>
            <div class="header-title">
                <h1>Account Settings</h1>
                <p>Manage your login credentials and security.</p>
            </div>
        </header>

        <?= $message ?>

        <div style="display: grid; grid-template-columns: 1fr; gap: 2rem;">
            <!-- Profile Info -->
            <div class="profile-card">
                <h3 style="margin-bottom: 1.5rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 1rem;">
                    <i class="ph ph-user"></i> Basic Information
                </h3>
                <form action="" method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user_data['username']) ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Update Username</button>
                </form>
            </div>

            <!-- Password Reset -->
            <div class="profile-card">
                <h3 style="margin-bottom: 1.5rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 1rem; color: var(--accent);">
                    <i class="ph ph-lock"></i> Change Password
                </h3>
                <form action="" method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="old_password" class="form-control" placeholder="Required to confirm changes" required>
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" class="form-control" placeholder="Minimum 6 characters" required>
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="Repeat new password" required>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%; background: linear-gradient(135deg, var(--accent), #ff5500);">
                        Reset Password
                    </button>
                </form>
            </div>
        </div>
    </main>

</body>
</html>
