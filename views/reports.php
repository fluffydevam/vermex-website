<?php
session_start();

// Guard against unauthenticated access
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// 1. Load database connection first
require_once __DIR__ . '/../config/db.php';

// 2. Load auth middleware
require_once __DIR__ . '/../middleware/auth.php';

// 3. Restrict access strictly to Admin role
requireRole(['Admin']);

$userFullName = $_SESSION['full_name'] ?? 'Operations Manager';
$userRole = $_SESSION['role'] ?? 'Admin';

// Date filter handling (default to 'this-month')
$filter = $_GET['filter'] ?? 'this-month';

if ($filter === 'this-month') {
    $payWhere = "MONTH(payment_date) = MONTH(CURRENT_DATE()) AND YEAR(payment_date) = YEAR(CURRENT_DATE())";
    $jobWhere = "MONTH(scheduled_date) = MONTH(CURRENT_DATE()) AND YEAR(scheduled_date) = YEAR(CURRENT_DATE())";
    $inspWhere = "MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())";
    $matWhere = "MONTH(logged_at) = MONTH(CURRENT_DATE()) AND YEAR(logged_at) = YEAR(CURRENT_DATE())";
    
    $jobWhereAlias = "MONTH(j.scheduled_date) = MONTH(CURRENT_DATE()) AND YEAR(j.scheduled_date) = YEAR(CURRENT_DATE())";
} elseif ($filter === 'last-month') {
    $payWhere = "MONTH(payment_date) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) AND YEAR(payment_date) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))";
    $jobWhere = "MONTH(scheduled_date) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) AND YEAR(scheduled_date) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))";
    $inspWhere = "MONTH(created_at) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) AND YEAR(created_at) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))";
    $matWhere = "MONTH(logged_at) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) AND YEAR(logged_at) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))";
    
    $jobWhereAlias = "MONTH(j.scheduled_date) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) AND YEAR(j.scheduled_date) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))";
} else { // year-to-date
    $payWhere = "YEAR(payment_date) = YEAR(CURRENT_DATE())";
    $jobWhere = "YEAR(scheduled_date) = YEAR(CURRENT_DATE())";
    $inspWhere = "YEAR(created_at) = YEAR(CURRENT_DATE())";
    $matWhere = "YEAR(logged_at) = YEAR(CURRENT_DATE())";
    
    $jobWhereAlias = "YEAR(j.scheduled_date) = YEAR(CURRENT_DATE())";
}

// ==========================================
// 1. FINANCIAL & REVENUE METRICS (Filtered)
// ==========================================
$revenueQuery = "SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE $payWhere";
$totalRevenue = $pdo->query($revenueQuery)->fetchColumn() ?: 0.00;

$contractValQuery = "SELECT COALESCE(SUM(contract_value), 0) FROM contracts WHERE contract_status = 'active'";
$activeContractValue = $pdo->query($contractValQuery)->fetchColumn() ?: 0.00;

// ==========================================
// 2. OPERATIONAL & SERVICE METRICS (Filtered)
// ==========================================
$jobsCompletedQuery = "SELECT COUNT(*) FROM job_orders WHERE route_status = 'Completed' AND $jobWhere";
$completedJobsCount = $pdo->query($jobsCompletedQuery)->fetchColumn() ?: 0;

$totalJobsQuery = "SELECT COUNT(*) FROM job_orders WHERE $jobWhere";
$totalJobsCount = $pdo->query($totalJobsQuery)->fetchColumn() ?: 0;
$fulfillmentRate = $totalJobsCount > 0 ? round(($completedJobsCount / $totalJobsCount) * 100, 1) : 0;

$inspectionsQuery = "SELECT COUNT(*) FROM site_inspections WHERE $inspWhere";
$totalInspections = $pdo->query($inspectionsQuery)->fetchColumn() ?: 0;

// ==========================================
// 2.5 DYNAMIC TARGET PEST / SERVICE TYPE DISTRIBUTION
// ==========================================
$pestDistQuery = "SELECT service_type, COUNT(*) as count 
                  FROM job_orders 
                  WHERE $jobWhere 
                  GROUP BY service_type 
                  ORDER BY count DESC LIMIT 4";
$pestStmt = $pdo->query($pestDistQuery);
$pestDistributions = $pestStmt->fetchAll(PDO::FETCH_ASSOC);

