<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "vermex_pest_solutions";

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8", $user, $pass);$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");

    // 1. Users Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            role ENUM('Admin', 'Billing Officer', 'Field Technician', 'Chemical Custodian') NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 2. Clients Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS clients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_name VARCHAR(150) NOT NULL,
            contact_person VARCHAR(100) NOT NULL,
            phone_number VARCHAR(20) NOT NULL,
            email VARCHAR(100) NULL,
            property_address TEXT NOT NULL,
            client_type ENUM('Residential', 'Commercial') NOT NULL DEFAULT 'Residential',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 3. Contracts Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS contracts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            contract_type ENUM('1/2-Year Residential', 'Monthly Commercial') NOT NULL,
            contract_value DECIMAL(10,2) NOT NULL,
            downpayment_amount DECIMAL(10,2) NOT NULL,
            downpayment_status ENUM('Pending Verification', 'Verified / Cleared') NOT NULL DEFAULT 'Pending Verification',
            balance_status ENUM('Pending 30-Day Window', 'Cleared', 'Overdue') NOT NULL DEFAULT 'Pending 30-Day Window',
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            contract_status ENUM('Active', 'Pending Clearance', 'Expired', 'Cancelled') NOT NULL DEFAULT 'Pending Clearance',
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 4. Job Orders / Dispatch Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS job_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_name VARCHAR(150) NOT NULL,
            location VARCHAR(255) NOT NULL,
            service_type VARCHAR(100) NOT NULL,
            assigned_tech VARCHAR(100) NOT NULL,
            service_window VARCHAR(50) NOT NULL,
            priority ENUM('Standard', 'High', 'Urgent') DEFAULT 'Standard',
            route_status ENUM('Scheduled', 'En route', 'On site', 'Completed', 'Delayed') DEFAULT 'Scheduled',
            payment_cleared TINYINT(1) DEFAULT 0,
            scheduled_date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Seed default Admin user if none exists
    $stmt =$pdo->query("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    if ($stmt->fetchColumn() == 0) {$defaultPassword = password_hash('Admin123!', PASSWORD_BCRYPT);
        $insertAdmin =$pdo->prepare("
            INSERT INTO users (username, password, full_name, role) 
            VALUES (:username, :password, :full_name, :role)
        ");
        $insertAdmin->execute([
            'username' => 'admin',
            'password' => $defaultPassword,
            'full_name' => 'Paolo M. Cremat',
            'role' => 'Admin'
        ]);
    }

} catch (PDOException $e) {
    die("Database Initialization Failed: " . $e->getMessage());
}
?>