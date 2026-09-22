<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "vermex_pest_solutions";

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");

    // 1. Users Table (Updated with separate first_name and last_name)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(150) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            phone VARCHAR(30) NULL,
            role ENUM('Admin', 'Billing Officer', 'Field Technician', 'Chemical Custodian') NOT NULL,
            sector_region VARCHAR(100) DEFAULT 'Davao Head Office',
            status ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_active TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 2. Clients Table (Updated with standardized address fields)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS clients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_name VARCHAR(150) NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            phone_number VARCHAR(20) NOT NULL,
            email VARCHAR(100) NULL,
            street_address VARCHAR(255) NOT NULL,
            barangay VARCHAR(100) NOT NULL,
            city VARCHAR(100) NOT NULL DEFAULT 'Davao City',
            client_type ENUM('Residential', 'Commercial') NOT NULL DEFAULT 'Residential',
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            contract_status ENUM('active', 'expiring_soon', 'cancelled', 'expired') NOT NULL DEFAULT 'active',
            contract_start_date DATE NULL,
            contract_end_date DATE NULL,
            final_balance_notes TEXT NULL,
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

    // Seed default Admin user if none exists (using first_name and last_name)
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    if ($stmt->fetchColumn() == 0) {
        $defaultPassword = password_hash('Admin123!', PASSWORD_BCRYPT);
        $insertAdmin = $pdo->prepare("
            INSERT INTO users (username, email, password, first_name, last_name, phone, role, sector_region, status)
            VALUES (:username, :email, :password, :first_name, :last_name, :phone, :role, :sector_region, 'active')
        ");
        $insertAdmin->execute([
            'username'      => 'admin',
            'email'         => 'admin@vermexpest.com',
            'password'      => $defaultPassword,
            'first_name'    => 'Paolo M.',
            'last_name'     => 'Cremat',
            'phone'         => '+63 900 000 0000',
            'role'          => 'Admin',
            'sector_region' => 'Davao Head Office'
        ]);
    }

    // 5. Site Inspections Table (Combined with Findings JSON)
$pdo->exec("
CREATE TABLE IF NOT EXISTS site_inspections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_name VARCHAR(255) NOT NULL,
    client_email VARCHAR(255),
    account_type VARCHAR(100),
    service_address TEXT,
    inspection_status VARCHAR(100) DEFAULT 'Pending Visit',
    technician_name VARCHAR(255),
    inspection_date DATE,
    time_in TIME,
    time_out TIME,
    ilt_qty INT DEFAULT 0,
    ilt_remarks VARCHAR(255),
    rat_cage_qty INT DEFAULT 0,
    rat_cage_remarks VARCHAR(255),
    rat_bait_qty INT DEFAULT 0,
    rat_bait_remarks VARCHAR(255),
    glue_trap_qty INT DEFAULT 0,
    glue_trap_remarks VARCHAR(255),
    vermex_representative VARCHAR(255),
    client_representative VARCHAR(255),
    conditions_json TEXT,
    findings_json TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

} catch (PDOException $e) {
    die("Database Initialization Failed: " . $e->getMessage());
}
