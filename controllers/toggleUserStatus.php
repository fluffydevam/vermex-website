<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth.php';
requireRole(['Admin']); // Only Admins can toggle account status

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $currentStatus = trim($_POST['current_status'] ?? '');

    if ($userId <= 0) {
        $_SESSION['error'] = "Invalid user specified.";
        header('Location: ../views/users.php');
        exit;
    }

    // Prevent Admin from disabling their own account
    if ($userId === (int)($_SESSION['user_id'] ?? 0)) {
        $_SESSION['error'] = "You cannot disable your own active Admin account.";
        header('Location: ../views/users.php');
        exit;
    }

    $newStatus = ($currentStatus === 'active') ? 'disabled' : 'active';

    try {
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $userId]);

        $_SESSION['success'] = "User status updated to " . ucfirst($newStatus) . ".";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Failed to update status: " . $e->getMessage();
    }
}

header('Location: ../views/users.php');
exit;