$totalRangeJobs = $pdo->query("SELECT COUNT(*) FROM job_orders WHERE $jobWhere")->fetchColumn() ?: 1;

// ==========================================
// 3. CHEMICAL CONSUMPTION & COST ANALYSIS (Filtered)
// ==========================================
$chemUsedQuery = "SELECT COALESCE(SUM(quantity_used), 0) FROM job_order_materials WHERE $matWhere";
$totalChemicalsUsed = $pdo->query($chemUsedQuery)->fetchColumn() ?: 0;

$topChemsQuery = "SELECT i.item_name, i.unit, i.unit_cost, COALESCE(SUM(jom.quantity_used), 0) as total_qty,
                  (COALESCE(SUM(jom.quantity_used), 0) * i.unit_cost) as estimated_cost
                  FROM inventory i 
                  LEFT JOIN job_order_materials jom ON i.id = jom.inventory_id AND jom.id IN (SELECT id FROM job_order_materials WHERE $matWhere)
                  GROUP BY i.id, i.item_name, i.unit, i.unit_cost 
                  ORDER BY total_qty DESC LIMIT 3";
$topChemsStmt = $pdo->query($topChemsQuery);
$topChems = $topChemsStmt->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// 4. FIELD TECHNICIAN PRODUCTIVITY (Filtered)
// ==========================================
$techActivityQuery = "SELECT u.id, u.first_name, u.last_name, u.sector_region, u.status,
                      (SELECT COUNT(*) FROM job_orders j WHERE j.assigned_tech LIKE CONCAT('%', u.first_name, '%') AND j.route_status = 'Completed' AND $jobWhereAlias) as completed_jobs,
                      (SELECT COALESCE(SUM(jom.quantity_used), 0) FROM job_order_materials jom JOIN job_orders j ON jom.job_order_id = j.id WHERE j.assigned_tech LIKE CONCAT('%', u.first_name, '%') AND $jobWhereAlias) as total_chem
                      FROM users u WHERE u.role = 'Field Technician'";
