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

$inspectionId = $_GET['id'] ?? null;
if (!$inspectionId || !is_numeric($inspectionId)) {
    header("Location: dashboard.php");
    exit;
}

$successMessage = '';
$errorMessage = '';

// Handle Form Submission / Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $clientName = $_POST['client_name'] ?? '';
        $serviceAddress = $_POST['service_address'] ?? '';
        $contractId = $_POST['contract_id'] ?? null;
        $inspectionDate = $_POST['inspection_date'] ?? date('Y-m-d');
        $timeIn = $_POST['time_in'] ?? null;
        $timeOut = $_POST['time_out'] ?? null;
        $techAssigned = $_POST['technician_name'] ?? $technicianName;
        $inspectionStatus = $_POST['inspection_status'] ?? 'Completed';

        // Devices
        $iltQty = intval($_POST['ilt_qty'] ?? 0);
        $iltRemarks = $_POST['ilt_remarks'] ?? '';
        $ratCageQty = intval($_POST['rat_cage_qty'] ?? 0);
        $ratCageRemarks = $_POST['rat_cage_remarks'] ?? '';
        $ratBaitQty = intval($_POST['rat_bait_qty'] ?? 0);
        $ratBaitRemarks = $_POST['rat_bait_remarks'] ?? '';
        $glueTrapQty = intval($_POST['glue_trap_qty'] ?? 0);
        $glueTrapRemarks = $_POST['glue_trap_remarks'] ?? '';

        // Signatures
        $vermexRep = $_POST['vermex_representative'] ?? $technicianName;
        $clientRep = $_POST['client_representative'] ?? '';

        // Dynamic Findings (JSON)
        $findingsRows = [];
        if (isset($_POST['finding_area']) && is_array($_POST['finding_area'])) {
            for ($i = 0; $i < count($_POST['finding_area']); $i++) {
                if (!empty(trim($_POST['finding_area'][$i])) || !empty(trim($_POST['finding_findings'][$i]))) {
                    $findingsRows[] = [
                        'area' => $_POST['finding_area'][$i] ?? '',
                        'findings' => $_POST['finding_findings'][$i] ?? '',
                        'action_taken' => $_POST['finding_action_taken'][$i] ?? '',
                        'remarks' => $_POST['finding_remarks'][$i] ?? ''
                    ];
                }
            }
        }
        $findingsJson = json_encode($findingsRows);

        // Contributing Conditions (JSON)
        $conditionsData = $_POST['condition'] ?? [];
        $conditionsJson = json_encode($conditionsData);

        $updateStmt = $pdo->prepare("
            UPDATE site_inspections SET 
                client_name = ?, 
                service_address = ?, 
                contract_id = ?, 
                inspection_date = ?, 
                time_in = ?, 
                time_out = ?, 
                technician_name = ?, 
                inspection_status = ?,
                ilt_qty = ?, 
                ilt_remarks = ?, 
                rat_cage_qty = ?, 
                rat_cage_remarks = ?, 
                rat_bait_qty = ?, 
                rat_bait_remarks = ?, 
                glue_trap_qty = ?, 
                glue_trap_remarks = ?, 
                vermex_representative = ?, 
                client_representative = ?,
                conditions_json = ?, 
                findings_json = ?
            WHERE id = ?
        ");

        $updateStmt->execute([
            $clientName, $serviceAddress, $contractId ?: null, $inspectionDate, 
            $timeIn ?: null, $timeOut ?: null, $techAssigned, $inspectionStatus,
            $iltQty, $iltRemarks, $ratCageQty, $ratCageRemarks, 
            $ratBaitQty, $ratBaitRemarks, $glueTrapQty, $glueTrapRemarks, 
            $vermexRep, $clientRep, $conditionsJson, $findingsJson, $inspectionId
        ]);

        $successMessage = "Inspection report successfully updated!";
    } catch (Exception $e) {
        $errorMessage = "Error updating report: " . $e->getMessage();
    }
}

// Fetch current inspection data
try {
    $stmt = $pdo->prepare("SELECT * FROM site_inspections WHERE id = ?");
    $stmt->execute([$inspectionId]);
    $inspection = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$inspection) {
        header("Location: dashboard.php");
        exit;
    }

    $savedFindings = json_decode($inspection['findings_json'] ?? '[]', true);
    $savedConditions = json_decode($inspection['conditions_json'] ?? '{}', true);

} catch (Exception $e) {
    header("Location: dashboard.php");
    exit;
}

