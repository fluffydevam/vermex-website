<?php
session_start();

// 1. Load database connection first
require_once __DIR__ . '/../../config/db.php';

// 2. Load auth middleware
require_once __DIR__ . '/../../middleware/auth.php';

// 3. Restrict access strictly to Chemical Custodians and Admins
requireRole(['Chemical Custodian', 'Admin']);

$currentPage = 'dashboard.php';
$successMessage = '';
$errorMessage = '';

// Get current logged-in user's name robustly based on your users table structure (first_name + last_name)
$currentUserName = 'System / Admin';

if (!empty($_SESSION['user_name'])) {
    $currentUserName = $_SESSION['user_name'];
} elseif (!empty($_SESSION['first_name']) && !empty($_SESSION['last_name'])) {
    $currentUserName = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
} elseif (!empty($_SESSION['username'])) {
    $currentUserName = $_SESSION['username'];
} elseif (!empty($_SESSION['user']['first_name'])) {
    $currentUserName = $_SESSION['user']['first_name'] . ' ' . $_SESSION['user']['last_name'];
}

// -----------------------------------------------------------------------------
// 1. HANDLE FORM SUBMISSION: ADD NEW STOCK ITEM
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_item') {
    $item_name        = trim($_POST['item_name'] ?? '');
    $category         = trim($_POST['category'] ?? 'Chemical');
    $quantity_in_stock = floatval($_POST['quantity_in_stock'] ?? 0);
    $unit             = trim($_POST['unit'] ?? 'Pcs');
    $min_threshold    = floatval($_POST['min_threshold'] ?? 10);
    $unit_cost        = floatval($_POST['unit_cost'] ?? 0);
    $batch_number     = trim($_POST['batch_number'] ?? '');
    $storage_location = trim($_POST['storage_location'] ?? '');

    // Determine initial status based on threshold
    if ($quantity_in_stock <= 0) {
        $status = 'Out of Stock';
    } elseif ($quantity_in_stock <= $min_threshold) {
        $status = 'Low Stock';
    } else {
        $status = 'In Stock';
    }

    if (!empty($item_name)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO inventory 
                (item_name, category, quantity_in_stock, unit, min_threshold, unit_cost, batch_number, storage_location, status, updated_by) 
                VALUES 
                (:item_name, :category, :quantity_in_stock, :unit, :min_threshold, :unit_cost, :batch_number, :storage_location, :status, :updated_by)
            ");
            
            $stmt->execute([
                ':item_name'         => $item_name,
                ':category'          => $category,
                ':quantity_in_stock' => $quantity_in_stock,
                ':unit'              => $unit,
                ':min_threshold'     => $min_threshold,
                ':unit_cost'         => $unit_cost,
                ':batch_number'      => $batch_number,
                ':storage_location'  => $storage_location,
                ':status'            => $status,
                ':updated_by'        => $currentUserName
            ]);

            $_SESSION['success_msg'] = "New item '$item_name' added successfully!";
            header("Location: dashboard.php");
            exit;
        } catch (PDOException $e) {
            $errorMessage = "Database Error: " . $e->getMessage();
        }
    } else {
        $errorMessage = "Item Name is required.";
    }
}

// -----------------------------------------------------------------------------
// 2. FETCH DASHBOARD METRICS
// -----------------------------------------------------------------------------
try {
    $stmtTotalSKUs = $pdo->query("SELECT COUNT(*) FROM inventory");
    $totalSKUs = $stmtTotalSKUs->fetchColumn() ?: 0;

    $stmtLowStock = $pdo->query("SELECT COUNT(*) FROM inventory WHERE quantity_in_stock <= min_threshold OR status = 'Low Stock'");
    $lowStockCount = $stmtLowStock->fetchColumn() ?: 0;

    $stmtChemVol = $pdo->query("SELECT SUM(quantity_in_stock) FROM inventory WHERE category = 'Chemical'");
    $totalChemicalVolume = $stmtChemVol->fetchColumn() ?: 0;

    // Fetch recent warehouse activity (sorted by latest update/creation)
    $stmtActivity = $pdo->query("SELECT * FROM inventory ORDER BY updated_at DESC, id DESC LIMIT 10");
    $recentActivity = $stmtActivity->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $totalSKUs = 0;
    $lowStockCount = 0;
    $totalChemicalVolume = 0;
    $recentActivity = [];
}

