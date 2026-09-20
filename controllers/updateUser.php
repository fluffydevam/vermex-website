<?php
require_once __DIR__ . '/../middleware/auth.php';
requireRole(['Admin']);
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId       = (int)($_POST['user_id'] ?? 0);
    $fullName     = trim($_POST['full_name'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $role         = trim($_POST['role'] ?? '');
    $sectorRegion = trim($_POST['sector_region'] ?? 'Davao Head Office');

    if ($userId <= 0 || empty($fullName) || empty($email) || empty($role)) {
        $_SESSION['error'] = "Please fill in all required fields.";
        header('Location: ../views/users.php');
        exit;
    }

    try {
        // Check for duplicate email (excluding current user)
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $checkStmt->execute([$email, $userId]);
        if ($checkStmt->fetch()) {
            $_SESSION['error'] = "Another account is already using this email address.";
            header('Location: ../views/users.php');
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE users 
            SET full_name = ?, email = ?, phone = ?, role = ?, sector_region = ?
            WHERE id = ?
        ");
        $stmt->execute([$fullName, $email, $phone, $role, $sectorRegion, $userId]);

        $_SESSION['success'] = "User account updated successfully!";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
    }
}

header('Location: ../views/users.php');
exit;