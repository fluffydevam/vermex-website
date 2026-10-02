<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientId = $_POST['client_id'] ?? null;
    $clientName = trim($_POST['client_name'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $clientType = trim($_POST['client_type'] ?? 'Commercial');
    $status = strtolower(trim($_POST['status'] ?? 'active')); // Standardize to lowercase
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone_number'] ?? '');
    $street = trim($_POST['street_address'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $city = trim($_POST['city'] ?? 'Davao City');

    if ($clientId && !empty($clientName)) {
        $stmt = $pdo->prepare("
            UPDATE clients 
            SET client_name = :client_name,
                first_name = :first_name,
                last_name = :last_name,
                client_type = :client_type,
                status = :status,
                email = :email,
                phone_number = :phone_number,
                street_address = :street_address,
                barangay = :barangay,
                city = :city
            WHERE id = :id
        ");
        
        $stmt->execute([
            'client_name' => $clientName,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'client_type' => $clientType,
            'status' => $status,
            'email' => $email,
            'phone_number' => $phone,
            'street_address' => $street,
            'barangay' => $barangay,
            'city' => $city,
            'id' => $clientId
        ]);

        if ($status === 'archived' || $status === 'inactive') {
            $_SESSION['success'] = "Client account has been archived.";
            header('Location: ../views/archived-clients.php');
            exit;
        }

        $_SESSION['success'] = "Client profile updated successfully.";
    } else {
        $_SESSION['error'] = "Failed to update client profile. Missing required fields.";
    }
}

header('Location: ../views/clients.php?tab=clients');
exit;