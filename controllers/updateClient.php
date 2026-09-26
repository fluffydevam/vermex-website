<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id      = (int)($_POST['client_id'] ?? 0);
    $company_name   = trim($_POST['company_name'] ?? '');
    $first_name     = trim($_POST['first_name'] ?? '');
    $last_name      = trim($_POST['last_name'] ?? '');
    $client_type    = trim($_POST['client_type'] ?? 'Commercial');
    $email          = trim($_POST['email'] ?? '');
    $phone          = trim($_POST['phone'] ?? '');
    $street_address = trim($_POST['street_address'] ?? '');
    $barangay       = trim($_POST['barangay'] ?? '');
    $city           = trim($_POST['city'] ?? 'Davao City');

    if ($client_id <= 0 || empty($company_name) || empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($street_address) || empty($barangay)) {
        $_SESSION['error'] = 'Invalid form data or missing required client details.';
        header('Location: ../views/clients.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE clients 
            SET client_name = :client_name, 
                first_name = :first_name, 
                last_name = :last_name, 
                client_type = :client_type, 
                email = :email, 
                phone_number = :phone, 
                street_address = :street_address, 
                barangay = :barangay, 
                city = :city 
            WHERE client_id = :client_id
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
            'city'           => $city,
            'client_id'      => $client_id
        ]);

        $_SESSION['success'] = "Client details for '{$company_name}' updated successfully.";
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    }

    header('Location: ../views/clients.php');
    exit;
} else {
    header('Location: ../views/clients.php');
    exit;
}
?>