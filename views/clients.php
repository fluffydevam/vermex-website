<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Search and Filter parameters
$search = trim($_GET['search'] ?? '');
$filterType = trim($_GET['type'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterContractStatus = trim($_GET['contract_status'] ?? ''); // Added Contract Status Filter

// Build Dynamic SQL Query for Clients List
$query = "SELECT * FROM clients WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (client_name LIKE :search OR first_name LIKE :search OR last_name LIKE :search OR email LIKE :search OR phone_number LIKE :search OR street_address LIKE :search OR barangay LIKE :search)";
    $params['search'] = "%{$search}%";
}

if (!empty($filterType)) {
    $query .= " AND client_type = :type";
    $params['type'] = $filterType;
}

if (!empty($filterStatus)) {
    $query .= " AND status = :status";
    $params['status'] = $filterStatus;
}

// Added Contract Status Condition to SQL Query
if (!empty($filterContractStatus)) {
    $query .= " AND contract_status = :contract_status";
    $params['contract_status'] = $filterContractStatus;
}

$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$clients = $stmt->fetchAll();

// Metrics & KPI Calculations
$totalClients = count($clients);
$activeClients = 0;
$pendingRenewals = 0;
$commercialCount = 0;

foreach ($clients as $c) {
    if (($c['status'] ?? '') === 'active') {
        $activeClients++;
    }
    if (($c['contract_status'] ?? '') === 'expiring_soon') {
        $pendingRenewals++;
    }
    if (($c['client_type'] ?? '') === 'Commercial') {
        $commercialCount++;
    }
}

// --- ADD STEP 1 HANDLER HERE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_inspection_from_client'])) {
    try {
        $clientName = trim($_POST['client_name'] ?? '');
        $clientEmail = trim($_POST['client_email'] ?? '');
        $accountType = trim($_POST['account_type'] ?? 'Residential');
        $serviceAddress = trim($_POST['service_address'] ?? '');

        // Insert into site_inspections table as 'Pending Visit'
        $stmt = $pdo->prepare("
            INSERT INTO site_inspections (client_name, client_email, account_type, service_address, inspection_status) 
            VALUES (?, ?, ?, ?, 'Pending Visit')
        ");
        $stmt->execute([$clientName, $clientEmail, $accountType, $serviceAddress]);

        header("Location: inspections.php?success=inspection_created");
        exit;
    } catch (Exception $e) {
        $errorMessage = "Failed to create site inspection: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vermex - Client & Contract Management</title>

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

            <!-- Session Notification Alerts -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg text-xs flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-2">
                        <i data-lucide="check-circle" class="w-4 h-4 text-[#007a55]"></i>
                        <span><?= htmlspecialchars($_SESSION['success']) ?></span>
                    </div>
                    <button onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-900">&times;</button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-lg text-xs flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-2">
                        <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600"></i>
                        <span><?= htmlspecialchars($_SESSION['error']) ?></span>
                    </div>
                    <button onclick="this.parentElement.remove()" class="text-rose-600 hover:text-rose-900">&times;</button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Page Header -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-200 pb-5">
                <div>
                    <div class="flex items-center gap-2 text-[11px] text-[#007a55] font-semibold tracking-wider uppercase mb-1">
                        <span>CRM & Operations</span>
                        <span>•</span>
                        <span>Client Accounts</span>
                    </div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Clients & Contracts Dashboard</h1>
                    <p class="text-xs text-slate-500 mt-1">Manage commercial and residential accounts, track pest control service contracts, and register service locations.</p>
                </div>

                <div class="flex items-center gap-3">
                    <button onclick="toggleClientForm()" class="bg-[#007a55] hover:bg-[#006344] text-white font-medium text-xs px-4 py-2 rounded-lg flex items-center gap-2 transition shadow-sm">
                        <i data-lucide="building" class="w-4 h-4"></i>
                        <span>Register New Client</span>
                    </button>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white border border-slate-200/80 rounded-xl p-4 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total Client Accounts</p>
                        <p class="text-2xl font-bold text-slate-900 mt-1"><?= $totalClients ?> <span class="text-xs font-normal text-slate-500">Clients</span></p>
                        <p class="text-[11px] text-emerald-600 font-medium mt-1"><?= $activeClients ?> Active Contracts</p>
                    </div>
                    <div class="bg-emerald-50 p-2.5 rounded-lg text-[#007a55] border border-emerald-100">
                        <i data-lucide="briefcase" class="w-5 h-5"></i>
                    </div>
                </div>

                <div class="bg-white border border-slate-200/80 rounded-xl p-4 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Commercial Clients</p>
                        <p class="text-2xl font-bold text-slate-900 mt-1"><?= $commercialCount ?> <span class="text-xs font-normal text-slate-500">Accounts</span></p>
                        <p class="text-[11px] text-blue-600 font-medium mt-1">Key Enterprise Accounts</p>
                    </div>
                    <div class="bg-blue-50 p-2.5 rounded-lg text-blue-600 border border-blue-100">
                        <i data-lucide="store" class="w-5 h-5"></i>
                    </div>
                </div>

                <div class="bg-white border border-slate-200/80 rounded-xl p-4 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Expiring / Renewals</p>
                        <p class="text-2xl font-bold text-amber-600 mt-1"><?= $pendingRenewals ?> <span class="text-xs font-normal text-slate-500">Pending</span></p>
                        <p class="text-[11px] text-amber-600 font-medium mt-1">Requires renewal review</p>
                    </div>
                    <div class="bg-amber-50 p-2.5 rounded-lg text-amber-600 border border-amber-100">
                        <i data-lucide="clock" class="w-5 h-5"></i>
                    </div>
                </div>

                <div class="bg-white border border-slate-200/80 rounded-xl p-4 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Active Status</p>
                        <p class="text-2xl font-bold text-slate-900 mt-1"><?= $activeClients ?> <span class="text-xs font-normal text-slate-500">Active</span></p>
                        <p class="text-[11px] text-purple-600 font-medium mt-1">Verified Accounts</p>
                    </div>
                    <div class="bg-purple-50 p-2.5 rounded-lg text-purple-600 border border-purple-100">
                        <i data-lucide="shield-check" class="w-5 h-5"></i>
                    </div>
                </div>
            </div>

            <!-- Registration Form Panel (Initially Hidden) -->
            <div id="clientCreationSection" class="hidden grid grid-cols-1 gap-6">
                <div class="bg-white border border-slate-200/80 rounded-xl p-5 space-y-4 shadow-sm">
                    <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-2 text-[10px] text-[#007a55] font-semibold tracking-wider uppercase">
                                <i data-lucide="file-plus" class="w-3.5 h-3.5"></i> Client Account Onboarding
                            </div>
                            <h2 class="text-base font-semibold text-slate-900 mt-0.5">Register New Client & Service Contract</h2>
                            <p class="text-[11px] text-slate-400">Configure client business profile, standardized service location, and billing details.</p>
                        </div>
                        <button type="button" onclick="toggleClientForm()" class="text-slate-400 hover:text-slate-700 transition"><i data-lucide="x" class="w-5 h-5"></i></button>
                    </div>

                    <form action="../controllers/createClient.php" method="POST" class="space-y-3.5 text-xs">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-slate-600 font-medium mb-1">Company / Account Name *</label>
                                <input type="text" name="company_name" required placeholder="e.g. Marco Polo Hotel Davao" class="w-full bg-white border border-slate-200 text-slate-800 placeholder-slate-400 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55] transition">
                            </div>
                            <div>
                                <label class="block text-slate-600 font-medium mb-1">Contact First Name *</label>
                                <input type="text" name="first_name" required placeholder="First Name" class="w-full bg-white border border-slate-200 text-slate-800 placeholder-slate-400 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55] transition">
                            </div>
                            <div>
                                <label class="block text-slate-600 font-medium mb-1">Contact Last Name *</label>
                                <input type="text" name="last_name" required placeholder="Last Name" class="w-full bg-white border border-slate-200 text-slate-800 placeholder-slate-400 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55] transition">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-slate-600 font-medium mb-1">Account Type *</label>
                                <select name="client_type" required class="w-full bg-white border border-slate-200 text-slate-800 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55] transition">
                                    <option value="Commercial">Commercial</option>
                                    <option value="Residential">Residential</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-slate-600 font-medium mb-1">Email Address *</label>
                                <input type="email" name="email" required placeholder="contact@company.com" class="w-full bg-white border border-slate-200 text-slate-800 placeholder-slate-400 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55] transition">
                            </div>
                            <div>
                                <label class="block text-slate-600 font-medium mb-1">Phone Number *</label>
                                <input type="text" name="phone" required placeholder="+63 9XX XXX XXXX" class="w-full bg-white border border-slate-200 text-slate-800 placeholder-slate-400 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55] transition">
                            </div>
                        </div>

                        <!-- Standardized Address Fields -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-slate-600 font-medium mb-1">Street Address *</label>
                                <input type="text" name="street_address" required placeholder="e.g. Door 4, Prieto Bldg" class="w-full bg-white border border-slate-200 text-slate-800 placeholder-slate-400 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55] transition">
                            </div>
                            <div>
                                <label class="block text-slate-600 font-medium mb-1">Barangay *</label>
                                <input type="text" name="barangay" required placeholder="e.g. Brgy. 27-C" class="w-full bg-white border border-slate-200 text-slate-800 placeholder-slate-400 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55] transition">
                            </div>
                            <div>
                                <label class="block text-slate-600 font-medium mb-1">City *</label>
                                <input type="text" name="city" required value="Davao City" class="w-full bg-white border border-slate-200 text-slate-800 placeholder-slate-400 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55] transition">
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-2">
                            <button type="button" onclick="toggleClientForm()" class="bg-white hover:bg-slate-50 text-slate-600 px-4 py-2 rounded-lg border border-slate-200 transition font-medium">Cancel</button>
                            <button type="submit" class="bg-[#007a55] hover:bg-[#006344] text-white font-medium px-4 py-2 rounded-lg transition flex items-center gap-1.5 shadow-sm">
                                <i data-lucide="check" class="w-3.5 h-3.5"></i>
                                Register Client Account
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Client Accounts Table -->
            <div class="bg-white border border-slate-200/80 rounded-xl p-5 space-y-4 shadow-sm">
                <form method="GET" action="clients.php" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Registered Accounts</h2>
                        <p class="text-[11px] text-slate-400 mt-0.5"><?= $totalClients ?> accounts found • <?= $activeClients ?> active</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <div class="relative flex-1 sm:w-56">
                            <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, company, email..." class="w-full bg-white border border-slate-200 text-slate-800 placeholder-slate-400 text-xs rounded-lg pl-8 pr-3 py-1.5 focus:outline-none focus:border-[#007a55] transition">
                        </div>
                        <select name="type" onchange="this.form.submit()" class="bg-white border border-slate-200 text-slate-700 text-xs rounded-lg px-2.5 py-1.5 focus:outline-none focus:border-[#007a55] transition font-medium">
                            <option value="">Type: All</option>
                            <option value="Commercial" <?= $filterType === 'Commercial' ? 'selected' : '' ?>>Commercial</option>
                            <option value="Residential" <?= $filterType === 'Residential' ? 'selected' : '' ?>>Residential</option>
                        </select>
                        <select name="status" onchange="this.form.submit()" class="bg-white border border-slate-200 text-slate-700 text-xs rounded-lg px-2.5 py-1.5 focus:outline-none focus:border-[#007a55] transition font-medium">
                            <option value="">Status: All</option>
                            <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                        <!-- Added Contract Status Filter Dropdown -->
                        <select name="contract_status" onchange="this.form.submit()" class="bg-white border border-slate-200 text-slate-700 text-xs rounded-lg px-2.5 py-1.5 focus:outline-none focus:border-[#007a55] transition font-medium">
                            <option value="">Contract: All</option>
                            <option value="active" <?= $filterContractStatus === 'active' ? 'selected' : '' ?>>Active Service</option>
                            <option value="to_be_contracted" <?= $filterContractStatus === 'to_be_contracted' ? 'selected' : '' ?>>To-Be-Contracted</option>
                            <option value="expiring_soon" <?= $filterContractStatus === 'expiring_soon' ? 'selected' : '' ?>>Expiring Soon</option>
                            <option value="cancelled" <?= $filterContractStatus === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            <option value="expired" <?= $filterContractStatus === 'expired' ? 'selected' : '' ?>>Expired</option>
                        </select>
                    </div>
                </form>

                <div class="overflow-x-auto overflow-y-auto max-h-[400px]">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-[10px] uppercase text-slate-400 border-b border-slate-100">
                                <th class="pb-2 font-semibold">Client Name & Contact</th>
                                <th class="pb-2 font-semibold">Type</th>
                                <th class="pb-2 font-semibold">Standardized Address</th>
                                <th class="pb-2 font-semibold">Contract Status</th>
                                <th class="pb-2 font-semibold">Account Status</th>
                                <th class="pb-2 font-semibold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($clients)): ?>
                                <tr>
                                    <td colspan="6" class="py-6 text-center text-slate-400">No client records found matching your query.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($clients as $client): ?>
                                    <?php
                                    $typeClass = "bg-blue-50 text-blue-700 border-blue-200/60";
                                    if (($client['client_type'] ?? '') === 'Residential') {
                                        $typeClass = "bg-emerald-50 text-[#007a55] border-emerald-200/60";
                                    }

                                    $contractStatusBadge = '<span class="bg-emerald-50 text-[#007a55] border border-emerald-200/60 px-2 py-0.5 rounded text-[10px] font-semibold">Active</span>';

                                    if (($client['contract_status'] ?? '') === 'to_be_contracted') {
                                        $contractStatusBadge = '<span class="bg-slate-100 text-slate-600 border border-slate-300 px-2 py-0.5 rounded text-[10px] font-semibold">To-Be-Contracted</span>';
                                    } elseif (($client['contract_status'] ?? '') === 'expiring_soon') {
                                        $contractStatusBadge = '<span class="bg-amber-50 text-amber-700 border border-amber-200/60 px-2 py-0.5 rounded text-[10px] font-semibold">Expiring Soon</span>';
                                    } elseif (($client['contract_status'] ?? '') === 'cancelled') {
                                        $contractStatusBadge = '<span class="bg-purple-50 text-purple-700 border border-purple-200/60 px-2 py-0.5 rounded text-[10px] font-semibold" title="' . htmlspecialchars($client['final_balance_notes'] ?? '') . '">Cancelled</span>';
                                    } elseif (($client['contract_status'] ?? '') === 'expired') {
                                        $contractStatusBadge = '<span class="bg-rose-50 text-rose-700 border border-rose-200/60 px-2 py-0.5 rounded text-[10px] font-semibold">Expired</span>';
                                    }

                                    $statusBadge = ($client['status'] ?? 'active') === 'active'
                                        ? '<span class="bg-emerald-50 text-[#007a55] border border-emerald-200/60 px-2 py-0.5 rounded text-[10px] font-semibold">Active</span>'
                                        : '<span class="bg-slate-100 text-slate-500 border border-slate-200 px-2 py-0.5 rounded text-[10px] font-semibold">Disabled</span>';

                                    $fullAddress = trim(($client['street_address'] ?? '') . ', ' . ($client['barangay'] ?? '') . ', ' . ($client['city'] ?? 'Davao City'));
                                    ?>
                                    <tr class="hover:bg-slate-50/80 transition">
                                        <td class="py-3 px-1">
                                            <div>
                                                <button onclick='openViewModal(<?= json_encode($client) ?>)' class="font-semibold text-slate-900 text-xs hover:text-[#007a55] hover:underline text-left transition">
                                                    <?= htmlspecialchars($client['client_name']) ?>
                                                </button>
                                                <div class="text-[11px] text-slate-500 font-medium mt-0.5">
                                                    <?= htmlspecialchars(($client['last_name'] ?? '') . ', ' . ($client['first_name'] ?? '')) ?> • <span class="font-mono text-slate-400"><?= htmlspecialchars($client['phone_number'] ?? '') ?></span>
                                                </div>
                                                <div class="text-[10px] text-slate-400 font-mono">
                                                    <?= htmlspecialchars($client['email'] ?? '') ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3">
                                            <span class="<?= $typeClass ?> border px-2 py-0.5 rounded text-[10px] font-medium">
                                                <?= htmlspecialchars($client['client_type'] ?? 'Commercial') ?>
                                            </span>
                                        </td>
                                        <td class="py-3">
                                            <div class="text-[11px] text-slate-700 font-medium truncate max-w-xs"><?= htmlspecialchars($fullAddress) ?></div>
                                            <div class="text-[10px] text-slate-400"><?= htmlspecialchars($client['barangay'] ?? '') ?>, <?= htmlspecialchars($client['city'] ?? 'Davao City') ?></div>
                                        </td>
                                        <td class="py-3"><?= $contractStatusBadge ?></td>
                                        <td class="py-3"><?= $statusBadge ?></td>
                                        <td class="py-3 text-right relative">
                                            <button onclick="toggleActionMenu(<?= $client['id'] ?>)" class="text-slate-400 hover:text-slate-700 p-1.5 rounded-lg hover:bg-slate-100 transition">
                                                <i data-lucide="more-vertical" class="w-4 h-4"></i>
                                            </button>

                                            <!-- Dropdown Menu -->
                                            <div id="action-menu-<?= $client['id'] ?>" class="hidden absolute right-0 mt-1 w-48 bg-white border border-slate-200 rounded-lg shadow-lg z-20 py-1 text-left">
                                                <button onclick='openEditModal(<?= json_encode($client) ?>)' class="w-full px-3 py-2 text-xs text-slate-700 hover:bg-slate-50 flex items-center gap-2 transition font-medium">
                                                    <i data-lucide="edit-3" class="w-3.5 h-3.5 text-[#007a55]"></i> Edit Client Info
                                                </button>

                                                <button onclick='openContractModal(<?= json_encode($client) ?>)' class="w-full px-3 py-2 text-xs text-slate-700 hover:bg-slate-50 flex items-center gap-2 transition font-medium">
                                                    <i data-lucide="file-text" class="w-3.5 h-3.5 text-blue-600"></i> Manage Contract
                                                </button>

                                                <div class="border-t border-slate-100 my-1"></div>

                                                <form action="../controllers/toggleClientStatus.php" method="POST" onsubmit="return confirm('Change client status?');">
                                                    <input type="hidden" name="client_id" value="<?= $client['id'] ?>">
                                                    <input type="hidden" name="current_status" value="<?= $client['status'] ?? 'active' ?>">
                                                    <button type="submit" class="w-full px-3 py-2 text-xs flex items-center gap-2 transition font-medium <?= ($client['status'] ?? 'active') === 'active' ? 'text-rose-600 hover:bg-rose-50' : 'text-[#007a55] hover:bg-emerald-50' ?>">
                                                        <i data-lucide="<?= ($client['status'] ?? 'active') === 'active' ? 'slash' : 'check-circle' ?>" class="w-3.5 h-3.5"></i>
                                                        <?= ($client['status'] ?? 'active') === 'active' ? 'Deactivate Account' : 'Activate Account' ?>
                                                    </button>
                                                </form>

                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- Edit Client Modal -->
        <div id="editModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4 overflow-y-auto">
            <div class="bg-white border border-slate-200 rounded-xl p-6 max-w-lg w-full space-y-4 shadow-xl relative">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-semibold text-slate-900 flex items-center gap-2">
                        <i data-lucide="edit" class="w-4 h-4 text-[#007a55]"></i> Edit Client Details
                    </h3>
                    <button type="button" onclick="closeEditModal()" class="text-slate-400 hover:text-slate-700 text-lg">&times;</button>
                </div>

                <form action="../controllers/updateClient.php" method="POST" class="space-y-3.5 text-xs">
                    <input type="hidden" id="edit_client_id" name="client_id">

                    <div>
                        <label class="block text-slate-600 font-medium mb-1">Company Name *</label>
                        <input type="text" id="edit_company_name" name="company_name" required class="w-full bg-white border border-slate-200 text-slate-800 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55] transition">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-600 font-medium mb-1">Contact First Name *</label>
                            <input type="text" id="edit_first_name" name="first_name" required class="w-full bg-white border border-slate-200 text-slate-800 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55] transition">
                        </div>
                        <div>
                            <label class="block text-slate-600 font-medium mb-1">Contact Last Name *</label>
                            <input type="text" id="edit_last_name" name="last_name" required class="w-full bg-white border border-slate-200 text-slate-800 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55] transition">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-600 font-medium mb-1">Email *</label>
                            <input type="email" id="edit_email" name="email" required class="w-full bg-white border border-slate-200 text-slate-800 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55] transition">
                        </div>
                        <div>
                            <label class="block text-slate-600 font-medium mb-1">Phone *</label>
                            <input type="text" id="edit_phone" name="phone" required class="w-full bg-white border border-slate-200 text-slate-800 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55] transition">
                        </div>
                    </div>

                    <!-- Standardized Address Fields for Edit -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-slate-600 font-medium mb-1">Street Address *</label>
                            <input type="text" id="edit_street_address" name="street_address" required class="w-full bg-white border border-slate-200 text-slate-800 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55] transition">
                        </div>
                        <div>
                            <label class="block text-slate-600 font-medium mb-1">Barangay *</label>
                            <input type="text" id="edit_barangay" name="barangay" required class="w-full bg-white border border-slate-200 text-slate-800 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55] transition">
                        </div>
                        <div>
                            <label class="block text-slate-600 font-medium mb-1">City *</label>
                            <input type="text" id="edit_city" name="city" required class="w-full bg-white border border-slate-200 text-slate-800 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55] transition">
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                        <button type="button" onclick="closeEditModal()" class="bg-white text-slate-600 px-4 py-2 rounded-lg border border-slate-200 hover:bg-slate-50 transition font-medium">Cancel</button>
                        <button type="submit" class="bg-[#007a55] hover:bg-[#006344] text-white font-medium px-4 py-2 rounded-lg transition shadow-sm">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Client & Contract Profile Modal (Figma Style) -->
        <div id="viewContractModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4 overflow-y-auto">
            <div class="bg-white border border-slate-200 rounded-2xl max-w-4xl w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden my-auto">

                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 border border-emerald-200/60 flex items-center justify-center text-[#007a55]">
                            <i data-lucide="shield-check" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Client & Contract Profile</h3>
                            <p class="text-xs text-slate-500">Complete account, agreement and service coverage details</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span id="header_contract_badge" class="px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider"></span>
                        <button onclick="closeViewModal()" class="text-slate-400 hover:text-slate-700 p-1 rounded-lg hover:bg-slate-100 transition">
                            <i data-lucide="x" class="w-5 h-5"></i>
                        </button>
                    </div>
                </div>

                <!-- Modal Body Content -->
                <div class="p-6 overflow-y-auto space-y-6 flex-1 text-xs">

                    <!-- Grid Row 1: Client Details & Contract Summary -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                        <!-- Client Details Card -->
                        <div class="bg-white border border-slate-200 rounded-xl p-5 space-y-4 shadow-sm">
                            <div class="flex items-center gap-2 text-slate-800 font-bold border-b border-slate-100 pb-2">
                                <i data-lucide="building-2" class="w-4 h-4 text-blue-600"></i> Client Details
                            </div>

                            <div>
                                <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Company / Account Name</span>
                                <div class="flex items-center justify-between mt-0.5">
                                    <span id="view_company_name" class="text-sm font-bold text-slate-900"></span>
                                    <span id="view_client_type" class="px-2.5 py-0.5 bg-slate-100 text-slate-700 font-semibold rounded text-[10px]"></span>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Contact Person</span>
                                    <p id="view_contact_person" class="text-slate-800 font-medium mt-0.5"></p>
                                </div>
                                <div>
                                    <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Phone Number</span>
                                    <p id="view_phone" class="text-slate-800 font-mono font-medium mt-0.5"></p>
                                </div>
                            </div>

                            <div>
                                <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Email Address</span>
                                <p id="view_email" class="text-slate-800 font-mono mt-0.5"></p>
                            </div>

                            <div>
                                <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Standardized Service Property Address</span>
                                <p id="view_address" class="text-slate-800 font-medium mt-0.5"></p>
                            </div>
                        </div>

                        <!-- Contract Summary Card -->
                        <div class="bg-white border border-slate-200 rounded-xl p-5 space-y-4 shadow-sm">
                            <div class="flex items-center gap-2 text-slate-800 font-bold border-b border-slate-100 pb-2">
                                <i data-lucide="file-text" class="w-4 h-4 text-[#007a55]"></i> Contract Summary
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Contract Status</span>
                                    <p id="view_contract_status_text" class="font-bold uppercase mt-0.5 text-slate-800"></p>
                                </div>
                                <div>
                                    <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Account Status</span>
                                    <p id="view_account_status" class="font-bold uppercase mt-0.5 text-slate-800"></p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Start Date</span>
                                    <p id="view_start_date" class="font-medium text-slate-800 mt-0.5"></p>
                                </div>
                                <div>
                                    <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Expiry / Term Date</span>
                                    <p id="view_end_date" class="font-medium text-slate-800 mt-0.5"></p>
                                </div>
                            </div>

                            <!-- Cancellation Notice Box -->
                            <div id="view_balance_container" class="hidden bg-purple-50 border border-purple-200 rounded-xl p-3 space-y-1">
                                <span class="text-[10px] uppercase font-bold text-purple-700 tracking-wider flex items-center gap-1">
                                    <i data-lucide="alert-circle" class="w-3 h-3"></i> Cancellation & Payout Terms
                                </span>
                                <p id="view_final_balance" class="text-purple-900 font-medium"></p>
                            </div>

                            <div class="bg-emerald-50/60 border border-emerald-100 rounded-xl p-3 flex items-center justify-between">
                                <div>
                                    <span class="text-[10px] uppercase font-bold text-emerald-700 tracking-wider">System Record ID</span>
                                    <p id="view_client_id" class="font-mono font-bold text-slate-800"></p>
                                </div>
                                <span class="text-[10px] bg-emerald-100 text-emerald-800 font-semibold px-2 py-1 rounded">Verified Agreement</span>
                            </div>
                        </div>

                    </div>

                    <!-- Service Plan & Coverage Card -->
                    <div class="bg-white border border-slate-200 rounded-xl p-5 space-y-3 shadow-sm">
                        <div class="flex items-center gap-2 text-slate-800 font-bold border-b border-slate-100 pb-2">
                            <i data-lucide="calendar-check" class="w-4 h-4 text-amber-600"></i> Service Plan & Coverage Overview
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Service Scope</span>
                                <p class="text-slate-800 font-medium mt-0.5">Pest Control & Sanitation Maintenance</p>
                            </div>
                            <div>
                                <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Cancellation Policy</span>
                                <p class="text-slate-800 font-medium mt-0.5">Can be cancelled anytime; final billing/payout applies.</p>
                            </div>
                            <div>
                                <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Database Created Date</span>
                                <p id="view_created_at" class="text-slate-800 font-mono mt-0.5"></p>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Modal Footer Buttons -->
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <!-- Create Pre-Contract Site Inspection Form -->
                        <form method="POST" action="clients.php" class="inline">
                            <input type="hidden" name="create_inspection_from_client" value="1">
                            <input type="hidden" name="client_name" id="modalClientNameInput" value="">
                            <input type="hidden" name="client_email" id="modalClientEmailInput" value="">
                            <input type="hidden" name="account_type" id="modalAccountTypeInput" value="">
                            <input type="hidden" name="service_address" id="modalServiceAddressInput" value="">
                            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white px-4 py-2 rounded-lg text-xs font-medium transition shadow-sm flex items-center gap-1.5">
                                <i data-lucide="clipboard-list" class="w-3.5 h-3.5"></i> Create Pre-Contract Inspection
                            </button>                           
                        </form>
                        
                        <!-- Create Job Order Button with Error Logic -->
                        <button type="button" id="createJobOrderBtn" class="bg-[#007a55] hover:bg-[#006344] text-white px-4 py-2 rounded-lg text-xs font-medium transition shadow-sm flex items-center gap-1.5">
                            <i data-lucide="file-text" class="w-3.5 h-3.5"></i> Create Job Order
                        </button>

                        <!-- Inspection History Log Button -->
                        <button onclick="openInspectionHistoryModal()" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-medium px-4 py-2.5 rounded-lg flex items-center gap-2 transition">
                            <i data-lucide="history" class="w-4 h-4"></i> Inspection History Logsheet
                        </button>

                        <div class="flex items-center gap-2">
                            <button type="button" onclick='openContractModal(currentClientObject)' class="bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 px-4 py-2 rounded-xl transition font-semibold text-xs flex items-center gap-1.5">
                                <i data-lucide="file-edit" class="w-3.5 h-3.5"></i> Manage Contract & Cancellation
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- INSPECTION HISTORY LOGSHEET MODAL -->
        <div id="inspectionHistoryModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
            <div class="bg-white rounded-xl max-w-3xl w-full p-6 shadow-xl border border-slate-100 max-h-[90vh] flex flex-col">

                <!-- Modal Header -->
                <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-emerald-50 text-[#007a55] flex items-center justify-center">
                            <i data-lucide="clipboard-list" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-900 text-base">Inspection History Logsheet</h3>
                            <p class="text-xs text-slate-500">Past service and visit records for <span id="historyClientName" class="font-semibold text-slate-800">Client</span></p>
                        </div>
                    </div>
                    <button onclick="closeInspectionHistoryModal()" class="text-slate-400 hover:text-slate-600">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <!-- Logsheet Table Content -->
                <div class="overflow-y-auto flex-1 mb-4">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider bg-slate-50">
                                <th class="py-3 px-3">Date & Time</th>
                                <th class="py-3 px-3">Inspection Type</th>
                                <th class="py-3 px-3">Findings / Pests</th>
                                <th class="py-3 px-3">Assigned Tech</th>
                                <th class="py-3 px-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody id="clientHistoryTableBody" class="divide-y divide-slate-100 text-xs text-slate-700">
                            <tr>
                                <td colspan="5" class="py-6 text-center text-slate-400 italic">No inspection history records found for this client.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Modal Footer -->
                <div class="flex justify-end pt-3 border-t border-slate-100">
                    <button onclick="closeInspectionHistoryModal()" class="px-4 py-2 rounded-lg text-xs font-medium border border-slate-200 text-slate-600 hover:bg-slate-50 transition">
                        Close Logsheet
                    </button>
                </div>

            </div>
        </div>

        <!-- Manage Contract Modal -->
        <div id="contractModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
            <div class="bg-white border border-slate-200 rounded-xl p-5 max-w-md w-full space-y-4 shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-semibold text-slate-900 flex items-center gap-2">
                        <i data-lucide="file-check" class="w-4 h-4 text-blue-600"></i> Contract & SLA Settings
                    </h3>
                    <button onclick="closeContractModal()" class="text-slate-400 hover:text-slate-700 text-lg">&times;</button>
                </div>

                <form action="../controllers/updateContract.php" method="POST" class="space-y-3 text-xs">
                    <input type="hidden" id="contract_client_id" name="client_id">

                    <p class="text-slate-500">Update agreement terms for <span id="contract_client_name" class="font-bold text-slate-800"></span>.</p>

                    <div>
                        <label class="block text-slate-600 font-medium mb-1">Contract Status</label>
                        <select name="contract_status" id="editContractStatus" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#007a55]">
                            <option value="to_be_contracted">To-Be-Contracted (Pre-Inspection)</option>
                            <option value="active">Active Service</option>
                            <option value="expiring_soon">Expiring Soon (Renewal Pending)</option>
                            <option value="cancelled">Cancelled (Client Terminated - Payout Required)</option>
                            <option value="expired">Expired / Terminated</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-600 font-medium mb-1">Start Date</label>
                            <input type="date" id="contract_start_date" name="start_date" class="w-full bg-white border border-slate-200 text-slate-800 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55] transition">
                        </div>
                        <div>
                            <label class="block text-slate-600 font-medium mb-1">Expiry Date / Cancellation Date</label>
                            <input type="date" id="contract_end_date" name="end_date" class="w-full bg-white border border-slate-200 text-slate-800 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55] transition">
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-600 font-medium mb-1">Final Balance / Payment Remarks</label>
                        <input type="text" id="contract_final_balance" name="final_balance_notes" placeholder="e.g. Pending final treatment collection: ₱1,500" class="w-full bg-white border border-slate-200 text-slate-800 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55] transition">
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                        <button type="button" onclick="closeContractModal()" class="bg-white text-slate-600 px-4 py-2 rounded-lg border border-slate-200 hover:bg-slate-50 transition font-medium">Cancel</button>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg transition shadow-sm">Update Contract</button>
                    </div>

                </form>
            </div>
        </div>
    </main>

    <!-- Helper Scripts -->
    <script>
        lucide.createIcons();

        function toggleClientForm() {
            const section = document.getElementById('clientCreationSection');
            section.classList.toggle('hidden');
        }

        function toggleActionMenu(id) {
            document.querySelectorAll('[id^="action-menu-"]').forEach(el => {
                if (el.id !== `action-menu-${id}`) el.classList.add('hidden');
            });
            document.getElementById(`action-menu-${id}`).classList.toggle('hidden');
        }

        window.addEventListener('click', function(e) {
            if (!e.target.closest('.relative')) {
                document.querySelectorAll('[id^="action-menu-"]').forEach(el => el.classList.add('hidden'));
            }
        });

        function openEditModal(client) {
            document.getElementById('edit_client_id').value = client.id;
            document.getElementById('edit_company_name').value = client.client_name || '';
            document.getElementById('edit_first_name').value = client.first_name || '';
            document.getElementById('edit_last_name').value = client.last_name || '';
            document.getElementById('edit_email').value = client.email || '';
            document.getElementById('edit_phone').value = client.phone_number || '';
            document.getElementById('edit_street_address').value = client.street_address || '';
            document.getElementById('edit_barangay').value = client.barangay || '';
            document.getElementById('edit_city').value = client.city || 'Davao City';
            document.getElementById('editModal').classList.remove('hidden');
        }

        let currentClientId = null;
        let currentClientName = '';
        let currentClientEmail = '';
        let currentClientObject = null;

        function openViewModal(client) {
            currentClientId = client.id;
            currentClientName = client.client_name;
            currentClientEmail = client.email || '';
            currentClientObject = client;

            document.getElementById('view_client_id').innerText = '#' + client.id;
            document.getElementById('view_company_name').innerText = client.client_name || 'N/A';
            document.getElementById('view_contact_person').innerText = (client.last_name || '') + ', ' + (client.first_name || '');
            document.getElementById('view_client_type').innerText = client.client_type || 'Commercial';
            document.getElementById('view_email').innerText = client.email || 'N/A';
            document.getElementById('view_phone').innerText = client.phone_number || 'N/A';

            const fullAddr = [client.street_address, client.barangay, client.city].filter(Boolean).join(', ');
            document.getElementById('view_address').innerText = fullAddr || 'N/A';

            // Populate hidden inputs for inspection
            document.getElementById('modalClientNameInput').value = client.client_name || '';
            document.getElementById('modalClientEmailInput').value = client.email || '';
            document.getElementById('modalAccountTypeInput').value = client.client_type || 'Commercial';
            document.getElementById('modalServiceAddressInput').value = fullAddr || '';

            document.getElementById('view_contract_status_text').innerText = client.contract_status || 'active';
            document.getElementById('view_account_status').innerText = client.status || 'active';
            document.getElementById('view_start_date').innerText = client.contract_start_date || 'Not set';
            document.getElementById('view_end_date').innerText = client.contract_end_date || 'Not set';
            document.getElementById('view_created_at').innerText = client.created_at || 'N/A';

            // Header Badge Styling based on status
            const badge = document.getElementById('header_contract_badge');
            if (client.contract_status === 'cancelled') {
                badge.className = 'px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider bg-purple-100 text-purple-700 border border-purple-200';
                badge.innerText = 'CANCELLED';
            } else if (client.contract_status === 'expiring_soon') {
                badge.className = 'px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider bg-amber-100 text-amber-700 border border-amber-200';
                badge.innerText = 'EXPIRING SOON';
            } else if (client.contract_status === 'to_be_contracted') {
                badge.className = 'px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider bg-slate-100 text-slate-700 border border-slate-200';
                badge.innerText = 'TO-BE-CONTRACTED';
            } else {
                badge.className = 'px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider bg-emerald-100 text-emerald-700 border border-emerald-200';
                badge.innerText = 'ACTIVE CONTRACT';
            }

            // Final balance note display for cancelled accounts
            const balanceContainer = document.getElementById('view_balance_container');
            if (client.contract_status === 'cancelled' && client.final_balance_notes) {
                document.getElementById('view_final_balance').innerText = client.final_balance_notes;
                balanceContainer.classList.remove('hidden');
            } else {
                balanceContainer.classList.add('hidden');
            }

            document.getElementById('viewContractModal').classList.remove('hidden');
            lucide.createIcons();
        }

        function closeViewModal() {
            document.getElementById('viewContractModal').classList.add('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        function openContractModal(client) {
            document.getElementById('contract_client_id').value = client.id;
            document.getElementById('contract_client_name').innerText = client.client_name || 'Client';

            const contractStatusSelect = document.getElementById('editContractStatus');
            if (contractStatusSelect) {
                contractStatusSelect.value = client.contract_status || 'to_be_contracted';
            }

            document.getElementById('contract_start_date').value = client.contract_start_date || '';
            document.getElementById('contract_end_date').value = client.contract_end_date || '';
            document.getElementById('contract_final_balance').value = client.final_balance_notes || '';

            document.getElementById('contractModal').classList.remove('hidden');
        }

        function closeContractModal() {
            document.getElementById('contractModal').classList.add('hidden');
        }

        // Job Order Validation & Trigger Handler
        document.getElementById('createJobOrderBtn').addEventListener('click', function() {
            const status = (currentClientObject?.contract_status || '').toLowerCase();

            // Error Handling Rule: Block if contract status is 'to_be_contracted'
            if (status === 'to_be_contracted' || status === 'to-be-contracted') {
                alert('Action Blocked: This client is currently "To-Be-Contracted". You must complete the pre-contract inspection and finalize a contract before you can file a job order.');
                return;
            }

            const clientName = document.getElementById('modalClientNameInput')?.value || '';
            const serviceAddress = document.getElementById('modalServiceAddressInput')?.value || '';

            const encodedName = encodeURIComponent(clientName);
            const encodedAddress = encodeURIComponent(serviceAddress);

            // Redirect to Operations / Dispatch workspace
            window.location.href = `pest-operations.php?client=${encodedName}&address=${encodedAddress}`;
        });

        function openInspectionHistoryModal() {
            document.getElementById('historyClientName').innerText = currentClientName || 'Client';

            const tbody = document.getElementById('clientHistoryTableBody');
            tbody.innerHTML = `<tr><td colspan="5" class="py-6 text-center text-slate-400 italic">Loading inspection history...</td></tr>`;

            fetch(`../controllers/getInspectionHistory.php?client_name=${encodeURIComponent(currentClientName)}&client_email=${encodeURIComponent(currentClientEmail)}`)
                .then(response => response.json())
                .then(res => {
                    if (res.success && res.data.length > 0) {
                        let rows = '';
                        res.data.forEach(item => {
                            let statusBadge = `<span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">${item.inspection_status || 'Pending Visit'}</span>`;

                            rows += `
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="py-3 px-3 font-mono text-slate-600">${item.created_at ?? 'N/A'}</td>
                                    <td class="py-3 px-3">${statusBadge}</td>
                                    <td class="py-3 px-3 text-slate-800 font-medium">${item.findings || item.service_address || 'Standard Site Inspection'}</td>
                                    <td class="py-3 px-3 text-slate-600">${item.technician_name ?? 'Unassigned'}</td>
                                    <td class="py-3 px-3 text-right">
                                        <a href="inspections.php?id=${item.id}" class="text-[#007a55] hover:underline font-semibold text-[11px]">View</a>
                                    </td>
                                </tr>
                            `;
                        });
                        tbody.innerHTML = rows;
                    } else {
                        tbody.innerHTML = `<tr><td colspan="5" class="py-6 text-center text-slate-400 italic">No inspection history records found for this client.</td></tr>`;
                    }
                })
                .catch(err => {
                    console.error('Error fetching history:', err);
                    tbody.innerHTML = `<tr><td colspan="5" class="py-6 text-center text-rose-500">Failed to load history records.</td></tr>`;
                });

            document.getElementById('inspectionHistoryModal').classList.remove('hidden');
            document.getElementById('inspectionHistoryModal').classList.add('flex');
            lucide.createIcons();
        }

        function closeInspectionHistoryModal() {
            document.getElementById('inspectionHistoryModal').classList.remove('flex');
            document.getElementById('inspectionHistoryModal').classList.add('hidden');
        }
    </script>
</body>

</html>