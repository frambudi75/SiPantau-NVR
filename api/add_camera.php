<?php
require_once '../core/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $rtsp_url = $_POST['rtsp_url'] ?? '';
    $location = $_POST['location'] ?? '';

    if (!empty($name) && !empty($rtsp_url)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO cameras (name, rtsp_url, location) VALUES (?, ?, ?)");
            $stmt->execute([$name, $rtsp_url, $location]);
            
            header("Location: ../index.php?success=1");
            exit;
        } catch (PDOException $e) {
            die("Error: " . $e->getMessage());
        }
    }
}

header("Location: ../index.php?error=empty_fields");
exit;
