<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id           = trim($_POST['client_id'] ?? '');
    $contract_status     = trim($_POST['contract_status'] ?? 'active');
    $start_date          = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $end_date            = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $final_balance_notes = trim($_POST['final_balance_notes'] ?? '');

    if (empty($client_id)) {
        $_SESSION['error'] = 'Invalid client identification.';
        header('Location: ../views/clients.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE clients 
            SET contract_status = :contract_status, 
                contract_start_date = :start_date, 
                contract_end_date = :end_date,
                final_balance_notes = :balance_notes 
            WHERE id = :id
        ");

        $stmt->execute([
            'contract_status'     => $contract_status,
            'start_date'          => $start_date,
            'end_date'            => $end_date,
            'balance_notes'       => $final_balance_notes,
            'id'                  => $client_id
        ]);

        $_SESSION['success'] = 'Contract agreement and cancellation terms updated successfully.';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    }

    header('Location: ../views/clients.php');
    exit;
} else {
    header('Location: ../views/clients.php');
    exit;
}