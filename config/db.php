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

    // 2. Clients Table
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
            status ENUM('active', 'inactive', 'archived') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 3. Contracts Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS contracts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            contract_name VARCHAR(150) NOT NULL,
            contract_status ENUM('active', 'to_be_contracted', 'expiring_soon', 'cancelled', 'expired') DEFAULT 'to_be_contracted',
            contract_start_date DATE NULL,
            contract_end_date DATE NULL,
            contract_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            final_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            final_balance_notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 4. Site Inspections Table (Linked with contract_id)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS site_inspections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            contract_id INT NULL,
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
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 5. Job Orders / Dispatch Table (Fixed foreign keys to reference `id`)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS job_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            contract_id INT NULL,
            client_id INT NULL,
            client_name VARCHAR(150) NOT NULL,
            location VARCHAR(255) NOT NULL,
            service_type VARCHAR(100) NOT NULL,
            assigned_tech VARCHAR(100) NOT NULL,
            service_window VARCHAR(50) NOT NULL,
            priority ENUM('Standard', 'High', 'Urgent') DEFAULT 'Standard',
            route_status ENUM('Scheduled', 'En route', 'On site', 'Completed', 'Delayed') DEFAULT 'Scheduled',
            time_in VARCHAR(50) NULL,
            time_out VARCHAR(50) NULL,
            comments TEXT NULL,
            area_findings TEXT NULL,
            payment_cleared TINYINT(1) DEFAULT 0,
            scheduled_date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE SET NULL,
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 6. Payments Audit & Transaction Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            contract_id INT NULL,
            client_id INT NOT NULL,
            user_id INT NULL,
            amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            payment_type ENUM('Downpayment', 'Balance Settlement', 'Full Payment', 'Adjustment') NOT NULL,
            payment_method ENUM('Cash', 'Bank Transfer', 'Check', 'GCash', 'Credit Card') NOT NULL,
            reference_number VARCHAR(100) NULL,
            remarks TEXT NULL,
            payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 7. Inventory Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS inventory (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_name VARCHAR(255) NOT NULL,
            category ENUM('Chemical', 'Device/Trap', 'Equipment', 'PPE') NOT NULL DEFAULT 'Chemical',
            quantity_in_stock DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            unit VARCHAR(50) NOT NULL DEFAULT 'mL',
            min_threshold DECIMAL(10,2) NOT NULL DEFAULT 10.00,
            unit_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            batch_number VARCHAR(100) NULL,
            storage_location VARCHAR(100) NULL,
            status ENUM('In Stock', 'Low Stock', 'Out of Stock') NOT NULL DEFAULT 'In Stock',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
            updated_by VARCHAR(100) NULL DEFAULT 'System / Admin'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Inventory Logs Table
    $pdo->exec(" 
        CREATE TABLE IF NOT EXISTS inventory_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            material_id INT NOT NULL,
            job_order_id INT DEFAULT NULL,
            action_type VARCHAR(50) DEFAULT 'Job Order Deduction',
            quantity_changed DECIMAL(10,2) NOT NULL,
            remarks TEXT,
            performed_by VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 8. Job Order Materials Junction Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS job_order_materials (
            id INT AUTO_INCREMENT PRIMARY KEY,
            job_order_id INT NOT NULL,
            inventory_id INT NOT NULL,
            quantity_used DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            unit_used VARCHAR(50) NOT NULL,
            logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (job_order_id) REFERENCES job_orders(id) ON DELETE CASCADE,
            FOREIGN KEY (inventory_id) REFERENCES inventory(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Seed default Admin user if none exists
    $stmt =$pdo->query("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    if ($stmt->fetchColumn() == 0) {$defaultPassword = password_hash('Admin123!', PASSWORD_BCRYPT);
        $insertAdmin =$pdo->prepare("
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
} catch (PDOException $e) {
    die("Database Initialization Failed: " . $e->getMessage());
}