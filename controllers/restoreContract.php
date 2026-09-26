<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (isset($_GET['id'])) {
    try {
        $contractId = $_GET['id'];
        $stmt = $pdo->prepare("UPDATE contracts SET contract_status = 'to_be_contracted' WHERE id = ?");
        $stmt->execute([$contractId]);

        $_SESSION['success'] = "Contract successfully restored!";
        header("Location: ../views/clients.php?tab=contracts&status=archived");
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to restore contract: " . $e->getMessage();
        header("Location: ../views/clients.php?tab=contracts&status=archived");
        exit;
    }
}