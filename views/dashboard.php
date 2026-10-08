<?php
session_start();

// Guard against unauthenticated access
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// 1. Load database connection first (returns $pdo)
require_once __DIR__ . '/../config/db.php';

// 2. Load auth middleware
require_once __DIR__ . '/../middleware/auth.php';

// 3. Restrict access strictly to Admin role
requireRole(['Admin']);

$userFullName = $_SESSION['full_name'] ?? 'Operations Manager';$userRole = $_SESSION['role'] ?? 'Admin';$today = date('Y-m-d');

// ==========================================
// DYNAMIC DATABASE KPI QUERIES
// ==========================================

// 1. Today's Jobs Count & Status Breakdown
$todayJobsQuery = "SELECT COUNT(*) as total, 
                   SUM(CASE WHEN route_status = 'Completed' THEN 1 ELSE 0 END) as completed,
                   SUM(CASE WHEN route_status = 'Scheduled' THEN 1 ELSE 0 END) as scheduled
                   FROM job_orders WHERE DATE(scheduled_date) = ?";
$stmt = $pdo->prepare($todayJobsQuery);
$stmt->execute([$today]);
$todayJobsData =$stmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'completed' => 0, 'scheduled' => 0];
$todayJobsCount =$todayJobsData['total'] ?? 0;

// 2. Payment Alerts (Logged transactions from payments table)
$paymentAlertsQuery = "SELECT COUNT(*) as count FROM payments"; 
$stmt = $pdo->query($paymentAlertsQuery);
$paymentAlertsData =$stmt->fetch(PDO::FETCH_ASSOC) ?: ['count' => 0];
$pendingPaymentsCount =$paymentAlertsData['count'] ?? 0;

// 3. Active Contracts
$activeContractsQuery = "SELECT COUNT(*) as count FROM contracts WHERE contract_status = 'Active'";
$stmt = $pdo->query($activeContractsQuery);
$activeContractsData =$stmt->fetch(PDO::FETCH_ASSOC) ?: ['count' => 0];
$activeContractsCount =$activeContractsData['count'] ?? 0;

// 4. Low Stock Chemical Alerts
$lowStockQuery = "SELECT COUNT(*) as count FROM inventory WHERE quantity_in_stock <= min_threshold";
$stmt = $pdo->query($lowStockQuery);
$lowStockData =$stmt->fetch(PDO::FETCH_ASSOC) ?: ['count' => 0];
$lowStockAlertsCount =$lowStockData['count'] ?? 0;

// ==========================================
// FEED & LIST QUERIES
// ==========================================

// 5. Recent Clients & Contracts Feed
$inquiriesQuery = "SELECT c.*, ct.id AS contract_id, ct.contract_status, ct.contract_start_date, ct.contract_end_date 
                   FROM clients c 
                   LEFT JOIN contracts ct ON c.id = ct.client_id 
                   ORDER BY c.created_at DESC LIMIT 3";
$inquiriesStmt = $pdo->query($inquiriesQuery);

// 6. Today's Dispatch Schedule
$dispatchQuery = "SELECT * FROM job_orders 
                  WHERE DATE(scheduled_date) = ? 
                  ORDER BY scheduled_date ASC LIMIT 4";
$dispatchStmt = $pdo->prepare($dispatchQuery);
$dispatchStmt->execute([$today]);

// 7. Job Order Status Progress Counts (Using non-reserved alias `delayed_count`)
$statusCountsQuery = "SELECT 
    SUM(CASE WHEN route_status = 'Scheduled' THEN 1 ELSE 0 END) as scheduled,
    SUM(CASE WHEN route_status = 'En route' OR route_status = 'On site' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN route_status = 'Delayed' THEN 1 ELSE 0 END) as delayed_count,
    COUNT(*) as total_open
    FROM job_orders WHERE route_status != 'Completed'";
$statusStmt = $pdo->query($statusCountsQuery);
$statusCounts =$statusStmt->fetch(PDO::FETCH_ASSOC) ?: ['scheduled' => 0, 'in_progress' => 0, 'delayed_count' => 0, 'total_open' => 0];

