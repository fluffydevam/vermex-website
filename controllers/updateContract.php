<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $contractId = $_POST['contract_id'] ?? null;
        $contractName = trim($_POST['contract_name'] ?? '');
        $contractStatus = trim($_POST['contract_status'] ?? 'active');
        $contractValue = $_POST['contract_value'] ?? 0;
        $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

        if (!$contractId) {
            throw new Exception("Contract ID is missing.");
        }

        $stmt = $pdo->prepare("
            UPDATE contracts 
            SET contract_name = ?, 
                contract_status = ?, 
                contract_start_date = ?, 
                contract_end_date = ?, 
                contract_value = ? 
            WHERE id = ?
        ");
        $stmt->execute([$contractName, $contractStatus, $startDate, $endDate, $contractValue, $contractId]);

        $_SESSION['success'] = "Contract successfully updated!";
        header("Location: ../views/clients.php?tab=contracts");
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to update contract: " . $e->getMessage();
        header("Location: ../views/clients.php?tab=contracts");
        exit;
    }
}