<?php
// 1. Include your database connection script
require_once '../config/db.php'; // Adjust path to your DB connection file

// 2. Check if 'id' parameter is provided in the URL
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $client_id = $_GET['id'];

    try {
        // 3. Prepare the DELETE statement to prevent SQL injection
        $stmt = $pdo->prepare("DELETE FROM clients WHERE id = :id");
        $stmt->bindParam(':id', $client_id, PDO::PARAM_INT);

        // 4. Execute the query
        if ($stmt->execute()) {
            // Success: Redirect back to the client list page
           header("Location: ../views/clients.php?status=deleted");
exit();
        } else {
            echo "Error: Could not delete the record.";
        }
    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
} else {
    // If no ID was passed in the URL
    header("Location: ../views/clients.php?status=deleted");
exit();
}