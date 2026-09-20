<?php
// middleware/auth.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error'] = "Please log in to access this page.";
        header('Location: ../auth/login.php');
        exit;
    }

    // Periodically verify if user account is still active in DB
    require_once __DIR__ . '/../config/db.php';
    global $pdo;

    $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $status = $stmt->fetchColumn();

    if ($status !== 'active') {
        // Destroy session and kick user out immediately
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['error'] = "Your account has been disabled. Please contact an administrator.";
        header('Location: ../auth/login.php');
        exit;
    }
}

function requireRole(array $allowedRoles) {
    requireAuth();

    if (!in_array($_SESSION['role'] ?? '', $allowedRoles)) {
        $_SESSION['error'] = "Unauthorized access.";
        header('Location: ../views/dashboard.php');
        exit;
    }
}