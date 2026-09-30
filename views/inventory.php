<?php
session_start();

// Include database connection (Fixed path)
require_once __DIR__ . '/../config/db.php';

// Initialize Alert Messages
$successMessage = '';
$errorMessage = '';

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
                (item_name, category, quantity_in_stock, unit, min_threshold, unit_cost, batch_number, storage_location, status) 
                VALUES 
                (:item_name, :category, :quantity_in_stock, :unit, :min_threshold, :unit_cost, :batch_number, :storage_location, :status)
            ");
            
            $stmt->execute([
                ':item_name'         => $item_name,
                ':category'          => $category,
                ':quantity_in_stock' => $quantity_in_stock,
                ':unit'              => $unit,
                ':min_threshold'     => $min_threshold,
                ':unit_cost'         => $unit_cost,
                ':batch_number'      => $batch_number,
                ':storage_location' => $storage_location,
                ':status'            => $status
            ]);

            $_SESSION['success_msg'] = "New item '$item_name' added successfully!";
            header("Location: inventory.php");
            exit;
        } catch (PDOException $e) {
            $errorMessage = "Database Error: " . $e->getMessage();
        }
    } else {
        $errorMessage = "Item Name is required.";
    }
}

// -----------------------------------------------------------------------------
// 2. HANDLE FORM SUBMISSION: ADJUST STOCK LEVEL
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'adjust_stock') {
    $itemId     = (int)($_POST['item_id'] ?? 0);
    $adjustType = $_POST['adjustment_type'] ?? 'add';
    $amount     = floatval($_POST['adjust_amount'] ?? 0);

    if ($itemId > 0 && $amount >= 0) {
        try {
            // Fetch current stock and threshold
            $stmt = $pdo->prepare("SELECT quantity_in_stock, min_threshold, item_name FROM inventory WHERE id = ?");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($item) {
                $currentQty   = (float)$item['quantity_in_stock'];
                $minThreshold = (float)$item['min_threshold'];

                // Calculate updated quantity
                if ($adjustType === 'add') {
                    $newQty = $currentQty + $amount;
                } elseif ($adjustType === 'subtract') {
                    $newQty = max(0, $currentQty - $amount);
                } else { // 'set'
                    $newQty = max(0, $amount);
                }

                // Recalculate status
                if ($newQty <= 0) {
                    $status = 'Out of Stock';
                } elseif ($newQty <= $minThreshold) {
                    $status = 'Low Stock';
                } else {
                    $status = 'In Stock';
                }

                // Update MySQL record
                $updateStmt = $pdo->prepare("UPDATE inventory SET quantity_in_stock = ?, status = ? WHERE id = ?");
                $updateStmt->execute([$newQty, $status, $itemId]);

                $_SESSION['success_msg'] = "Stock level adjusted for '{$item['item_name']}'. New Total: " . number_format($newQty, 2);
                header("Location: inventory.php");
                exit;
            }
        } catch (PDOException $e) {
            $errorMessage = "Database Error: " . $e->getMessage();
        }
    } else {
        $errorMessage = "Invalid item or adjustment quantity.";
    }
}

// Session message alerts
if (isset($_SESSION['success_msg'])) {
    $successMessage = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}

