<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

// Security check: Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit;
}

$technicianName = $_SESSION['full_name'] ?? '';
if (empty($technicianName) && isset($_SESSION['first_name'], $_SESSION['last_name'])) {
    $technicianName = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
}

$jobOrderId = $_GET['id'] ?? null;
if (!$jobOrderId || !is_numeric($jobOrderId)) {
    header("Location: dashboard.php");
    exit;
}

$successMessage = '';
$errorMessage = '';

// Fetch active inventory items for the materials dropdown
$inventoryItems = [];
try {
    $invStmt = $pdo->query("SELECT id, item_name, unit, quantity_in_stock FROM inventory WHERE status != 'Out of Stock' ORDER BY item_name ASC");
    $inventoryItems = $invStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $inventoryItems = [];
}

// Handle Form Submission / Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $clientName = $_POST['client_name'] ?? '';
        $location = $_POST['location'] ?? '';
        $serviceType = $_POST['service_type'] ?? '';
        $scheduledDate = $_POST['scheduled_date'] ?? date('Y-m-d');
        $serviceWindow = $_POST['service_window'] ?? '';
        $assignedTech = $_POST['assigned_tech'] ?? $technicianName;
        $priority = $_POST['priority'] ?? 'Standard';
        $routeStatus = $_POST['route_status'] ?? 'Complete';
        $timeIn = $_POST['time_in'] ?? null;
        $timeOut = $_POST['time_out'] ?? null;
        $comments = $_POST['comments'] ?? '';

        // Dynamic Area Findings (JSON)
        $findingsRows = [];
        if (isset($_POST['finding_area']) && is_array($_POST['finding_area'])) {
            for ($i = 0; $i < count($_POST['finding_area']); $i++) {
                if (!empty(trim($_POST['finding_area'][$i])) || !empty(trim($_POST['finding_action'][$i]))) {
                    $findingsRows[] = [
                        'area' => $_POST['finding_area'][$i] ?? '',
                        'action_taken' => $_POST['finding_action'][$i] ?? ''
                    ];
                }
            }
        }
        $areaFindingsJson = json_encode($findingsRows);

        // Update Job Order record
        $updateStmt = $pdo->prepare("
            UPDATE job_orders SET 
                client_name = ?, 
                location = ?, 
                service_type = ?, 
                scheduled_date = ?, 
                service_window = ?, 
                assigned_tech = ?, 
                priority = ?, 
                route_status = ?, 
                time_in = ?, 
                time_out = ?, 
                comments = ?, 
                area_findings = ?
            WHERE id = ?
        ");
        $updateStmt->execute([
            $clientName, $location, $serviceType, $scheduledDate, $serviceWindow,
            $assignedTech, $priority, $routeStatus, $timeIn ?: null, $timeOut ?: null,
            $comments, $areaFindingsJson, $jobOrderId
        ]);

        // Handle Materials / Chemicals Used (Inventory Deduction)
        if (isset($_POST['material_id']) && is_array($_POST['material_id'])) {
            // Optional: clear old materials for this job order if re-submitting, or append. Let's sync them cleanly:
            // First, retrieve existing materials to revert stock if needed, or simply record new logs.
            for ($i = 0; $i < count($_POST['material_id']); $i++) {
                $invId = intval($_POST['material_id'][$i]);
                $qtyUsed = floatval($_POST['material_quantity'][$i] ?? 0);

                if ($invId > 0 && $qtyUsed > 0) {
                    // Check unit
                    $uStmt = $pdo->prepare("SELECT item_name, unit, quantity_in_stock FROM inventory WHERE id = ?");
                    $uStmt->execute([$invId]);
                    $invItem = $uStmt->fetch(PDO::FETCH_ASSOC);

                    if ($invItem) {
                        $unit = $invItem['unit'];
                        
                        // Insert into job_order_materials
                        $jomStmt = $pdo->prepare("INSERT INTO job_order_materials (job_order_id, inventory_id, quantity_used, unit_used) VALUES (?, ?, ?, ?)");
                        $jomStmt->execute([$jobOrderId, $invId, $qtyUsed, $unit]);

                        // Deduct from inventory
                        $newStock = max(0, $invItem['quantity_in_stock'] - $qtyUsed);
                        $newStatus = ($newStock <= 0) ? 'Out of Stock' : (($newStock <= 10) ? 'Low Stock' : 'In Stock'); // simplified threshold check

                        $updInv = $pdo->prepare("UPDATE inventory SET quantity_in_stock = ?, status = ? WHERE id = ?");
                        $updInv->execute([$newStock, $newStatus, $invId]);

                        // Log to inventory_logs
                        $logStmt = $pdo->prepare("INSERT INTO inventory_logs (material_id, job_order_id, action_type, quantity_changed, remarks, performed_by) VALUES (?, ?, 'Job Order Deduction', ?, ?, ?)");
                        $logRemarks = "Deducted for Job Order #" . $jobOrderId . " (" . $clientName . " - " . $serviceType . ")";
                        $logStmt->execute([$invId, $jobOrderId, -$qtyUsed, $logRemarks, $technicianName]);
                    }
                }
            }
        }

        $successMessage = "Job Order execution report successfully saved and inventory updated!";
    } catch (Exception $e) {
        $errorMessage = "Error updating job order: " . $e->getMessage();
    }
}

