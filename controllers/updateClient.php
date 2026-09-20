<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id    = trim($_POST['client_id'] ?? '');
    $company_name = trim($_POST['company_name'] ?? '');
    $first_name   = trim($_POST['first_name'] ?? '');
    $last_name    = trim($_POST['last_name'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $address      = trim($_POST['address'] ?? '');

    if (empty($client_id) || empty($company_name) || empty($first_name) || empty($last_name) || empty($email)) {
        $_SESSION['error'] = 'Missing required fields for updating client details.';
        header('Location: ../views/clients.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE clients 
            SET client_name = :company_name, 
                first_name = :first_name, 
                last_name = :last_name, 
                email = :email, 
                phone_number = :phone, 
                property_address = :address 
            WHERE id = :id
        ");

        $stmt->execute([
            'company_name' => $company_name,
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'email'        => $email,
            'phone'        => $phone,
            'address'      => $address,
            'id'           => $client_id
        ]);

        $_SESSION['success'] = "Client details for '{$company_name}' updated successfully.";
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    }

    header('Location: ../views/clients.php');
    exit;
}