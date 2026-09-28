<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/db.php';

$userFullName = $_SESSION['full_name'] ?? 'Operations Manager';
$userRole     = $_SESSION['role'] ?? 'Admin';

// Handle Form Submission for Creating / Updating Job Orders
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_job_order') {
    $jobId         = !empty($_POST['job_id']) ? $_POST['job_id'] : null;
    $clientId      = !empty($_POST['client_id']) ? $_POST['client_id'] : null;
    $contractId    = !empty($_POST['contract_id']) ? $_POST['contract_id'] : null;
    $clientName    = trim($_POST['client_name'] ?? '');
    $location      = trim($_POST['location'] ?? '');
    $serviceType   = trim($_POST['service_type'] ?? '');
    $assignedTech  = trim($_POST['assigned_tech'] ?? '');
    $scheduledDate = !empty($_POST['scheduled_date']) ? $_POST['scheduled_date'] : date('Y-m-d');
    $serviceWindow = trim($_POST['service_window'] ?? '');
    $priority      = $_POST['priority'] ?? 'Standard';
    $routeStatus   = $_POST['route_status'] ?? 'Scheduled';
    
    // Additional Job Order Report fields
    $timeIn   = !empty($_POST['time_in']) ? trim($_POST['time_in']) : null;
    $timeOut  = !empty($_POST['time_out']) ? trim($_POST['time_out']) : null;
    $comments = trim($_POST['comments'] ?? '');

    // Process dynamic Area Findings & Actions
    $areaNames    = $_POST['area_name'] ?? [];
    $areaActions  = $_POST['area_action'] ?? [];
    $areaFindings = [];

    for ($i = 0; $i < count($areaNames); $i++) {
        $aName   = trim($areaNames[$i] ?? '');
        $aAction = trim($areaActions[$i] ?? '');
        if ($aName !== '') {
            $areaFindings[] = [
                'area'   => $aName,
                'action' => $aAction
            ];
        }
    }
    $areaFindingsJson = json_encode($areaFindings);

    if ($jobId) {
        $stmt = $pdo->prepare("UPDATE job_orders SET 
            client_id = ?, 
            contract_id = ?, 
            client_name = ?, 
            location = ?, 
            service_type = ?, 
            assigned_tech = ?, 
            scheduled_date = ?, 
            service_window = ?, 
            priority = ?, 
            route_status = ?, 
            time_in = ?, 
            time_out = ?, 
            comments = ?, 
            area_findings = ? 
            WHERE id = ?");
        $stmt->execute([
            $clientId, $contractId, $clientName, $location, $serviceType, 
            $assignedTech, $scheduledDate, $serviceWindow, $priority, 
            $routeStatus, $timeIn, $timeOut, $comments, $areaFindingsJson, $jobId
        ]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO job_orders (
            client_id, contract_id, client_name, location, service_type, 
            assigned_tech, scheduled_date, service_window, priority, 
            route_status, time_in, time_out, comments, area_findings
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $clientId, $contractId, $clientName, $location, $serviceType, 
            $assignedTech, $scheduledDate, $serviceWindow, $priority, 
            $routeStatus, $timeIn, $timeOut, $comments, $areaFindingsJson
        ]);
    }
    header("Location: dispatch.php?date=" . urlencode($scheduledDate));
    exit();
}

// Fetch active contract job orders for today or selected date from database
$filterDate  = $_GET['date'] ?? date('Y-m-d');
$searchQuery = trim($_GET['search'] ?? '');

$sql    = "SELECT * FROM job_orders WHERE scheduled_date = ?";
$params = [$filterDate];

if (!empty($searchQuery)) {
    $sql .= " AND (client_name LIKE ? OR location LIKE ? OR service_type LIKE ? OR assigned_tech LIKE ?)";
    $searchTerm = "%$searchQuery%";
    array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
}
$sql .= " ORDER BY service_window ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate KPI Metrics for selected date
$totalJobsCount  = count($jobs);
$inProgressCount = 0;
$completedCount  = 0;
$scheduledCount  = 0;

foreach ($jobs as $j) {
    $st = strtolower($j['route_status'] ?? '');
    if ($st === 'completed') {
        $completedCount++;
    } elseif ($st === 'en route' || $st === 'on site') {
        $inProgressCount++;
    } else {
        $scheduledCount++;
    }
}

