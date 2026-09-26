<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$clientId = $_GET['client_id'] ?? 0;
if (!$clientId) {
    echo json_encode(['success' => false, 'contracts' => []]);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM contracts WHERE client_id = ? ORDER BY created_at DESC");
$stmt->execute([$clientId]);
$contracts = $stmt->fetchAll();

echo json_encode(['success' => true, 'contracts' => $contracts]);