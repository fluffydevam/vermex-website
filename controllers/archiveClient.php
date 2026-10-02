<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Check if client ID is provided via GET or POST
$client_id = $_GET['id'] ?? $_POST['client_id'] ?? null;

// Default redirect fallback to main dashboard
$redirect_url = "../views/clients.php";

if (!$client_id) {
    $_SESSION['error'] = "Invalid client identification.";
    header("Location: " . $redirect_url);
    exit;
}

try {
    // 1. Check contract count and total unpaid remaining balance for this client
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(id) AS total_contracts,
            COALESCE(SUM(final_balance), 0) AS total_remaining_balance
        FROM contracts 
        WHERE client_id = ?
    ");
    $stmt->execute([$client_id]);
    $statusInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    $totalContracts = intval($statusInfo['total_contracts']);
    $totalRemaining = floatval($statusInfo['total_remaining_balance']);

    if ($totalContracts === 0) {
        // Case 1: No contracts exist -> Completely delete the client record
        $deleteStmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
        $deleteStmt->execute([$client_id]);

        $_SESSION['success'] = "Client deleted successfully as there were no associated contracts.";
        $redirect_url = "../views/clients.php";
    } else {
        // Case 2: Contracts exist -> Verify final balance
        if ($totalRemaining > 0) {
            $_SESSION['error'] = "Cannot archive or delete client with an unpaid balance (₱" . number_format($totalRemaining, 2) . ").";
            $redirect_url = "../views/clients.php";
        } else {
            // Case 3: Has contracts, but all paid off -> Set client status to archived
            $archiveStmt = $pdo->prepare("UPDATE clients SET status = 'archived' WHERE id = ?");
            $archiveStmt->execute([$client_id]);

            $_SESSION['success'] = "Client successfully archived.";
            
            // Redirect to the archived clients view page
            $redirect_url = "../views/archived-clients.php";
        }
    }

} catch (Exception $e) {
    $_SESSION['error'] = "An error occurred: " . $e->getMessage();
    $redirect_url = "../views/clients.php";
}

// Redirect to the determined target URL
header("Location: " . $redirect_url);
exit;
?>