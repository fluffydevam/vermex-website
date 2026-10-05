<?php
session_start();
require_once '../config/db.php'; // Adjust path to your database connection file as needed

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jobOrderId = $_POST['job_order_id'] ?? null;
    $serviceStatus = $_POST['service_status'] ?? 'Completed';
    $technicianNotes = $_POST['technician_notes'] ?? '';
    
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE job_orders 
            SET status = ?, technician_notes = ? 
            WHERE id = ?
        ");
        $stmt->execute([$serviceStatus, $technicianNotes, $jobOrderId]);

        // Handle materials used for standard job orders if applicable
        if (!empty($_POST['inventory_id'])) {
            foreach ($_POST['inventory_id'] as $index => $inventoryId) {
                $qtyUsed = $_POST['quantity_used'][$index] ?? 0;
                if (!empty($inventoryId) && $qtyUsed > 0) {
                    $invStmt = $pdo->prepare("UPDATE inventory SET stock_quantity = stock_quantity - ? WHERE id = ?");
                    $invStmt->execute([$qtyUsed, $inventoryId]);
                }
            }
        }

        $pdo->commit();
        header("Location: ../views/field-technician/dashboard.php?success=job_order_updated");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error processing job order update: " . $e->getMessage());
    }
} else {
    header("Location: ../views/field-technician/dashboard.php");
    exit();
}
?>