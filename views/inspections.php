<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$currentPage = 'inspections.php';

// Handle Form Submission / Saving Inspection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_inspection'])) {
    $clientName = trim($_POST['client_name']);
    $techName = trim($_POST['technician']);
    $date = $_POST['inspection_date'];
    $timeIn = $_POST['time_in'];
    $timeOut = $_POST['time_out'];
    
    // Devices & Remarks
    $iltQty = intval($_POST['ilt_qty']);
    $iltRemarks = trim($_POST['ilt_remarks'] ?? '');
    
    $ratCage = intval($_POST['rat_cage_qty']);
    $ratCageRemarks = trim($_POST['rat_cage_remarks'] ?? '');
    
    $ratBait = intval($_POST['rat_bait_qty']);
    $ratBaitRemarks = trim($_POST['rat_bait_remarks'] ?? '');
    
    $glueTrap = intval($_POST['glue_trap_qty']);
    $glueTrapRemarks = trim($_POST['glue_trap_remarks'] ?? '');

    // Signatures
    $vermexRep = trim($_POST['vermex_representative'] ?? 'Rodel Mamparil');
    $clientRep = trim($_POST['client_representative'] ?? '');

    // Contributing Conditions JSON
    $conditionsArray = [];
    if (isset($_POST['condition_label']) && is_array($_POST['condition_label'])) {
        for ($i = 0; $i < count($_POST['condition_label']); $i++) {
            $conditionsArray[] = [
                'label' => $_POST['condition_label'][$i],
                'status' => $_POST['condition_status'][$i] ?? '', // 'yes' or 'no'
                'area' => trim($_POST['condition_area'][$i] ?? '')
            ];
        }
    }
    $conditionsJson = json_encode($conditionsArray);

    // Findings JSON
    $findingsArray = [];
    if (isset($_POST['area']) && is_array($_POST['area'])) {
        for ($i = 0; $i < count($_POST['area']); $i++) {
            $area = trim($_POST['area'][$i]);
            $findings = trim($_POST['findings'][$i]);
            $action = trim($_POST['action_taken'][$i]);
            $remarks = trim($_POST['remarks'][$i]);

            if (!empty($area) || !empty($findings)) {
                $findingsArray[] = ['area' => $area, 'findings' => $findings, 'action_taken' => $action, 'remarks' => $remarks];
            }
        }
    }
    $findingsJson = json_encode($findingsArray);

    // Update Query (Ensuring columns exist in database)
    $stmt = $pdo->prepare("UPDATE site_inspections SET technician_name = ?, inspection_date = ?, time_in = ?, time_out = ?, ilt_qty = ?, ilt_remarks = ?, rat_cage_qty = ?, rat_cage_remarks = ?, rat_bait_qty = ?, rat_bait_remarks = ?, glue_trap_qty = ?, glue_trap_remarks = ?, vermex_representative = ?, client_representative = ?, conditions_json = ?, findings_json = ?, inspection_status = 'Inspected (Pest Found)' WHERE client_name = ?");
    $stmt->execute([$techName, $date, $timeIn, $timeOut, $iltQty, $iltRemarks, $ratCage, $ratCageRemarks, $ratBait, $ratBaitRemarks, $glueTrap, $glueTrapRemarks, $vermexRep, $clientRep, $conditionsJson, $findingsJson, $clientName]);

    header("Location: inspections.php?success=1");
    exit;
}