$techActivityStmt = $pdo->query($techActivityQuery);
$technicians = $techActivityStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vermex - Reports & Analytics</title>
    
    <!-- Tailwind CSS (CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        @media print {
            body { background: white; }
            aside, header button, .no-print { display: none !important; }
            main { overflow: visible !important; }
        }
    </style>
</head>
<body class="bg-[#f8faf9] text-slate-800 h-screen flex font-sans antialiased overflow-hidden">

    <!-- 1. Shared Sidebar Component -->
    <?php include 'components/sidebar.php'; ?>

    <!-- 2. Main Content Area -->
    <main class="flex-1 flex flex-col min-w-0 h-screen overflow-y-auto bg-[#f8faf9]">

        <!-- Mobile top bar with menu button -->
        <div class="lg:hidden sticky top-0 z-10 bg-white border-b border-slate-200 px-4 py-3 flex items-center gap-3">
            <button type="button" onclick="toggleAppSidebar()" class="p-2 -ml-2 text-slate-500 hover:text-slate-700 rounded-lg hover:bg-slate-100" aria-label="Open menu">
                <i data-lucide="menu" class="w-5 h-5"></i>
            </button>
            <span class="text-sm font-bold text-slate-900 tracking-tight">Reports & Analytics</span>
        </div>

        <div class="p-4 sm:p-6 space-y-6 w-full max-w-7xl mx-auto pb-12">
            
            <!-- Page Header -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-200 pb-5">
                <div>
                    <div class="flex items-center gap-2 text-[11px] text-emerald-700 font-semibold tracking-wider uppercase mb-1">
                        <span>Management</span>
                        <span>•</span>
                        <span>Service Reports & Financial Analytics</span>
                    </div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Reports & Analytics Command Center</h1>
                    <p class="text-xs text-slate-500 mt-1">Real-time aggregation of collections, active contract valuations, chemical expenditures, and technician output.</p>
                </div>
                
                <div class="flex items-center gap-3 no-print">
                    <form method="GET" class="flex items-center gap-2">
                        <select name="filter" onchange="this.form.submit()" class="bg-white border border-slate-200 text-slate-700 text-xs rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-600 transition shadow-sm font-medium">
                            <option value="this-month" <?= $filter === 'this-month' ? 'selected' : '' ?>>This Month</option>
                            <option value="last-month" <?= $filter === 'last-month' ? 'selected' : '' ?>>Last Month</option>
                            <option value="year-to-date" <?= $filter === 'year-to-date' ? 'selected' : '' ?>>Year to Date</option>
                        </select>
                    </form>
                    <button onclick="window.print()" class="bg-emerald-700 hover:bg-emerald-800 text-white font-medium text-xs px-4 py-2 rounded-lg flex items-center gap-2 transition shadow-sm">
                        <i data-lucide="printer" class="w-4 h-4"></i>
                        <span>Print / Export PDF</span>
                    </button>
                </div>
            </div>

            <!-- KEY FINANCIAL & OPERATIONAL METRIC CARDS -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white border border-slate-200/80 rounded-xl p-4 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total Collections</p>
                        <p class="text-2xl font-bold text-slate-900 mt-1">₱<?= number_format($totalRevenue, 2) ?></p>
                        <p class="text-[11px] text-emerald-600 font-medium mt-1">Verified Client Payments</p>
                    </div>
                    <div class="bg-emerald-50 p-2.5 rounded-lg text-emerald-700 border border-emerald-100">
                        <i data-lucide="wallet" class="w-5 h-5"></i>
                    </div>
                </div>

                <div class="bg-white border border-slate-200/80 rounded-xl p-4 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Active Contract Portfolio</p>
                        <p class="text-2xl font-bold text-slate-900 mt-1">₱<?= number_format($activeContractValue, 2) ?></p>
                        <p class="text-[11px] text-purple-600 font-medium mt-1">Commercial & Residential</p>
                    </div>
                    <div class="bg-purple-50 p-2.5 rounded-lg text-purple-600 border border-purple-100">
                        <i data-lucide="file-text" class="w-5 h-5"></i>
                    </div>
                </div>

                <div class="bg-white border border-slate-200/80 rounded-xl p-4 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Job Fulfillment Rate</p>
                        <p class="text-2xl font-bold text-slate-900 mt-1"><?= $fulfillmentRate ?>%</p>
                        <p class="text-[11px] text-blue-600 font-medium mt-1"><?= $completedJobsCount ?> of <?= $totalJobsCount ?> Jobs Completed</p>
                    </div>
                    <div class="bg-blue-50 p-2.5 rounded-lg text-blue-600 border border-blue-100">
                        <i data-lucide="check-circle-2" class="w-5 h-5"></i>
                    </div>
                </div>

                <div class="bg-white border border-slate-200/80 rounded-xl p-4 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Chemicals Consumed</p>
                        <p class="text-2xl font-bold text-slate-900 mt-1"><?= number_format($totalChemicalsUsed) ?> <span class="text-xs font-normal text-slate-500">mL</span></p>
                        <p class="text-[11px] text-amber-600 font-medium mt-1"><?= $totalInspections ?> Site Inspections</p>
                    </div>
                    <div class="bg-amber-50 p-2.5 rounded-lg text-amber-600 border border-amber-100">
                        <i data-lucide="flask-conical" class="w-5 h-5"></i>
                    </div>
                </div>
            </div>

            <!-- ANALYTICS BREAKDOWN: DYNAMIC PEST DISTRIBUTION & CHEMICAL EXPENDITURES -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                
                <!-- Dynamic Target Pest Service Distribution -->
                <div class="bg-white border border-slate-200/80 rounded-xl p-5 space-y-4 shadow-sm">
                    <div class="border-b border-slate-100 pb-3">
                        <h2 class="text-base font-semibold text-slate-900">Target Pest Service Distribution</h2>
                        <p class="text-[11px] text-slate-400 mt-0.5">Frequency of target pest treatments specified across Davao sectors.</p>
                    </div>
                    
                    <div class="space-y-3.5 text-xs">
                        <?php 
                        $colors = ['bg-purple-600', 'bg-amber-500', 'bg-blue-600', 'bg-emerald-600'];
                        $idx = 0;
                        if (!empty($pestDistributions)):
                            foreach ($pestDistributions as $pest):
                                $pct = round(($pest['count'] / $totalRangeJobs) * 100);
                                $colorClass = $colors[$idx % count($colors)];
                                $idx++;
                        ?>
                            <div>
                                <div class="flex justify-between text-slate-600 mb-1 font-medium">
                                    <span><?= htmlspecialchars($pest['service_type']) ?></span>
                                    <span class="font-bold text-slate-800"><?= $pct ?>% (<?= $pest['count'] ?> Jobs)</span>
                                </div>
                                <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                    <div class="<?= $colorClass ?> h-full rounded-full" style="width: <?= $pct ?>%;"></div>
                                </div>
                            </div>
                        <?php 
                            endforeach;
                        else: 
                        ?>
                            <p class="text-xs text-slate-400 py-6 text-center">No job orders recorded for this selected period.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Chemical Inventory Consumption & Cost Analysis -->
                <div class="bg-white border border-slate-200/80 rounded-xl p-5 space-y-4 shadow-sm">
                    <div class="border-b border-slate-100 pb-3 flex justify-between items-center">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">Chemical Consumption & Cost Analysis</h2>
                            <p class="text-[11px] text-slate-400 mt-0.5">Estimated material expenditures based on job usage logs.</p>
                        </div>
                        <span class="text-xs font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-200">Cost-Efficiency Active</span>
                    </div>

                    <div class="space-y-4 text-xs">
                        <?php if (!empty($topChems)): ?>
                            <?php foreach ($topChems as $chem): ?>
                                <div class="bg-slate-50 p-3 rounded-lg border border-slate-100 space-y-1.5">
                                    <div class="flex justify-between font-semibold text-slate-800">
                                        <span><?= htmlspecialchars($chem['item_name']) ?></span>
                                        <span class="text-emerald-700 font-mono">₱<?= number_format($chem['estimated_cost'], 2) ?></span>
                                    </div>
                                    <div class="flex justify-between text-[11px] text-slate-500">
                                        <span>Volume Used: <?= number_format($chem['total_qty']) ?> <?= htmlspecialchars($chem['unit']) ?></span>
                                        <span>Unit Cost: ₱<?= number_format($chem['unit_cost'], 2) ?></span>
                                    </div>
                                    <div class="w-full bg-slate-200 h-1.5 rounded-full overflow-hidden mt-1">
                                        <div class="bg-emerald-600 h-full w-[70%] rounded-full"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-xs text-slate-400 py-6 text-center">No chemical consumption logs found for this period.</p>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- FIELD TECHNICIAN PRODUCTIVITY TABLE -->
            <div class="bg-white border border-slate-200/80 rounded-xl p-5 space-y-4 shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Field Technician Productivity & Output</h2>
                        <p class="text-[11px] text-slate-400 mt-0.5">Completed assignments, sector assignments, and material application volumes per technician.</p>
                    </div>
                    <span class="bg-emerald-50 text-emerald-700 text-[10px] font-semibold px-2.5 py-1 rounded-full border border-emerald-200"><?= count($technicians) ?> Active Technicians</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-[10px] uppercase text-slate-400 border-b border-slate-100">
                                <th class="pb-2 font-semibold">Technician Name</th>
                                <th class="pb-2 font-semibold">Assigned Sector / Region</th>
                                <th class="pb-2 font-semibold">Completed Jobs</th>
                                <th class="pb-2 font-semibold">Total Chemical Applied</th>
                                <th class="pb-2 font-semibold text-right">Account Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (!empty($technicians)): ?>
                                <?php foreach ($technicians as $tech): ?>
                                    <tr class="hover:bg-slate-50/80 transition">
                                        <td class="py-3 font-bold text-slate-800"><?= htmlspecialchars($tech['first_name'] . ' ' . $tech['last_name']) ?></td>
                                        <td class="py-3 text-slate-500"><?= htmlspecialchars($tech['sector_region']) ?></td>
                                        <td class="py-3 text-slate-700 font-medium"><?= $tech['completed_jobs'] ?> Jobs Completed</td>
                                        <td class="py-3 text-emerald-700 font-mono font-medium"><?= number_format($tech['total_chem']) ?> mL</td>
                                        <td class="py-3 text-right">
                                            <span class="bg-emerald-50 text-emerald-700 border border-emerald-200/60 px-2.5 py-0.5 rounded text-[10px] font-semibold"><?= ucfirst($tech['status']) ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="py-4 text-center text-slate-400">No field technicians currently registered.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- 3. Initialize Lucide Icons -->
    <script>
        lucide.createIcons();
    </script>
</body>
</html>