<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = trim($_POST['company_name'] ?? '');
    $first_name   = trim($_POST['first_name'] ?? '');
    $last_name    = trim($_POST['last_name'] ?? '');
    $client_type  = trim($_POST['client_type'] ?? 'Commercial');
    $email        = trim($_POST['email'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $address      = trim($_POST['address'] ?? '');

    // Validation
    if (empty($company_name) || empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($address)) {
        $_SESSION['error'] = 'Please fill in all required fields to register the client account.';
        header('Location: ../views/clients.php');
        exit;
    }

    try {
        // Mapping to your existing database column structure
        $stmt = $pdo->prepare("
            INSERT INTO clients (client_name, first_name, last_name, client_type, email, phone_number, property_address, status, contract_status, created_at) 
            VALUES (:company_name, :first_name, :last_name, :client_type, :email, :phone, :address, 'active', 'active', NOW())
        ");
        
        $stmt->execute([
            'company_name' => $company_name,
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'client_type'  => $client_type,
            'email'        => $email,
            'phone'        => $phone,
            'address'      => $address
        ]);

        $_SESSION['success'] = "Client account '{$company_name}' successfully registered!";
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    }

    header('Location: ../views/clients.php');
    exit;
} else {
    header('Location: ../views/clients.php');
    exit;
}