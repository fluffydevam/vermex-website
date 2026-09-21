<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Access Control Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    $_SESSION['error'] = "Unauthorized access.";
    header('Location: ../views/users.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Sanitize & Collect Input Data
    $username     = trim($_POST['username'] ?? '');
    $firstName    = trim($_POST['first_name'] ?? '');
    $lastName     = trim($_POST['last_name'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $role         = trim($_POST['role'] ?? 'Field Technician');
    $sectorRegion = trim($_POST['sector_region'] ?? 'Davao Head Office');
    $rawPassword  = trim($_POST['password'] ?? '');

    // Auto-generate username from email if left empty
    if (empty($username) && !empty($email)) {
        $username = explode('@', $email)[0];
    }

    // 2. Validate Required Fields
    if (empty($username) || empty($firstName) || empty($lastName) || empty($email) || empty($rawPassword)) {
        $_SESSION['error'] = "Please fill in all required fields (Username, First Name, Last Name, Email, Password).";
        header('Location: ../views/users.php');
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format.";
        header('Location: ../views/users.php');
        exit;
    }

    try {
        // 3. Check for existing username or email address
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $username]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = "An account with this email address or username already exists.";
            header('Location: ../views/users.php');
            exit;
        }

        // 4. Securely Hash Password
        $passwordHash = password_hash($rawPassword, PASSWORD_BCRYPT);

        // 5. Insert New User into Database with separate first_name and last_name
        $insertStmt = $pdo->prepare("
            INSERT INTO users (username, first_name, last_name, email, phone, role, sector_region, password, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        
        $insertStmt->execute([
            $username,
            $firstName,
            $lastName,
            $email,
            $phone,
            $role,
            $sectorRegion,
            $passwordHash
        ]);

        $_SESSION['success'] = "User account for {$firstName} {$lastName} (@{$username}) successfully created!";
        header('Location: ../views/users.php');
        exit;

    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        header('Location: ../views/users.php');
        exit;
    }
} else {
    header('Location: ../views/users.php');
    exit;
}