// Default contributing conditions checklist items
$defaultConditions = [
    "Prolonged Open Door - Entry Point of Pests",
    "Open Garbage Bin - Attracts Rodents & Flies",
    "Litter/Dirt on Floor - Attracts Pests",
    "Food Debris on Floor - Attracts Pests",
    "Gaps on Door/Window - Entry Point",
    "Hole on Wall / Ceiling - Entry Point",
    "Poor Storage Practices - Harborage",
    "Clogged Drain / Stagnant Water",
    "Floor Drain - No Cover"
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inspection Report Form - Vermex Pest Solutions</title>
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
                <p class="text-xs text-gray-300">B29 L18 P2, Deca Homes, Brgy. Indangan, Davao City | Tel: (082) 291-7089[cite: 20]</p>
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
    <main class="flex-1 max-w-6xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

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
            
            <div class="text-center border-b border-[#18523b] pb-4">
                <h2 class="text-lg font-black tracking-wider uppercase text-white">Inspection Report Form</h2>
            </div>

            <!-- Top Header Inputs Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 bg-[#09261b] p-5 rounded-xl border border-[#164e37]">
                
                <!-- Left Column: Account Info -->
                <div class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-emerald-400 mb-1">Account Name:</label>
                        <input type="text" name="client_name" value="<?php echo htmlspecialchars($inspection['client_name'] ?? ''); ?>" required
                            class="w-full px-3.5 py-2 bg-[#061c13] border border-[#17523a] rounded-xl text-xs text-white focus:outline-none focus:border-emerald-500 transition shadow-inner">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-emerald-400 mb-1">Address:</label>
                        <textarea name="service_address" rows="2" required
                            class="w-full px-3.5 py-2 bg-[#061c13] border border-[#17523a] rounded-xl text-xs text-white focus:outline-none focus:border-emerald-500 transition shadow-inner resize-none"><?php echo htmlspecialchars($inspection['service_address'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- Right Column: Meta Info -->
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-emerald-400 mb-1">Contract ID:</label>
                            <input type="number" name="contract_id" value="<?php echo htmlspecialchars($inspection['contract_id'] ?? ''); ?>"
                                class="w-full px-3.5 py-2 bg-[#061c13] border border-[#17523a] rounded-xl text-xs text-white focus:outline-none focus:border-emerald-500 transition shadow-inner">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-emerald-400 mb-1">Date:</label>
                            <input type="date" name="inspection_date" value="<?php echo htmlspecialchars($inspection['inspection_date'] ?? date('Y-m-d')); ?>" required
                                class="w-full px-3.5 py-2 bg-[#061c13] border border-[#17523a] rounded-xl text-xs text-white focus:outline-none focus:border-emerald-500 transition shadow-inner">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-emerald-400 mb-1">Technician:</label>
                        <input type="text" name="technician_name" value="<?php echo htmlspecialchars($inspection['technician_name'] ?? $technicianName); ?>" required
                            class="w-full px-3.5 py-2 bg-[#061c13] border border-[#17523a] rounded-xl text-xs text-white focus:outline-none focus:border-emerald-500 transition shadow-inner">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-emerald-400 mb-1">Time In:</label>
                            <input type="time" name="time_in" value="<?php echo htmlspecialchars($inspection['time_in'] ?? ''); ?>"
                                class="w-full px-3.5 py-2 bg-[#061c13] border border-[#17523a] rounded-xl text-xs text-white focus:outline-none focus:border-emerald-500 transition shadow-inner">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-emerald-400 mb-1">Time Out:</label>
                            <input type="time" name="time_out" value="<?php echo htmlspecialchars($inspection['time_out'] ?? ''); ?>"
                                class="w-full px-3.5 py-2 bg-[#061c13] border border-[#17523a] rounded-xl text-xs text-white focus:outline-none focus:border-emerald-500 transition shadow-inner">
                        </div>
                    </div>
                    
                    <input type="hidden" name="inspection_status" value="Completed">
                </div>
            </div>

            <!-- AREA FINDINGS & ACTIONS TAKEN SECTION -->
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-400">Area Findings & Actions Taken</h3>
                    <button type="button" id="addFindingRow" class="bg-emerald-600/20 hover:bg-emerald-600/30 text-emerald-300 border border-emerald-500/30 px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1">
                        <i data-lucide="plus" class="w-3.5 h-3.5"></i> Add Area Row
                    </button>
                </div>

                <div class="overflow-x-auto rounded-xl border border-[#174e37]">
                    <table class="w-full text-left border-collapse" id="findingsTable">
                        <thead>
                            <tr class="border-b border-[#174e37] text-[10px] uppercase tracking-wider text-emerald-400 bg-[#09261b]">
                                <th class="py-3 px-3 font-bold w-1/4">Area</th>
                                <th class="py-3 px-3 font-bold w-1/3">Findings</th>
                                <th class="py-3 px-3 font-bold w-1/4">Action Taken</th>
                                <th class="py-3 px-3 font-bold">Remarks</th>
                                <th class="py-3 px-3 font-bold text-center w-12"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#174e37] text-xs">
                            <?php if (empty($savedFindings)): ?>
                                <tr class="finding-row">
                                    <td class="p-2"><input type="text" name="finding_area[]" placeholder="e.g. Ceiling" class="w-full px-3 py-2 bg-[#061c13] border border-[#17523a] rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500"></td>
                                    <td class="p-2"><input type="text" name="finding_findings[]" placeholder="Findings/Pests" class="w-full px-3 py-2 bg-[#061c13] border border-[#17523a] rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500"></td>
                                    <td class="p-2"><input type="text" name="finding_action_taken[]" placeholder="Action taken" class="w-full px-3 py-2 bg-[#061c13] border border-[#17523a] rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500"></td>
                                    <td class="p-2"><input type="text" name="finding_remarks[]" placeholder="Remarks" class="w-full px-3 py-2 bg-[#061c13] border border-[#17523a] rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500"></td>
                                    <td class="p-2 text-center"><button type="button" class="remove-row text-red-400 hover:text-red-300 p-1"><i data-lucide="trash-2" class="w-4 h-4"></i></button></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($savedFindings as $f): ?>
                                    <tr class="finding-row">
                                        <td class="p-2"><input type="text" name="finding_area[]" value="<?php echo htmlspecialchars($f['area'] ?? ''); ?>" class="w-full px-3 py-2 bg-[#061c13] border border-[#17523a] rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500"></td>
                                        <td class="p-2"><input type="text" name="finding_findings[]" value="<?php echo htmlspecialchars($f['findings'] ?? ''); ?>" class="w-full px-3 py-2 bg-[#061c13] border border-[#17523a] rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500"></td>
                                        <td class="p-2"><input type="text" name="finding_action_taken[]" value="<?php echo htmlspecialchars($f['action_taken'] ?? ''); ?>" class="w-full px-3 py-2 bg-[#061c13] border border-[#17523a] rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500"></td>
                                        <td class="p-2"><input type="text" name="finding_remarks[]" value="<?php echo htmlspecialchars($f['remarks'] ?? ''); ?>" class="w-full px-3 py-2 bg-[#061c13] border border-[#17523a] rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500"></td>
                                        <td class="p-2 text-center"><button type="button" class="remove-row text-red-400 hover:text-red-300 p-1"><i data-lucide="trash-2" class="w-4 h-4"></i></button></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- BOTTOM DUAL SECTIONS: CONTRIBUTING CONDITIONS & DEVICES -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                
                <!-- Contributing Conditions Checklist -->
                <div class="bg-[#09261b] p-5 rounded-xl border border-[#174e37] space-y-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-400 border-b border-[#174e37] pb-2">Contributing Conditions</h3>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="text-[10px] text-gray-400 uppercase border-b border-[#174e37] pb-1">
                                    <th class="pb-2 font-medium">Condition</th>
                                    <th class="pb-2 text-center w-12 font-medium">Yes</th>
                                    <th class="pb-2 text-center w-12 font-medium">No</th>
                                    <th class="pb-2 w-28 font-medium">Area</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#174e37]">
                                <?php foreach ($defaultConditions as $idx => $cond): ?>
                                    <?php 
                                        $val = $savedConditions[$cond] ?? ['ans' => '', 'area' => ''];
                                        $ans = $val['ans'] ?? '';
                                        $areaVal = $val['area'] ?? '';
                                    ?>
                                    <tr>
                                        <td class="py-2.5 pr-2 text-gray-300 text-[11px]"><?php echo htmlspecialchars($cond); ?></td>
                                        <td class="py-2.5 text-center">
                                            <input type="radio" name="condition[<?php echo htmlspecialchars($cond); ?>][ans]" value="Yes" <?php echo ($ans === 'Yes') ? 'checked' : ''; ?> class="accent-emerald-500 cursor-pointer">
                                        </td>
                                        <td class="py-2.5 text-center">
                                            <input type="radio" name="condition[<?php echo htmlspecialchars($cond); ?>][ans]" value="No" <?php echo ($ans === 'No') ? 'checked' : ''; ?> class="accent-emerald-500 cursor-pointer">
                                        </td>
                                        <td class="py-2.5 pl-2">
                                            <input type="text" name="condition[<?php echo htmlspecialchars($cond); ?>][area]" value="<?php echo htmlspecialchars($areaVal); ?>" placeholder="Area" class="w-full px-2 py-1 bg-[#061c13] border border-[#17523a] rounded text-[11px] text-white focus:outline-none focus:border-emerald-500">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Devices & Signatures Section -->
                <div class="space-y-6 flex flex-col justify-between">
                    
                    <!-- Devices Table -->
                    <div class="bg-[#09261b] p-5 rounded-xl border border-[#174e37] space-y-4">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-400 border-b border-[#174e37] pb-2">Devices Installed / Monitored</h3>
                        
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                               <thead>
                                    <tr class="text-[10px] text-gray-400 uppercase border-b border-[#174e37]">
                                        <th class="pb-2 font-medium w-1/3">Devices</th>
                                        <th class="pb-2 font-medium w-16">QTY</th>
                                        <th class="pb-2 font-medium">Remarks</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#174e37]">
                                    <!-- ILT -->
                                    <tr>
                                        <td class="py-2.5 font-semibold text-gray-300">ILT</td>
                                        <td class="py-2.5">
                                            <input type="number" name="ilt_qty" value="<?php echo intval($inspection['ilt_qty'] ?? 0); ?>" class="w-16 px-2 py-1 bg-[#061c13] border border-[#17523a] rounded text-xs text-white text-center focus:outline-none focus:border-emerald-500">
                                        </td>
                                        <td class="py-2.5 pl-2">
                                            <input type="text" name="ilt_remarks" value="<?php echo htmlspecialchars($inspection['ilt_remarks'] ?? ''); ?>" placeholder="Remarks" class="w-full px-2.5 py-1 bg-[#061c13] border border-[#17523a] rounded text-xs text-white focus:outline-none focus:border-emerald-500">
                                        </td>
                                    </tr>
                                    <!-- Rat Cage -->
                                    <tr>
                                        <td class="py-2.5 font-semibold text-gray-300">Rat Cage</td>
                                        <td class="py-2.5">
                                            <input type="number" name="rat_cage_qty" value="<?php echo intval($inspection['rat_cage_qty'] ?? 0); ?>" class="w-16 px-2 py-1 bg-[#061c13] border border-[#17523a] rounded text-xs text-white text-center focus:outline-none focus:border-emerald-500">
                                        </td>
                                        <td class="py-2.5 pl-2">
                                            <input type="text" name="rat_cage_remarks" value="<?php echo htmlspecialchars($inspection['rat_cage_remarks'] ?? ''); ?>" placeholder="Remarks" class="w-full px-2.5 py-1 bg-[#061c13] border border-[#17523a] rounded text-xs text-white focus:outline-none focus:border-emerald-500">
                                        </td>
                                    </tr>
                                    <!-- Rat Bait Stn -->
                                    <tr>
                                        <td class="py-2.5 font-semibold text-gray-300">Rat Bait Stn</td>
                                        <td class="py-2.5">
                                            <input type="number" name="rat_bait_qty" value="<?php echo intval($inspection['rat_bait_qty'] ?? 0); ?>" class="w-16 px-2 py-1 bg-[#061c13] border border-[#17523a] rounded text-xs text-white text-center focus:outline-none focus:border-emerald-500">
                                        </td>
                                        <td class="py-2.5 pl-2">
                                            <input type="text" name="rat_bait_remarks" value="<?php echo htmlspecialchars($inspection['rat_bait_remarks'] ?? ''); ?>" placeholder="Remarks" class="w-full px-2.5 py-1 bg-[#061c13] border border-[#17523a] rounded text-xs text-white focus:outline-none focus:border-emerald-500">
                                        </td>
                                    </tr>
                                    <!-- Glue Trap -->
                                    <tr>
                                        <td class="py-2.5 font-semibold text-gray-300">Glue Trap</td>
                                        <td class="py-2.5">
                                            <input type="number" name="glue_trap_qty" value="<?php echo intval($inspection['glue_trap_qty'] ?? 0); ?>" class="w-16 px-2 py-1 bg-[#061c13] border border-[#17523a] rounded text-xs text-white text-center focus:outline-none focus:border-emerald-500">
                                        </td>
                                        <td class="py-2.5 pl-2">
                                            <input type="text" name="glue_trap_remarks" value="<?php echo htmlspecialchars($inspection['glue_trap_remarks'] ?? ''); ?>" placeholder="Remarks" class="w-full px-2.5 py-1 bg-[#061c13] border border-[#17523a] rounded text-xs text-white focus:outline-none focus:border-emerald-500">
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Signatures Box -->
                    <div class="bg-[#09261b] p-5 rounded-xl border border-[#174e37] grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-emerald-400 mb-1">Vermex Representative:</label>
                            <input type="text" name="vermex_representative" value="<?php echo htmlspecialchars($inspection['vermex_representative'] ?? $technicianName); ?>"
                                class="w-full px-3 py-2 bg-[#061c13] border border-[#17523a] rounded-xl text-xs text-white text-center font-semibold focus:outline-none focus:border-emerald-500 shadow-inner">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-emerald-400 mb-1">Client Representative:</label>
                            <input type="text" name="client_representative" value="<?php echo htmlspecialchars($inspection['client_representative'] ?? ''); ?>" placeholder="Sign over printed name"
                                class="w-full px-3 py-2 bg-[#061c13] border border-[#17523a] rounded-xl text-xs text-white text-center focus:outline-none focus:border-emerald-500 shadow-inner">
                        </div>
                    </div>

                </div>

            </div>

            <!-- Submit Action Footer -->
            <div class="flex items-center justify-end gap-4 border-t border-[#18523b] pt-5">
                <a href="dashboard.php" class="px-5 py-2.5 rounded-xl text-xs font-semibold bg-[#164a34] hover:bg-[#1f5e43] text-gray-200 transition">
                    Close
                </a>
                <button type="submit" class="bg-gradient-to-r from-[#007a55] to-[#006243] hover:from-[#008f63] hover:to-[#007a55] text-white px-6 py-2.5 rounded-xl text-xs font-bold transition shadow-lg shadow-emerald-950 flex items-center gap-2">
                    <i data-lucide="save" class="w-4 h-4"></i> Save Inspection Report & Sign-off
                </button>
            </div>

        </form>

    </main>

    <!-- Client-side Scripts for Dynamic Rows -->
    <script>
        lucide.createIcons();

        document.getElementById('addFindingRow').addEventListener('click', function() {
            const tbody = document.querySelector('#findingsTable tbody');
            const newRow = document.createElement('tr');
            newRow.className = 'finding-row';
            newRow.innerHTML = `
                <td class="p-2"><input type="text" name="finding_area[]" placeholder="e.g. Ceiling" class="w-full px-3 py-2 bg-[#061c13] border border-[#17523a] rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500"></td>
                <td class="p-2"><input type="text" name="finding_findings[]" placeholder="Findings/Pests" class="w-full px-3 py-2 bg-[#061c13] border border-[#17523a] rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500"></td>
                <td class="p-2"><input type="text" name="finding_action_taken[]" placeholder="Action taken" class="w-full px-3 py-2 bg-[#061c13] border border-[#17523a] rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500"></td>
                <td class="p-2"><input type="text" name="finding_remarks[]" placeholder="Remarks" class="w-full px-3 py-2 bg-[#061c13] border border-[#17523a] rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500"></td>
                <td class="p-2 text-center"><button type="button" class="remove-row text-red-400 hover:text-red-300 p-1"><i data-lucide="trash-2" class="w-4 h-4"></i></button></td>
            `;
            tbody.appendChild(newRow);
            lucide.createIcons();
        });

        document.addEventListener('click', function(e) {
            if (e.target.closest('.remove-row')) {
                const row = e.target.closest('.finding-row');
                const tbody = document.querySelector('#findingsTable tbody');
                if (tbody.querySelectorAll('.finding-row').length > 1) {
                    row.remove();
                } else {
                    // Clear inputs instead of removing the last remaining row
                    row.querySelectorAll('input').forEach(input => input.value = '');
                }
            }
        });
    </script>
</body>
</html>