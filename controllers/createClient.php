<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $companyName = trim($_POST['company_name'] ?? '');
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $clientType = trim($_POST['client_type'] ?? 'Commercial');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $street = trim($_POST['street_address'] ?? '');
        $barangay = trim($_POST['barangay'] ?? '');
        $city = trim($_POST['city'] ?? 'Davao City');

        // Insert ONLY into the clients table
        $stmt = $pdo->prepare("
            INSERT INTO clients (client_name, first_name, last_name, client_type, email, phone_number, street_address, barangay, city, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ");
        $stmt->execute([$companyName, $firstName, $lastName, $clientType, $email, $phone, $street, $barangay, $city]);

        $_SESSION['success'] = "Client account successfully registered!";
        header("Location: ../views/clients.php?tab=clients");
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to register client: " . $e->getMessage();
        header("Location: ../views/clients.php?tab=clients");
        exit;
    }
}