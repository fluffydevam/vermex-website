<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name   = trim($_POST['company_name'] ?? '');
    $first_name     = trim($_POST['first_name'] ?? '');
    $last_name      = trim($_POST['last_name'] ?? '');
    $client_type    = trim($_POST['client_type'] ?? 'Commercial');
    $email          = trim($_POST['email'] ?? '');
    $phone          = trim($_POST['phone'] ?? '');
    $street_address = trim($_POST['street_address'] ?? '');
    $barangay       = trim($_POST['barangay'] ?? '');
    $city           = trim($_POST['city'] ?? 'Davao City');

    // Validation
    if (empty($company_name) || empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($street_address) || empty($barangay)) {
        $_SESSION['error'] = 'Please fill in all required fields to register the client account.';
        header('Location: ../public/clients.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare("
    INSERT INTO clients (client_name, first_name, last_name, client_type, email, phone_number, street_address, barangay, city, status, contract_status, created_at) 
    VALUES (:client_name, :first_name, :last_name, :client_type, :email, :phone, :street_address, :barangay, :city, 'active', 'to_be_contracted', NOW())
");
        
        $stmt->execute([
            'client_name'    => $company_name,
            'first_name'     => $first_name,
            'last_name'      => $last_name,
            'client_type'    => $client_type,
            'email'          => $email,
            'phone'          => $phone,
            'street_address' => $street_address,
            'barangay'       => $barangay,
            'city'           => $city
        ]);

        $_SESSION['success'] = "Client account '{$company_name}' successfully registered!";
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    }

    header('Location: ../views/clients.php');
    exit;
}