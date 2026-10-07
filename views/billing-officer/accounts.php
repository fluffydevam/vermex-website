<?php
session_start();

// 1. Load database connection
require_once __DIR__ . '/../../config/db.php';

// 2. Check session and role authentication for Billing Officer
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'Billing Officer' && $_SESSION['role'] !== 'Admin')) {
    header("Location: ../auth/login.php");
    exit();
}

$userFullName = $_SESSION['full_name'] ?? 'Billing Officer';
$userRole = $_SESSION['role'] ?? 'Billing Officer';

// Ensure final_balance column exists in contracts table
try {
    $pdo->exec("ALTER TABLE contracts ADD COLUMN final_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER contract_value");
} catch (PDOException $e) {
    // Column already exists
}

// AUTO-SYNC: Update final_balance for all contracts based on recorded payments
$pdo->exec("
    UPDATE contracts c
    SET c.final_balance = GREATEST(0.00, c.contract_value - COALESCE(
        (SELECT SUM(p.amount_paid) FROM payments p WHERE p.contract_id = c.id), 0.00
    ))
");

// Search parameter
$search = trim($_GET['search'] ?? '');

// Fetch Client Accounts with Contract & Balance Summaries
$query = "
    SELECT cl.id as client_id, cl.client_name, cl.client_type, cl.email, cl.phone_number, cl.street_address, cl.barangay, cl.city,
           c.id as contract_id, c.contract_name, c.contract_value, c.final_balance, c.contract_status,
           COALESCE((SELECT SUM(p.amount_paid) FROM payments p WHERE p.contract_id = c.id), 0.00) as total_paid
    FROM clients cl
    LEFT JOIN contracts c ON cl.id = c.client_id
    WHERE cl.status != 'archived'
";
$params = [];

if (!empty($search)) {
    $query .= " AND (cl.client_name LIKE :s OR cl.email LIKE :s OR cl.phone_number LIKE :s OR c.contract_name LIKE :s)";
    $params['s'] = "%$search%";
}

$query .= " ORDER BY cl.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$accounts = $stmt->fetchAll();

// KPI Metrics
$totalReceivables = $pdo->query("SELECT COALESCE(SUM(contract_value), 0) FROM contracts WHERE contract_status != 'archived'")->fetchColumn();
$totalCollected = $pdo->query("SELECT COALESCE(SUM(amount_paid), 0) FROM payments")->fetchColumn();
$totalBalances = $pdo->query("SELECT COALESCE(SUM(final_balance), 0) FROM contracts WHERE contract_status != 'archived'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Accounts - Vermex Billing Portal</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-100 text-slate-800 flex min-h-screen font-sans">

    <!-- SIDEBAR -->
    <?php include __DIR__ . '/components/sidebar.php'; ?>

    <!-- MAIN CONTENT CONTAINER -->
    <main class="flex-1 flex flex-col min-h-screen overflow-x-hidden">

        <!-- HEADER -->
        <header class="bg-white border-b border-slate-200 px-4 sm:px-8 py-4 flex items-center justify-between sticky top-0 z-30 shadow-sm">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="md:hidden text-slate-600 hover:text-slate-900 p-1 rounded-lg focus:outline-none">
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>
                <div>
                    <h1 class="text-lg sm:text-2xl font-bold text-slate-900 tracking-tight">Client Accounts & Ledger</h1>
                    <p class="text-xs text-slate-500 mt-0.5 hidden sm:block">Monitor active client profiles, contracts, and outstanding balances</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="payments.php" class="bg-emerald-700 hover:bg-emerald-800 text-white font-medium text-xs px-3 sm:px-4 py-2.5 rounded-lg flex items-center gap-2 shadow-sm transition">
                    <i data-lucide="credit-card" class="w-4 h-4"></i> <span class="hidden sm:inline">Go to </span>Payment Logger
                </a>
            </div>
        </header>

        <div class="p-4 sm:p-8 space-y-6 max-w-7xl w-full mx-auto">

            <!-- METRICS / KPI CARDS -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-5">
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total Contract Value</p>
                        <h3 class="text-2xl font-bold text-slate-900 mt-1">₱<?= number_format($totalReceivables, 2) ?></h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="briefcase" class="w-5 h-5"></i>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total Collected Revenue</p>
                        <h3 class="text-2xl font-bold text-emerald-700 mt-1">₱<?= number_format($totalCollected, 2) ?></h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="wallet" class="w-5 h-5"></i>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total Outstanding Balances</p>
                        <h3 class="text-2xl font-bold text-rose-600 mt-1">₱<?= number_format($totalBalances, 2) ?></h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="alert-circle" class="w-5 h-5"></i>
                    </div>
                </div>
            </div>

            <!-- SEARCH BAR -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                <form method="GET" class="flex flex-col sm:flex-row gap-3 w-full">
                    <div class="relative flex-1">
                        <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search client name, email, phone number, or contract..." class="w-full pl-9 pr-4 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                    </div>
                    <?php if (!empty($search)): ?>
                        <a href="accounts.php" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-xl text-xs font-medium hover:bg-slate-200 transition flex items-center justify-center">Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- CLIENT ACCOUNTS TABLE -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs whitespace-nowrap sm:whitespace-normal">
                        <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-semibold uppercase tracking-wider">
                            <tr>
                                <th class="px-5 py-3.5">Client Information</th>
                                <th class="px-5 py-3.5">Sector / Type</th>
                                <th class="px-5 py-3.5">Active Contract</th>
                                <th class="px-5 py-3.5">Contract Value</th>
                                <th class="px-5 py-3.5">Total Paid</th>
                                <th class="px-5 py-3.5">Balance</th>
                                <th class="px-5 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <?php if (count($accounts) > 0): ?>
                                <?php foreach ($accounts as $acc): ?>
                                    <tr class="hover:bg-slate-50/80 transition">
                                        <td class="px-5 py-4">
                                            <p class="font-bold text-slate-900"><?= htmlspecialchars($acc['client_name']) ?></p>
                                            <p class="text-[11px] text-slate-500"><?= htmlspecialchars($acc['email'] ?? 'No email') ?> • <?= htmlspecialchars($acc['phone_number'] ?? 'No phone') ?></p>
                                        </td>
                                        <td class="px-5 py-4">
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                                <?= htmlspecialchars($acc['client_type'] ?? 'Residential') ?>
                                            </span>
                                        </td>
                                        <td class="px-5 py-4">
                                            <?php if (!empty($acc['contract_id'])): ?>
                                                <p class="font-semibold text-slate-800"><?= htmlspecialchars($acc['contract_name']) ?></p>
                                                <span class="text-[10px] font-mono text-emerald-700">#CONTRACT-<?= $acc['contract_id'] ?></span>
                                            <?php else: ?>
                                                <span class="text-slate-400 italic">No contract assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-5 py-4 font-bold text-slate-700">
                                            ₱<?= number_format($acc['contract_value'] ?? 0, 2) ?>
                                        </td>
                                        <td class="px-5 py-4 font-bold text-emerald-600">
                                            ₱<?= number_format($acc['total_paid'] ?? 0, 2) ?>
                                        </td>
                                        <td class="px-5 py-4 font-bold <?= floatval($acc['final_balance'] ?? 0) > 0 ? 'text-rose-600' : 'text-slate-400' ?>">
                                            ₱<?= number_format($acc['final_balance'] ?? 0, 2) ?>
                                        </td>
                                        <td class="px-5 py-4 text-right">
                                            <?php if (!empty($acc['contract_id'])): ?>
                                                <a href="payments.php?contract_id=<?= $acc['contract_id'] ?>" class="inline-flex items-center gap-1 bg-emerald-50 text-emerald-700 border border-emerald-200 px-3 py-1.5 rounded-lg font-medium hover:bg-emerald-100 transition text-[11px]">
                                                    <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i> Record Payment
                                                </a>
                                            <?php else: ?>
                                                <span class="text-slate-400 text-[11px] italic">Unavailable</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-10 text-slate-400 font-medium">No client accounts found.</td>
                                </tr>
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