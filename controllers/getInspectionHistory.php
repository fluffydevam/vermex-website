<?php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

$clientName = trim($_GET['client_name'] ?? '');
$clientEmail = trim($_GET['client_email'] ?? '');

if (empty($clientName) && empty($clientEmail)) {
    echo json_encode(['success' => false, 'message' => 'Client identifier missing.']);
    exit;
}

try {
    // Fetch inspections linked by client name or email
    $stmt = $pdo->prepare("
        SELECT * FROM site_inspections 
        WHERE client_name = ? OR client_email = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$clientName, $clientEmail]);
    $inspections = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $inspections]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}