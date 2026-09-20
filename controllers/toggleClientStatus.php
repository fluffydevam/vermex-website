<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id      = trim($_POST['client_id'] ?? '');
    $current_status = trim($_POST['current_status'] ?? 'active');
    $new_status     = ($current_status === 'active') ? 'inactive' : 'active';

    if (empty($client_id)) {
        $_SESSION['error'] = 'Invalid client account ID.';
        header('Location: ../views/clients.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE clients SET status = :status WHERE id = :id");
        $stmt->execute([
            'status' => $new_status,
            'id'     => $client_id
        ]);

        $_SESSION['success'] = "Client account status successfully changed to {$new_status}.";
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    }

    header('Location: ../views/clients.php');
    exit;
}