// 8. Chemical Stock Alert Items
$chemicalAlertsQuery = "SELECT * FROM inventory WHERE quantity_in_stock <= min_threshold LIMIT 2";
$chemicalAlertsStmt = $pdo->query($chemicalAlertsQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Vermex Pest Solutions</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .custom-sidebar { background-color: #0b2219; }
        .active-nav { background-color: #14382a; border-radius: 0.5rem; }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased text-gray-800 flex h-screen overflow-hidden">

    <!-- SIDEBAR -->
    <?php include 'components/sidebar.php'; ?>

    <!-- MAIN CONTENT AREA -->
    <main class="flex-1 flex flex-col min-w-0 overflow-y-auto">

        <!-- Top Navigation Bar -->
        <header class="bg-white border-b border-gray-200 px-4 sm:px-8 py-4 flex justify-between items-center sticky top-0 z-10">
            <div class="flex items-center gap-3">
                <!-- Mobile menu button -->
                <button type="button" onclick="toggleAppSidebar()" class="lg:hidden p-2 -ml-2 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100" aria-label="Open menu">
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>
                <div class="flex items-center gap-2 text-xs text-gray-500">
                    <span>Operations</span>
                    <span>/</span>
                    <span class="font-medium text-gray-800">Dashboard</span>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <div class="hidden sm:flex items-center gap-2 text-xs text-gray-600 bg-gray-100 px-3 py-1.5 rounded-lg border border-gray-200">
                    <i data-lucide="map-pin" class="w-3.5 h-3.5 text-gray-500"></i>
                    <span>Davao City Coverage Zone</span>
                </div>
                
                
            </div>
        </header>

        <div class="p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto space-y-6">

            <!-- Welcome Header & Quick Action Trigger -->
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
                <div>
                    <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Good day, <?= htmlspecialchars(explode(' ', $userFullName)[0]) ?>!</h2>
                    <p class="text-sm text-gray-500 mt-0.5">Here is today's status across bookings, payment clearances, and chemical inventory.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <div class="bg-white border border-gray-200 text-gray-700 text-sm px-4 py-2 rounded-lg flex items-center gap-2 font-medium shadow-sm">
                        <i data-lucide="calendar" class="w-4 h-4 text-gray-500"></i>
                        <?= date('M d, Y') ?>
                    </div>
                    <a href="dispatch.php" class="bg-emerald-700 hover:bg-emerald-800 text-white px-4 py-2 rounded-lg text-sm font-semibold flex items-center gap-2 shadow-sm transition">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        New Job Request
                    </a>
                </div>
            </div>

            <!-- KEY METRIC CARDS -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Today's Schedule -->
                <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex justify-between items-start">
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Jobs Today</p>
                        <h3 class="text-2xl font-bold text-gray-900 mt-1"><?= $todayJobsCount ?></h3>
                        <p class="text-xs text-emerald-700 mt-1 font-medium"><?= $todayJobsData['scheduled'] ?> scheduled • <?= $todayJobsData['completed'] ?> completed</p>
                    </div>
                    <div class="p-2.5 bg-emerald-50 text-emerald-700 rounded-lg">
                        <i data-lucide="calendar-check" class="w-5 h-5"></i>
                    </div>
                </div>

                <!-- Payment Clearances -->
                <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex justify-between items-start">
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Payment Alerts</p>
                        <h3 class="text-2xl font-bold text-gray-900 mt-1"><?= $pendingPaymentsCount ?></h3>
                        <p class="text-xs text-amber-600 mt-1 font-medium">Logged transactions</p>
                    </div>
                    <div class="p-2.5 bg-blue-50 text-blue-700 rounded-lg">
                        <i data-lucide="credit-card" class="w-5 h-5"></i>
                    </div>
                </div>

                <!-- Active Contracts -->
                <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex justify-between items-start">
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Active Contracts</p>
                        <h3 class="text-2xl font-bold text-gray-900 mt-1"><?= $activeContractsCount ?></h3>
                        <p class="text-xs text-gray-500 mt-1 font-medium">Residential & Commercial</p>
                    </div>
                    <div class="p-2.5 bg-purple-50 text-purple-700 rounded-lg">
                        <i data-lucide="file-text" class="w-5 h-5"></i>
                    </div>
                </div>

                <!-- Inventory Warnings -->
                <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex justify-between items-start">
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Chemical Alerts</p>
                        <h3 class="text-2xl font-bold text-gray-900 mt-1"><?= $lowStockAlertsCount ?></h3>
                        <p class="text-xs text-rose-600 mt-1 font-medium">Items below reorder point</p>
                    </div>
                    <div class="p-2.5 bg-amber-50 text-amber-700 rounded-lg">
                        <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                    </div>
                </div>
            </div>

            <!-- DASHBOARD GRID: INQUIRIES & STATUS PANELS -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- LEFT COLUMN: Recent Clients & Schedules -->
                <div class="lg:col-span-2 space-y-6">

                    <!-- Recent Client Registrations / Inquiries Feed -->
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-gray-900 text-base">Recent Clients & Contracts</h3>
                            <a href="clients.php" class="text-xs font-semibold text-emerald-700 hover:text-emerald-800">View all</a>
                        </div>
                        
                        <div class="divide-y divide-gray-100">
                            <?php if ($inquiriesStmt &&$inquiriesStmt->rowCount() > 0): ?>
                                <?php while ($client =$inquiriesStmt->fetch(PDO::FETCH_ASSOC)): ?>
                                    <?php
                                        // Determine badge styling based on relational contract status
                                        $cStatus =$client['contract_status'] ?? 'none';
                                        if ($cStatus === 'Active') {
                                            $badgeClass = 'bg-emerald-50 text-emerald-700 border-emerald-200';$badgeText = 'Contract: Active';
                                        } elseif ($cStatus === 'expiring_soon') {
                                            $badgeClass = 'bg-amber-50 text-amber-700 border-amber-200';$badgeText = 'Expiring Soon';
                                        } else {
                                            $badgeClass = 'bg-slate-100 text-slate-600 border-slate-200';$badgeText = 'No Active Contract';
                                        }
                                    ?>
                                    <div class="py-3 flex justify-between items-start">
                                        <div class="flex gap-3">
                                            <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-xs mt-0.5">
                                                <?= strtoupper(substr($client['client_name'] ?? 'C', 0, 1)) ?>
                                            </div>
                                            <div>
                                                <h4 class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($client['client_name']) ?></h4>
                                                <p class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars($client['client_type']) ?> • <?= htmlspecialchars($client['street_address'] ?? ($client['barangay'] ?? 'Davao City')) ?></p>
                                                <span class="inline-block mt-2 px-2 py-0.5 <?= $badgeClass ?> border rounded text-[10px] font-semibold"><?= $badgeText ?></span>
                                            </div>
                                        </div>
                                        <span class="text-xs text-gray-400"><?= date('M d, Y', strtotime($client['created_at'])) ?></span>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p class="text-xs text-gray-400 py-4 text-center">No recent clients found.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Today's Dispatch Schedule -->
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-gray-900 text-base">Today's Dispatch Schedule</h3>
                            <a href="job_orders.php" class="text-xs font-semibold text-emerald-700 hover:text-emerald-800">Open schedule</a>
                        </div>
                        
                        <div class="space-y-3">
                            <?php if ($dispatchStmt &&$dispatchStmt->rowCount() > 0): ?>
                                <?php while ($job =$dispatchStmt->fetch(PDO::FETCH_ASSOC)): ?>
                                    <div class="border-l-4 border-emerald-700 bg-gray-50 p-3 rounded-r-lg flex justify-between items-center">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs font-bold text-emerald-800"><?= htmlspecialchars($job['service_window']) ?></span>
                                                <span class="text-xs text-gray-500">• Tech: <?= htmlspecialchars($job['assigned_tech']) ?></span>
                                            </div>
                                            <p class="text-sm font-semibold text-gray-800 mt-1"><?= htmlspecialchars($job['client_name']) ?> - <?= htmlspecialchars($job['service_type']) ?></p>
                                            <p class="text-xs text-gray-500"><?= htmlspecialchars($job['location']) ?></p>
                                        </div>
                                        <span class="px-2.5 py-1 bg-emerald-100 text-emerald-800 rounded-full text-xs font-semibold"><?= htmlspecialchars($job['route_status']) ?></span>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p class="text-xs text-gray-400 py-4 text-center">No dispatches scheduled for today.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

                <!-- RIGHT COLUMN: Job Order Status, Inventory Alerts & Quick Actions -->
                <div class="space-y-6">

                    <!-- Job-order Status Progress -->
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-gray-900 text-base">Job Order Status</h3>
                            <span class="text-xs font-semibold text-gray-500"><?= $statusCounts['total_open'] ?> Open</span>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <div class="flex justify-between text-xs font-medium mb-1">
                                    <span class="text-gray-600">Scheduled Dispatch</span>
                                    <span class="font-bold text-gray-900"><?= $statusCounts['scheduled'] ?></span>
                                </div>
                                <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden">
                                    <div class="bg-emerald-700 h-full" style="width: <?= $statusCounts['total_open'] > 0 ? ($statusCounts['scheduled'] /$statusCounts['total_open']) * 100 : 0 ?>%"></div>
                                </div>
                            </div>

                            <div>
                                <div class="flex justify-between text-xs font-medium mb-1">
                                    <span class="text-gray-600">In Progress (Field Work)</span>
                                    <span class="font-bold text-gray-900"><?= $statusCounts['in_progress'] ?></span>
                                </div>
                                <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden">
                                    <div class="bg-blue-600 h-full" style="width: <?= $statusCounts['total_open'] > 0 ? ($statusCounts['in_progress'] /$statusCounts['total_open']) * 100 : 0 ?>%"></div>
                                </div>
                            </div>

                            <div>
                                <div class="flex justify-between text-xs font-medium mb-1">
                                    <span class="text-gray-600">Delayed / Rescheduled</span>
                                    <span class="font-bold text-rose-600"><?= $statusCounts['delayed_count'] ?></span>
                                </div>
                                <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden">
                                    <div class="bg-rose-500 h-full" style="width: <?= $statusCounts['total_open'] > 0 ? ($statusCounts['delayed_count'] /$statusCounts['total_open']) * 100 : 0 ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Chemical Inventory Low Stock Warnings -->
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-gray-900 text-base">Chemical Stock Alerts</h3>
                            <a href="inventory.php" class="text-xs font-semibold text-emerald-700 hover:text-emerald-800">Manage</a>
                        </div>

                        <div class="space-y-3">
                            <?php if ($chemicalAlertsStmt &&$chemicalAlertsStmt->rowCount() > 0): ?>
                                <?php while ($chem =$chemicalAlertsStmt->fetch(PDO::FETCH_ASSOC)): ?>
                                    <div class="p-3 bg-rose-50 border border-rose-100 rounded-lg flex items-start gap-3">
                                        <i data-lucide="alert-circle" class="w-5 h-5 text-rose-600 mt-0.5"></i>
                                        <div>
                                            <h4 class="text-xs font-bold text-gray-800"><?= htmlspecialchars($chem['item_name']) ?></h4>
                                            <p class="text-[11px] text-rose-700 font-semibold mt-0.5"><?= $chem['quantity_in_stock'] . ' ' . $chem['unit'] ?> Remaining (Min: <?=$chem['min_threshold'] ?>)</p>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p class="text-xs text-gray-400 py-2 text-center">All chemical stock levels are optimal.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Actions Grid -->
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                        <h3 class="font-bold text-gray-900 text-base mb-3">Quick Actions</h3>
                        <div class="grid grid-cols-2 gap-3">
                            <a href="dispatch.php" class="p-3 bg-gray-50 hover:bg-emerald-50 hover:border-emerald-200 border border-gray-200 rounded-lg text-left transition flex flex-col items-center justify-center text-center">
                                <i data-lucide="file-plus" class="w-5 h-5 text-emerald-700 mb-1"></i>
                                <span class="text-xs font-medium text-gray-700">Create Job Order</span>
                            </a>
                            <a href="clients.php" class="p-3 bg-gray-50 hover:bg-emerald-50 hover:border-emerald-200 border border-gray-200 rounded-lg text-left transition flex flex-col items-center justify-center text-center">
                                <i data-lucide="user-plus" class="w-5 h-5 text-emerald-700 mb-1"></i>
                                <span class="text-xs font-medium text-gray-700">Add New Client</span>
                            </a>
                            <a href="payments.php" class="p-3 bg-gray-50 hover:bg-emerald-50 hover:border-emerald-200 border border-gray-200 rounded-lg text-left transition flex flex-col items-center justify-center text-center">
                                <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-700 mb-1"></i>
                                <span class="text-xs font-medium text-gray-700">Verify Payment</span>
                            </a>
                            <a href="inventory.php" class="p-3 bg-gray-50 hover:bg-emerald-50 hover:border-emerald-200 border border-gray-200 rounded-lg text-left transition flex flex-col items-center justify-center text-center">
                                <i data-lucide="package-plus" class="w-5 h-5 text-emerald-700 mb-1"></i>
                                <span class="text-xs font-medium text-gray-700">Log Chemical Usage</span>
                            </a>
                        </div>
                    </div>

                </div>

            </div>

        </div>
    </main>

    <!-- Lucide Icons Initialization -->
    <script>
        lucide.createIcons();
    </script>
</body>
</html>