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

// Get current logged-in user's name robustly
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
// FETCH DASHBOARD METRICS & INVENTORY LOGS ACTIVITY
// -----------------------------------------------------------------------------
try {
    $stmtTotalSKUs = $pdo->query("SELECT COUNT(*) FROM inventory");
    $totalSKUs = $stmtTotalSKUs->fetchColumn() ?: 0;

    $stmtLowStock = $pdo->query("SELECT COUNT(*) FROM inventory WHERE quantity_in_stock <= min_threshold OR status = 'Low Stock'");
    $lowStockCount = $stmtLowStock->fetchColumn() ?: 0;

    $stmtChemVol = $pdo->query("SELECT SUM(quantity_in_stock) FROM inventory WHERE category = 'Chemical'");
    $totalChemicalVolume = $stmtChemVol->fetchColumn() ?: 0;

    // Fetch unified activity logs from inventory_logs joined with inventory
    $recentActivity = [];
    
    $checkLogs = $pdo->query("SHOW TABLES LIKE 'inventory_logs'")->fetch();

    if ($checkLogs) {
        $stmtActivity = $pdo->query("
            SELECT 
                COALESCE(i.item_name, 'Unknown Item') AS item_name, 
                COALESCE(i.category, 'General') AS category, 
                l.quantity_changed AS stock_level, 
                COALESCE(i.unit, 'Pcs') AS unit, 
                COALESCE(l.remarks, CONCAT('Job Order #', l.job_order_id)) AS reference_info,
                COALESCE(l.performed_by, 'System / Admin') AS performed_by,
                COALESCE(l.action_type, 'Stock Adjustment') AS status_type,
                l.created_at AS log_date
            FROM inventory_logs l
            LEFT JOIN inventory i ON l.material_id = i.id
            ORDER BY l.created_at DESC 
            LIMIT 10
        ");
        $recentActivity = $stmtActivity->fetchAll(PDO::FETCH_ASSOC);
    }

    // If inventory_logs table is empty, fallback to inventory updates
    if (empty($recentActivity)) {
        $stmtActivity = $pdo->query("
            SELECT 
                item_name, 
                category, 
                quantity_in_stock AS stock_level, 
                unit, 
                COALESCE(batch_number, 'Direct Adjustment') AS reference_info,
                COALESCE(updated_by, 'System / Admin') AS performed_by,
                status AS status_type,
                updated_at AS log_date
            FROM inventory 
            ORDER BY updated_at DESC, id DESC 
            LIMIT 10
        ");
        $recentActivity = $stmtActivity->fetchAll(PDO::FETCH_ASSOC);
    }

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
<body class="bg-[#f8faf9] text-slate-800 min-h-screen flex font-sans antialiased">

    <!-- Include Custodian Sidebar -->
    <?php include 'components/sidebar.php'; ?>

    <!-- MAIN CONTENT AREA -->
    <main class="flex-1 flex flex-col min-h-screen overflow-x-hidden">
        
        <!-- Top Bar -->
        <header class="bg-white border-b border-slate-200 px-4 sm:px-8 py-3 flex items-center justify-between text-xs text-slate-500 sticky top-0 z-30">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="md:hidden text-slate-600 hover:text-slate-900 p-1 rounded-lg focus:outline-none">
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>
                <div class="truncate">Custodian Workspace / <span class="font-semibold text-slate-800">Warehouse Overview</span></div>
            </div>
            <div class="hidden sm:flex items-center gap-2">
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                    Active Role: Chemical Custodian (<?= htmlspecialchars($currentUserName) ?>)
                </span>
            </div>
        </header>

        <!-- Main Content Container -->
        <div class="p-4 sm:p-8 space-y-6 max-w-7xl w-full mx-auto">
            
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
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl sm:text-2xl font-bold text-slate-900">Chemical Custodian Dashboard</h1>
                    <p class="text-xs text-slate-500 mt-1">Manage stock allocations, batch numbers, and job order stock deductions.</p>
                </div>
                <div>
                    <a href="inventory.php" class="bg-[#007a55] hover:bg-[#006344] text-white font-medium text-xs px-4 py-2.5 rounded-lg inline-flex items-center justify-center gap-2 transition shadow-sm w-full sm:w-auto">
                        <i data-lucide="layers" class="w-4 h-4 text-emerald-200"></i> Manage Full Inventory
                    </a>
                </div>
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

            <!-- Recent Warehouse Stock & Job Order Deductions Table -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Job Order Deductions</h2>
                        <p class="text-[11px] text-slate-400 mt-0.5">Live log showing items deducted for job orders</p>
                    </div>
                    <span class="text-xs text-slate-400 font-medium hidden sm:inline">Real-time audit log</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs whitespace-nowrap sm:whitespace-normal">
                        <thead>
                            <tr class="text-[10px] uppercase text-slate-400 border-b border-slate-100 font-semibold">
                                <th class="pb-2.5 pr-4">Item Name</th>
                                <th class="pb-2.5 pr-4">Category</th>
                                <th class="pb-2.5 pr-4">Quantity Changed</th>
                                <th class="pb-2.5 pr-4">Remarks / Job Order Ref</th>
                                <th class="pb-2.5 pr-4">Performed By</th>
                                <th class="pb-2.5">Activity Type</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                            <?php if (empty($recentActivity)): ?>
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-slate-400 italic">No job order deductions or warehouse movements recorded yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentActivity as $act): ?>
                                    <tr class="hover:bg-slate-50 transition">
                                        <td class="py-3 pr-4 font-bold text-slate-900"><?= htmlspecialchars($act['item_name']) ?></td>
                                        <td class="py-3 pr-4">
                                            <span class="bg-slate-100 border border-slate-200 px-2 py-0.5 rounded text-[10px] text-slate-700">
                                                <?= htmlspecialchars($act['category'] ?? 'Chemical') ?>
                                            </span>
                                        </td>
                                        <td class="py-3 pr-4 font-mono font-bold <?= $act['stock_level'] < 0 ? 'text-rose-600' : 'text-emerald-600' ?>">
                                            <?= ($act['stock_level'] > 0 ? '+' : '') . number_format($act['stock_level'], 2) ?> <?= htmlspecialchars($act['unit'] ?? 'Pcs') ?>
                                        </td>
                                        <td class="py-3 pr-4 text-slate-800">
                                            <?= htmlspecialchars($act['reference_info']) ?>
                                        </td>
                                        <td class="py-3 pr-4 text-slate-700 font-semibold">
                                            <div class="flex items-center gap-1.5">
                                                <i data-lucide="user-check" class="w-3.5 h-3.5 text-[#007a55]"></i>
                                                <span><?= htmlspecialchars($act['performed_by']) ?></span>
                                            </div>
                                        </td>
                                        <td class="py-3">
                                            <?php 
                                                $type = $act['status_type'];
                                                $badgeColor = 'bg-rose-50 text-rose-700 border-rose-200';
                                                if (strpos($type, 'Add') !== false || strpos($type, 'Restock') !== false) {
                                                    $badgeColor = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                                                }
                                            ?>
                                            <span class="border px-2 py-0.5 rounded text-[10px] font-semibold <?= $badgeColor ?>">
                                                <?= htmlspecialchars($type) ?>
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

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            sidebar.classList.toggle('-translate-x-full');
            backdrop.classList.toggle('hidden');
        }
    </script>

</body>
</html>