<?php
session_start();
// 1. Load database connection first
require_once __DIR__ . '/../config/db.php';

// 2. Load auth middleware
require_once __DIR__ . '/../middleware/auth.php';

// 3. Restrict access strictly to Admin role
requireRole(['Admin']);

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

// Handle Form Submission for Recording New Payment
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_payment') {
    $contractId    = !empty($_POST['contract_id']) ? intval($_POST['contract_id']) : null;
    $amountPaid    = floatval($_POST['amount_paid'] ?? 0);
    $paymentType   = $_POST['payment_type'] ?? 'Downpayment';
    $paymentMethod = $_POST['payment_method'] ?? 'Cash';
    $refNumber     = trim($_POST['reference_number'] ?? '');
    $remarks       = trim($_POST['remarks'] ?? '');
    $paymentDate   = $_POST['payment_date'] ?? date('Y-m-d H:i:s');
    $userId        = $_SESSION['user_id'] ?? null;

    if ($contractId && $amountPaid > 0) {
        // Retrieve client_id for contract
        $stmtC = $pdo->prepare("SELECT client_id FROM contracts WHERE id = :cid");
        $stmtC->execute(['cid' => $contractId]);
        $contractData = $stmtC->fetch();

        if ($contractData) {
            $clientId = $contractData['client_id'];

            // Insert into payments table
            $insStmt = $pdo->prepare("
                INSERT INTO payments (contract_id, client_id, user_id, amount_paid, payment_type, payment_method, reference_number, remarks, payment_date)
                VALUES (:contract_id, :client_id, :user_id, :amount_paid, :payment_type, :payment_method, :reference_number, :remarks, :payment_date)
            ");
            $insStmt->execute([
                'contract_id'      => $contractId,
                'client_id'        => $clientId,
                'user_id'          => $userId,
                'amount_paid'      => $amountPaid,
                'payment_type'     => $paymentType,
                'payment_method'   => $paymentMethod,
                'reference_number' => $refNumber,
                'remarks'          => $remarks,
                'payment_date'     => $paymentDate
            ]);

            // Immediately Recalculate Final Balance for this contract
            $pdo->prepare("
                UPDATE contracts 
                SET final_balance = GREATEST(0.00, contract_value - (
                    SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE contract_id = :cid
                ))
                WHERE id = :cid
            ")->execute(['cid' => $contractId]);

            $message = "Payment of ₱" . number_format($amountPaid, 2) . " logged successfully!";
            $messageType = "success";
        }
    } else {
        $message = "Please select a valid contract and enter an amount greater than zero.";
        $messageType = "error";
    }
}

// Search & Filter parameters
$search       = trim($_GET['search'] ?? '');
$filterMethod = $_GET['method'] ?? '';
$filterType   = $_GET['type'] ?? '';
$dateFilter   = $_GET['date_filter'] ?? 'all';
$startDate    = $_GET['start_date'] ?? '';
$endDate      = $_GET['end_date'] ?? '';

// Build Date Condition for Payments Table (using payment_date)
$dateCondition = "1=1";
$params = [];

if ($dateFilter === 'this-month') {
    $dateCondition = "MONTH(p.payment_date) = MONTH(CURRENT_DATE()) AND YEAR(p.payment_date) = YEAR(CURRENT_DATE())";
} elseif ($dateFilter === 'last-month') {
    $dateCondition = "MONTH(p.payment_date) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) AND YEAR(p.payment_date) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))";
} elseif ($dateFilter === 'year-to-date') {
    $dateCondition = "YEAR(p.payment_date) = YEAR(CURRENT_DATE())";
} elseif ($dateFilter === 'custom' && !empty($startDate) && !empty($endDate)) {
    $dateCondition = "DATE(p.payment_date) BETWEEN :start_date AND :end_date";
    $params['start_date'] = $startDate;
    $params['end_date']   = $endDate;
}

// Fetch KPI Metrics (Respecting the active date filter for Total Collected & Transactions)
$totalCollectedQuery = "SELECT COALESCE(SUM(amount_paid), 0) FROM payments p WHERE $dateCondition";
$stmtCol = $pdo->prepare($totalCollectedQuery);
$stmtCol->execute($params);
$totalCollected = $stmtCol->fetchColumn();

$totalTransQuery = "SELECT COUNT(*) FROM payments p WHERE $dateCondition";
$stmtTrans = $pdo->prepare($totalTransQuery);
$stmtTrans->execute($params);
$totalTransactions = $stmtTrans->fetchColumn();

// Global portfolio metrics remain global for context
$totalContractValue = $pdo->query("SELECT COALESCE(SUM(contract_value), 0) FROM contracts WHERE contract_status != 'archived'")->fetchColumn();
$totalOutstanding   = $pdo->query("SELECT COALESCE(SUM(final_balance), 0) FROM contracts WHERE contract_status != 'archived'")->fetchColumn();

// Fetch Payments Listing with Filters
$payQuery = "
    SELECT p.*, c.contract_name, c.contract_value, c.final_balance, cl.client_name, u.first_name as user_fname, u.last_name as user_lname
    FROM payments p
    LEFT JOIN contracts c ON p.contract_id = c.id
    LEFT JOIN clients cl ON p.client_id = cl.id
    LEFT JOIN users u ON p.user_id = u.id
    WHERE $dateCondition
";

if (!empty($search)) {
    $payQuery .= " AND (cl.client_name LIKE :s OR c.contract_name LIKE :s OR p.reference_number LIKE :s OR p.contract_id LIKE :s OR CONCAT('CONTRACT-', p.contract_id) LIKE :s)";
    $params['s'] = "%$search%";
}
if (!empty($filterMethod)) {
    $payQuery .= " AND p.payment_method = :m";
    $params['m'] = $filterMethod;
}
if (!empty($filterType)) {
    $payQuery .= " AND p.payment_type = :t";
    $params['t'] = $filterType;
}

$payQuery .= " ORDER BY p.payment_date DESC";
$payStmt = $pdo->prepare($payQuery);
$payStmt->execute($params);
$payments = $payStmt->fetchAll();

// Fetch Active & Eligible Contracts for Payment Modal
$eligibleContracts = $pdo->query("
    SELECT con.id, con.contract_name, con.contract_value, con.final_balance, cl.client_name 
    FROM contracts con 
    JOIN clients cl ON con.client_id = cl.id 
    WHERE con.contract_status != 'archived'
    ORDER BY con.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments & Billing - Vermex Pest Solutions</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Custom Vermex Theme Rules -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-slate-100 text-slate-800 flex h-screen overflow-hidden font-sans">

    <!-- SIDEBAR -->
    <?php include 'components/sidebar.php';?>

    <!-- MAIN CONTENT CONTAINER -->
    <main class="flex-1 flex flex-col h-screen overflow-y-auto">

        <!-- HEADER -->
        <header class="bg-white border-b border-slate-200 px-8 py-5 flex items-center justify-between sticky top-0 z-10 shadow-sm">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Payments & Billing</h1>
                <p class="text-xs text-slate-500 mt-1">Manage customer transactions and monitor live contract balances</p>
            </div>
            <button onclick="openModal()" class="bg-emerald-700 hover:bg-emerald-800 text-white font-medium text-xs px-4 py-2.5 rounded-lg flex items-center gap-2 shadow-sm transition">
                <i data-lucide="plus-circle" class="w-4 h-4"></i> Record New Payment
            </button>
        </header>

        <div class="p-8 space-y-6">

            <!-- ALERTS -->
            <?php if (!empty($message)): ?>
                <div class="p-4 rounded-xl text-xs font-medium flex items-center justify-between shadow-sm <?= $messageType === 'success' ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-rose-50 border border-rose-200 text-rose-800' ?>">
                    <span><?= htmlspecialchars($message) ?></span>
                    <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-4 h-4"></i></button>
                </div>
            <?php endif; ?>

            <!-- METRICS / KPI CARDS -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total Collected</p>
                        <h3 class="text-2xl font-bold text-emerald-700 mt-1">₱<?= number_format($totalCollected, 2) ?></h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center">
                        <i data-lucide="wallet" class="w-5 h-5"></i>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Outstanding Balance</p>
                        <h3 class="text-2xl font-bold text-rose-600 mt-1">₱<?= number_format($totalOutstanding, 2) ?></h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center">
                        <i data-lucide="clock" class="w-5 h-5"></i>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total Contract Portfolio</p>
                        <h3 class="text-2xl font-bold text-slate-800 mt-1">₱<?= number_format($totalContractValue, 2) ?></h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center">
                        <i data-lucide="file-text" class="w-5 h-5"></i>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total Transactions</p>
                        <h3 class="text-2xl font-bold text-slate-800 mt-1"><?= number_format($totalTransactions) ?></h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center">
                        <i data-lucide="receipt" class="w-5 h-5"></i>
                    </div>
                </div>
            </div>

            <!-- FILTERS & SEARCH -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col lg:flex-row gap-4 items-center justify-between">
                <form method="GET" class="flex flex-col lg:flex-row gap-3 w-full items-center">
                    <div class="relative flex-1 w-full">
                        <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search client, contract ID, name, or reference no..." class="w-full pl-9 pr-4 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                    </div>

                    <!-- Date Filter Dropdown -->
                    <select name="date_filter" id="dateFilterSelect" onchange="toggleCustomDates(this.value)" class="px-3 py-2 border border-slate-200 rounded-xl text-xs text-slate-600 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-600">
                        <option value="all" <?= $dateFilter === 'all' ? 'selected' : '' ?>>All Time</option>
                        <option value="this-month" <?= $dateFilter === 'this-month' ? 'selected' : '' ?>>This Month</option>
                        <option value="last-month" <?= $dateFilter === 'last-month' ? 'selected' : '' ?>>Last Month</option>
                        <option value="year-to-date" <?= $dateFilter === 'year-to-date' ? 'selected' : '' ?>>Year to Date</option>
                        <option value="custom" <?= $dateFilter === 'custom' ? 'selected' : '' ?>>Custom Date Range</option>
                    </select>

                    <!-- Custom Date Inputs -->
                    <div id="customDateRange" class="flex items-center gap-2 <?= $dateFilter === 'custom' ? 'flex' : 'hidden' ?>">
                        <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" class="px-2.5 py-2 border border-slate-200 rounded-xl text-xs text-slate-600 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                        <span class="text-xs text-slate-400">to</span>
                        <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" class="px-2.5 py-2 border border-slate-200 rounded-xl text-xs text-slate-600 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                    </div>

                    <select name="method" class="px-3 py-2 border border-slate-200 rounded-xl text-xs text-slate-600 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-600">
                        <option value="">All Payment Methods</option>
                        <option value="Cash" <?= $filterMethod === 'Cash' ? 'selected' : '' ?>>Cash</option>
                        <option value="Bank Transfer" <?= $filterMethod === 'Bank Transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                        <option value="Check" <?= $filterMethod === 'Check' ? 'selected' : '' ?>>Check</option>
                        <option value="GCash" <?= $filterMethod === 'GCash' ? 'selected' : '' ?>>GCash</option>
                        <option value="Credit Card" <?= $filterMethod === 'Credit Card' ? 'selected' : '' ?>>Credit Card</option>
                    </select>

                    <select name="type" class="px-3 py-2 border border-slate-200 rounded-xl text-xs text-slate-600 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-600">
                        <option value="">All Payment Types</option>
                        <option value="Downpayment" <?= $filterType === 'Downpayment' ? 'selected' : '' ?>>Downpayment</option>
                        <option value="Balance Settlement" <?= $filterType === 'Balance Settlement' ? 'selected' : '' ?>>Balance Settlement</option>
                        <option value="Full Payment" <?= $filterType === 'Full Payment' ? 'selected' : '' ?>>Full Payment</option>
                        <option value="Adjustment" <?= $filterType === 'Adjustment' ? 'selected' : '' ?>>Adjustment</option>
                    </select>

                    <div class="flex items-center gap-2">
                        <button type="submit" class="px-3 py-2 bg-emerald-700 text-white rounded-xl text-xs font-medium hover:bg-emerald-800 transition">Filter</button>
                        <?php if (!empty($search) || !empty($filterMethod) || !empty($filterType) || $dateFilter !== 'all'): ?>
                            <a href="payments.php" class="px-3 py-2 bg-slate-100 text-slate-600 rounded-xl text-xs font-medium hover:bg-slate-200 transition flex items-center justify-center">Reset</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- PAYMENTS TABLE -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-semibold uppercase tracking-wider">
                            <tr>
                                <th class="px-5 py-3.5">Ref / Trans ID</th>
                                <th class="px-5 py-3.5">Client & Contract</th>
                                <th class="px-5 py-3.5">Payment Type</th>
                                <th class="px-5 py-3.5">Method</th>
                                <th class="px-5 py-3.5">Contract Value</th>
                                <th class="px-5 py-3.5">Amount Paid</th>
                                <th class="px-5 py-3.5">Remaining Balance</th>
                                <th class="px-5 py-3.5">Date</th>
                                <th class="px-5 py-3.5">Logged By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <?php if (count($payments) > 0): ?>
                                <?php foreach ($payments as $p): ?>
                                    <tr class="hover:bg-slate-50/80 transition">
                                        <td class="px-5 py-4 font-semibold text-slate-900">
                                            #PAY-<?= str_pad($p['id'], 4, '0', STR_PAD_LEFT) ?>
                                            <?php if (!empty($p['reference_number'])): ?>
                                                <span class="block text-[10px] text-slate-400 font-normal">Ref: <?= htmlspecialchars($p['reference_number']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-5 py-4">
                                            <p class="font-bold text-slate-900"><?= htmlspecialchars($p['client_name'] ?? 'N/A') ?></p>
                                            <div class="flex items-center gap-1.5 text-[11px] text-slate-500 mt-0.5">
                                                <span><?= htmlspecialchars($p['contract_name'] ?? 'General') ?></span>
                                                <span class="font-mono text-[10px] bg-slate-100 border border-slate-200 px-1.5 py-0.5 rounded text-slate-600">#CONTRACT-<?= htmlspecialchars($p['contract_id']) ?></span>
                                            </div>
                                        </td>
                                        <td class="px-5 py-4">
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/60">
                                                <?= htmlspecialchars($p['payment_type']) ?>
                                            </span>
                                        </td>
                                        <td class="px-5 py-4 font-medium text-slate-600">
                                            <?= htmlspecialchars($p['payment_method']) ?>
                                        </td>
                                        <td class="px-5 py-4 font-bold text-slate-700">
                                            ₱<?= number_format($p['contract_value'] ?? 0, 2) ?>
                                        </td>
                                        <td class="px-5 py-4 font-bold text-emerald-600">
                                            ₱<?= number_format($p['amount_paid'], 2) ?>
                                        </td>
                                        <td class="px-5 py-4 font-bold <?= floatval($p['final_balance']) > 0 ? 'text-rose-600' : 'text-slate-400' ?>">
                                            ₱<?= number_format($p['final_balance'] ?? 0, 2) ?>
                                        </td>
                                        <td class="px-5 py-4 text-slate-500 whitespace-nowrap">
                                            <?= date('M d, Y g:i A', strtotime($p['payment_date'])) ?>
                                        </td>
                                        <td class="px-5 py-4 text-slate-500">
                                            <?= htmlspecialchars(($p['user_fname'] ?? 'System') . ' ' . ($p['user_lname'] ?? '')) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-10 text-slate-400 font-medium">No payment transactions recorded for this period.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- RECORD PAYMENT MODAL -->
    <div id="paymentModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden">
            <div class="bg-emerald-900 px-6 py-4 text-white flex items-center justify-between">
                <h3 class="font-bold text-sm tracking-wide">RECORD CONTRACT PAYMENT</h3>
                <button onclick="closeModal()" class="text-emerald-300 hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="add_payment">

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Select Contract *</label>
                    <select name="contract_id" id="contractSelect" onchange="updateBalanceHint()" required class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                        <option value="" data-balance="0">-- Select Contract --</option>
                        <?php foreach ($eligibleContracts as $ec): ?>
                            <option value="<?= $ec['id'] ?>" data-balance="<?= $ec['final_balance'] ?>" data-value="<?= $ec['contract_value'] ?>">
                                [#CONTRACT-<?= $ec['id'] ?>] <?= htmlspecialchars($ec['client_name']) ?> — <?= htmlspecialchars($ec['contract_name']) ?> (Bal: ₱<?= number_format($ec['final_balance'], 2) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs text-slate-600 flex justify-between">
                    <span>Current Outstanding Balance:</span>
                    <span id="balanceHint" class="font-bold text-rose-600">₱0.00</span>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Amount Paid (₱) *</label>
                        <input type="number" step="0.01" name="amount_paid" placeholder="0.00" required class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none font-bold text-emerald-700">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Payment Type *</label>
                        <select name="payment_type" required class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                            <option value="Downpayment">Downpayment</option>
                            <option value="Balance Settlement">Balance Settlement</option>
                            <option value="Full Payment">Full Payment</option>
                            <option value="Adjustment">Adjustment</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Payment Method *</label>
                        <select name="payment_method" required class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Check">Check</option>
                            <option value="GCash">GCash</option>
                            <option value="Credit Card">Credit Card</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Reference No. (Optional)</label>
                        <input type="text" name="reference_number" placeholder="OR# or Ref#" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Remarks / Notes</label>
                    <textarea name="remarks" rows="2" placeholder="Additional details..." class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none"></textarea>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 bg-slate-200 text-slate-700 font-medium text-xs rounded-xl hover:bg-slate-300 transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-700 text-white font-medium text-xs rounded-xl hover:bg-emerald-800 transition shadow-sm">Submit Payment</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function openModal() {
            document.getElementById('paymentModal').classList.remove('hidden');
            document.getElementById('paymentModal').classList.add('flex');
        }

        function closeModal() {
            document.getElementById('paymentModal').classList.add('hidden');
            document.getElementById('paymentModal').classList.remove('flex');
        }

        function updateBalanceHint() {
            const select = document.getElementById('contractSelect');
            const selectedOption = select.options[select.selectedIndex];
            const balance = parseFloat(selectedOption.getAttribute('data-balance') || 0);
            document.getElementById('balanceHint').innerText = '₱' + balance.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }

        function toggleCustomDates(val) {
            const customDiv = document.getElementById('customDateRange');
            if (val === 'custom') {
                customDiv.classList.remove('hidden');
                customDiv.classList.add('flex');
            } else {
                customDiv.classList.add('hidden');
                customDiv.classList.remove('flex');
                // Auto submit form on quick preset change for smooth UX
                document.forms[0].submit();
            }
        }

        // Auto-select contract and trigger payment modal if contract_id is passed in URL
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const targetContractId = urlParams.get('contract_id');
            
            if (targetContractId) {
                const select = document.getElementById('contractSelect');
                if (select) {
                    select.value = targetContractId;
                    updateBalanceHint();
                    openModal();
                }
            }
        });
    </script>
</body>
</html>