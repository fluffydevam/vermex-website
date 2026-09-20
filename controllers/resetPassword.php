<?php
require_once __DIR__ . '/../middleware/auth.php';
requireRole(['Admin']);
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId      = (int)($_POST['user_id'] ?? 0);
    $newPassword = trim($_POST['new_password'] ?? '');

    if ($userId <= 0 || empty($newPassword)) {
        $_SESSION['error'] = "Please provide a valid password.";
        header('Location: ../views/users.php');
        exit;
    }

    $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);

    try {
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$passwordHash, $userId]);

        $_SESSION['success'] = "Password successfully reset for user ID #{$userId}.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Failed to reset password: " . $e->getMessage();
    }
}

header('Location: ../views/users.php');
exit;