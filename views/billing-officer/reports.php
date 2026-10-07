<?php
session_start();

// 1. Load database connection
require_once __DIR__ . '/../../config/db.php';

// 2. Check session and role authentication for Billing Officer / Admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'Billing Officer' && $_SESSION['role'] !== 'Admin')) {
    header("Location: ../auth/login.php");
    exit();
}

$userFullName = $_SESSION['full_name'] ?? 'Billing Officer';
$userRole = $_SESSION['role'] ?? 'Billing Officer';

// Date filters (Default to current month if not provided)
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// Fetch KPI metrics within date filter
$kpiStmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(amount_paid), 0) as total_collected,
        COUNT(id) as total_transactions
    FROM payments 
    WHERE DATE(payment_date) BETWEEN :start AND :end
");
$kpiStmt->execute(['start' => $startDate, 'end' => $endDate]);
$kpi = $kpiStmt->fetch();

$totalCollected = $kpi['total_collected'];
$totalTransactions = $kpi['total_transactions'];

// Total Outstanding & Contract Portfolio (Overall)
$totalContractValue = $pdo->query("SELECT COALESCE(SUM(contract_value), 0) FROM contracts")->fetchColumn();
$totalOutstanding = $pdo->query("SELECT COALESCE(SUM(final_balance), 0) FROM contracts")->fetchColumn();

