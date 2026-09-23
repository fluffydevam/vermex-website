<?php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inspectionId = $_POST['inspection_id'] ?? '';
    $technicianName = trim($_POST['technician_name'] ?? '');

    if (empty($inspectionId) || empty($technicianName)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit;
    }

    try {
        // Updated table name from 'inspections' to 'site_inspections'
        $stmt = $pdo->prepare("UPDATE site_inspections SET technician_name = ? WHERE id = ?");
        $stmt->execute([$technicianName, $inspectionId]);

        echo json_encode(['success' => true, 'message' => 'Technician assigned successfully.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}