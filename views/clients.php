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

// 1. Fetch Clients Data
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
}
$clientQuery .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($clientQuery);
$stmt->execute($clientParams);
$clients = $stmt->fetchAll();

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
    $contractQuery .= " AND (con.contract_name LIKE :c_search OR c.client_name LIKE :c_search OR con.contract_status LIKE :c_search)";
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
$totalClients = count($clients);
$activeClients = 0;
$commercialCount = 0;
$residentialCount = 0;

foreach ($clients as $c) {
    if (($c['status'] ?? '') === 'active') $activeClients++;
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
                    <div class="flex items-center gap-2 text-[11px] text-[#007a55] font-semibold tracking-wider uppercase mb-1">
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
                        <button onclick="toggleClientForm()" class="bg-[#007a55] hover:bg-[#006344] text-white font-medium text-xs px-4 py-2 rounded-lg flex items-center gap-2 transition shadow-sm">
                            <i data-lucide="building" class="w-4 h-4"></i> Register New Client
                        </button>
                    <?php else: ?>
                        <button onclick="openCreateContractModal()" class="bg-[#007a55] hover:bg-[#006344] text-white font-medium text-xs px-4 py-2 rounded-lg flex items-center gap-2 transition shadow-sm">
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
                            <p class="text-[11px] text-emerald-600 font-medium mt-1"><?= $activeClients ?> Active Status Accounts</p>
                        </div>
                        <div class="bg-emerald-50 p-2.5 rounded-lg text-[#007a55] border border-emerald-100">
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
                                    <input type="text" name="company_name" required placeholder="e.g. Marco Polo Hotel Davao" class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55]">
                                </div>
                                <div>
                                    <label class="block text-slate-600 font-medium mb-1">Contact First Name *</label>
                                    <input type="text" name="first_name" required placeholder="First Name" class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55]">
                                </div>
                                <div>
                                    <label class="block text-slate-600 font-medium mb-1">Contact Last Name *</label>
                                    <input type="text" name="last_name" required placeholder="Last Name" class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55]">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-slate-600 font-medium mb-1">Account Type *</label>
                                    <select name="client_type" required class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55]">
                                        <option value="Commercial">Commercial</option>
                                        <option value="Residential">Residential</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-slate-600 font-medium mb-1">Email Address *</label>
                                    <input type="email" name="email" required placeholder="contact@company.com" class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55]">
                                </div>
                                <div>
                                    <label class="block text-slate-600 font-medium mb-1">Phone Number *</label>
                                    <input type="text" name="phone" required placeholder="+63 9XX XXX XXXX" class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55]">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-slate-600 font-medium mb-1">Street Address *</label>
                                    <input type="text" name="street_address" required placeholder="e.g. Door 4, Prieto Bldg" class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55]">
                                </div>
                                <div>
                                    <label class="block text-slate-600 font-medium mb-1">Barangay *</label>
                                    <input type="text" name="barangay" required placeholder="e.g. Brgy. 27-C" class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55]">
                                </div>
                                <div>
                                    <label class="block text-slate-600 font-medium mb-1">City *</label>
                                    <input type="text" name="city" required value="Davao City" class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55]">
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-3 pt-2">
                                <button type="button" onclick="toggleClientForm()" class="bg-white text-slate-600 px-4 py-2 rounded-lg border border-slate-200 font-medium">Cancel</button>
                                <button type="submit" class="bg-[#007a55] hover:bg-[#006344] text-white font-medium px-4 py-2 rounded-lg shadow-sm">Register Client Account</button>
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
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, company, email..." class="w-full border border-slate-200 text-xs rounded-lg pl-8 pr-3 py-1.5 focus:outline-none focus:border-[#007a55]">
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
                            </select>
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
                                        $typeClass = ($client['client_type'] ?? '') === 'Residential' ? "bg-emerald-50 text-[#007a55] border-emerald-200/60" : "bg-blue-50 text-blue-700 border-blue-200/60";
                                        $statusBadge = ($client['status'] ?? 'active') === 'active'
                                            ? '<span class="bg-emerald-50 text-[#007a55] border border-emerald-200/60 px-2 py-0.5 rounded text-[10px] font-semibold">Active</span>'
                                            : '<span class="bg-slate-100 text-slate-500 border border-slate-200 px-2 py-0.5 rounded text-[10px] font-semibold">Disabled</span>';
                                        $fullAddress = trim(($client['street_address'] ?? '') . ', ' . ($client['barangay'] ?? '') . ', ' . ($client['city'] ?? 'Davao City'));
                                        ?>
                                        <tr class="hover:bg-slate-50/80 transition">
                                            <td class="py-3 px-1">
                                                <button onclick='openViewModal(<?= json_encode($client) ?>)' class="font-semibold text-slate-900 hover:text-[#007a55] hover:underline text-left transition">
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
                                                <button onclick='openViewModal(<?= json_encode($client) ?>)' class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-2.5 py-1 rounded-lg text-xs font-medium transition">View Profile</button>
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
                            <p class="text-[11px] text-emerald-600 font-medium mt-1"><?= $activeContractsCount ?> Active Service Contracts</p>
                        </div>
                        <div class="bg-emerald-50 p-2.5 rounded-lg text-[#007a55] border border-emerald-100">
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
                                <input type="text" name="c_search" value="<?= htmlspecialchars($contractSearch) ?>" placeholder="Search contract or client..." class="w-full border border-slate-200 text-xs rounded-lg pl-8 pr-3 py-1.5 focus:outline-none focus:border-[#007a55]">
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
                                        $cStatusBadge = '<span class="bg-emerald-50 text-[#007a55] border border-emerald-200 px-2 py-0.5 rounded text-[10px] font-semibold">Active</span>';
                                        if ($con['contract_status'] === 'to_be_contracted') $cStatusBadge = '<span class="bg-slate-100 text-slate-600 border border-slate-200 px-2 py-0.5 rounded text-[10px] font-semibold">To-Be-Contracted</span>';
                                        elseif ($con['contract_status'] === 'expiring_soon') $cStatusBadge = '<span class="bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 rounded text-[10px] font-semibold">Expiring Soon</span>';
                                        elseif ($con['contract_status'] === 'expired') $cStatusBadge = '<span class="bg-rose-50 text-rose-700 border border-rose-200 px-2 py-0.5 rounded text-[10px] font-semibold">Expired</span>';
                                        elseif ($con['contract_status'] === 'cancelled') $cStatusBadge = '<span class="bg-purple-50 text-purple-700 border border-purple-200 px-2 py-0.5 rounded text-[10px] font-semibold">Cancelled</span>';
                                        elseif ($con['contract_status'] === 'archived') $cStatusBadge = '<span class="bg-slate-200 text-slate-700 border border-slate-300 px-2 py-0.5 rounded text-[10px] font-semibold">Archived</span>';

                                        // Determine client type badge style
                                        $clientTypeBadge = ($con['client_type'] ?? '') === 'Residential'
                                            ? '<span class="bg-emerald-50 text-[#007a55] border border-emerald-200/60 px-2 py-0.5 rounded text-[10px] font-medium">Residential</span>'
                                            : '<span class="bg-blue-50 text-blue-700 border border-blue-200/60 px-2 py-0.5 rounded text-[10px] font-medium">Commercial</span>';
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
                                                <div class="text-[11px] text-slate-700"><?= $con['contract_start_date'] ?: 'Not set' ?> to <?= $con['contract_end_date'] ?: 'Not set' ?></div>
                                            </td>
                                            <td class="py-3 font-mono font-medium text-slate-800">
                                                ₱<?= number_format($con['contract_value'] ?? 0, 2) ?>
                                            </td>
                                            <td class="py-3 font-mono font-medium text-rose-600">
                                                ₱<?= number_format($con['final_balance_notes'] ?? 0, 2) ?>
                                            </td>
                                            <td class="py-3 font-mono text-slate-500 text-[11px]"><?= $con['created_at'] ?></td>
                                            <td class="py-3 text-right">
    <?php if ($isArchivedView): ?>
        <a href="../controllers/restoreContract.php?id=<?= $con['id'] ?>" onclick="return confirm('Are you sure you want to restore this contract?');" class="text-emerald-700 hover:text-emerald-900 font-medium text-xs px-2.5 py-1 bg-emerald-50 border border-emerald-200 rounded transition">Restore</a>
    <?php else: ?>
        <!-- Conditional Pre-Contract Inspection Button (Only for to_be_contracted) -->
        <?php if (($con['contract_status'] ?? '') === 'to_be_contracted'): ?>
            <a href="inspections.php?contract_id=<?= $con['id'] ?>&client_id=<?= $con['client_id_ref'] ?>" class="text-emerald-700 hover:text-emerald-900 font-medium text-xs px-2 py-1 bg-emerald-50 border border-emerald-200 rounded transition mr-1 inline-flex items-center gap-1" title="Schedule Pre-Contract Inspection">
                <i data-lucide="clipboard-check" class="w-3 h-3"></i> Inspection
            </a>
        <?php endif; ?>

        <button onclick='openEditContractModal(<?= json_encode($con) ?>)' class="text-blue-600 hover:text-blue-800 font-medium text-xs px-2 py-1 bg-blue-50 rounded transition mr-1">Edit</button>
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
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 border border-emerald-200/60 flex items-center justify-center text-[#007a55]">
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
                    <!-- Client Details Card (As Is) -->
                    <div class="bg-white border border-slate-200 rounded-xl p-5 space-y-4 shadow-sm">
                        <div class="flex items-center gap-2 text-slate-800 font-bold border-b border-slate-100 pb-2">
                            <i data-lucide="user" class="w-4 h-4 text-blue-600"></i> Client Details
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
                                <i data-lucide="file-text" class="w-4 h-4 text-[#007a55]"></i> Contracts Summary & History
                            </div>
                            <button type="button" onclick="openAddContractForClient()" class="bg-[#007a55] hover:bg-[#006344] text-white text-[11px] px-3 py-1.5 rounded-lg flex items-center gap-1.5 transition font-medium shadow-sm">
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
                                    </tr>
                                </thead>
                                <tbody id="modalContractsTableBody" class="divide-y divide-slate-100">
                                    <tr>
                                        <td colspan="5" class="py-4 text-center text-slate-400 italic">Loading contracts...</td>
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

        <!-- Add / Create Contract Modal -->
        <div id="createContractModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
            <div class="bg-white border border-slate-200 rounded-xl p-6 max-w-md w-full space-y-4 shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-semibold text-slate-900 flex items-center gap-2">
                        <i data-lucide="file-plus" class="w-4 h-4 text-[#007a55]"></i> Create New Contract
                    </h3>
                    <button onclick="closeCreateContractModal()" class="text-slate-400 hover:text-slate-700">&times;</button>
                </div>

                <form action="../controllers/createContract.php" method="POST" class="space-y-3.5 text-xs">
                    <!-- Auto-generated / Hidden ID info display -->
                    <div class="bg-slate-50 p-2.5 rounded-lg border border-slate-200 text-[11px] text-slate-600 flex justify-between">
                        <span>Contract ID: <strong class="font-mono text-slate-900">Auto-Generated</strong></span>
                        <span>Status: <strong class="text-[#007a55]">To-Be-Contracted</strong></span>
                    </div>

                    <div>
                        <label class="block text-slate-600 font-medium mb-1">Select Client *</label>
                        <select name="client_id" id="contract_modal_client_id" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-xs focus:outline-none focus:border-[#007a55]">
                            <option value="">-- Choose Client Account --</option>
                            <?php foreach ($clients as $cl): ?>
                                <option value="<?= $cl['id'] ?>"><?= htmlspecialchars($cl['client_name']) ?> (ID: #<?= $cl['id'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-600 font-medium mb-1">Contract Name *</label>
                        <input type="text" name="contract_name" required placeholder="e.g. Annual Pest Maintenance 2026" class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55]">
                    </div>

                    <div>
                        <label class="block text-slate-600 font-medium mb-1">Contract Value (₱) *</label>
                        <input type="number" step="0.01" name="contract_value" required placeholder="0.00" class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55]">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-600 font-medium mb-1">Start Date</label>
                            <input type="date" name="start_date" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-slate-400 focus:outline-none focus:border-[#007a55]" placeholder="Not Set">
                        </div>
                        <div>
                            <label class="block text-slate-600 font-medium mb-1">Expiry Date</label>
                            <input type="date" name="end_date" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-slate-400 focus:outline-none focus:border-[#007a55]" placeholder="Not Set">
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                        <button type="button" onclick="closeCreateContractModal()" class="bg-white text-slate-600 px-4 py-2 rounded-lg border border-slate-200 font-medium">Cancel</button>
                        <button type="submit" class="bg-[#007a55] hover:bg-[#006344] text-white font-medium px-4 py-2 rounded-lg shadow-sm">Save Contract</button>
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
                        <input type="text" id="edit_contract_name" name="contract_name" required class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55]">
                    </div>

                    <div>
                        <label class="block text-slate-600 font-medium mb-1">Contract Status *</label>
                        <select name="contract_status" id="edit_contract_status" required class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55]">
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
                        <input type="number" step="0.01" id="edit_contract_value" name="contract_value" required class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55]">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-600 font-medium mb-1">Start Date</label>
                            <input type="date" id="edit_contract_start" name="start_date" class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55]">
                        </div>
                        <div>
                            <label class="block text-slate-600 font-medium mb-1">Expiry Date</label>
                            <input type="date" id="edit_contract_end" name="end_date" class="w-full border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55]">
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                        <button type="button" onclick="closeEditContractModal()" class="bg-white text-slate-600 px-4 py-2 rounded-lg border border-slate-200 font-medium">Cancel</button>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg shadow-sm">Update Contract</button>
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

        function openViewModal(client) {
            selectedActiveClientId = client.id;
            document.getElementById('view_company_name').innerText = client.client_name || 'N/A';
            document.getElementById('view_contact_person').innerText = (client.last_name || '') + ', ' + (client.first_name || '');
            document.getElementById('view_client_type').innerText = client.client_type || 'Commercial';
            document.getElementById('view_email').innerText = client.email || 'N/A';
            document.getElementById('view_phone').innerText = client.phone_number || 'N/A';
            document.getElementById('view_address').innerText = [client.street_address, client.barangay, client.city].filter(Boolean).join(', ') || 'N/A';

            // Fetch all contracts for this specific client ID via AJAX or render from server array
            fetchClientContracts(client.id);

            document.getElementById('viewContractModal').classList.remove('hidden');
        }

        function closeViewModal() {
            document.getElementById('viewContractModal').classList.add('hidden');
        }

        function fetchClientContracts(clientId) {
            const tbody = document.getElementById('modalContractsTableBody');
            tbody.innerHTML = `<tr><td colspan="5" class="py-4 text-center text-slate-400 italic">Loading contracts...</td></tr>`;

            fetch(`../controllers/getClientContracts.php?client_id=${clientId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.contracts.length > 0) {
                        let rows = '';
                        data.contracts.forEach(con => {
                            let badge = '<span class="bg-emerald-50 text-[#007a55] border border-emerald-200 px-2 py-0.5 rounded text-[10px] font-semibold">Active</span>';
                            if (con.contract_status === 'to_be_contracted') badge = '<span class="bg-slate-100 text-slate-600 border px-2 py-0.5 rounded text-[10px] font-semibold">To-Be-Contracted</span>';
                            else if (con.contract_status === 'expiring_soon') badge = '<span class="bg-amber-50 text-amber-700 border px-2 py-0.5 rounded text-[10px] font-semibold">Expiring Soon</span>';
                            else if (con.contract_status === 'expired') badge = '<span class="bg-rose-50 text-rose-700 border px-2 py-0.5 rounded text-[10px] font-semibold">Expired</span>';
                            else if (con.contract_status === 'archived') badge = '<span class="bg-slate-200 text-slate-700 border px-2 py-0.5 rounded text-[10px] font-semibold">Archived</span>';

                            rows += `
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="py-2.5 font-mono text-slate-500">#CONTRACT-${con.id}</td>
                                    <td class="py-2.5 font-semibold text-slate-900">${con.contract_name || 'Standard Contract'}</td>
                                    <td class="py-2.5">${badge}</td>
                                    <td class="py-2.5 text-slate-600">${con.contract_start_date || 'Not set'} to ${con.contract_end_date || 'Not set'}</td>
                                    <td class="py-2.5 font-mono text-right font-medium text-slate-800">₱${Number(con.contract_value || 0).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                                </tr>
                            `;
                        });
                        tbody.innerHTML = rows;
                    } else {
                        tbody.innerHTML = `<tr><td colspan="5" class="py-4 text-center text-slate-400 italic">No contracts recorded for this client yet.</td></tr>`;
                    }
                })
                .catch(err => {
                    tbody.innerHTML = `<tr><td colspan="5" class="py-4 text-center text-rose-500">Failed to load contracts.</td></tr>`;
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