// -----------------------------------------------------------------------------
// 3. FETCH KPI METRICS FROM DATABASE
// -----------------------------------------------------------------------------
try {
    // Total Chemical Stock
    $stmtChem = $pdo->query("SELECT SUM(quantity_in_stock) as total_chem, COUNT(*) as chem_count FROM inventory WHERE category = 'Chemical'");
    $chemData = $stmtChem->fetch(PDO::FETCH_ASSOC);
    $totalChemicalStock = $chemData['total_chem'] ?? 0;
    $activeChemCount    = $chemData['chem_count'] ?? 0;

    // Deployed Field Traps / Devices
    $stmtTraps = $pdo->query("SELECT SUM(quantity_in_stock) as total_traps FROM inventory WHERE category IN ('Device/Trap', 'Traps & Devices')");
    $totalTraps = $stmtTraps->fetchColumn() ?: 0;

    // Low Stock Alerts
    $stmtLow = $pdo->query("SELECT COUNT(*) FROM inventory WHERE quantity_in_stock <= min_threshold OR status = 'Low Stock'");
    $lowStockCount = $stmtLow->fetchColumn() ?: 0;

    // Total Inventory Items
    $stmtTotal = $pdo->query("SELECT COUNT(*) FROM inventory");
    $totalItemCount = $stmtTotal->fetchColumn() ?: 0;

} catch (PDOException $e) {
    $totalChemicalStock = 0;
    $activeChemCount    = 0;
    $totalTraps         = 0;
    $lowStockCount      = 0;
    $totalItemCount     = 0;
}

