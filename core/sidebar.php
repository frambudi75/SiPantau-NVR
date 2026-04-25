<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Sidebar -->
<nav class="sidebar">
    <div class="logo">
        <i class="ph-fill ph-eye"></i>
        <span>SI<span>PANTAU</span></span>
    </div>
    <ul class="nav-links">
        <li class="nav-item">
            <a href="index.php" class="nav-link <?= $current_page == 'index.php' ? 'active' : '' ?>">
                <i class="ph ph-squares-four"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a href="cameras.php" class="nav-link <?= $current_page == 'cameras.php' ? 'active' : '' ?>">
                <i class="ph ph-video-camera"></i> Cameras
            </a>
        </li>
        <li class="nav-item">
            <a href="playback.php" class="nav-link <?= $current_page == 'playback.php' ? 'active' : '' ?>">
                <i class="ph ph-clock-counter-clockwise"></i> Playback
            </a>
        </li>
        <li class="nav-item">
            <a href="settings.php" class="nav-link <?= $current_page == 'settings.php' ? 'active' : '' ?>">
                <i class="ph ph-gear"></i> Settings
            </a>
        </li>
        <li class="nav-item">
            <a href="storage.php" class="nav-link <?= $current_page == 'storage.php' ? 'active' : '' ?>">
                <i class="ph ph-hard-drives"></i> Storage
            </a>
        </li>
        <li class="nav-item">
            <a href="profile.php" class="nav-link <?= $current_page == 'profile.php' ? 'active' : '' ?>">
                <i class="ph ph-user-circle"></i> Profile
            </a>
        </li>
        <li class="nav-item">
            <a href="logs.php" class="nav-link <?= $current_page == 'logs.php' ? 'active' : '' ?>">
                <i class="ph ph-article"></i> System Logs
            </a>
        </li>
    </ul>

    <!-- Bottom Actions -->
    <ul class="nav-links" style="position: absolute; bottom: 2rem; width: calc(100% - 3rem);">
        <li class="nav-item">
            <a href="logout.php" class="nav-link" style="color: var(--danger);">
                <i class="ph ph-sign-out"></i> Logout
            </a>
        </li>
    </ul>
</nav>
