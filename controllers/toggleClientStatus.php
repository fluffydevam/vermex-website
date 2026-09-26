<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id      = (int)($_POST['client_id'] ?? 0);
    $current_status = trim($_POST['current_status'] ?? 'active');
    $new_status     = ($current_status === 'active') ? 'inactive' : 'active';

    if ($client_id <= 0) {
        $_SESSION['error'] = 'Invalid client account identification ID.';
        header('Location: ../views/clients.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE clients SET status = :status WHERE client_id = :client_id");
        $stmt->execute([
            'status'    => $new_status,
            'client_id' => $client_id
        ]);

        $_SESSION['success'] = "Client account status successfully updated to {$new_status}.";
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    }

    header('Location: ../views/clients.php');
    exit;
} else {
    header('Location: ../views/clients.php');
    exit;
}
?>