// Fetch current job order data
try {
    $stmt = $pdo->prepare("SELECT * FROM job_orders WHERE id = ?");
    $stmt->execute([$jobOrderId]);
    $jobOrder = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$jobOrder) {
        header("Location: dashboard.php");
        exit;
    }

    $savedAreaFindings = json_decode($jobOrder['area_findings'] ?? '[]', true);

    // Fetch previously logged materials for this job order
    $matStmt = $pdo->prepare("SELECT jm.*, i.item_name FROM job_order_materials jm JOIN inventory i ON jm.inventory_id = i.id WHERE jm.job_order_id = ?");
    $matStmt->execute([$jobOrderId]);
    $loggedMaterials = $matStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Order Execution Form - Vermex Pest Solutions</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-[#071811] text-gray-100 font-sans antialiased min-h-screen flex flex-col selection:bg-emerald-500 selection:text-white">

    <!-- Top Navigation Header -->
    <header class="bg-[#0d2e21]/90 backdrop-blur-md border-b border-[#164a34] px-6 py-4 flex justify-between items-center sticky top-0 z-50 shadow-xl">
        <div class="flex items-center space-x-3">
            <div class="bg-gradient-to-br from-emerald-500 to-[#007a55] p-2.5 rounded-xl text-white font-bold shadow-lg flex items-center justify-center w-10 h-10">
                <i data-lucide="shield" class="w-5 h-5"></i>
            </div>
            <div>
                <h1 class="text-xs font-black tracking-wider uppercase text-emerald-400">Vermex Pest Solutions</h1>
                <p class="text-xs text-gray-300">Field Dispatch & Service Report</p>
            </div>
        </div>
        <div class="flex items-center space-x-3">
            <a href="dashboard.php" class="bg-[#164a34] hover:bg-[#1f5e43] text-gray-200 text-xs px-4 py-2 rounded-xl transition font-semibold flex items-center gap-2 shadow-sm">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Dashboard
            </a>
            <button onclick="window.print();" class="bg-emerald-600/20 hover:bg-emerald-600/30 text-emerald-300 text-xs px-3.5 py-2 rounded-xl transition border border-emerald-500/30 flex items-center gap-1.5 font-semibold">
                <i data-lucide="printer" class="w-3.5 h-3.5"></i> Print
            </button>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-1 max-w-5xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        <?php if (!empty($successMessage)): ?>
            <div class="bg-emerald-500/20 border border-emerald-500/40 text-emerald-300 p-4 rounded-xl text-xs flex items-center gap-2 shadow-lg">
                <i data-lucide="check-circle" class="w-4 h-4 shrink-0"></i> <?php echo htmlspecialchars($successMessage); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMessage)): ?>
            <div class="bg-red-500/20 border border-red-500/40 text-red-300 p-4 rounded-xl text-xs flex items-center gap-2 shadow-lg">
                <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i> <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>

        <!-- Form Wrapper -->
        <form method="POST" action="" class="bg-[#0e3023] rounded-2xl border border-[#18523b] p-6 sm:p-8 shadow-2xl space-y-8">
            
            <div class="border-b border-[#18523b] pb-4 flex justify-between items-center">
                <div>
                    <h2 class="text-base font-black tracking-wider uppercase text-white">Job Order Execution Form</h2>
                    <p class="text-[11px] text-gray-400">Vermex Pest Solutions — Field Dispatch & Service Report[cite: 24]</p>
                </div>
                <span class="px-3 py-1 rounded-lg text-xs font-mono font-bold bg-emerald-950 text-emerald-300 border border-emerald-700">
                    JO #<?php echo str_pad($jobOrder['id'], 4, '0', STR_PAD_LEFT); ?>
                </span>
            </div>

            <!-- Top Header Inputs Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-[#09261b] p-5 rounded-xl border border-[#164e37]">
                
                <!-- Left Column -->
                <div class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-emerald-400 mb-1">Contract Client Account:</label>
                        <input type="text" name="client_name" value="<?php echo htmlspecialchars($jobOrder['client_name'] ?? ''); ?>" required
                            class="w-full px-3.5 py-2 bg-[#061c13] border border-[#17523a] rounded-xl text-xs text-white focus:outline-none focus:border-emerald-500 transition shadow-inner">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-emerald-400 mb-1">Service Address Location:</label>
                        <input type="text" name="location" value="<?php echo htmlspecialchars($jobOrder['location'] ?? ''); ?>" required
                            class="w-full px-3.5 py-2 bg-[#061c13] border border-[#17523a] rounded-xl text-xs text-white focus:outline-none focus:border-emerald-500 transition shadow-inner">
                    </div>
                </div>

                <!-- Right Column -->
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-emerald-400 mb-1">Scheduled Date:</label>
                            <input type="date" name="scheduled_date" value="<?php echo htmlspecialchars($jobOrder['scheduled_date'] ?? date('Y-m-d')); ?>" required
                                class="w-full px-3.5 py-2 bg-[#061c13] border border-[#17523a] rounded-xl text-xs text-white focus:outline-none focus:border-emerald-500 transition shadow-inner">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-emerald-400 mb-1">Service Window:</label>
                            <input type="text" name="service_window" value="<?php echo htmlspecialchars($jobOrder['service_window'] ?? ''); ?>" placeholder="e.g. 09:00 AM - 10:30 AM"
                                class="w-full px-3.5 py-2 bg-[#061c13] border border-[#17523a] rounded-xl text-xs text-white focus:outline-none focus:border-emerald-500 transition shadow-inner">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-emerald-400 mb-1">Assigned Personnel / Field Tech:</label>
                        <input type="text" name="assigned_tech" value="<?php echo htmlspecialchars($jobOrder['assigned_tech'] ?? $technicianName); ?>" required
                            class="w-full px-3.5 py-2 bg-[#061c13] border border-[#17523a] rounded-xl text-xs text-white focus:outline-none focus:border-emerald-500 transition shadow-inner">
                    </div>
                </div>
            </div>

            <!-- Second Row Inputs: Service Type, Time In/Out, Priority, Status -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-emerald-400 mb-1">Treatment / Service Type:</label>
                    <input type="text" name="service_type" value="<?php echo htmlspecialchars($jobOrder['service_type'] ?? ''); ?>" placeholder="e.g. General Pest Control" required
                        class="w-full px-3.5 py-2 bg-[#061c13] border border-[#17523a] rounded-xl text-xs text-white focus:outline-none focus:border-emerald-500 transition shadow-inner">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-emerald-400 mb-1">Time In:</label>
                    <input type="time" name="time_in" value="<?php echo htmlspecialchars($jobOrder['time_in'] ?? ''); ?>"
                        class="w-full px-3.5 py-2 bg-[#061c13] border border-[#17523a] rounded-xl text-xs text-white focus:outline-none focus:border-emerald-500 transition shadow-inner">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-emerald-400 mb-1">Time Out:</label>
                    <input type="time" name="time_out" value="<?php echo htmlspecialchars($jobOrder['time_out'] ?? ''); ?>"
                        class="w-full px-3.5 py-2 bg-[#061c13] border border-[#17523a] rounded-xl text-xs text-white focus:outline-none focus:border-emerald-500 transition shadow-inner">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-emerald-400 mb-1">Route Status:</label>
                    <select name="route_status" class="w-full px-3.5 py-2 bg-[#061c13] border border-[#17523a] rounded-xl text-xs text-white focus:outline-none focus:border-emerald-500 transition shadow-inner cursor-pointer">
                        <?php $statuses = ['Scheduled', 'En route', 'On site', 'Complete']; ?>
                        <?php foreach($statuses as $st): ?>
                            <option value="<?php echo $st; ?>" <?php echo (($jobOrder['route_status'] ?? '') === $st) ? 'selected' : ''; ?>><?php echo $st; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- AREA FINDINGS & ACTION TAKEN SECTION -->
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-400">Area Findings & Action Taken</h3>
                    <button type="button" id="addFindingRow" class="bg-emerald-600/20 hover:bg-emerald-600/30 text-emerald-300 border border-emerald-500/30 px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1">
                        <i data-lucide="plus" class="w-3.5 h-3.5"></i> Add Area Row
                    </button>
                </div>

                <div class="overflow-x-auto rounded-xl border border-[#174e37]">
                    <table class="w-full text-left border-collapse" id="findingsTable">
                        <thead>
                            <tr class="border-b border-[#174e37] text-[10px] uppercase tracking-wider text-emerald-400 bg-[#09261b]">
                                <th class="py-3 px-4 font-bold w-1/3">Area / Target Location</th>
                                <th class="py-3 px-4 font-bold">Action Taken / Treatment Remarks</th>
                                <th class="py-3 px-4 font-bold text-center w-12"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#174e37] text-xs">
                            <?php if (empty($savedAreaFindings)): ?>
                                <tr class="finding-row">
                                    <td class="p-2.5"><input type="text" name="finding_area[]" placeholder="e.g. Kitchen / Perimeter" class="w-full px-3 py-2 bg-[#061c13] border border-[#17523a] rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500"></td>
                                    <td class="p-2.5"><input type="text" name="finding_action[]" placeholder="Residual spraying applied" class="w-full px-3 py-2 bg-[#061c13] border border-[#17523a] rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500"></td>
                                    <td class="p-2.5 text-center"><button type="button" class="remove-row text-red-400 hover:text-red-300 p-1"><i data-lucide="trash-2" class="w-4 h-4"></i></button></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($savedAreaFindings as $af): ?>
                                    <tr class="finding-row">
                                        <td class="p-2.5"><input type="text" name="finding_area[]" value="<?php echo htmlspecialchars($af['area'] ?? ''); ?>" class="w-full px-3 py-2 bg-[#061c13] border border-[#17523a] rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500"></td>
                                        <td class="p-2.5"><input type="text" name="finding_action[]" value="<?php echo htmlspecialchars($af['action_taken'] ?? ''); ?>" class="w-full px-3 py-2 bg-[#061c13] border border-[#17523a] rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500"></td>
                                        <td class="p-2.5 text-center"><button type="button" class="remove-row text-red-400 hover:text-red-300 p-1"><i data-lucide="trash-2" class="w-4 h-4"></i></button></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- MATERIALS / CHEMICALS USED SECTION (Inventory Deduction) -->
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-400">Materials / Chemicals Used (Inventory Deduction)</h3>
                        <p class="text-[10px] text-gray-400">Selecting items here will automatically deduct stock from inventory and log usage.</p>
                    </div>
                    <button type="button" id="addMaterialRow" class="bg-emerald-600/20 hover:bg-emerald-600/30 text-emerald-300 border border-emerald-500/30 px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1">
                        <i data-lucide="plus" class="w-3.5 h-3.5"></i> Add Material Row
                    </button>
                </div>

                <!-- Previously logged materials display -->
                <?php if (!empty($loggedMaterials)): ?>
                    <div class="bg-[#09261b] p-3 rounded-xl border border-[#174e37] space-y-2">
                        <p class="text-[10px] uppercase font-bold text-gray-400">Previously Logged Materials for this Job Order:</p>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach($loggedMaterials as $lm): ?>
                                <span class="px-2.5 py-1 rounded-lg text-xs bg-emerald-950 text-emerald-300 border border-emerald-800 flex items-center gap-1.5">
                                    <i data-lucide="package" class="w-3 h-3 text-emerald-400"></i>
                                    <strong><?php echo htmlspecialchars($lm['item_name']); ?>:</strong> 
                                    <?php echo floatval($lm['quantity_used']); ?> <?php echo htmlspecialchars($lm['unit_used']); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="overflow-x-auto rounded-xl border border-[#174e37]">
                    <table class="w-full text-left border-collapse" id="materialsTable">
                        <thead>
                            <tr class="border-b border-[#174e37] text-[10px] uppercase tracking-wider text-emerald-400 bg-[#09261b]">
                                <th class="py-3 px-4 font-bold">Inventory Item / Chemical</th>
                                <th class="py-3 px-4 font-bold w-40">Quantity Consumed</th>
                                <th class="py-3 px-4 font-bold text-center w-12"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#174e37] text-xs">
                            <tr class="material-row">
                                <td class="p-2.5">
                                    <select name="material_id[]" class="w-full px-3 py-2 bg-[#061c13] border border-[#17523a] rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500 cursor-pointer">
                                        <option value="">Select Inventory Item / Chemical</option>
                                        <?php foreach ($inventoryItems as $item): ?>
                                            <option value="<?php echo $item['id']; ?>">
                                                <?php echo htmlspecialchars($item['item_name']); ?> (In Stock: <?php echo floatval($item['quantity_in_stock']); ?> <?php echo htmlspecialchars($item['unit']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="p-2.5">
                                    <input type="number" step="0.01" name="material_quantity[]" placeholder="0.00" class="w-full px-3 py-2 bg-[#061c13] border border-[#17523a] rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500">
                                </td>
                                <td class="p-2.5 text-center">
                                    <button type="button" class="remove-material-row text-red-400 hover:text-red-300 p-1"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Notes & Signatures Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-emerald-400 mb-1">Notes / Dilution Rate Comments:</label>
                    <textarea name="comments" rows="4" placeholder="Dilution rates, dosage, water volume..."
                        class="w-full px-3.5 py-2.5 bg-[#09261b] border border-[#17523a] rounded-xl text-xs text-white focus:outline-none focus:border-emerald-500 transition shadow-inner resize-none"><?php echo htmlspecialchars($jobOrder['comments'] ?? ''); ?></textarea>
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-emerald-400 mb-1">Client Acknowledgment:</label>
                    <div class="bg-[#09261b] p-6 rounded-xl border border-[#17523a] text-center space-y-2 h-[104px] flex flex-col justify-center">
                        <p class="text-[11px] text-gray-400 font-medium">Authorized Signature Line</p>
                        <p class="text-xs font-bold text-emerald-300 tracking-wide">Digital verification captured on-site</p>
                    </div>
                </div>
            </div>

            <!-- Submit Action Footer -->
            <div class="flex items-center justify-end gap-4 border-t border-[#18523b] pt-5">
                <a href="dashboard.php" class="px-5 py-2.5 rounded-xl text-xs font-semibold bg-[#164a34] hover:bg-[#1f5e43] text-gray-200 transition">
                    Cancel
                </a>
                <button type="submit" class="bg-gradient-to-r from-[#007a55] to-[#006243] hover:from-[#008f63] hover:to-[#007a55] text-white px-6 py-2.5 rounded-xl text-xs font-bold transition shadow-lg shadow-emerald-950 flex items-center gap-2">
                    <i data-lucide="save" class="w-4 h-4"></i> Save Job Order
                </button>
            </div>

        </form>

    </main>

    <!-- Client-side Scripts for Dynamic Rows -->
    <script>
        lucide.createIcons();

        // Add Finding Row
        document.getElementById('addFindingRow').addEventListener('click', function() {
            const tbody = document.querySelector('#findingsTable tbody');
            const newRow = document.createElement('tr');
            newRow.className = 'finding-row';
            newRow.innerHTML = `
                <td class="p-2.5"><input type="text" name="finding_area[]" placeholder="e.g. Kitchen / Perimeter" class="w-full px-3 py-2 bg-[#061c13] border border-[#17523a] rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500"></td>
                <td class="p-2.5"><input type="text" name="finding_action[]" placeholder="Residual spraying applied" class="w-full px-3 py-2 bg-[#061c13] border border-[#17523a] rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500"></td>
                <td class="p-2.5 text-center"><button type="button" class="remove-row text-red-400 hover:text-red-300 p-1"><i data-lucide="trash-2" class="w-4 h-4"></i></button></td>
            `;
            tbody.appendChild(newRow);
            lucide.createIcons();
        });

        // Remove Finding Row
        document.addEventListener('click', function(e) {
            if (e.target.closest('.remove-row')) {
                const row = e.target.closest('.finding-row');
                const tbody = document.querySelector('#findingsTable tbody');
                if (tbody.querySelectorAll('.finding-row').length > 1) {
                    row.remove();
                } else {
                    row.querySelectorAll('input').forEach(input => input.value = '');
                }
            }
        });

        // Add Material Row
        document.getElementById('addMaterialRow').addEventListener('click', function() {
            const tbody = document.querySelector('#materialsTable tbody');
            const firstRowSelect = tbody.querySelector('select[name="material_id[]"]');
            
            const newRow = document.createElement('tr');
            newRow.className = 'material-row';
            newRow.innerHTML = `
                <td class="p-2.5">
                    <select name="material_id[]" class="w-full px-3 py-2 bg-[#061c13] border border-[#17523a] rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500 cursor-pointer">
                        ${firstRowSelect ? firstRowSelect.innerHTML : '<option value="">Select Item</option>'}
                    </select>
                </td>
                <td class="p-2.5">
                    <input type="number" step="0.01" name="material_quantity[]" placeholder="0.00" class="w-full px-3 py-2 bg-[#061c13] border border-[#17523a] rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500">
                </td>
                <td class="p-2.5 text-center">
                    <button type="button" class="remove-material-row text-red-400 hover:text-red-300 p-1"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                </td>
            `;
            tbody.appendChild(newRow);
            lucide.createIcons();
        });

        // Remove Material Row
        document.addEventListener('click', function(e) {
            if (e.target.closest('.remove-material-row')) {
                const row = e.target.closest('.material-row');
                const tbody = document.querySelector('#materialsTable tbody');
                if (tbody.querySelectorAll('.material-row').length > 1) {
                    row.remove();
                } else {
                    row.querySelector('select').value = '';
                    row.querySelector('input').value = '';
                }
            }
        });
    </script>
</body>
</html>