// Fetch Collections Grouped by Payment Method
$methodStmt = $pdo->prepare("
    SELECT payment_method, COUNT(id) as count, SUM(amount_paid) as total
    FROM payments
    WHERE DATE(payment_date) BETWEEN :start AND :end
    GROUP BY payment_method
    ORDER BY total DESC
");
$methodStmt->execute(['start' => $startDate, 'end' => $endDate]);
$paymentMethods = $methodStmt->fetchAll();

// Fetch Collections Grouped by Payment Type
$typeStmt = $pdo->prepare("
    SELECT payment_type, COUNT(id) as count, SUM(amount_paid) as total
    FROM payments
    WHERE DATE(payment_date) BETWEEN :start AND :end
    GROUP BY payment_type
    ORDER BY total DESC
");
$typeStmt->execute(['start' => $startDate, 'end' => $endDate]);
$paymentTypes = $typeStmt->fetchAll();

// Fetch Detailed Transactions for the Report Table
$transStmt = $pdo->prepare("
    SELECT p.*, c.contract_name, c.contract_value, c.final_balance, cl.client_name, u.first_name, u.last_name
    FROM payments p
    LEFT JOIN contracts c ON p.contract_id = c.id
    LEFT JOIN clients cl ON p.client_id = cl.id
    LEFT JOIN users u ON p.user_id = u.id
    WHERE DATE(p.payment_date) BETWEEN :start AND :end
    ORDER BY p.payment_date DESC
");
$transStmt->execute(['start' => $startDate, 'end' => $endDate]);
$transactions = $transStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Reports - Vermex Billing Portal</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- html2pdf.js for PDF downloads -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</head>
<body class="bg-[#f8faf9] text-slate-800 flex min-h-screen font-sans">

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
                    <h1 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight">Financial & Collection Reports</h1>
                    <p class="text-xs text-slate-500 mt-0.5 hidden sm:block">Analyze revenue collections, payment channels, and transaction summaries</p>
                </div>
            </div>
            <div class="flex items-center gap-2 sm:gap-3">
                <button onclick="downloadReportPdf()" class="bg-[#007a55] hover:bg-[#006344] text-white font-medium text-xs px-3 py-2 rounded-lg flex items-center gap-1.5 transition shadow-sm">
                    <i data-lucide="download" class="w-3.5 h-3.5"></i> <span class="hidden sm:inline">Download </span>PDF
                </button>
                <button onclick="window.print()" class="border border-slate-200 hover:bg-slate-50 text-slate-700 font-medium text-xs px-3 py-2 rounded-lg hidden sm:flex items-center gap-1.5 transition shadow-sm">
                    <i data-lucide="printer" class="w-3.5 h-3.5"></i> Print
                </button>
            </div>
        </header>

        <!-- PRINTABLE / PDF WRAPPER AREA -->
        <div id="reportPrintArea" class="p-4 sm:p-8 space-y-6 max-w-7xl w-full mx-auto bg-[#f8faf9]">

            <!-- DATE FILTER BAR -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
                <form method="GET" class="flex flex-wrap items-center gap-3 w-full">
                    <div class="flex items-center gap-2 flex-1 sm:flex-none">
                        <label class="text-xs font-semibold text-slate-600">From:</label>
                        <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" class="w-full sm:w-auto px-3 py-1.5 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-[#007a55] focus:outline-none">
                    </div>
                    <div class="flex items-center gap-2 flex-1 sm:flex-none">
                        <label class="text-xs font-semibold text-slate-600">To:</label>
                        <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" class="w-full sm:w-auto px-3 py-1.5 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-[#007a55] focus:outline-none">
                    </div>
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <button type="submit" class="flex-1 sm:flex-none bg-[#007a55] hover:bg-[#006344] text-white font-medium text-xs px-4 py-2 rounded-xl transition shadow-sm">
                            Filter Report
                        </button>
                        <a href="reports.php" class="px-3 py-2 bg-slate-100 text-slate-600 rounded-xl text-xs font-medium hover:bg-slate-200 transition text-center">Reset</a>
                    </div>
                </form>
            </div>

            <!-- KPI METRICS GRID -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white p-5 rounded-xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Collected (Selected Range)</p>
                        <h3 class="text-2xl font-bold text-[#007a55] mt-1">₱<?= number_format($totalCollected, 2) ?></h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-[#007a55] flex items-center justify-center flex-shrink-0">
                        <i data-lucide="wallet" class="w-5 h-5"></i>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Outstanding Balances</p>
                        <h3 class="text-2xl font-bold text-rose-600 mt-1">₱<?= number_format($totalOutstanding, 2) ?></h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="clock" class="w-5 h-5"></i>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total Contract Portfolio</p>
                        <h3 class="text-2xl font-bold text-slate-900 mt-1">₱<?= number_format($totalContractValue, 2) ?></h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="briefcase" class="w-5 h-5"></i>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Transactions Count</p>
                        <h3 class="text-2xl font-bold text-slate-900 mt-1"><?= number_format($totalTransactions) ?></h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="receipt" class="w-5 h-5"></i>
                    </div>
                </div>
            </div>

            <!-- BREAKDOWN TABLES (METHOD & TYPE) -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Payment Methods Breakdown -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
                    <h3 class="font-bold text-slate-900 text-sm flex items-center gap-2">
                        <i data-lucide="credit-card" class="w-4 h-4 text-[#007a55]"></i> Collections by Payment Method
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-semibold uppercase tracking-wider">
                                <tr>
                                    <th class="px-3 py-2.5">Method</th>
                                    <th class="px-3 py-2.5 text-center">Count</th>
                                    <th class="px-3 py-2.5 text-right">Total Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                <?php if (count($paymentMethods) > 0): ?>
                                    <?php foreach ($paymentMethods as $m): ?>
                                        <tr class="hover:bg-slate-50/50">
                                            <td class="px-3 py-3 font-semibold text-slate-900"><?= htmlspecialchars($m['payment_method']) ?></td>
                                            <td class="px-3 py-3 text-center"><?= $m['count'] ?></td>
                                            <td class="px-3 py-3 text-right font-bold text-[#007a55]">₱<?= number_format($m['total'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center py-6 text-slate-400 italic">No collections in this date range.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Payment Types Breakdown -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
                    <h3 class="font-bold text-slate-900 text-sm flex items-center gap-2">
                        <i data-lucide="pie-chart" class="w-4 h-4 text-[#007a55]"></i> Collections by Payment Type
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-semibold uppercase tracking-wider">
                                <tr>
                                    <th class="px-3 py-2.5">Type</th>
                                    <th class="px-3 py-2.5 text-center">Count</th>
                                    <th class="px-3 py-2.5 text-right">Total Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                <?php if (count($paymentTypes) > 0): ?>
                                    <?php foreach ($paymentTypes as $t): ?>
                                        <tr class="hover:bg-slate-50/50">
                                            <td class="px-3 py-3 font-semibold text-slate-900"><?= htmlspecialchars($t['payment_type']) ?></td>
                                            <td class="px-3 py-3 text-center"><?= $t['count'] ?></td>
                                            <td class="px-3 py-3 text-right font-bold text-[#007a55]">₱<?= number_format($t['total'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center py-6 text-slate-400 italic">No collections in this date range.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- DETAILED TRANSACTIONS REPORT TABLE -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden space-y-3">
                <div class="p-5 border-b border-slate-200 flex items-center justify-between">
                    <h3 class="font-bold text-slate-900 text-sm flex items-center gap-2">
                        <i data-lucide="file-text" class="w-4 h-4 text-[#007a55]"></i> Transaction Ledger (<?= date('M d, Y', strtotime($startDate)) ?> to <?= date('M d, Y', strtotime($endDate)) ?>)
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs whitespace-nowrap sm:whitespace-normal">
                        <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-semibold uppercase tracking-wider">
                            <tr>
                                <th class="px-5 py-3">Trans ID</th>
                                <th class="px-5 py-3">Client & Contract</th>
                                <th class="px-5 py-3">Type / Method</th>
                                <th class="px-5 py-3">Amount Paid</th>
                                <th class="px-5 py-3">Remaining Balance</th>
                                <th class="px-5 py-3">Date</th>
                                <th class="px-5 py-3">Processed By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <?php if (count($transactions) > 0): ?>
                                <?php foreach ($transactions as $tx): ?>
                                    <tr class="hover:bg-slate-50/50 transition">
                                        <td class="px-5 py-3.5 font-bold text-slate-900">
                                            #PAY-<?= str_pad($tx['id'], 4, '0', STR_PAD_LEFT) ?>
                                            <?php if (!empty($tx['reference_number'])): ?>
                                                <span class="block text-[10px] text-slate-400 font-normal">Ref: <?= htmlspecialchars($tx['reference_number']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-5 py-3.5">
                                            <p class="font-bold text-slate-900"><?= htmlspecialchars($tx['client_name']) ?></p>
                                            <p class="text-[11px] text-slate-500"><?= htmlspecialchars($tx['contract_name'] ?? 'N/A') ?></p>
                                        </td>
                                        <td class="px-5 py-3.5">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-[#007a55] border border-emerald-200">
                                                <?= htmlspecialchars($tx['payment_type']) ?>
                                            </span>
                                            <span class="block text-[10px] text-slate-500 mt-0.5"><?= htmlspecialchars($tx['payment_method']) ?></span>
                                        </td>
                                        <td class="px-5 py-3.5 font-bold text-[#007a55]">
                                            ₱<?= number_format($tx['amount_paid'], 2) ?>
                                        </td>
                                        <td class="px-5 py-3.5 font-bold <?= floatval($tx['final_balance']) > 0 ? 'text-rose-600' : 'text-slate-400' ?>">
                                            ₱<?= number_format($tx['final_balance'], 2) ?>
                                        </td>
                                        <td class="px-5 py-3.5 text-slate-500 whitespace-nowrap">
                                            <?= date('M d, Y g:i A', strtotime($tx['payment_date'])) ?>
                                        </td>
                                        <td class="px-5 py-3.5 text-slate-500">
                                            <?= htmlspecialchars(($tx['first_name'] ?? 'System') . ' ' . ($tx['last_name'] ?? '')) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-10 text-slate-400 font-medium">No transactions found for the selected date range.</td>
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

        function downloadReportPdf() {
            const element = document.getElementById('reportPrintArea');
            const options = {
                margin:       10,
                filename:     'vermex-financial-report-<?= $startDate ?>-to-<?= $endDate ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
            };
            html2pdf().from(element).set(options).save();
        }
    </script>
</body>
</html>