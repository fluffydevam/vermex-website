<?php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

try {
    // Query matching the exact column names in your users table
    $stmt = $pdo->prepare("
        SELECT id, CONCAT(first_name, ' ', last_name) AS technician_name 
        FROM users 
        WHERE role = 'Field Technician'
        ORDER BY first_name ASC
    ");
    $stmt->execute();
    $technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $technicians]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}