<?php
require_once 'core/config.php';
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        header("Location: index.php");
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - VISION PRO NVR</title>
    <link rel="stylesheet" href="assets/css/index.css">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }
        .login-card {
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            padding: 3rem;
            border-radius: 30px;
            width: 400px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo" style="justify-content: center; margin-bottom: 2rem;">
            <i class="ph-fill ph-eye"></i>
            <span>SI<span>PANTAU</span></span>
        </div>
        
        <h2 style="margin-bottom: 0.5rem;">Welcome to SiPantau</h2>
        <p style="color: var(--text-muted); margin-bottom: 2rem; font-size: 0.9rem;">Please login to access NVR Server.</p>

        <?php if ($error): ?>
            <div style="background: rgba(239, 68, 68, 0.1); color: var(--danger); padding: 10px; border-radius: 10px; margin-bottom: 1rem; font-size: 0.85rem;">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-group" style="text-align: left;">
                <label>Username</label>
                <input type="text" name="username" class="form-control" placeholder="Enter username" required>
            </div>
            <div class="form-group" style="text-align: left;">
                <label>Password</label>
                <input type="password" name="password" class="form-control" placeholder="Enter password" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem; padding: 1rem;">
                Sign In
            </button>
        </form>
    </div>
</body>
</html>