// -----------------------------------------------------------------------------
// 4. FETCH DISTINCT STORAGE LOCATIONS FOR FILTER DROPDOWN
// -----------------------------------------------------------------------------
try {
    $stmtLocations = $pdo->query("
        SELECT DISTINCT storage_location 
        FROM inventory 
        WHERE storage_location IS NOT NULL AND TRIM(storage_location) != '' 
        ORDER BY storage_location ASC
    ");
    $locationsList = $stmtLocations->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $locationsList = [];
}

// -----------------------------------------------------------------------------
// 5. FETCH FILTERED INVENTORY LIST
// -----------------------------------------------------------------------------
$searchKeyword  = trim($_GET['search'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');
$locationFilter = trim($_GET['location'] ?? '');

$query  = "SELECT * FROM inventory WHERE 1=1";
$params = [];

if (!empty($searchKeyword)) {
    $query .= " AND (item_name LIKE :search OR batch_number LIKE :search OR storage_location LIKE :search)";
    $params[':search'] = '%' . $searchKeyword . '%';
}

if (!empty($categoryFilter)) {
    $query .= " AND category = :category";
    $params[':category'] = $categoryFilter;
}

if (!empty($locationFilter)) {
    $query .= " AND storage_location = :location";
    $params[':location'] = $locationFilter;
}

$query .= " ORDER BY created_at DESC";

try {
    $stmtList = $pdo->prepare($query);
    $stmtList->execute($params);
    $inventoryItems = $stmtList->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $inventoryItems = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vermex - Inventory Management</title>
    
    <!-- Tailwind CSS (CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
</head>
<body class="bg-[#f8faf9] text-slate-800 min-h-screen flex font-sans antialiased overflow-hidden">

    <!-- 1. Shared Sidebar Component -->
    <?php include 'components/sidebar.php'; ?>

    <!-- 2. Main Content Area -->
    <main class="flex-1 flex flex-col overflow-y-auto bg-[#f8faf9]">
        
        <div class="p-6 space-y-6 w-full max-w-7xl mx-auto">
            
            <!-- Alert Notifications -->
            <?php if (!empty($successMessage)): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-xs flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-2">
                        <i data-lucide="check-circle" class="w-4 h-4 text-[#007a55]"></i>
                        <span><?= htmlspecialchars($successMessage) ?></span>
                    </div>
                    <button onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-900 font-bold">&times;</button>
                </div>
            <?php endif; ?>

            <?php if (!empty($errorMessage)): ?>
                <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-xl text-xs flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-2">
                        <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600"></i>
                        <span><?= htmlspecialchars($errorMessage) ?></span>
                    </div>
                    <button onclick="this.parentElement.remove()" class="text-rose-600 hover:text-rose-900 font-bold">&times;</button>
                </div>
            <?php endif; ?>

            <!-- Header Section -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-200 pb-5">
                <div>
                    <div class="flex items-center gap-2 text-[11px] text-[#007a55] font-semibold tracking-wider uppercase mb-1">
                        <span>Operations</span>
                        <span>•</span>
                        <span>Warehouse & Stock Control</span>
                    </div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Inventory Management</h1>
                    <p class="text-xs text-slate-500 mt-1">Track chemical volumes, trap deployment counts, PPE stock, and technician van allocations.</p>
                </div>
                
                <div class="flex items-center gap-3">
                    <button onclick="openModal()" class="bg-[#007a55] hover:bg-[#006344] text-white font-medium text-xs px-4 py-2 rounded-lg flex items-center gap-2 transition shadow-sm">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        <span>Add Stock Item</span>
                    </button>
                </div>
            </div>

            <!-- Dynamic KPI Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white border border-slate-200/80 rounded-xl p-4 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total Chemical Stock</p>
                        <p class="text-2xl font-bold text-slate-900 mt-1"><?= number_format($totalChemicalStock) ?> <span class="text-xs font-normal text-slate-500">mL</span></p>
                        <p class="text-[11px] text-emerald-600 font-medium mt-1"><?= $activeChemCount ?> Active Formulations</p>
                    </div>
                    <div class="bg-emerald-50 p-2.5 rounded-lg text-[#007a55] border border-emerald-100">
                        <i data-lucide="flask-conical" class="w-5 h-5"></i>
                    </div>
                </div>

                <div class="bg-white border border-slate-200/80 rounded-xl p-4 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Device & Trap Stock</p>
                        <p class="text-2xl font-bold text-slate-900 mt-1"><?= number_format($totalTraps) ?> <span class="text-xs font-normal text-slate-500">units</span></p>
                        <p class="text-[11px] text-blue-600 font-medium mt-1">Cages, Glue Traps & ILTs</p>
                    </div>
                    <div class="bg-blue-50 p-2.5 rounded-lg text-blue-600 border border-blue-100">
                        <i data-lucide="box" class="w-5 h-5"></i>
                    </div>
                </div>

                <div class="bg-white border border-slate-200/80 rounded-xl p-4 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Low Stock Alerts</p>
                        <p class="text-2xl font-bold text-slate-900 mt-1"><?= $lowStockCount ?> <span class="text-xs font-normal text-amber-600">items</span></p>
                        <p class="text-[11px] text-amber-600 font-medium mt-1">Below minimum threshold</p>
                    </div>
                    <div class="bg-amber-50 p-2.5 rounded-lg text-amber-600 border border-amber-100">
                        <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                    </div>
                </div>

                <div class="bg-white border border-slate-200/80 rounded-xl p-4 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total Inventory SKUs</p>
                        <p class="text-2xl font-bold text-slate-900 mt-1"><?= $totalItemCount ?> <span class="text-xs font-normal text-slate-500">items</span></p>
                        <p class="text-[11px] text-slate-500 font-medium mt-1">Warehouse & Field Items</p>
                    </div>
                    <div class="bg-slate-50 p-2.5 rounded-lg text-slate-600 border border-slate-200">
                        <i data-lucide="layers" class="w-5 h-5"></i>
                    </div>
                </div>
            </div>

            <!-- Filters & Search Toolbar -->
            <form method="GET" action="inventory.php" class="bg-white border border-slate-200/80 rounded-xl p-4 flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-sm">
                <div class="flex flex-1 flex-wrap items-center gap-3">
                    <div class="relative flex-1 min-w-[200px] max-w-xs">
                        <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($searchKeyword) ?>" placeholder="Search item, batch #, or location..." class="w-full bg-slate-50 border border-slate-200 text-slate-800 placeholder-slate-400 text-xs rounded-lg pl-9 pr-4 py-2 focus:outline-none focus:border-[#007a55] transition">
                    </div>
                    <select name="category" onchange="this.form.submit()" class="bg-slate-50 border border-slate-200 text-slate-600 text-xs rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55] transition">
                        <option value="">All Categories</option>
                        <option value="Chemical" <?= $categoryFilter === 'Chemical' ? 'selected' : '' ?>>Chemicals & Concentrates</option>
                        <option value="Device/Trap" <?= $categoryFilter === 'Device/Trap' ? 'selected' : '' ?>>Traps & Devices</option>
                        <option value="PPE" <?= $categoryFilter === 'PPE' ? 'selected' : '' ?>>PPE & Safety Gear</option>
                        <option value="Equipment" <?= $categoryFilter === 'Equipment' ? 'selected' : '' ?>>Sprayers & Applicators</option>
                    </select>
                    
                    <!-- Dynamic Database Locations Filter -->
                    <select name="location" onchange="this.form.submit()" class="bg-slate-50 border border-slate-200 text-slate-600 text-xs rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55] transition">
                        <option value="">All Locations</option>
                        <?php foreach ($locationsList as $loc): ?>
                            <option value="<?= htmlspecialchars($loc) ?>" <?= $locationFilter === $loc ? 'selected' : '' ?>>
                                <?= htmlspecialchars($loc) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php if (!empty($searchKeyword) || !empty($categoryFilter) || !empty($locationFilter)): ?>
                        <a href="inventory.php" class="text-xs text-rose-600 hover:underline flex items-center gap-1">
                            <i data-lucide="x-circle" class="w-3.5 h-3.5"></i> Clear Filters
                        </a>
                    <?php endif; ?>
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="bg-[#007a55] text-white text-xs px-3.5 py-2 rounded-lg font-medium hover:bg-[#006344] transition">
                        Filter
                    </button>
                </div>
            </form>

            <!-- Main Inventory Data Table -->
            <div class="bg-white border border-slate-200/80 rounded-xl p-5 space-y-4 shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Stock Items & Device Inventory</h2>
                        <p class="text-[11px] text-slate-400 mt-0.5">Live stock counts synced with database records and field Job Orders.</p>
                    </div>
                    <span class="bg-emerald-50 text-[#007a55] text-[10px] font-semibold px-2.5 py-1 rounded-full border border-emerald-100"><?= count($inventoryItems) ?> items listed</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-[10px] uppercase text-slate-400 border-b border-slate-100">
                                <th class="pb-2.5 font-semibold">Item Name</th>
                                <th class="pb-2.5 font-semibold">Category</th>
                                <th class="pb-2.5 font-semibold">Available Stock</th>
                                <th class="pb-2.5 font-semibold">Min Threshold</th>
                                <th class="pb-2.5 font-semibold">Unit Cost</th>
                                <th class="pb-2.5 font-semibold">Status</th>
                                <th class="pb-2.5 font-semibold">Storage Location</th>
                                <th class="pb-2.5 font-semibold text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (!empty($inventoryItems)): ?>
                                <?php foreach ($inventoryItems as $item): ?>
                                    <tr class="hover:bg-slate-50/80 transition">
                                        <td class="py-3">
                                            <p class="font-bold text-slate-800"><?= htmlspecialchars($item['item_name']) ?></p>
                                            <p class="text-[10px] text-slate-400 font-mono">
                                                ID: #<?= $item['id'] ?> 
                                                <?= !empty($item['batch_number']) ? '• Batch #' . htmlspecialchars($item['batch_number']) : '' ?>
                                            </p>
                                        </td>
                                        <td class="py-3">
                                            <?php 
                                                $catColor = 'bg-slate-50 text-slate-700 border-slate-200';
                                                if ($item['category'] === 'Chemical') {
                                                    $catColor = 'bg-emerald-50 text-[#007a55] border-emerald-200/60';
                                                } elseif ($item['category'] === 'Device/Trap' || $item['category'] === 'Traps & Devices') {
                                                    $catColor = 'bg-blue-50 text-blue-700 border-blue-200/60';
                                                } elseif ($item['category'] === 'PPE') {
                                                    $catColor = 'bg-purple-50 text-purple-700 border-purple-200/60';
                                                }
                                            ?>
                                            <span class="<?= $catColor ?> border px-2 py-0.5 rounded text-[10px] font-medium">
                                                <?= htmlspecialchars($item['category']) ?>
                                            </span>
                                        </td>
                                        <td class="py-3">
                                            <p class="font-bold font-mono text-slate-800">
                                                <?= number_format($item['quantity_in_stock'], 2) ?> 
                                                <span class="text-[10px] font-normal text-slate-400"><?= htmlspecialchars($item['unit']) ?></span>
                                            </p>
                                        </td>
                                        <td class="py-3 text-slate-600 font-mono">
                                            <?= number_format($item['min_threshold'], 2) ?> <?= htmlspecialchars($item['unit']) ?>
                                        </td>
                                        <td class="py-3 text-slate-700 font-mono">
                                            ₱<?= number_format($item['unit_cost'], 2) ?>
                                        </td>
                                        <td class="py-3">
                                            <?php 
                                                $status = $item['status'];
                                                $statusBg = 'bg-emerald-50 text-[#007a55] border-emerald-200/60';
                                                if ($status === 'Low Stock' || $item['quantity_in_stock'] <= $item['min_threshold']) {
                                                    $status = 'Low Stock';
                                                    $statusBg = 'bg-amber-50 text-amber-700 border-amber-200/60';
                                                } elseif ($status === 'Out of Stock' || $item['quantity_in_stock'] <= 0) {
                                                    $status = 'Out of Stock';
                                                    $statusBg = 'bg-rose-50 text-rose-700 border-rose-200/60';
                                                }
                                            ?>
                                            <span class="<?= $statusBg ?> border px-2 py-0.5 rounded text-[10px] font-semibold">
                                                <?= htmlspecialchars($status) ?>
                                            </span>
                                        </td>
                                        <td class="py-3 text-slate-700">
                                            <?= htmlspecialchars($item['storage_location'] ?: 'Unassigned') ?>
                                        </td>
                                        <td class="py-3 text-right">
                                            <button 
                                                onclick="openAdjustModal(<?= $item['id']; ?>, '<?= addslashes($item['item_name']); ?>', '<?= addslashes($item['unit']); ?>')" 
                                                class="text-[#007a55] hover:text-[#006344] bg-emerald-50 hover:bg-emerald-100 border border-emerald-100 px-2.5 py-1 rounded text-[11px] font-semibold transition">
                                                Adjust
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-8 text-slate-400">
                                        <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2 opacity-50"></i>
                                        <p class="text-xs">No inventory items found matching your filter criteria.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>

        </div>
    </main>

    <!-- =====================================================================
         ADD ITEM MODAL DIALOG
         ===================================================================== -->
    <div id="addItemModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-xl border border-slate-200 w-full max-w-lg overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                    <i data-lucide="plus-circle" class="w-4 h-4 text-[#007a55]"></i>
                    Add New Inventory Item
                </h3>
                <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 font-bold">&times;</button>
            </div>
            
            <form method="POST" action="inventory.php" class="p-5 space-y-4 text-xs">
                <input type="hidden" name="action" value="add_item">

                <div>
                    <label class="block font-medium text-slate-700 mb-1">Item Name *</label>
                    <input type="text" name="item_name" required placeholder="e.g. Termidor HE Termiticide" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-slate-800 focus:outline-none focus:border-[#007a55]">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Category</label>
                        <select name="category" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-slate-800 focus:outline-none focus:border-[#007a55]">
                            <option value="Chemical">Chemical</option>
                            <option value="Device/Trap">Device / Trap</option>
                            <option value="Equipment">Equipment</option>
                            <option value="PPE">PPE</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Unit of Measure</label>
                        <select name="unit" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-slate-800 focus:outline-none focus:border-[#007a55]">
                            <option value="Pcs">Pcs (Pieces)</option>
                            <option value="Units">Units</option>
                            <option value="mL">mL (Milliliters)</option>
                            <option value="L">L (Liters)</option>
                            <option value="Grams">Grams (g)</option>
                            <option value="Kg">Kg (Kilograms)</option>
                            <option value="Boxes">Boxes</option>
                            <option value="Packs">Packs</option>
                            <option value="Tubes">Tubes</option>
                            <option value="Sets">Sets</option>
                            <option value="Rolls">Rolls</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Initial Stock</label>
                        <input type="number" step="0.01" name="quantity_in_stock" required value="0.00" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-slate-800 focus:outline-none focus:border-[#007a55]">
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Min Threshold</label>
                        <input type="number" step="0.01" name="min_threshold" required value="10.00" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-slate-800 focus:outline-none focus:border-[#007a55]">
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Unit Cost (₱)</label>
                        <input type="number" step="0.01" name="unit_cost" value="0.00" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-slate-800 focus:outline-none focus:border-[#007a55]">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Batch / Lot #</label>
                        <input type="text" name="batch_number" placeholder="e.g. TH-2026-A" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-slate-800 focus:outline-none focus:border-[#007a55]">
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Storage Location</label>
                        <input type="text" name="storage_location" placeholder="e.g. Chemical Cage A" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-slate-800 focus:outline-none focus:border-[#007a55]">
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                    <button type="button" onclick="closeModal()" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-lg font-medium transition">Cancel</button>
                    <button type="submit" class="bg-[#007a55] hover:bg-[#006344] text-white px-4 py-2 rounded-lg font-medium transition shadow-sm">Save Stock Item</button>
                </div>
            </form>
        </div>
    </div>

    <!-- =====================================================================
         STOCK ADJUSTMENT MODAL DIALOG
         ===================================================================== -->
    <div id="adjustModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center hidden z-50 p-4">
        <div class="bg-white rounded-xl shadow-xl border border-slate-200 w-full max-w-md overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Adjust Stock Level</h3>
                    <p id="adjustItemName" class="text-xs text-[#007a55] font-semibold mt-0.5">Item Name</p>
                </div>
                <button onclick="closeAdjustModal()" class="text-slate-400 hover:text-slate-600 transition font-bold">&times;</button>
            </div>

            <form action="inventory.php" method="POST" class="p-6 space-y-4 text-xs">
                <input type="hidden" name="action" value="adjust_stock">
                <input type="hidden" name="item_id" id="adjustItemId">

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Adjustment Type</label>
                    <select name="adjustment_type" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-slate-800 focus:outline-none focus:border-[#007a55]">
                        <option value="add">Add Stock (+) (Restock / Received)</option>
                        <option value="subtract">Deduct Stock (-) (Manual Usage / Damaged)</option>
                        <option value="set">Set Exact Count (=) (Physical Inventory Audit)</option>
                    </select>
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">
                        Quantity / Amount (<span id="adjustUnitLabel">Units</span>)
                    </label>
                    <input type="number" step="0.01" min="0.01" name="adjust_amount" required placeholder="e.g. 500" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-slate-800 focus:outline-none focus:border-[#007a55]">
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Reason for Adjustment</label>
                    <textarea name="remarks" rows="2" placeholder="e.g., Supplier Restock, Damaged during transit, Audit correction..." class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-slate-800 focus:outline-none focus:border-[#007a55]"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" onclick="closeAdjustModal()" class="px-4 py-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 font-medium">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-[#007a55] hover:bg-[#006344] text-white font-semibold shadow-sm transition">Save Adjustment</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 3. Initialize Lucide Icons & Modal Controls -->
    <script>
        lucide.createIcons();

        function openModal() {
            document.getElementById('addItemModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('addItemModal').classList.add('hidden');
        }

        function openAdjustModal(id, name, unit) {
            document.getElementById('adjustItemId').value = id;
            document.getElementById('adjustItemName').innerText = name;
            document.getElementById('adjustUnitLabel').innerText = unit;
            document.getElementById('adjustModal').classList.remove('hidden');
        }

        function closeAdjustModal() {
            document.getElementById('adjustModal').classList.add('hidden');
        }
    </script>
</body>
</html>