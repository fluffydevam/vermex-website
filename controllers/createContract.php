<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $clientId = $_POST['client_id'] ?? null;
        $contractName = trim($_POST['contract_name'] ?? '');
        $contractValue = $_POST['contract_value'] ?? 0;
        $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        
        // Default status per your requirement
        $status = ($startDate && $endDate) ? 'active' : 'to_be_contracted';

        $stmt = $pdo->prepare("
            INSERT INTO contracts (client_id, contract_name, contract_status, contract_start_date, contract_end_date, contract_value, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$clientId, $contractName, $status, $startDate, $endDate, $contractValue]);

        $_SESSION['success'] = "New contract successfully added!";
        header("Location: ../views/clients.php?tab=contracts");
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to create contract: " . $e->getMessage();
        header("Location: ../views/clients.php?tab=contracts");
        exit;
    }
}