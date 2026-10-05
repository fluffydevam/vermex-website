<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$jobOrderId = intval($_GET['job_order_id'] ?? 0);

if ($jobOrderId <= 0) {
    echo json_encode(['success' => false, 'materials' => []]);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT inventory_id, quantity_used FROM job_order_materials WHERE job_order_id = ?");
    $stmt->execute([$jobOrderId]);
    $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'materials' => $materials]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'materials' => []]);
}