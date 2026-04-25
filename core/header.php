<?php
/**
 * Shared Header Component
 * Includes common HTML head, CSS, JS libraries, and sidebar.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? 'SiPantau NVR') ?> - SiPantau NVR</title>
    <link rel="stylesheet" href="assets/css/index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body>

<?php require_once __DIR__ . '/sidebar.php'; ?>
