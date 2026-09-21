<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id      = (int)($_POST['client_id'] ?? 0);
    $company_name   = trim($_POST['company_name'] ?? '');
    $first_name     = trim($_POST['first_name'] ?? '');
    $last_name      = trim($_POST['last_name'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $phone          = trim($_POST['phone'] ?? '');
    $street_address = trim($_POST['street_address'] ?? '');
    $barangay       = trim($_POST['barangay'] ?? '');
    $city           = trim($_POST['city'] ?? 'Davao City');

    if ($client_id <= 0 || empty($company_name) || empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($street_address) || empty($barangay)) {
        $_SESSION['error'] = 'Invalid form data or missing required address fields.';
        header('Location: ../public/clients.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE clients 
            SET client_name = ?, first_name = ?, last_name = ?, email = ?, phone_number = ?, street_address = ?, barangay = ?, city = ?
            WHERE id = ?
        ");
        $stmt->execute([$company_name, $first_name, $last_name, $email, $phone, $street_address, $barangay, $city, $client_id]);

        $_SESSION['success'] = "Client details successfully updated!";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Failed to update client: " . $e->getMessage();
    }

    header('Location: ../views/clients.php');
    exit;
}