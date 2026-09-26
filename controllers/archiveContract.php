<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (isset($_GET['id'])) {
    try {
        $contractId = $_GET['id'];

        $stmt = $pdo->prepare("UPDATE contracts SET contract_status = 'archived' WHERE id = ?");
        $stmt->execute([$contractId]);

        $_SESSION['success'] = "Contract successfully archived!";
        header("Location: ../views/clients.php?tab=contracts");
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to archive contract: " . $e->getMessage();
        header("Location: ../views/clients.php?tab=contracts");
        exit;
    }
} else {
    $_SESSION['error'] = "Invalid contract identification.";
    header("Location: ../views/clients.php?tab=contracts");
    exit;
}