// Check for incoming prefill parameters from clients redirect
$prefilledClient   = trim($_GET['client'] ?? $_GET['client_name'] ?? '');
$prefilledAddress  = trim($_GET['address'] ?? $_GET['location'] ?? '');
$prefilledClientId = trim($_GET['client_id'] ?? '');

// Fetch ACTIVE contracted clients strictly by joining the contracts table
try {
    $clientsStmt = $pdo->query("
        SELECT DISTINCT c.*, con.id AS contract_id, con.contract_status 
        FROM clients c
        INNER JOIN contracts con ON c.id = con.client_id
        WHERE LOWER(REPLACE(con.contract_status, '-', '_')) = 'active'
        ORDER BY c.client_name ASC
    ");
    $clients = $clientsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    try {
        $clientsStmt = $pdo->query("
            SELECT * FROM clients 
            WHERE LOWER(REPLACE(COALESCE(contract_status, ''), '-', '_')) = 'active'
              AND LOWER(REPLACE(COALESCE(contract_status, ''), '-', '_')) != 'to_be_contracted'
            ORDER BY client_name ASC
        ");
        $clients = $clientsStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e2) {
        $clients = [];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Order Dispatch - Vermex Pest Solutions</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-gray-100 font-sans antialiased text-gray-800 flex h-screen overflow-hidden">

    <!-- SIDEBAR -->
    <?php include 'components/sidebar.php'; ?>

    <!-- MAIN CONTENT AREA -->
    <main class="flex-1 flex flex-col overflow-y-auto">

        <!-- Header Bar -->
        <header class="bg-white border-b border-gray-200 px-8 py-3.5 flex justify-between items-center sticky top-0 z-10">
            <div class="flex items-center gap-2 text-xs text-gray-500">
                <span>Operations</span>
                <span>/</span>
                <span class="font-medium text-gray-800">Job Order Dispatch</span>
            </div>
            <div class="flex items-center gap-4">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-700 text-xs font-semibold rounded-full border border-emerald-200">
                    <span class="w-2 h-2 bg-emerald-500 rounded-full animate-ping"></span>
                    Live Dispatch Board
                </span>
            </div>
        </header>

        <div class="p-8 max-w-7xl w-full mx-auto space-y-6">

            <!-- Title & Action Bar -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Contract Job Orders</h2>
                    <p class="text-xs text-gray-500 mt-1">Dispatch and track field technicians for active contract clients.</p>
                </div>
                <button onclick="openScheduleModal()" class="bg-emerald-700 hover:bg-emerald-800 text-white px-4 py-2 rounded-lg text-xs font-semibold flex items-center gap-2 shadow-sm transition">
                    <i data-lucide="plus" class="w-4 h-4"></i> Schedule Job Order
                </button>
            </div>

            <!-- Operations Executive KPI Metric Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Scheduled Today</p>
                        <h3 class="text-2xl font-bold text-gray-900 mt-1"><?= $totalJobsCount ?></h3>
                    </div>
                    <div class="p-3 bg-emerald-50 text-emerald-700 rounded-xl">
                        <i data-lucide="calendar" class="w-6 h-6"></i>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">In Progress / On Site</p>
                        <h3 class="text-2xl font-bold text-blue-600 mt-1"><?= $inProgressCount ?></h3>
                    </div>
                    <div class="p-3 bg-blue-50 text-blue-600 rounded-xl">
                        <i data-lucide="truck" class="w-6 h-6"></i>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Completed Jobs</p>
                        <h3 class="text-2xl font-bold text-emerald-600 mt-1"><?= $completedCount ?></h3>
                    </div>
                    <div class="p-3 bg-emerald-50 text-emerald-600 rounded-xl">
                        <i data-lucide="check-circle-2" class="w-6 h-6"></i>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Pending Route</p>
                        <h3 class="text-2xl font-bold text-amber-600 mt-1"><?= $scheduledCount ?></h3>
                    </div>
                    <div class="p-3 bg-amber-50 text-amber-600 rounded-xl">
                        <i data-lucide="clock" class="w-6 h-6"></i>
                    </div>
                </div>
            </div>

            <!-- Schedule & Filter Bar -->
            <div class="flex flex-wrap items-center justify-between bg-white p-3.5 rounded-xl border border-gray-200 shadow-sm gap-4">
                <form method="GET" class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-semibold text-gray-600 flex items-center gap-1.5">
                            <i data-lucide="calendar" class="w-4 h-4 text-emerald-600"></i> Date:
                        </label>
                        <input type="date" name="date" value="<?= htmlspecialchars($filterDate) ?>" onchange="this.form.submit()" class="border border-gray-300 rounded-lg text-xs px-3 py-1.5 focus:ring-2 focus:ring-emerald-500 bg-white">
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Search client, tech, or service..." class="border border-gray-300 rounded-lg text-xs px-3 py-1.5 w-60 focus:ring-2 focus:ring-emerald-500">
                        <button type="submit" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs px-3 py-1.5 rounded-lg font-medium border border-gray-300">Filter</button>
                    </div>
                </form>
                <div class="text-xs text-gray-500">
                    Total Scheduled for Date: <span class="font-bold text-gray-900"><?= count($jobs) ?></span>
                </div>
            </div>

            <!-- Job Orders Table -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-[10px] font-bold uppercase text-gray-500 tracking-wider">
                                <th class="py-3 px-4">Service Window</th>
                                <th class="py-3 px-4">Account Name & Address</th>
                                <th class="py-3 px-4">Treatment / Service</th>
                                <th class="py-3 px-4">Assigned Personnel</th>
                                <th class="py-3 px-4">Priority</th>
                                <th class="py-3 px-4">Route Status</th>
                                <th class="py-3 px-4 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-xs">
                            <?php if (empty($jobs)): ?>
                                <tr>
                                    <td colspan="7" class="py-10 text-center text-gray-400">
                                        <i data-lucide="calendar-x" class="w-8 h-8 mx-auto mb-2 text-gray-300"></i>
                                        No active job orders scheduled for this date.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($jobs as $job): ?>
                                    <tr class="hover:bg-gray-50/80 transition">
                                        <td class="py-3.5 px-4 font-bold text-gray-800 whitespace-nowrap">
                                            <?= htmlspecialchars($job['service_window']) ?>
                                        </td>
                                        <td class="py-3.5 px-4">
                                            <div class="font-bold text-gray-900"><?= htmlspecialchars($job['client_name']) ?></div>
                                            <div class="text-gray-400 text-[10px] truncate max-w-[220px]"><?= htmlspecialchars($job['location']) ?></div>
                                        </td>
                                        <td class="py-3.5 px-4 text-gray-700 font-medium">
                                            <?= htmlspecialchars($job['service_type']) ?>
                                        </td>
                                        <td class="py-3.5 px-4 text-gray-800 font-medium whitespace-nowrap">
                                            <div class="flex items-center gap-1.5">
                                                <i data-lucide="user-check" class="w-3.5 h-3.5 text-emerald-600"></i>
                                                <span><?= htmlspecialchars($job['assigned_tech'] ?: 'Unassigned') ?></span>
                                            </div>
                                        </td>
                                        <td class="py-3.5 px-4 whitespace-nowrap">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold 
                                                <?= $job['priority'] === 'Urgent' ? 'bg-red-100 text-red-700 border border-red-200' : ($job['priority'] === 'High' ? 'bg-amber-100 text-amber-800 border border-amber-200' : 'bg-emerald-100 text-emerald-800 border border-emerald-200') ?>">
                                                <?= htmlspecialchars($job['priority']) ?>
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-4 whitespace-nowrap">
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold border 
                                                <?= $job['route_status'] === 'En route' ? 'bg-blue-50 text-blue-700 border-blue-200' : ($job['route_status'] === 'On site' ? 'bg-amber-50 text-amber-700 border-amber-200' : ($job['route_status'] === 'Completed' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-gray-100 text-gray-700 border-gray-200')) ?>">
                                                <?= htmlspecialchars($job['route_status']) ?>
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-4 text-center">
                                            <button onclick='openEditModal(<?= json_encode($job, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' class="px-3 py-1 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200 rounded font-semibold transition text-xs inline-flex items-center gap-1">
                                                <i data-lucide="file-edit" class="w-3.5 h-3.5"></i> Edit Form
                                            </button>
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

    <!-- JOB ORDER EXECUTION & SCHEDULING MODAL -->
    <div id="jobModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full overflow-hidden max-h-[90vh] flex flex-col">
            
            <div class="bg-[#0b2219] text-white px-6 py-4 flex justify-between items-center">
                <div>
                    <h3 class="font-bold text-base">Job Order Execution Form</h3>
                    <p class="text-[11px] text-emerald-300">Vermex Pest Solutions — Field Dispatch & Service Report</p>
                </div>
                <button type="button" onclick="closeModal()" class="text-gray-300 hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <form method="POST" class="p-6 overflow-y-auto space-y-6 flex-1 text-xs">
                <input type="hidden" name="action" value="save_job_order">
                <input type="hidden" name="job_id" id="modal_job_id">
                <input type="hidden" name="client_id" id="modal_client_id">
                <input type="hidden" name="contract_id" id="modal_contract_id">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Contract Client Account</label>
                        <select name="client_name" id="modal_client_name" onchange="updateClientData(this)" class="w-full border border-gray-300 rounded-lg p-2 font-medium bg-white" required>
                            <option value="">Select Active Client Account</option>
                            <?php foreach ($clients as $c): 
                                $cName      = $c['client_name'] ?? $c['company_name'] ?? $c['name'] ?? 'Client';
                                $cId        = $c['id'] ?? '';
                                $contractId = $c['contract_id'] ?? '';
                                
                                $street = (!empty($c['street_address']) && $c['street_address'] !== 'NULL') ? $c['street_address'] . ', ' : '';
                                $brgy   = (!empty($c['barangay']) && $c['barangay'] !== 'NULL') ? $c['barangay'] . ', ' : '';
                                $city   = (!empty($c['city']) && $c['city'] !== 'NULL') ? $c['city'] : '';
                                
                                $fullAddr = trim(trim("$street$brgy$city"), ',');
                                if (empty($fullAddr) && !empty($c['property_address'])) {
                                    $fullAddr = $c['property_address'];
                                }
                                if (empty($fullAddr) && !empty($c['address'])) {
                                    $fullAddr = $c['address'];
                                }
                            ?>
                                <option value="<?= htmlspecialchars($cName) ?>" 
                                        data-id="<?= htmlspecialchars($cId) ?>"
                                        data-contract-id="<?= htmlspecialchars($contractId) ?>"
                                        data-address="<?= htmlspecialchars($fullAddr) ?>">
                                    <?= htmlspecialchars($cName) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="location" id="modal_location" placeholder="Service Address Location" class="w-full mt-2 border border-gray-300 rounded-lg p-2 bg-white" required>
                    </div>

                    <div class="space-y-2">
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Scheduled Date</label>
                                <input type="date" name="scheduled_date" id="modal_scheduled_date" class="w-full border border-gray-300 rounded-lg p-2 bg-white" required>
                            </div>
                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Service Window</label>
                                <input type="text" name="service_window" id="modal_service_window" placeholder="08:00–09:30" class="w-full border border-gray-300 rounded-lg p-2 bg-white" required>
                            </div>
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">Assigned Personnel / Field Tech</label>
                            <select name="assigned_tech" id="modal_assigned_tech" class="w-full border border-gray-300 rounded-lg p-2 font-medium bg-white" required>
                                <option value="">Loading technicians...</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="md:col-span-1">
                        <label class="block font-bold text-gray-700 mb-1">Treatment / Service Type</label>
                        <input type="text" name="service_type" id="modal_service_type" placeholder="e.g. General Pest Control" class="w-full border border-gray-300 rounded-lg p-2 bg-white" required>
                    </div>
                    <div class="md:col-span-1">
                        <label class="block font-bold text-gray-700 mb-1">Time In / Time Out</label>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="text" name="time_in" id="modal_time_in" placeholder="08:10 AM" class="border border-gray-300 rounded-lg p-2 bg-white">
                            <input type="text" name="time_out" id="modal_time_out" placeholder="10:15 AM" class="border border-gray-300 rounded-lg p-2 bg-white">
                        </div>
                    </div>
                    <div class="md:col-span-1">
                        <label class="block font-bold text-gray-700 mb-1">Priority</label>
                        <select name="priority" id="modal_priority" class="w-full border border-gray-300 rounded-lg p-2 font-medium bg-white">
                            <option value="Standard">Standard</option>
                            <option value="High">High</option>
                            <option value="Urgent">Urgent</option>
                        </select>
                    </div>
                    <div class="md:col-span-1">
                        <label class="block font-bold text-gray-700 mb-1">Route Status</label>
                        <select name="route_status" id="modal_route_status" class="w-full border border-gray-300 rounded-lg p-2 font-medium bg-white">
                            <option value="Scheduled">Scheduled</option>
                            <option value="En route">En route</option>
                            <option value="On site">On site</option>
                            <option value="Completed">Completed</option>
                            <option value="Delayed">Delayed</option>
                        </select>
                    </div>
                </div>

                <!-- DYNAMIC AREA FINDINGS TABLE -->
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label class="font-bold text-gray-700">Area Findings & Action Taken</label>
                        <button type="button" onclick="addAreaRow()" class="text-emerald-700 font-bold hover:underline text-[11px] flex items-center gap-1">
                            <i data-lucide="plus" class="w-3.5 h-3.5"></i> Add Area Row
                        </button>
                    </div>
                    <table class="w-full border border-gray-200 rounded-lg overflow-hidden" id="areaTable">
                        <thead class="bg-gray-50 text-[10px] text-gray-500 uppercase">
                            <tr>
                                <th class="p-2 border-b text-left">Area / Target Location</th>
                                <th class="p-2 border-b text-left">Action Taken / Treatment Remarks</th>
                                <th class="p-2 border-b w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <!-- Dynamic rows appended via JS -->
                        </tbody>
                    </table>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Chemicals & Dilution Rate</label>
                        <textarea name="comments" id="modal_comments" rows="3" placeholder="Chemicals applied, dilution rates, dosage, water volume..." class="w-full border border-gray-300 rounded-lg p-2 bg-white"></textarea>
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Client Acknowledgment</label>
                        <div class="border border-dashed border-gray-300 rounded-lg p-4 text-center text-gray-400 bg-gray-50">
                            <span class="block text-[11px]">Authorized Signature Line</span>
                            <span class="font-semibold text-gray-600">Digital verification captured on-site</span>
                        </div>
                    </div>
                </div>

                <div class="border-t pt-4 flex justify-end gap-2">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-semibold">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-emerald-700 hover:bg-emerald-800 text-white rounded-lg font-semibold shadow-sm">Save Job Order</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function fetchTechnicians(selectedTech = '') {
            const select = document.getElementById('modal_assigned_tech');
            select.innerHTML = '<option value="">Loading technicians...</option>';

            fetch('../controllers/getTechnicians.php')
                .then(response => response.json())
                .then(res => {
                    if (res.success && res.data.length > 0) {
                        let options = '<option value="">Select Field Technician</option>';
                        res.data.forEach(tech => {
                            const name = tech.technician_name || tech.full_name || tech.name;
                            const isSelected = (name === selectedTech) ? 'selected' : '';
                            options += `<option value="${escapeHtml(name)}" ${isSelected}>${escapeHtml(name)}</option>`;
                        });
                        select.innerHTML = options;
                    } else {
                        select.innerHTML = '<option value="">No field technicians found</option>';
                    }
                })
                .catch(err => {
                    console.error('Error fetching technicians:', err);
                    select.innerHTML = '<option value="">Failed to load technicians</option>';
                });
        }

        function openScheduleModal(prefillName = '', prefillAddress = '', prefillClientId = '') {
            document.getElementById('modal_job_id').value = '';
            document.getElementById('modal_client_id').value = prefillClientId;
            document.getElementById('modal_contract_id').value = '';
            
            const clientSelect = document.getElementById('modal_client_name');
            clientSelect.value = prefillName;
            
            // If option exists, update client address data
            if (prefillName && clientSelect.selectedIndex >= 0) {
                updateClientData(clientSelect);
            }

            if (prefillAddress) {
                document.getElementById('modal_location').value = prefillAddress;
            } else if (!clientSelect.value) {
                document.getElementById('modal_location').value = '';
            }

            document.getElementById('modal_service_type').value = '';
            document.getElementById('modal_scheduled_date').value = '<?= htmlspecialchars($filterDate) ?>';
            document.getElementById('modal_service_window').value = '09:00–10:30';
            document.getElementById('modal_time_in').value = '';
            document.getElementById('modal_time_out').value = '';
            document.getElementById('modal_priority').value = 'Standard';
            document.getElementById('modal_route_status').value = 'Scheduled';
            document.getElementById('modal_comments').value = '';
            
            populateAreaRows([]);
            fetchTechnicians();
            document.getElementById('jobModal').classList.remove('hidden');
        }

        function openEditModal(job) {
            document.getElementById('modal_job_id').value = job.id || '';
            document.getElementById('modal_client_id').value = job.client_id || '';
            document.getElementById('modal_contract_id').value = job.contract_id || '';
            
            const clientSelect = document.getElementById('modal_client_name');
            clientSelect.value = job.client_name || '';

            document.getElementById('modal_location').value = job.location || '';
            document.getElementById('modal_service_type').value = job.service_type || '';
            document.getElementById('modal_scheduled_date').value = job.scheduled_date || '<?= date('Y-m-d') ?>';
            document.getElementById('modal_service_window').value = job.service_window || '';
            document.getElementById('modal_priority').value = job.priority || 'Standard';
            document.getElementById('modal_route_status').value = job.route_status || 'Scheduled';
            document.getElementById('modal_time_in').value = job.time_in || '';
            document.getElementById('modal_time_out').value = job.time_out || '';
            document.getElementById('modal_comments').value = job.comments || '';
            
            populateAreaRows(job.area_findings);
            fetchTechnicians(job.assigned_tech);
            document.getElementById('jobModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('jobModal').classList.add('hidden');
        }

        function updateClientData(selectElement) {
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            if (!selectedOption) return;

            const address    = selectedOption.getAttribute('data-address') || '';
            const clientId   = selectedOption.getAttribute('data-id') || '';
            const contractId = selectedOption.getAttribute('data-contract-id') || '';

            document.getElementById('modal_location').value = address;
            document.getElementById('modal_client_id').value = clientId;
            document.getElementById('modal_contract_id').value = contractId;
        }

        function addAreaRow(area = '', action = '') {
            const tbody = document.querySelector('#areaTable tbody');
            const row   = document.createElement('tr');
            row.innerHTML = `
                <td class="p-2"><input type="text" name="area_name[]" value="${escapeHtml(area)}" placeholder="e.g. Kitchen / Perimeter" class="w-full border border-gray-200 rounded p-1.5 text-xs bg-white" required></td>
                <td class="p-2"><input type="text" name="area_action[]" value="${escapeHtml(action)}" placeholder="Residual spraying applied" class="w-full border border-gray-200 rounded p-1.5 text-xs bg-white"></td>
                <td class="p-2 text-center"><button type="button" onclick="this.closest('tr').remove()" class="text-red-500 hover:text-red-700"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button></td>
            `;
            tbody.appendChild(row);
            lucide.createIcons();
        }

        function populateAreaRows(areaFindingsData) {
            const tbody = document.querySelector('#areaTable tbody');
            tbody.innerHTML = '';
            
            let findings = [];
            if (typeof areaFindingsData === 'string' && areaFindingsData.trim() !== '') {
                try {
                    findings = JSON.parse(areaFindingsData);
                } catch(e) {
                    findings = [];
                }
            } else if (Array.isArray(areaFindingsData)) {
                findings = areaFindingsData;
            }

            if (findings.length === 0) {
                addAreaRow();
            } else {
                findings.forEach(item => {
                    addAreaRow(item.area || '', item.action || '');
                });
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            return text.toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Auto-open schedule modal if client parameters are passed in the URL
        document.addEventListener('DOMContentLoaded', () => {
            const prefilledClient = "<?= addslashes(htmlspecialchars($prefilledClient)) ?>";
            const prefilledAddress = "<?= addslashes(htmlspecialchars($prefilledAddress)) ?>";
            const prefilledClientId = "<?= addslashes(htmlspecialchars($prefilledClientId)) ?>";

            if (prefilledClient || prefilledAddress || prefilledClientId) {
                openScheduleModal(prefilledClient, prefilledAddress, prefilledClientId);
            }
        });
    </script>
</body>
</html>