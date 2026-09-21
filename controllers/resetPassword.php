<?php
// 1. Load database connection FIRST so $pdo is available
require_once __DIR__ . '/../config/db.php';

// 2. Then load authentication middleware and check role
require_once __DIR__ . '/../middleware/auth.php';
requireRole(['Admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId      = (int)($_POST['user_id'] ?? 0);
    $newPassword = trim($_POST['new_password'] ?? '');

    if ($userId <= 0 || empty($newPassword)) {
        $_SESSION['error'] = "Invalid user or password provided.";
        header('Location: ../views/users.php');
        exit;
    }

    try {
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $userId]);

        $_SESSION['success'] = "Password successfully reset for the user.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Failed to reset password: " . $e->getMessage();
    }
}

header('Location: ../views/users.php');
exit;
?>