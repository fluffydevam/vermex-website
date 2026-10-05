<?php
session_start();
require_once '../config/db.php'; // Adjust path to your database connection file as needed

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inspectionId = $_POST['inspection_id'] ?? null;
    $timeIn = $_POST['time_in'] ?? null;
    $timeOut = $_POST['time_out'] ?? null;
    
    // Devices tracking
    $iltQty = $_POST['ilt_qty'] ?? 0;
    $iltRemarks = $_POST['ilt_remarks'] ?? '';
    $ratCageQty = $_POST['rat_cage_qty'] ?? 0;
    $ratCageRemarks = $_POST['rat_cage_remarks'] ?? '';
    $ratBaitQty = $_POST['rat_bait_qty'] ?? 0;
    $ratBaitRemarks = $_POST['rat_bait_remarks'] ?? '';
    $glueTrapQty = $_POST['glue_trap_qty'] ?? 0;
    $glueTrapRemarks = $_POST['glue_trap_remarks'] ?? '';

    // Process Contributing Conditions into JSON format
    $conditionsData = [];
    if (!empty($_POST['condition_name'])) {
        foreach ($_POST['condition_name'] as $i => $name) {
            $conditionsData[] = [
                'condition' => $name,
                'status' => $_POST['condition_status'][$i] ?? 'No',
                'area' => $_POST['condition_area'][$i] ?? ''
            ];
        }
    }
    $conditionsJson = json_encode($conditionsData);

    // Process Area Findings & Actions Taken into JSON format
    $findingsData = [];
    if (!empty($_POST['finding_area'])) {
        foreach ($_POST['finding_area'] as $i => $area) {
            if (!empty($area) || !empty($_POST['finding_pest'][$i])) {
                $findingsData[] = [
                    'area' => $area,
                    'findings' => $_POST['finding_pest'][$i] ?? '',
                    'action_taken' => $_POST['finding_action'][$i] ?? ''
                ];
            }
        }
    }
    $findingsJson = json_encode($findingsData);

    try {
        $pdo->beginTransaction();

        // Update site inspections record
        $stmt = $pdo->prepare("
            UPDATE site_inspections 
            SET time_in = ?, time_out = ?, 
                ilt_qty = ?, ilt_remarks = ?, 
                rat_cage_qty = ?, rat_cage_remarks = ?, 
                rat_bait_qty = ?, rat_bait_remarks = ?, 
                glue_trap_qty = ?, glue_trap_remarks = ?, 
                conditions_json = ?, findings_json = ?, 
                inspection_status = 'Completed' 
            WHERE id = ?
        ");
        $stmt->execute([
            $timeIn, $timeOut, 
            $iltQty, $iltRemarks, 
            $ratCageQty, $ratCageRemarks, 
            $ratBaitQty, $ratBaitRemarks, 
            $glueTrapQty, $glueTrapRemarks, 
            $conditionsJson, $findingsJson, 
            $inspectionId
        ]);

        // Handle automatic inventory deduction for materials used
        if (!empty($_POST['inventory_id'])) {
            foreach ($_POST['inventory_id'] as $index => $inventoryId) {
                $qtyUsed = $_POST['quantity_used'][$index] ?? 0;

                if (!empty($inventoryId) && $qtyUsed > 0) {
                    // Deduct stock from inventory table
                    $invStmt = $pdo->prepare("UPDATE inventory SET stock_quantity = stock_quantity - ? WHERE id = ?");
                    $invStmt->execute([$qtyUsed, $inventoryId]);

                    // Log action to inventory audit trail
                    $logStmt = $pdo->prepare("INSERT INTO inventory_logs (inventory_id, action_type, quantity_changed, reference_id, remarks) VALUES (?, 'Site Inspection Deduction', ?, ?, ?)");
                    $logStmt->execute([$inventoryId, -$qtyUsed, $inspectionId, "Deducted via Site Inspection Report #$inspectionId"]);
                }
            }
        }

        $pdo->commit();
        header("Location: ../views/field-technician/dashboard.php?success=inspection_saved");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error processing site inspection update: " . $e->getMessage());
    }
} else {
    header("Location: ../views/field-technician/dashboard.php");
    exit();
}
?>