// Fetch filter parameters
$statusFilter = $_GET['status'] ?? '';
$typeFilter = $_GET['type'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Build dynamic query
$sql = "SELECT * FROM site_inspections WHERE 1=1";
$params = [];

if (!empty($statusFilter)) {
    $sql .= " AND inspection_status = ?";
    $params[] = $statusFilter;
}
if (!empty($typeFilter)) {
    $sql .= " AND account_type = ?";
    $params[] = $typeFilter;
}
if (!empty($searchQuery)) {
    $sql .= " AND (client_name LIKE ? OR service_address LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$inspections = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Metrics Counts
$pendingCount = $pdo->query("SELECT COUNT(*) FROM site_inspections WHERE inspection_status = 'Pending Visit'")->fetchColumn();
$completedCount = $pdo->query("SELECT COUNT(*) FROM site_inspections WHERE inspection_status LIKE 'Inspected%'")->fetchColumn();
$contractReadyCount = $pdo->query("SELECT COUNT(*) FROM site_inspections WHERE inspection_status = 'Ready for Contract'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Inspections - Vermex Pest Solutions</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="bg-[#f8faf9] text-slate-800 font-sans flex h-screen overflow-hidden">

    <!-- Sidebar Component -->
    <?php include 'components/sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col h-screen overflow-y-auto">
        <header class="bg-white border-b border-slate-200 px-8 py-3 flex items-center justify-between text-xs text-slate-500">
            <div>Operations / <span class="font-semibold text-slate-800">Site Inspections</span></div>
            <div class="flex items-center gap-2">
                <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
                <span class="font-medium text-slate-700">Operations Live (MySQL Connected)</span>
            </div>
        </header>

        <div class="p-8 space-y-6 max-w-7xl w-full mx-auto">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Pre-Contract Site Inspections</h1>
                    <p class="text-xs text-slate-500 mt-1">Manage client site visits, log pest findings, and prepare service contracts.</p>
                </div>
            </div>

            <!-- Metrics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div class="bg-white p-5 rounded-xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Pending Site Visits</p>
                        <p class="text-3xl font-bold text-slate-900 mt-1"><?= $pendingCount ?></p>
                    </div>
                    <div class="bg-amber-50 p-3 rounded-xl text-amber-600 border border-amber-100"><i data-lucide="clock" class="w-6 h-6"></i></div>
                </div>
                <div class="bg-white p-5 rounded-xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Inspections Completed</p>
                        <p class="text-3xl font-bold text-slate-900 mt-1"><?= $completedCount ?></p>
                    </div>
                    <div class="bg-emerald-50 p-3 rounded-xl text-[#007a55] border border-emerald-100"><i data-lucide="clipboard-check" class="w-6 h-6"></i></div>
                </div>
                <div class="bg-white p-5 rounded-xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Ready For Contract</p>
                        <p class="text-3xl font-bold text-slate-900 mt-1"><?= $contractReadyCount ?></p>
                    </div>
                    <div class="bg-blue-50 p-3 rounded-xl text-blue-600 border border-blue-100"><i data-lucide="file-text" class="w-6 h-6"></i></div>
                </div>
            </div>

            <!-- Pipeline Container with Database Filters -->
            <div class="bg-white border border-slate-200/80 rounded-xl p-5 space-y-4 shadow-sm">
                <form method="GET" action="inspections.php" class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-100 pb-4">
                    <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Client Inquiries & Inspection Pipeline</h2>

                    <div class="flex flex-wrap items-center gap-3">
                        <select name="status" onchange="this.form.submit()" class="bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55]">
                            <option value="">All Statuses</option>
                            <option value="Pending Visit" <?= $statusFilter === 'Pending Visit' ? 'selected' : '' ?>>Pending Visit</option>
                            <option value="Inspected (Pest Found)" <?= $statusFilter === 'Inspected (Pest Found)' ? 'selected' : '' ?>>Inspected (Pest Found)</option>
                        </select>

                        <select name="type" onchange="this.form.submit()" class="bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55]">
                            <option value="">All Account Types</option>
                            <option value="Commercial" <?= $typeFilter === 'Commercial' ? 'selected' : '' ?>>Commercial</option>
                            <option value="Residential" <?= $typeFilter === 'Residential' ? 'selected' : '' ?>>Residential</option>
                        </select>

                        <div class="relative">
                            <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5"></i>
                            <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Search client or address..." class="bg-slate-50 border border-slate-200 text-xs rounded-lg pl-9 pr-4 py-2 focus:outline-none focus:border-[#007a55] w-64">
                        </div>
                    </div>
                </form>

                <!-- Database Table Output -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-[11px] uppercase text-slate-400 border-b border-slate-100 font-semibold">
                                <th class="pb-3">Client / Company Name</th>
                                <th class="pb-3">Account Type</th>
                                <th class="pb-3">Service Address</th>
                                <th class="pb-3">Inspection Status</th>
                                <th class="pb-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700 font-medium">
                            <?php if (count($inspections) > 0): ?>
                                <?php foreach ($inspections as $row): ?>
                                    <?php
                                    // Decode JSON columns
                                    $clientFindings = !empty($row['findings_json']) ? json_decode($row['findings_json'], true) : [];
                                    $clientConditions = !empty($row['conditions_json']) ? json_decode($row['conditions_json'], true) : [];

                                    // Encode safely as JSON for JavaScript modal
                                    $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                                    $findingsJson = htmlspecialchars(json_encode($clientFindings), ENT_QUOTES, 'UTF-8');
                                    $conditionsJson = htmlspecialchars(json_encode($clientConditions), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <tr class="hover:bg-slate-50/80 transition">
                                        <td class="py-3.5">
                                            <div class="font-bold text-slate-900"><?= htmlspecialchars($row['client_name']) ?></div>
                                            <div class="text-[11px] text-slate-400 font-normal"><?= htmlspecialchars($row['client_email']) ?></div>
                                        </td>
                                        <td class="py-3.5">
                                            <span class="<?= $row['account_type'] === 'Commercial' ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200' ?> border px-2.5 py-0.5 rounded-full text-[10px] font-semibold">
                                                <?= htmlspecialchars($row['account_type']) ?>
                                            </span>
                                        </td>
                                        <td class="py-3.5 text-slate-500"><?= htmlspecialchars($row['service_address']) ?></td>
                                        <td class="py-3.5">
                                            <span class="inline-flex items-center gap-1.5 <?= $row['inspection_status'] === 'Pending Visit' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-emerald-50 text-[#007a55] border-emerald-200' ?> border px-2.5 py-0.5 rounded-full text-[10px] font-semibold">
                                                <span class="w-1.5 h-1.5 rounded-full <?= $row['inspection_status'] === 'Pending Visit' ? 'bg-amber-500' : 'bg-emerald-500' ?>"></span>
                                                <?= htmlspecialchars($row['inspection_status']) ?>
                                            </span>
                                        </td>
                                        <td class="py-3.5 text-right space-x-2">
                                            <button class="border border-slate-200 hover:bg-slate-100 text-slate-700 px-3 py-1.5 rounded-lg text-xs font-medium transition">Assign Tech</button>
                                            <button onclick="openInspectionFormModal(<?= $rowJson ?>, <?= $findingsJson ?>, <?= $conditionsJson ?>)" class="bg-[#007a55] hover:bg-[#006344] text-white px-3 py-1.5 rounded-lg text-xs font-medium transition shadow-sm">
                                                <?= $row['inspection_status'] === 'Pending Visit' ? 'Inspection Form' : 'View Report' ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="py-6 text-center text-slate-400">No inspection records found matching your filters.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- ACCURATE INSPECTION REPORT FORM MODAL -->
    <div id="inspectionModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs hidden items-center justify-center z-50 p-4 overflow-y-auto">
        <div class="bg-white rounded-xl shadow-2xl max-w-5xl w-full overflow-hidden border border-slate-200 my-8">
            <form method="POST" action="inspections.php">
                <input type="hidden" name="save_inspection" value="1">

                <!-- Modal Header -->
                <div class="bg-[#062d1f] text-white px-6 py-4 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-base">VERMEX PEST SOLUTIONS</h3>
                        <p class="text-[11px] text-emerald-300">B29 L18 P2, Deca Homes, Brgy. Indangan, Davao City | Tel: (082) 291-7089</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="printForm()" class="bg-white/10 hover:bg-white/20 text-white text-xs px-3 py-1.5 rounded-lg flex items-center gap-1.5 transition"><i data-lucide="printer" class="w-3.5 h-3.5"></i> Print</button>
                        <button type="button" onclick="downloadFormPdf()" class="bg-[#007a55] hover:bg-[#006344] text-white text-xs px-3 py-1.5 rounded-lg flex items-center gap-1.5 transition shadow-sm"><i data-lucide="download" class="w-3.5 h-3.5"></i> Download PDF</button>
                        <button type="button" onclick="closeInspectionModal()" class="text-slate-300 hover:text-white p-1 ml-2"><i data-lucide="x" class="w-5 h-5"></i></button>
                    </div>
                </div>

                <div id="printableArea" class="p-6 space-y-4 text-xs text-slate-800 bg-white">
                    <div class="text-center border-b border-slate-200 pb-2">
                        <h2 class="text-base font-bold uppercase tracking-wider text-slate-900">INSPECTION REPORT FORM</h2>
                    </div>

                    <!-- Top Meta Info -->
                    <div class="border border-slate-300 rounded-lg overflow-hidden grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-slate-300 bg-slate-50/50">
                        <div class="p-3 space-y-2">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-slate-500 w-24">ACCOUNT NAME:</span>
                                <input type="text" id="modalClientName" name="client_name" readonly class="flex-1 bg-slate-100 border border-slate-200 rounded px-2 py-1 font-bold text-slate-900 text-xs">
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-slate-500 w-24">ADDRESS:</span>
                                <input type="text" id="modalAddress" name="address" readonly class="flex-1 bg-slate-100 border border-slate-200 rounded px-2 py-1 text-slate-800 text-xs">
                            </div>
                        </div>
                        <div class="p-3 space-y-2">
                            <div class="grid grid-cols-2 gap-2">
                                <div class="flex items-center gap-1">
                                    <span class="font-semibold text-slate-500">DATE:</span>
                                    <input type="date" name="inspection_date" class="flex-1 bg-white border border-slate-200 rounded px-2 py-1 text-xs" value="<?= date('Y-m-d') ?>">
                                </div>
                                <div class="flex items-center gap-1">
                                    <span class="font-semibold text-slate-500">TECH:</span>
                                    <input type="text" name="technician" class="flex-1 bg-white border border-slate-200 rounded px-2 py-1 font-medium text-slate-800 text-xs" value="Rodel Mamparil">
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-slate-500 w-16">TIME IN:</span>
                                <input type="time" name="time_in" class="w-28 bg-white border border-slate-200 rounded px-2 py-1 text-xs" value="09:15">
                                <span class="font-semibold text-slate-500 ml-2">TIME OUT:</span>
                                <input type="time" name="time_out" class="w-28 bg-white border border-slate-200 rounded px-2 py-1 text-xs" value="10:10">
                            </div>
                        </div>
                    </div>

                    <!-- Area Findings Table (Typable & Multi-row) -->
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <label class="font-semibold text-slate-700 uppercase">Area Findings & Actions Taken</label>
                            <button type="button" onclick="addFindingRow()" class="text-[11px] bg-slate-100 hover:bg-slate-200 text-[#007a55] font-semibold px-2.5 py-1 rounded transition flex items-center gap-1">
                                <i data-lucide="plus" class="w-3 h-3"></i> Add Area Row
                            </button>
                        </div>
                        <table class="w-full border-collapse border border-slate-300 text-center text-xs">
                            <thead>
                                <tr class="bg-slate-100 text-slate-700 font-semibold border-b border-slate-300 uppercase">
                                    <th class="border-r border-slate-300 p-2 w-1/4">AREA</th>
                                    <th class="border-r border-slate-300 p-2 w-2/5">FINDINGS</th>
                                    <th class="border-r border-slate-300 p-2 w-1/5">ACTION TAKEN</th>
                                    <th class="p-2 w-1/6">REMARKS</th>
                                </tr>
                            </thead>
                            <tbody id="findingsTableBody" class="divide-y divide-slate-300 text-left">
                                <!-- Populated dynamically via JavaScript -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Two Column Section: Contributing Conditions & Devices + Signatures -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

                        <!-- Contributing Conditions Checklist -->
                        <table class="w-full border-collapse border border-slate-300 text-xs">
                            <thead>
                                <tr class="bg-slate-100 text-slate-700 font-semibold border-b border-slate-300">
                                    <th class="border-r border-slate-300 p-2 text-left">CONTRIBUTING CONDITIONS:</th>
                                    <th class="border-r border-slate-300 p-2 w-10 text-center">YES</th>
                                    <th class="border-r border-slate-300 p-2 w-10 text-center">NO</th>
                                    <th class="p-2 w-28 text-center">AREA</th>
                                </tr>
                            </thead>
                            <tbody id="conditionsTableBody" class="divide-y divide-slate-200 text-slate-700">
                                <!-- Populated dynamically via JS -->
                            </tbody>
                        </table>

                        <!-- Devices Table & Signatures -->
                        <div class="space-y-4">
                            <table class="w-full border-collapse border border-slate-300 text-center">
                                <thead>
                                    <tr class="bg-slate-100 text-slate-700 font-semibold border-b border-slate-300 uppercase">
                                        <th class="border-r border-slate-300 p-1.5 text-left pl-2">Devices</th>
                                        <th class="border-r border-slate-300 p-1.5 w-16">QTY</th>
                                        <th class="p-1.5">Remarks</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200">
                                    <tr>
                                        <td class="border-r border-slate-300 p-1.5 text-left pl-2 font-medium">ILT</td>
                                        <td class="border-r border-slate-300 p-1"><input type="number" name="ilt_qty" value="0" class="w-12 text-center border rounded text-xs"></td>
                                        <td class="p-1"><input type="text" name="ilt_remarks" class="w-full border-0 bg-transparent text-xs" placeholder="Remarks"></td>
                                    </tr>
                                    <tr>
                                        <td class="border-r border-slate-300 p-1.5 text-left pl-2 font-medium">Rat Cage</td>
                                        <td class="border-r border-slate-300 p-1"><input type="number" name="rat_cage_qty" value="1" class="w-12 text-center border rounded text-xs"></td>
                                        <td class="p-1"><input type="text" name="rat_cage_remarks" class="w-full border-0 bg-transparent text-xs" placeholder="Remarks"></td>
                                    </tr>
                                    <tr>
                                        <td class="border-r border-slate-300 p-1.5 text-left pl-2 font-medium">Rat Bait Stn</td>
                                        <td class="border-r border-slate-300 p-1"><input type="number" name="rat_bait_qty" value="0" class="w-12 text-center border rounded text-xs"></td>
                                        <td class="p-1"><input type="text" name="rat_bait_remarks" class="w-full border-0 bg-transparent text-xs" placeholder="Remarks"></td>
                                    </tr>
                                    <tr>
                                        <td class="border-r border-slate-300 p-1.5 text-left pl-2 font-medium">Glue Trap</td>
                                        <td class="border-r border-slate-300 p-1"><input type="number" name="glue_trap_qty" value="2" class="w-12 text-center border rounded text-xs"></td>
                                        <td class="p-1"><input type="text" name="glue_trap_remarks" class="w-full border-0 bg-transparent text-xs" placeholder="Remarks"></td>
                                    </tr>
                                </tbody>
                            </table>

                            <!-- Signatures Block -->
                            <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-200">
                                <div>
                                    <label class="block text-[10px] font-semibold text-slate-500 uppercase">Vermex Representative:</label>
                                    <input type="text" name="vermex_representative" class="w-full mt-2 border-b border-slate-300 border-t-0 border-x-0 bg-transparent text-xs pb-1 font-medium text-slate-800 text-center" value="Rodel Mamparil">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-semibold text-slate-500 uppercase">Client Representative:</label>
                                    <input type="text" name="client_representative" class="w-full mt-2 border-b border-slate-300 border-t-0 border-x-0 bg-transparent text-xs pb-1 font-medium text-slate-800 text-center" placeholder="Sign over printed name">
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="bg-slate-50 border-t border-slate-200 px-6 py-3 flex justify-end gap-3">
                    <button type="button" onclick="closeInspectionModal()" class="border border-slate-300 hover:bg-slate-100 text-slate-700 px-4 py-2 rounded-lg text-xs font-medium transition">Close</button>
                    <button type="submit" class="bg-[#007a55] hover:bg-[#006344] text-white px-4 py-2 rounded-lg text-xs font-semibold transition shadow-sm flex items-center gap-1.5">
                        <i data-lucide="check-circle" class="w-3.5 h-3.5"></i> Save Inspection Report & Sign-off
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();

        const defaultConditions = [
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

        function openInspectionFormModal(inspection, findings = [], conditions = []) {
            // Populate Main Info
            document.getElementById('modalClientName').value = inspection.client_name || '';
            document.getElementById('modalAddress').value = inspection.service_address || '';

            // Populate Meta & Schedule Info
            if (inspection.inspection_date) document.querySelector('input[name="inspection_date"]').value = inspection.inspection_date;
            if (inspection.technician_name) document.querySelector('input[name="technician"]').value = inspection.technician_name;
            if (inspection.time_in) document.querySelector('input[name="time_in"]').value = inspection.time_in;
            if (inspection.time_out) document.querySelector('input[name="time_out"]').value = inspection.time_out;

            // Populate Device Quantities & Remarks
            if (inspection.ilt_qty !== undefined) document.querySelector('input[name="ilt_qty"]').value = inspection.ilt_qty;
            if (inspection.ilt_remarks !== undefined) document.querySelector('input[name="ilt_remarks"]').value = inspection.ilt_remarks;

            if (inspection.rat_cage_qty !== undefined) document.querySelector('input[name="rat_cage_qty"]').value = inspection.rat_cage_qty;
            if (inspection.rat_cage_remarks !== undefined) document.querySelector('input[name="rat_cage_remarks"]').value = inspection.rat_cage_remarks;

            if (inspection.rat_bait_qty !== undefined) document.querySelector('input[name="rat_bait_qty"]').value = inspection.rat_bait_qty;
            if (inspection.rat_bait_remarks !== undefined) document.querySelector('input[name="rat_bait_remarks"]').value = inspection.rat_bait_remarks;

            if (inspection.glue_trap_qty !== undefined) document.querySelector('input[name="glue_trap_qty"]').value = inspection.glue_trap_qty;
            if (inspection.glue_trap_remarks !== undefined) document.querySelector('input[name="glue_trap_remarks"]').value = inspection.glue_trap_remarks;

            // Populate Signatures
            if (inspection.vermex_representative) document.querySelector('input[name="vermex_representative"]').value = inspection.vermex_representative;
            if (inspection.client_representative) document.querySelector('input[name="client_representative"]').value = inspection.client_representative;

            // Populate Contributing Conditions Table
            let condTbody = document.getElementById('conditionsTableBody');
            condTbody.innerHTML = '';
            defaultConditions.forEach((label, index) => {
                let existing = conditions.find(c => c.label === label) || { status: '', area: '' };
                let yesChecked = existing.status === 'yes' ? 'checked' : '';
                let noChecked = existing.status === 'no' ? 'checked' : '';

                let tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="border-r border-slate-300 p-1.5 text-[11px]">
                        <input type="hidden" name="condition_label[]" value="${label}">
                        ${label}
                    </td>
                    <td class="border-r border-slate-300 text-center"><input type="radio" name="condition_status[${index}]" value="yes" ${yesChecked}></td>
                    <td class="border-r border-slate-300 text-center"><input type="radio" name="condition_status[${index}]" value="no" ${noChecked}></td>
                    <td class="p-1"><input type="text" name="condition_area[]" value="${existing.area || ''}" class="w-full border-0 bg-transparent text-xs"></td>
                `;
                condTbody.appendChild(tr);
            });

            // Populate Findings Table
            let tbody = document.getElementById('findingsTableBody');
            tbody.innerHTML = ''; 

            if (findings && findings.length > 0) {
                findings.forEach(f => {
                    let row = document.createElement('tr');
                    row.innerHTML = `
                        <td class="border-r border-slate-300 p-1"><input type="text" name="area[]" value="${f.area || ''}" class="w-full border-0 bg-transparent p-1 focus:ring-0 text-xs"></td>
                        <td class="border-r border-slate-300 p-1"><input type="text" name="findings[]" value="${f.findings || ''}" class="w-full border-0 bg-transparent p-1 focus:ring-0 text-xs"></td>
                        <td class="border-r border-slate-300 p-1"><input type="text" name="action_taken[]" value="${f.action_taken || ''}" class="w-full border-0 bg-transparent p-1 focus:ring-0 text-xs"></td>
                        <td class="p-1 flex items-center justify-between">
                            <input type="text" name="remarks[]" value="${f.remarks || ''}" class="w-full border-0 bg-transparent p-1 focus:ring-0 text-xs">
                            <button type="button" onclick="this.closest('tr').remove()" class="text-red-400 hover:text-red-600 px-1"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            } else {
                addFindingRow();
            }

            document.getElementById('inspectionModal').classList.remove('hidden');
            document.getElementById('inspectionModal').classList.add('flex');
            lucide.createIcons();
        }

        function closeInspectionModal() {
            document.getElementById('inspectionModal').classList.remove('flex');
            document.getElementById('inspectionModal').classList.add('hidden');
        }

        function printForm() {
            let printContents = document.getElementById('printableArea').innerHTML;
            let originalContents = document.body.innerHTML;
            document.body.innerHTML = printContents;
            window.print();
            document.body.innerHTML = originalContents;
            location.reload();
        }

        function downloadFormPdf() {
            alert('Generating PDF of the Inspection Report Form...');
        }

        function addFindingRow() {
            let tbody = document.getElementById('findingsTableBody');
            let newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td class="border-r border-slate-300 p-1"><input type="text" name="area[]" placeholder="e.g. Ceiling" class="w-full border-0 bg-transparent p-1 focus:ring-0 text-xs"></td>
                <td class="border-r border-slate-300 p-1"><input type="text" name="findings[]" placeholder="Findings/Pests" class="w-full border-0 bg-transparent p-1 focus:ring-0 text-xs"></td>
                <td class="border-r border-slate-300 p-1"><input type="text" name="action_taken[]" placeholder="Action taken" class="w-full border-0 bg-transparent p-1 focus:ring-0 text-xs"></td>
                <td class="p-1 flex items-center justify-between">
                    <input type="text" name="remarks[]" placeholder="Remarks" class="w-full border-0 bg-transparent p-1 focus:ring-0 text-xs">
                    <button type="button" onclick="this.closest('tr').remove()" class="text-red-400 hover:text-red-600 px-1"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                </td>
            `;
            tbody.appendChild(newRow);
            lucide.createIcons();
        }
    </script>
</body>

</html>