if (isset($_SESSION['success_msg'])) {
    $successMessage = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chemical Custodian Dashboard - Vermex</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-[#f8faf9] text-slate-800 min-h-screen flex font-sans antialiased overflow-hidden">

    <!-- Include Custodian Sidebar -->
    <?php include 'components/sidebar.php'; ?>

    <!-- MAIN CONTENT AREA -->
    <main class="flex-1 flex flex-col h-screen overflow-y-auto">
        
        <!-- Top Bar -->
        <header class="bg-white border-b border-slate-200 px-8 py-3 flex items-center justify-between text-xs text-slate-500">
            <div>Custodian Workspace / <span class="font-semibold text-slate-800">Warehouse Overview</span></div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                    Active Role: Chemical Custodian (<?= htmlspecialchars($currentUserName) ?>)
                </span>
            </div>
        </header>

        <!-- Main Content Container -->
        <div class="p-8 space-y-6 max-w-7xl w-full mx-auto">
            
            <!-- Notifications -->
            <?php if (!empty($successMessage)): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-xs flex items-center justify-between shadow-sm">
                    <span><?= htmlspecialchars($successMessage) ?></span>
                    <button onclick="this.parentElement.remove()" class="text-emerald-600 font-bold">&times;</button>
                </div>
            <?php endif; ?>

            <?php if (!empty($errorMessage)): ?>
                <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-xl text-xs flex items-center justify-between shadow-sm">
                    <span><?= htmlspecialchars($errorMessage) ?></span>
                    <button onclick="this.parentElement.remove()" class="text-rose-600 font-bold">&times;</button>
                </div>
            <?php endif; ?>

            <!-- Header Section -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Chemical Custodian Dashboard</h1>
                    <p class="text-xs text-slate-500 mt-1">Manage stock allocations, batch numbers, and warehouse safety thresholds.</p>
                </div>
                <a href="inventory.php" class="bg-[#007a55] hover:bg-[#006344] text-white font-medium text-xs px-4 py-2.5 rounded-lg flex items-center gap-2 transition shadow-sm">
                    <i data-lucide="layers" class="w-4 h-4 text-emerald-200"></i> Manage Full Inventory
                </a>
            </div>

            <!-- KPI Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white border border-slate-200/80 rounded-xl p-5 shadow-sm">
                    <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total SKUs in Warehouse</p>
                    <p class="text-3xl font-bold text-slate-900 mt-2"><?= number_format($totalSKUs) ?></p>
                    <p class="text-[11px] text-emerald-600 font-medium mt-1">Active tracking</p>
                </div>
                <div class="bg-white border border-slate-200/80 rounded-xl p-5 shadow-sm">
                    <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Low Stock Warnings</p>
                    <p class="text-3xl font-bold text-rose-600 mt-2"><?= $lowStockCount ?></p>
                    <p class="text-[11px] text-rose-500 font-medium mt-1">Requires restock review</p>
                </div>
                <div class="bg-white border border-slate-200/80 rounded-xl p-5 shadow-sm">
                    <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total Chemical Volume</p>
                    <p class="text-3xl font-bold text-slate-900 mt-2"><?= number_format($totalChemicalVolume, 2) ?> <span class="text-xs font-normal text-slate-500">units</span></p>
                    <p class="text-[11px] text-slate-500 font-medium mt-1">Available for dispatch</p>
                </div>
            </div>

            <!-- Recent Warehouse Stock Activity Table -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h2 class="text-base font-semibold text-slate-900">Recent Warehouse Stock Activity</h2>
                    <span class="text-xs text-slate-400 font-medium">Latest updates and logs</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-[10px] uppercase text-slate-400 border-b border-slate-100 font-semibold">
                                <th class="pb-2.5">Item Name</th>
                                <th class="pb-2.5">Category</th>
                                <th class="pb-2.5">Stock Level</th>
                                <th class="pb-2.5">Batch Number</th>
                                <th class="pb-2.5">Performed By</th>
                                <th class="pb-2.5">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                            <?php if (empty($recentActivity)): ?>
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-slate-400 italic">No recent warehouse activity recorded.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentActivity as $act): ?>
                                    <tr class="hover:bg-slate-50 transition">
                                        <td class="py-3 font-bold text-slate-900"><?= htmlspecialchars($act['item_name']) ?></td>
                                        <td class="py-3">
                                            <span class="bg-slate-100 border border-slate-200 px-2 py-0.5 rounded text-[10px] text-slate-700">
                                                <?= htmlspecialchars($act['category']) ?>
                                            </span>
                                        </td>
                                        <td class="py-3 font-mono font-bold text-slate-900">
                                            <?= number_format($act['quantity_in_stock'], 2) ?> <?= htmlspecialchars($act['unit']) ?>
                                        </td>
                                        <td class="py-3 font-mono text-slate-500">
                                            <?= !empty($act['batch_number']) ? htmlspecialchars($act['batch_number']) : 'N/A' ?>
                                        </td>
                                        <td class="py-3 text-slate-700 font-semibold">
                                            <div class="flex items-center gap-1.5">
                                                <i data-lucide="user" class="w-3.5 h-3.5 text-[#007a55]"></i>
                                                <span><?= htmlspecialchars($act['updated_by'] ?? 'System / Admin') ?></span>
                                            </div>
                                        </td>
                                        <td class="py-3">
                                            <?php 
                                                $status = $act['status'];
                                                $badgeColor = ($status === 'Low Stock' || $act['quantity_in_stock'] <= $act['min_threshold']) 
                                                    ? 'bg-amber-50 text-amber-700 border-amber-200' 
                                                    : 'bg-emerald-50 text-emerald-700 border-emerald-200';
                                            ?>
                                            <span class="border px-2 py-0.5 rounded text-[10px] font-semibold <?= $badgeColor ?>">
                                                <?= htmlspecialchars($status) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>