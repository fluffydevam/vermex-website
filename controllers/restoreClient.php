<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$client_id = $_GET['id'] ?? null;

if (!$client_id) {
    $_SESSION['error'] = "Invalid client record selected.";
    header("Location: ../views/archived-clients.php");
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE clients SET status = 'active' WHERE id = ?");
    $stmt->execute([$client_id]);

    $_SESSION['success'] = "Client successfully restored to active status.";
} catch (Exception $e) {
    $_SESSION['error'] = "Failed to restore client: " . $e->getMessage();
}

header("Location: ../views/archived-clients.php");
exit;
?>