<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Active Tab Switcher (clients vs contracts)
$currentTab = $_GET['tab'] ?? 'clients';

// Search and Filter parameters for Clients
$search = trim($_GET['search'] ?? '');
$filterType = trim($_GET['type'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');

// Search and Filter parameters for Contracts
$contractSearch = trim($_GET['c_search'] ?? '');
$filterContractStatus = trim($_GET['c_status'] ?? '');
$isArchivedView = (isset($_GET['status']) && $_GET['status'] === 'archived');

// 1. Fetch Clients Data (Include active, inactive, disabled, and archived clients)
$clientQuery = "SELECT * FROM clients WHERE 1=1";
$clientParams = [];

if (!empty($search)) {
    $clientQuery .= " AND (client_name LIKE :search OR first_name LIKE :search OR last_name LIKE :search OR email LIKE :search OR phone_number LIKE :search)";
    $clientParams['search'] = "%{$search}%";
}
if (!empty($filterType)) {
    $clientQuery .= " AND client_type = :type";
    $clientParams['type'] = $filterType;
}

if (!empty($filterStatus)) {
    $clientQuery .= " AND status = :status";
    $clientParams['status'] = $filterStatus;
} else {
    // Hide archived clients by default when no specific status filter is active
    $clientQuery .= " AND status != 'archived'";
}

$clientQuery .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($clientQuery);
$stmt->execute($clientParams);
$clients = $stmt->fetchAll();

// Auto-update contract statuses based on expiry dates
$today = date('Y-m-d');

// Mark active or expiring_soon contracts whose end date has passed as 'expired'
$updateExpiredStmt = $pdo->prepare("
    UPDATE contracts 
    SET contract_status = 'expired' 
    WHERE contract_status IN ('active', 'expiring_soon') 
      AND contract_end_date IS NOT NULL 
      AND contract_end_date != '' 
      AND contract_end_date != '0000-00-00'
      AND contract_end_date < :today
");
$updateExpiredStmt->execute(['today' => $today]);

// Revert 'expiring_soon' contracts back to 'active' if they have more than 2 days remaining
$revertExpiringStmt = $pdo->prepare("
    UPDATE contracts 
    SET contract_status = 'active' 
    WHERE contract_status = 'expiring_soon' 
      AND contract_end_date IS NOT NULL 
      AND contract_end_date != '' 
      AND contract_end_date != '0000-00-00'
      AND contract_end_date > DATE_ADD(:today, INTERVAL 2 DAY)
");
$revertExpiringStmt->execute(['today' => $today]);

// Mark active contracts expiring within 2 days as 'expiring_soon'
$updateExpiringSoonStmt = $pdo->prepare("
    UPDATE contracts 
    SET contract_status = 'expiring_soon' 
    WHERE contract_status = 'active' 
      AND contract_end_date IS NOT NULL 
      AND contract_end_date != '' 
      AND contract_end_date != '0000-00-00'
      AND contract_end_date >= :today 
      AND contract_end_date <= DATE_ADD(:today, INTERVAL 2 DAY)
");
$updateExpiringSoonStmt->execute(['today' => $today]);

$pdo->exec("
    UPDATE contracts c
    SET c.final_balance_notes = GREATEST(0.00, c.contract_value - COALESCE(
        (SELECT SUM(p.amount_paid) FROM payments p WHERE p.contract_id = c.id), 0.00
    ))
");

// 2. Fetch Contracts Data (with Client details via JOIN)
$contractQuery = "
    SELECT con.*, c.client_name, c.client_type, c.id as client_id_ref 
    FROM contracts con 
    JOIN clients c ON con.client_id = c.id 
    WHERE 1=1 
";

if ($isArchivedView) {
    $contractQuery .= " AND con.contract_status = 'archived'";
} else {
    $contractQuery .= " AND con.contract_status != 'archived'";
}

$contractParams = [];

if (!empty($contractSearch)) {
    $contractQuery .= " AND (con.contract_name LIKE :c_search OR c.client_name LIKE :c_search OR con.contract_status LIKE :c_search OR con.id LIKE :c_search)";
    $contractParams['c_search'] = "%{$contractSearch}%";
}
if (!empty($filterContractStatus) && !$isArchivedView) {
    $contractQuery .= " AND con.contract_status = :c_status";
    $contractParams['c_status'] = $filterContractStatus;
}
$contractQuery .= " ORDER BY con.created_at DESC";

$stmtContract = $pdo->prepare($contractQuery);
$stmtContract->execute($contractParams);
$contracts = $stmtContract->fetchAll();

// KPI Calculations
$archivedCountStmt = $pdo->query("SELECT COUNT(*) FROM clients WHERE status = 'archived'");
$archivedClients = (int) $archivedCountStmt->fetchColumn();

$totalClients = count($clients);
$activeClients = 0;
$inactiveClients = 0;
$commercialCount = 0;
$residentialCount = 0;

foreach ($clients as $c) {
    $st = $c['status'] ?? '';
    if ($st === 'active') $activeClients++;
    elseif ($st === 'inactive' || $st === 'disabled') $inactiveClients++;

    if (($c['client_type'] ?? '') === 'Commercial') $commercialCount++;
    if (($c['client_type'] ?? '') === 'Residential') $residentialCount++;
}

$totalContractsCount = count($contracts);
$activeContractsCount = 0;
$toBeContractedCount = 0;
$expiredContractsCount = 0;
$expiringSoonCount = 0;

foreach ($contracts as $con) {
    $st = $con['contract_status'] ?? '';
    if ($st === 'active') $activeContractsCount++;
    if ($st === 'to_be_contracted') $toBeContractedCount++;
    if ($st === 'expired') $expiredContractsCount++;
    if ($st === 'expiring_soon') $expiringSoonCount++;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vermex - Client & Contract Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="bg-[#f8faf9] text-slate-800 min-h-screen flex font-sans antialiased overflow-hidden">

    <!-- Shared Sidebar Component -->
    <?php include 'components/sidebar.php'; ?>

    <main class="flex-1 flex flex-col overflow-y-auto bg-[#f8faf9]">
        <div class="p-6 space-y-6 w-full max-w-7xl mx-auto">

            <!-- Notifications -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg text-xs flex items-center justify-between shadow-sm">
                    <span><?= htmlspecialchars($_SESSION['success']) ?></span>
                    <button onclick="this.parentElement.remove()" class="text-emerald-600">&times;</button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-lg text-xs flex items-center justify-between shadow-sm">
                    <span><?= htmlspecialchars($_SESSION['error']) ?></span>
                    <button onclick="this.parentElement.remove()" class="text-rose-600">&times;</button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Page Header & Dashboard Switcher -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-200 pb-5">
                <div>
                    <div class="flex items-center gap-2 text-[11px] text-emerald-700 font-semibold tracking-wider uppercase mb-1">
                        <span>CRM & Operations</span>
                        <span>•</span>
                        <span><?= $currentTab === 'contracts' ? ($isArchivedView ? 'Archived Contracts' : 'Service Contracts') : 'Client Accounts' ?></span>
                    </div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">
                        <?= $currentTab === 'contracts' ? ($isArchivedView ? 'Archived Contracts View' : 'Contracts Management Dashboard') : 'Clients Management Dashboard' ?>
                    </h1>
                    <p class="text-xs text-slate-500 mt-1">Manage master client profiles or oversee multiple service contracts, values, and timelines.</p>
                </div>

                <!-- Dashboard Switcher Buttons -->
                <div class="flex items-center gap-3">
                    <div class="bg-slate-200/80 p-1 rounded-xl flex items-center text-xs font-medium">
                        <a href="clients.php?tab=clients" class="px-4 py-2 rounded-lg transition <?= $currentTab === 'clients' ? 'bg-white text-slate-900 shadow-sm font-semibold' : 'text-slate-600 hover:text-slate-900' ?>">
                            Clients Dashboard
                        </a>
                        <a href="clients.php?tab=contracts" class="px-4 py-2 rounded-lg transition <?= $currentTab === 'contracts' && !$isArchivedView ? 'bg-white text-slate-900 shadow-sm font-semibold' : 'text-slate-600 hover:text-slate-900' ?>">
                            Contracts Dashboard
                        </a>
                    </div>

                    <?php if ($currentTab === 'clients'): ?>
                        <button onclick="toggleClientForm()" class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-xs px-4 py-2 rounded-lg flex items-center gap-2 transition shadow-sm">
                            <i data-lucide="building" class="w-4 h-4"></i> Register New Client
                        </button>
                    <?php else: ?>
                        <button onclick="openCreateContractModal()" class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-xs px-4 py-2 rounded-lg flex items-center gap-2 transition shadow-sm">
                            <i data-lucide="file-plus" class="w-4 h-4"></i> Add New Contract
                        </button>
                    <?php endif; ?>

                </div>
            </div>

            <!-- ================= TAB 1: CLIENTS DASHBOARD ================= -->
            <?php if ($currentTab === 'clients'): ?>

                <!-- KPI Cards for Clients -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="bg-white border border-slate-200/80 rounded-xl p-4 flex items-center justify-between shadow-sm">
                        <div>
                            <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total Client Accounts</p>
                            <p class="text-2xl font-bold text-slate-900 mt-1"><?= $totalClients ?> <span class="text-xs font-normal text-slate-500">Clients</span></p>
                            <div class="flex items-center gap-2 text-[11px] font-medium mt-1">
                                <span class="text-emerald-700"><?= $activeClients ?> Active</span>
                                <span class="text-slate-300">•</span>
                                <span class="text-amber-600"><?= $inactiveClients ?> Inactive</span>
                                <span class="text-slate-300">•</span>
                                <span class="text-slate-500"><?= $archivedClients ?> Archived</span>
                            </div>
                        </div>
                        <div class="bg-emerald-50 p-2.5 rounded-lg text-emerald-700 border border-emerald-100">
                            <i data-lucide="briefcase" class="w-5 h-5"></i>
                        </div>
                    </div>

                    <div class="bg-white border border-slate-200/80 rounded-xl p-4 flex items-center justify-between shadow-sm">
                        <div>
                            <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Client Types (Commercial / Residential)</p>
                            <p class="text-2xl font-bold text-slate-900 mt-1"><?= $commercialCount ?> <span class="text-xs font-normal text-slate-500">Commercial</span> / <?= $residentialCount ?> <span class="text-xs font-normal text-slate-500">Residential</span></p>
                            <p class="text-[11px] text-blue-600 font-medium mt-1">Combined Account Segmentation</p>
                        </div>
                        <div class="bg-blue-50 p-2.5 rounded-lg text-blue-600 border border-blue-100">
                            <i data-lucide="store" class="w-5 h-5"></i>
                        </div>
                    </div>

                    <div class="bg-white border border-slate-200/80 rounded-xl p-4 flex items-center justify-between shadow-sm">
                        <div>
                            <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Expiring / Renewals</p>
                            <p class="text-2xl font-bold text-amber-600 mt-1"><?= $expiringSoonCount ?> <span class="text-xs font-normal text-slate-500">Contracts</span></p>
                            <p class="text-[11px] text-amber-600 font-medium mt-1">Requires renewal attention</p>
                        </div>
                        <div class="bg-amber-50 p-2.5 rounded-lg text-amber-600 border border-amber-100">
                            <i data-lucide="clock" class="w-5 h-5"></i>
                        </div>
                    </div>
                </div>

                <!-- Registration Form Panel -->
                <div id="clientCreationSection" class="hidden grid grid-cols-1 gap-6">
                    <div class="bg-white border border-slate-200/80 rounded-xl p-5 space-y-4 shadow-sm">
                        <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
                            <div>
                                <h2 class="text-base font-semibold text-slate-900">Register New Client Account</h2>
                                <p class="text-[11px] text-slate-400">Configure client business profile, standardized service location, and billing details.</p>
                            </div>
                            <button type="button" onclick="toggleClientForm()" class="text-slate-400 hover:text-slate-700"><i data-lucide="x" class="w-5 h-5"></i></button>
                        </div>

                        <form action="../controllers/createClient.php" method="POST" class="space-y-3.5 text-xs">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-slate-600 font-medium mb-1">Company / Account Name *</label>
                                    <input type="text" name="company_name" required placeholder="e.g. Marco Polo Hotel Davao" class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-600">
                                </div>
                                <div>
                                    <label class="block text-slate-600 font-medium mb-1">Contact First Name *</label>
                                    <input type="text" name="first_name" required placeholder="First Name" class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-600">
                                </div>
                                <div>
                                    <label class="block text-slate-600 font-medium mb-1">Contact Last Name *</label>
                                    <input type="text" name="last_name" required placeholder="Last Name" class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-600">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-slate-600 font-medium mb-1">Account Type *</label>
                                    <select name="client_type" required class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-600">
                                        <option value="Commercial">Commercial</option>
                                        <option value="Residential">Residential</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-slate-600 font-medium mb-1">Email Address *</label>
                                    <input type="email" name="email" required placeholder="contact@company.com" class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-600">
                                </div>
                                <div>
                                    <label class="block text-slate-600 font-medium mb-1">Phone Number *</label>
                                    <input type="text" name="phone" required placeholder="+63 9XX XXX XXXX" class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-600">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-slate-600 font-medium mb-1">Street Address *</label>
                                    <input type="text" name="street_address" required placeholder="e.g. Door 4, Prieto Bldg" class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-600">
                                </div>
                                <div>
                                    <label class="block text-slate-600 font-medium mb-1">Barangay *</label>
                                    <input type="text" name="barangay" required placeholder="e.g. Brgy. 27-C" class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-600">
                                </div>
                                <div>
                                    <label class="block text-slate-600 font-medium mb-1">City *</label>
                                    <input type="text" name="city" required value="Davao City" class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-600">
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-3 pt-2">
                                <button type="button" onclick="toggleClientForm()" class="bg-white text-slate-600 px-4 py-2 rounded-lg border border-slate-200 font-medium">Cancel</button>
                                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-4 py-2 rounded-lg shadow-sm">Register Client Account</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Clients Table View -->
                <div class="bg-white border border-slate-200/80 rounded-xl p-5 space-y-4 shadow-sm">
                    <form method="GET" action="clients.php" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
                        <input type="hidden" name="tab" value="clients">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">Registered Accounts</h2>
                            <p class="text-[11px] text-slate-400 mt-0.5"><?= $totalClients ?> accounts found</p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <div class="relative flex-1 sm:w-56">
                                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, company, email..." class="w-full border border-slate-200 text-xs rounded-lg pl-8 pr-3 py-1.5 focus:outline-none focus:border-emerald-600">
                            </div>

                            <select name="type" onchange="this.form.submit()" class="border border-slate-200 text-slate-700 text-xs rounded-lg px-2.5 py-1.5 font-medium">
                                <option value="">Type: All</option>
                                <option value="Commercial" <?= $filterType === 'Commercial' ? 'selected' : '' ?>>Commercial</option>
                                <option value="Residential" <?= $filterType === 'Residential' ? 'selected' : '' ?>>Residential</option>
                            </select>
                            <select name="status" onchange="this.form.submit()" class="border border-slate-200 text-slate-700 text-xs rounded-lg px-2.5 py-1.5 font-medium">
                                <option value="">Status: All</option>
                                <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                <option value="archived" <?= $filterStatus === 'archived' ? 'selected' : '' ?>>Archived</option>
                            </select>
                            <a href="archived-clients.php" class="bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-medium px-4 py-2.5 rounded-lg flex items-center gap-2 shadow-sm transition">
                                <i data-lucide="archive" class="w-4 h-4"></i> Archived Clients
                            </a>
                        </div>
                    </form>

                    <div class="overflow-x-auto overflow-y-auto max-h-[450px]">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="text-[10px] uppercase text-slate-400 border-b border-slate-100">
                                    <th class="pb-2 font-semibold">Client Name & Contact</th>
                                    <th class="pb-2 font-semibold">Type</th>
                                    <th class="pb-2 font-semibold">Standardized Address</th>
                                    <th class="pb-2 font-semibold">Account Status</th>
                                    <th class="pb-2 font-semibold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (empty($clients)): ?>
                                    <tr>
                                        <td colspan="5" class="py-6 text-center text-slate-400">No client records found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($clients as $client): ?>
                                        <?php
                                        $typeClass = ($client['client_type'] ?? '') === 'Residential' ? "bg-emerald-50 text-emerald-700 border-emerald-200/60" : "bg-blue-50 text-blue-700 border-blue-200/60";

                                        $clientStatus = $client['status'] ?? 'active';
                                        if ($clientStatus === 'active') {
                                            $statusBadge = '<span class="bg-emerald-50 text-emerald-700 border border-emerald-200/60 px-2 py-0.5 rounded text-[10px] font-semibold">Active</span>';
                                        } elseif ($clientStatus === 'inactive' || $clientStatus === 'disabled') {
                                            $statusBadge = '<span class="bg-amber-50 text-amber-700 border border-amber-200/60 px-2 py-0.5 rounded text-[10px] font-semibold">' . ucfirst($clientStatus) . '</span>';
                                        } elseif ($clientStatus === 'archived') {
                                            $statusBadge = '<span class="bg-slate-100 text-slate-600 border border-slate-200 px-2 py-0.5 rounded text-[10px] font-semibold">Archived</span>';
                                        } else {
                                            $statusBadge = '<span class="bg-slate-50 text-slate-700 border border-slate-200 px-2 py-0.5 rounded text-[10px] font-semibold">' . ucfirst($clientStatus) . '</span>';
                                        }

                                        $fullAddress = trim(($client['street_address'] ?? '') . ', ' . ($client['barangay'] ?? '') . ', ' . ($client['city'] ?? 'Davao City'));
                                        $encodedClient = htmlspecialchars(json_encode($client), ENT_QUOTES, 'UTF-8');
                                        ?>
                                        <tr class="hover:bg-slate-50/80 transition">
                                            <td class="py-3 px-1">
                                                <button onclick="openViewModal(<?= $encodedClient ?>)" class="font-semibold text-slate-900 hover:text-emerald-700 hover:underline text-left transition">
                                                    <?= htmlspecialchars($client['client_name']) ?>
                                                </button>
                                                <div class="text-[11px] text-slate-500 mt-0.5"><?= htmlspecialchars(($client['last_name'] ?? '') . ', ' . ($client['first_name'] ?? '')) ?> • <span class="font-mono text-slate-400"><?= htmlspecialchars($client['phone_number'] ?? '') ?></span></div>
                                            </td>
                                            <td class="py-3"><span class="<?= $typeClass ?> border px-2 py-0.5 rounded text-[10px] font-medium"><?= htmlspecialchars($client['client_type'] ?? 'Commercial') ?></span></td>
                                            <td class="py-3">
                                                <div class="text-[11px] text-slate-700 truncate max-w-xs"><?= htmlspecialchars($fullAddress) ?></div>
                                            </td>
                                            <td class="py-3"><?= $statusBadge ?></td>
                                            <td class="py-3 text-right">
                                                <button onclick="openViewModal(<?= $encodedClient ?>)" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-2.5 py-1 rounded-lg text-xs font-medium transition">View Profile</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ================= TAB 2: CONTRACTS DASHBOARD ================= -->
            <?php else: ?>

                <!-- KPI Cards for Contracts -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-white border border-slate-200/80 rounded-xl p-4 flex items-center justify-between shadow-sm">
                        <div>
                            <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total Contracts</p>
                            <p class="text-2xl font-bold text-slate-900 mt-1"><?= $totalContractsCount ?> <span class="text-xs font-normal text-slate-500">Agreements</span></p>
                            <p class="text-[11px] text-emerald-700 font-medium mt-1"><?= $activeContractsCount ?> Active Service Contracts</p>
                        </div>
                        <div class="bg-emerald-50 p-2.5 rounded-lg text-emerald-700 border border-emerald-100">
                            <i data-lucide="file-text" class="w-5 h-5"></i>
                        </div>
                    </div>

                    <div class="bg-white border border-slate-200/80 rounded-xl p-4 flex items-center justify-between shadow-sm">
                        <div>
                            <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">To-Be-Contracted</p>
                            <p class="text-2xl font-bold text-slate-900 mt-1"><?= $toBeContractedCount ?> <span class="text-xs font-normal text-slate-500">Pending</span></p>
                            <p class="text-[11px] text-blue-600 font-medium mt-1">Awaiting inspection or setup</p>
                        </div>
                        <div class="bg-blue-50 p-2.5 rounded-lg text-blue-600 border border-blue-100">
                            <i data-lucide="clipboard-list" class="w-5 h-5"></i>
                        </div>
                    </div>

                    <div class="bg-white border border-slate-200/80 rounded-xl p-4 flex items-center justify-between shadow-sm">
                        <div>
                            <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Expired / Terminated</p>
                            <p class="text-2xl font-bold text-rose-600 mt-1"><?= $expiredContractsCount ?> <span class="text-xs font-normal text-slate-500">Closed</span></p>
                            <p class="text-[11px] text-rose-600 font-medium mt-1">Past completed agreements</p>
                        </div>
                        <div class="bg-rose-50 p-2.5 rounded-lg text-rose-600 border border-rose-100">
                            <i data-lucide="alert-circle" class="w-5 h-5"></i>
                        </div>
                    </div>
                </div>

                <!-- Contracts Table View -->
                <div class="bg-white border border-slate-200/80 rounded-xl p-5 space-y-4 shadow-sm">

                    <!-- Toggle Button for Archived View -->
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900"><?= $isArchivedView ? 'Archived Contracts Registry' : 'Master Contracts Registry' ?></h2>
                            <p class="text-[11px] text-slate-400 mt-0.5"><?= $totalContractsCount ?> contracts recorded</p>
                        </div>
                        <div>
                            <?php if ($isArchivedView): ?>
                                <a href="clients.php?tab=contracts" class="text-xs font-semibold px-3 py-2 bg-slate-800 text-white rounded-lg shadow-sm hover:bg-slate-700 transition flex items-center gap-1.5">
                                    <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Back to Active Contracts
                                </a>
                            <?php else: ?>
                                <a href="clients.php?tab=contracts&status=archived" class="text-xs font-semibold px-3 py-2 bg-slate-100 text-slate-700 border border-slate-200 rounded-lg shadow-sm hover:bg-slate-200 transition flex items-center gap-1.5">
                                    <i data-lucide="archive" class="w-3.5 h-3.5"></i> View Archived Contracts
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <form method="GET" action="clients.php" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-1">
                        <input type="hidden" name="tab" value="contracts">
                        <?php if ($isArchivedView): ?>
                            <input type="hidden" name="status" value="archived">
                        <?php endif; ?>

                        <div class="flex flex-wrap items-center gap-2 w-full">
                            <div class="relative flex-1 sm:w-56">
                                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                                <input type="text" name="c_search" value="<?= htmlspecialchars($contractSearch) ?>" placeholder="Search contract, ID, or client..." class="w-full border border-slate-200 text-xs rounded-lg pl-8 pr-3 py-1.5 focus:outline-none focus:border-emerald-600">
                            </div>
                            <?php if (!$isArchivedView): ?>
                                <select name="c_status" onchange="this.form.submit()" class="border border-slate-200 text-slate-700 text-xs rounded-lg px-2.5 py-1.5 font-medium">
                                    <option value="">Status: All</option>
                                    <option value="active" <?= $filterContractStatus === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="to_be_contracted" <?= $filterContractStatus === 'to_be_contracted' ? 'selected' : '' ?>>To-Be-Contracted</option>
                                    <option value="expiring_soon" <?= $filterContractStatus === 'expiring_soon' ? 'selected' : '' ?>>Expiring Soon</option>
                                    <option value="expired" <?= $filterContractStatus === 'expired' ? 'selected' : '' ?>>Expired</option>
                                    <option value="cancelled" <?= $filterContractStatus === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                            <?php endif; ?>
                        </div>
                    </form>

                    <div class="overflow-x-auto overflow-y-auto max-h-[450px]">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="text-[10px] uppercase text-slate-400 border-b border-slate-100">
                                    <th class="pb-2 font-semibold">Contract ID & Name</th>
                                    <th class="pb-2 font-semibold">Client Name (ID)</th>
                                    <th class="pb-2 font-semibold">Client Type</th>
                                    <th class="pb-2 font-semibold">Status</th>
                                    <th class="pb-2 font-semibold">Start & Expiry Dates</th>
                                    <th class="pb-2 font-semibold">Contract Value</th>
                                    <th class="pb-2 font-semibold">Final Balance</th>
                                    <th class="pb-2 font-semibold">Created Timestamp</th>
                                    <th class="pb-2 font-semibold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (empty($contracts)): ?>
                                    <tr>
                                        <td colspan="9" class="py-6 text-center text-slate-400">No contract records found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($contracts as $con): ?>
                                        <?php
                                        $cStatusBadge = '<span class="bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-0.5 rounded text-[10px] font-semibold">Active</span>';
                                        if ($con['contract_status'] === 'to_be_contracted') $cStatusBadge = '<span class="bg-slate-100 text-slate-600 border border-slate-200 px-2 py-0.5 rounded text-[10px] font-semibold">To-Be-Contracted</span>';
                                        elseif ($con['contract_status'] === 'expiring_soon') $cStatusBadge = '<span class="bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 rounded text-[10px] font-semibold">Expiring Soon</span>';
                                        elseif ($con['contract_status'] === 'expired') $cStatusBadge = '<span class="bg-rose-50 text-rose-700 border border-rose-200 px-2 py-0.5 rounded text-[10px] font-semibold">Expired</span>';
                                        elseif ($con['contract_status'] === 'cancelled') $cStatusBadge = '<span class="bg-purple-50 text-purple-700 border border-purple-200 px-2 py-0.5 rounded text-[10px] font-semibold">Cancelled</span>';
                                        elseif ($con['contract_status'] === 'archived') $cStatusBadge = '<span class="bg-slate-200 text-slate-700 border border-slate-300 px-2 py-0.5 rounded text-[10px] font-semibold">Archived</span>';

                                        // Determine client type badge style
                                        $clientTypeBadge = ($con['client_type'] ?? '') === 'Residential'
                                            ? '<span class="bg-emerald-50 text-emerald-700 border border-emerald-200/60 px-2 py-0.5 rounded text-[10px] font-medium">Residential</span>'
                                            : '<span class="bg-blue-50 text-blue-700 border border-blue-200/60 px-2 py-0.5 rounded text-[10px] font-medium">Commercial</span>';

                                        $encodedContract = htmlspecialchars(json_encode($con), ENT_QUOTES, 'UTF-8');
                                        ?>
                                        <tr class="hover:bg-slate-50/80 transition">
                                            <td class="py-3 px-1">
                                                <div class="font-semibold text-slate-900"><?= htmlspecialchars($con['contract_name'] ?? 'Service Contract #' . $con['id']) ?></div>
                                                <div class="text-[10px] font-mono text-slate-400">#CONTRACT-<?= $con['id'] ?></div>
                                            </td>
                                            <td class="py-3">
                                                <div class="font-medium text-slate-800"><?= htmlspecialchars($con['client_name']) ?></div>
                                                <div class="text-[10px] text-slate-400">ID: #<?= $con['client_id_ref'] ?></div>
                                            </td>
                                            <td class="py-3">
                                                <?= $clientTypeBadge ?>
                                            </td>
                                            <td class="py-3"><?= $cStatusBadge ?></td>
                                            <td class="py-3">
                                                <div class="text-[11px] text-slate-700"><?= htmlspecialchars($con['contract_start_date'] ?: 'Not set') ?> to <?= htmlspecialchars($con['contract_end_date'] ?: 'Not set') ?></div>
                                            </td>
                                            <td class="py-3 font-mono font-medium text-slate-800">
                                                ₱<?= number_format($con['contract_value'] ?? 0, 2) ?>
                                            </td>
                                            <td class="py-3 font-mono font-medium text-rose-600">
                                                ₱<?= number_format($con['final_balance'] ?? 0, 2) ?>
                                            </td>
                                            <td class="py-3 font-mono text-slate-500 text-[11px]"><?= htmlspecialchars($con['created_at']) ?></td>
                                            <td class="py-3 text-right">
                                                <?php if ($isArchivedView): ?>
                                                    <a href="../controllers/restoreContract.php?id=<?= $con['id'] ?>" onclick="return confirm('Are you sure you want to restore this contract?');" class="text-emerald-700 hover:text-emerald-900 font-medium text-xs px-2.5 py-1 bg-emerald-50 border border-emerald-200 rounded transition">Restore</a>
                                                <?php else: ?>
                                                    <!-- Conditional Pre-Contract Inspection Button -->
                                                    <?php if (($con['contract_status'] ?? '') === 'to_be_contracted'): ?>
                                                        <a href="inspections.php?contract_id=<?= $con['id'] ?>&client_id=<?= $con['client_id_ref'] ?>" class="text-emerald-700 hover:text-emerald-900 font-medium text-xs px-2 py-1 bg-emerald-50 border border-emerald-200 rounded transition mr-1 inline-flex items-center gap-1" title="Schedule Pre-Contract Inspection">
                                                            <i data-lucide="clipboard-check" class="w-3 h-3"></i> Inspection
                                                        </a>
                                                    <?php endif; ?>

                                                    <!-- Shortcut Button: View Payment History for this Contract -->
                                                    <a href="payments.php?search=CONTRACT-<?= $con['id'] ?>" class="text-emerald-700 hover:text-emerald-900 font-medium text-xs px-2 py-1 bg-emerald-50 border border-emerald-200/60 rounded transition mr-1 inline-flex items-center gap-1" title="View Payments">
                                                        <i data-lucide="credit-card" class="w-3 h-3"></i> Payments
                                                    </a>

                                                    <button onclick="openEditContractModal(<?= $encodedContract ?>)" class="text-blue-600 hover:text-blue-800 font-medium text-xs px-2 py-1 bg-blue-50 rounded transition mr-1">Edit</button>
                                                    <a href="../controllers/archiveContract.php?id=<?= $con['id'] ?>" onclick="return confirm('Are you sure you want to archive this contract?');" class="text-slate-600 hover:text-slate-800 font-medium text-xs px-2 py-1 bg-slate-100 rounded transition">Archive</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php endif; ?>

        </div>

        <!-- ================= MODALS ================= -->

        <!-- Client & Contracts Profile Modal -->
        <div id="viewContractModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4 overflow-y-auto">
            <div class="bg-white border border-slate-200 rounded-2xl max-w-4xl w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden my-auto">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 border border-emerald-200/60 flex items-center justify-center text-emerald-700">
                            <i data-lucide="building-2" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Client Profile & Contracts History</h3>
                            <p class="text-xs text-slate-500">Manage account information and active/past contracts</p>
                        </div>
                    </div>
                    <button onclick="closeViewModal()" class="text-slate-400 hover:text-slate-700 p-1 rounded-lg hover:bg-slate-100 transition"><i data-lucide="x" class="w-5 h-5"></i></button>
                </div>

                <div class="p-6 overflow-y-auto space-y-6 flex-1 text-xs">
                    <!-- Client Details Card -->
                    <div class="bg-white border border-slate-200 rounded-xl p-5 space-y-4 shadow-sm">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                            <div class="flex items-center gap-2 text-slate-800 font-bold">
                                <i data-lucide="user" class="w-4 h-4 text-blue-600"></i> Client Details
                                <span id="view_status_badge"></span> <!-- Status Badge Output -->
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" onclick="openEditClientModal()" class="bg-blue-50 text-blue-600 hover:bg-blue-100 border border-blue-200 px-3 py-1 rounded-lg text-xs font-semibold flex items-center gap-1 transition">
                                    <i data-lucide="edit" class="w-3.5 h-3.5"></i> Edit Client
                                </button>
                                <span id="clientActionContainer"></span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Company / Name</span>
                                <p id="view_company_name" class="text-sm font-bold text-slate-900 mt-0.5"></p>
                            </div>
                            <div>
                                <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Contact Person</span>
                                <p id="view_contact_person" class="text-slate-800 font-medium mt-0.5"></p>
                            </div>
                            <div>
                                <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Account Type</span>
                                <p id="view_client_type" class="text-slate-800 font-medium mt-0.5"></p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Email Address</span>
                                <p id="view_email" class="text-slate-800 font-mono mt-0.5"></p>
                            </div>
                            <div>
                                <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Phone Number</span>
                                <p id="view_phone" class="text-slate-800 font-mono mt-0.5"></p>
                            </div>
                            <div>
                                <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Service Address</span>
                                <p id="view_address" class="text-slate-800 mt-0.5"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Contract Summary: Scrollable Contract List -->
                    <div class="bg-white border border-slate-200 rounded-xl p-5 space-y-4 shadow-sm">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                            <div class="flex items-center gap-2 text-slate-800 font-bold">
                                <i data-lucide="file-text" class="w-4 h-4 text-emerald-700"></i> Contracts Summary & History
                            </div>
                            <button type="button" onclick="openAddContractForClient()" class="bg-emerald-700 hover:bg-emerald-800 text-white text-[11px] px-3 py-1.5 rounded-lg flex items-center gap-1.5 transition font-medium shadow-sm">
                                <i data-lucide="plus" class="w-3.5 h-3.5"></i> Add Contract
                            </button>
                        </div>

                        <!-- Scrollable Contract List Table -->
                        <div class="overflow-x-auto max-h-56">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="text-[10px] uppercase text-slate-400 border-b border-slate-100">
                                        <th class="pb-2 font-semibold">Contract ID</th>
                                        <th class="pb-2 font-semibold">Contract Name</th>
                                        <th class="pb-2 font-semibold">Status</th>
                                        <th class="pb-2 font-semibold">Start - Expiry Date</th>
                                        <th class="pb-2 font-semibold text-right">Contract Value</th>
                                        <th class="pb-2 font-semibold text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="modalContractsTableBody" class="divide-y divide-slate-100">
                                    <tr>
                                        <td colspan="6" class="py-4 text-center text-slate-400 italic">Loading contracts...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-end">
                    <button onclick="closeViewModal()" class="bg-white hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg border border-slate-200 text-xs font-medium transition">Close Profile</button>
                </div>
            </div>
        </div>

        <!-- Edit Client Modal -->
        <div id="editClientModal" class="hidden fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
            <div class="bg-white border border-slate-200 rounded-xl p-6 max-w-xl w-full space-y-4 shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-semibold text-slate-900 flex items-center gap-2">
                        <i data-lucide="user-cog" class="w-4 h-4 text-blue-600"></i> Edit Client Account
                    </h3>
                    <button onclick="closeEditClientModal()" class="text-slate-400 hover:text-slate-700">&times;</button>
                </div>

                <form action="../controllers/updateClient.php" method="POST" class="space-y-3.5 text-xs">
                    <input type="hidden" id="edit_client_id" name="client_id">

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-slate-600 font-medium mb-1">Company / Name *</label>
                            <input type="text" id="edit_client_name" name="client_name" required class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-600">
                        </div>
                        <div>
                            <label class="block text-slate-600 font-medium mb-1">First Name *</label>
                            <input type="text" id="edit_first_name" name="first_name" required class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-600">
                        </div>
                        <div>
                            <label class="block text-slate-600 font-medium mb-1">Last Name *</label>
                            <input type="text" id="edit_last_name" name="last_name" required class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-600">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-slate-600 font-medium mb-1">Account Type *</label>
                            <select id="edit_client_type" name="client_type" required class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-600">
                                <option value="Commercial">Commercial</option>
                                <option value="Residential">Residential</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-slate-600 font-medium mb-1">Account Status *</label>
                            <select id="edit_client_status" name="status" required class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-600">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-slate-600 font-medium mb-1">Email Address *</label>
                            <input type="email" id="edit_email" name="email" required class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-600">
                        </div>
                        <div>
                            <label class="block text-slate-600 font-medium mb-1">Phone Number *</label>
                            <input type="text" id="edit_phone" name="phone_number" required class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-600">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-slate-600 font-medium mb-1">Street Address *</label>
                            <input type="text" id="edit_street_address" name="street_address" required class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-600">
                        </div>
                        <div>
                            <label class="block text-slate-600 font-medium mb-1">Barangay *</label>
                            <input type="text" id="edit_barangay" name="barangay" required class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-600">
                        </div>
                        <div>
                            <label class="block text-slate-600 font-medium mb-1">City *</label>
                            <input type="text" id="edit_city" name="city" required class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-600">
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                        <button type="button" onclick="closeEditClientModal()" class="bg-white text-slate-600 px-4 py-2 rounded-lg border border-slate-200 font-medium">Cancel</button>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg shadow-sm transition">Update Client Account</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add / Create Contract Modal -->
        <div id="createContractModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
            <div class="bg-white border border-slate-200 rounded-xl p-6 max-w-md w-full space-y-4 shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-semibold text-slate-900 flex items-center gap-2">
                        <i data-lucide="file-plus" class="w-4 h-4 text-emerald-700"></i> Create New Contract
                    </h3>
                    <button onclick="closeCreateContractModal()" class="text-slate-400 hover:text-slate-700">&times;</button>
                </div>

                <form action="../controllers/createContract.php" method="POST" class="space-y-3.5 text-xs">
                    <!-- Auto-generated / Hidden ID info display -->
                    <div class="bg-slate-50 p-2.5 rounded-lg border border-slate-200 text-[11px] text-slate-600 flex justify-between">
                        <span>Contract ID: <strong class="font-mono text-slate-900">Auto-Generated</strong></span>
                        <span>Status: <strong class="text-emerald-700">To-Be-Contracted</strong></span>
                    </div>

                    <div>
                        <label class="block text-slate-600 font-medium mb-1">Select Client *</label>
                        <select name="client_id" id="contract_modal_client_id" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-xs focus:outline-none focus:border-emerald-600">
                            <option value="">-- Choose Client Account --</option>
                            <?php foreach ($clients as $cl): ?>
                                <option value="<?= $cl['id'] ?>"><?= htmlspecialchars($cl['client_name']) ?> (ID: #<?= $cl['id'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-600 font-medium mb-1">Contract Name *</label>
                        <input type="text" name="contract_name" required placeholder="e.g. Annual Pest Maintenance 2026" class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-600">
                    </div>

                    <div>
                        <label class="block text-slate-600 font-medium mb-1">Contract Value (₱) *</label>
                        <input type="number" step="0.01" name="contract_value" required placeholder="0.00" class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-600">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-600 font-medium mb-1">Start Date</label>
                            <input type="date" name="start_date" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-slate-600 focus:outline-none focus:border-emerald-600">
                        </div>
                        <div>
                            <label class="block text-slate-600 font-medium mb-1">Expiry Date</label>
                            <input type="date" name="end_date" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-slate-600 focus:outline-none focus:border-emerald-600">
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                        <button type="button" onclick="closeCreateContractModal()" class="bg-white text-slate-600 px-4 py-2 rounded-lg border border-slate-200 font-medium">Cancel</button>
                        <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-medium px-4 py-2 rounded-lg shadow-sm transition">Save Contract</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Contract Modal -->
        <div id="editContractModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
            <div class="bg-white border border-slate-200 rounded-xl p-6 max-w-md w-full space-y-4 shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-semibold text-slate-900 flex items-center gap-2">
                        <i data-lucide="edit" class="w-4 h-4 text-blue-600"></i> Edit Service Contract
                    </h3>
                    <button onclick="closeEditContractModal()" class="text-slate-400 hover:text-slate-700">&times;</button>
                </div>

                <form action="../controllers/updateContract.php" method="POST" class="space-y-3.5 text-xs">
                    <input type="hidden" id="edit_contract_id" name="contract_id">

                    <div>
                        <label class="block text-slate-600 font-medium mb-1">Contract Name *</label>
                        <input type="text" id="edit_contract_name" name="contract_name" required class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-600">
                    </div>

                    <div>
                        <label class="block text-slate-600 font-medium mb-1">Contract Status *</label>
                        <select name="contract_status" id="edit_contract_status" required class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-600">
                            <option value="to_be_contracted">To-Be-Contracted</option>
                            <option value="active">Active</option>
                            <option value="expiring_soon">Expiring Soon</option>
                            <option value="expired">Expired</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-600 font-medium mb-1">Contract Value (₱) *</label>
                        <input type="number" step="0.01" id="edit_contract_value" name="contract_value" required class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-600">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-600 font-medium mb-1">Start Date</label>
                            <input type="date" id="edit_contract_start" name="start_date" class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-600">
                        </div>
                        <div>
                            <label class="block text-slate-600 font-medium mb-1">Expiry Date</label>
                            <input type="date" id="edit_contract_end" name="end_date" class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-600">
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                        <button type="button" onclick="closeEditContractModal()" class="bg-white text-slate-600 px-4 py-2 rounded-lg border border-slate-200 font-medium">Cancel</button>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg shadow-sm transition">Update Contract</button>
                    </div>
                </form>
            </div>
        </div>

    </main>

    <script>
        lucide.createIcons();

        function toggleClientForm() {
            document.getElementById('clientCreationSection').classList.toggle('hidden');
        }

        let selectedActiveClientId = null;
        let currentClientData = null;

        function openViewModal(client) {
            selectedActiveClientId = client.id;
            currentClientData = client;

            document.getElementById('view_company_name').innerText = client.client_name || 'N/A';
            document.getElementById('view_contact_person').innerText = (client.last_name || '') + ', ' + (client.first_name || '');
            document.getElementById('view_client_type').innerText = client.client_type || 'Commercial';
            document.getElementById('view_email').innerText = client.email || 'N/A';
            document.getElementById('view_phone').innerText = client.phone_number || 'N/A';
            document.getElementById('view_address').innerText = [client.street_address, client.barangay, client.city].filter(Boolean).join(', ') || 'N/A';

            // Render Status Badge
            let st = (client.status || 'active').toLowerCase();
            let badgeHtml = '';
            if (st === 'active') {
                badgeHtml = '<span class="bg-emerald-50 text-emerald-700 border border-emerald-200/60 px-2 py-0.5 rounded text-[10px] font-semibold">Active</span>';
            } else if (st === 'inactive' || st === 'disabled') {
                badgeHtml = '<span class="bg-amber-50 text-amber-700 border border-amber-200/60 px-2 py-0.5 rounded text-[10px] font-semibold">Inactive</span>';
            } else if (st === 'archived') {
                badgeHtml = '<span class="bg-slate-100 text-slate-600 border border-slate-200 px-2 py-0.5 rounded text-[10px] font-semibold">Archived</span>';
            }
            document.getElementById('view_status_badge').innerHTML = badgeHtml;

            fetchClientContracts(client.id);
            document.getElementById('viewContractModal').classList.remove('hidden');
        }

        function closeViewModal() {
            document.getElementById('viewContractModal').classList.add('hidden');
        }

        function openEditClientModal() {
            if (!currentClientData) return;

            document.getElementById('edit_client_id').value = currentClientData.id;
            document.getElementById('edit_client_name').value = currentClientData.client_name || '';
            document.getElementById('edit_first_name').value = currentClientData.first_name || '';
            document.getElementById('edit_last_name').value = currentClientData.last_name || '';
            document.getElementById('edit_client_type').value = currentClientData.client_type || 'Commercial';
            document.getElementById('edit_client_status').value = currentClientData.status || 'active'; // Set current status
            document.getElementById('edit_email').value = currentClientData.email || '';
            document.getElementById('edit_phone').value = currentClientData.phone_number || '';
            document.getElementById('edit_street_address').value = currentClientData.street_address || '';
            document.getElementById('edit_barangay').value = currentClientData.barangay || '';
            document.getElementById('edit_city').value = currentClientData.city || 'Davao City';

            document.getElementById('editClientModal').classList.remove('hidden');
        }

        function closeEditClientModal() {
            document.getElementById('editClientModal').classList.add('hidden');
        }

        function fetchClientContracts(clientId) {
            const tbody = document.getElementById('modalContractsTableBody');
            const actionContainer = document.getElementById('clientActionContainer');
            tbody.innerHTML = `<tr><td colspan="6" class="py-4 text-center text-slate-400 italic">Loading contracts...</td></tr>`;
            actionContainer.innerHTML = '';

            fetch(`../controllers/getClientContracts.php?client_id=${clientId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.contracts.length > 0) {
                        let rows = '';
                        let hasRemainingBalance = false;

                        data.contracts.forEach(con => {
                            let badge = '<span class="bg-emerald-50 text-[#007a55] border border-emerald-200 px-2 py-0.5 rounded text-[10px] font-semibold">Active</span>';
                            if (con.contract_status === 'to_be_contracted') badge = '<span class="bg-slate-100 text-slate-600 border border-slate-200 px-2 py-0.5 rounded text-[10px] font-semibold">To-Be-Contracted</span>';
                            else if (con.contract_status === 'expiring_soon') badge = '<span class="bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 rounded text-[10px] font-semibold">Expiring Soon</span>';
                            else if (con.contract_status === 'expired') badge = '<span class="bg-rose-50 text-rose-700 border border-rose-200 px-2 py-0.5 rounded text-[10px] font-semibold">Expired</span>';
                            else if (con.contract_status === 'cancelled') badge = '<span class="bg-purple-50 text-purple-700 border border-purple-200 px-2 py-0.5 rounded text-[10px] font-semibold">Cancelled</span>';
                            else if (con.contract_status === 'archived') badge = '<span class="bg-slate-200 text-slate-700 border border-slate-300 px-2 py-0.5 rounded text-[10px] font-semibold">Archived</span>';

                            if (parseFloat(con.final_balance || 0) > 0) {
                                hasRemainingBalance = true;
                            }

                            rows += `
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="py-2.5 font-mono text-slate-500">#CONTRACT-${con.id}</td>
                                    <td class="py-2.5 font-semibold text-slate-900">${con.contract_name || 'Standard Contract'}</td>
                                    <td class="py-2.5">${badge}</td>
                                    <td class="py-2.5 text-slate-600">${con.contract_start_date || 'Not set'} to ${con.contract_end_date || 'Not set'}</td>
                                    <td class="py-2.5 font-mono text-right font-medium text-slate-800">₱${Number(con.contract_value || 0).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                                    <td class="py-2.5 text-right">
                                        <a href="payments.php?search=CONTRACT-${con.id}" class="text-emerald-700 hover:text-emerald-900 font-medium text-[11px] px-2 py-0.5 bg-emerald-50 border border-emerald-200/60 rounded transition">
                                            Payments
                                        </a>
                                    </td>
                                </tr>
                            `;
                        });
                        tbody.innerHTML = rows;

                        // Action button condition based on contracts & remaining final balance
                        if (!hasRemainingBalance) {
                            actionContainer.innerHTML = `
                                <a href="../controllers/archiveClient.php?id=${clientId}" onclick="return confirm('Are you sure you want to archive this client?');" class="bg-slate-100 text-slate-600 hover:bg-slate-200 border border-slate-200 px-3 py-1 rounded-lg text-xs font-semibold flex items-center gap-1 transition">
                                    <i data-lucide="archive" class="w-3.5 h-3.5"></i> Archive Client
                                </a>
                            `;
                        } else {
                            actionContainer.innerHTML = `
                                <button disabled title="Cannot delete or archive while contracts have an outstanding balance" class="bg-slate-100 text-slate-400 border border-slate-200 px-3 py-1 rounded-lg text-xs font-semibold flex items-center gap-1 cursor-not-allowed">
                                    <i data-lucide="lock" class="w-3.5 h-3.5"></i> Has Balance
                                </button>
                            `;
                        }
                    } else {
                        tbody.innerHTML = `<tr><td colspan="6" class="py-4 text-center text-slate-400 italic">No contracts recorded for this client yet.</td></tr>`;

                        // If no contracts exist, allow full deletion
                        actionContainer.innerHTML = `
                            <a href="../controllers/deleteClient.php?id=${clientId}" onclick="return confirm('Are you sure you want to permanently delete this client?');" class="bg-rose-50 text-rose-600 hover:bg-rose-100 border border-rose-200 px-3 py-1 rounded-lg text-xs font-semibold flex items-center gap-1 transition">
                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Delete Client
                            </a>
                        `;
                    }
                    lucide.createIcons();
                })
                .catch(err => {
                    tbody.innerHTML = `<tr><td colspan="6" class="py-4 text-center text-rose-500">Failed to load contracts.</td></tr>`;
                });
        }

        function openCreateContractModal() {
            if (selectedActiveClientId) {
                document.getElementById('contract_modal_client_id').value = selectedActiveClientId;
            }
            document.getElementById('createContractModal').classList.remove('hidden');
        }

        function closeCreateContractModal() {
            document.getElementById('createContractModal').classList.add('hidden');
        }

        function openAddContractForClient() {
            closeViewModal();
            openCreateContractModal();
        }

        function openEditContractModal(con) {
            document.getElementById('edit_contract_id').value = con.id;
            document.getElementById('edit_contract_name').value = con.contract_name || '';
            document.getElementById('edit_contract_status').value = con.contract_status || 'active';
            document.getElementById('edit_contract_value').value = con.contract_value || 0;
            document.getElementById('edit_contract_start').value = con.contract_start_date || '';
            document.getElementById('edit_contract_end').value = con.contract_end_date || '';
            document.getElementById('editContractModal').classList.remove('hidden');
        }

        function closeEditContractModal() {
            document.getElementById('editContractModal').classList.add('hidden');
        }
    </script>
</body>

</html>