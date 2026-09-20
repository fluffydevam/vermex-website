<?php
session_start();

// Guard against unauthenticated access
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/db.php';

// Fetch dynamic operational counts (Fallback defaults if database tables are empty)
$todayJobsCount = 6;            // Daily transaction average (4 to 9 daily)
$pendingPaymentsCount = 4;      // Pending 50% downpayment / 30-day balance clearings
$activeContractsCount = 185;     // Active 1/2-Yr or Monthly contracts (~160-220 monthly calls)
$lowStockAlertsCount = 3;       // Chemical inventory reorder alerts[cite: 1]

$userFullName = $_SESSION['full_name'] ?? 'Operations Manager';
$userRole = $_SESSION['role'] ?? 'Admin';
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
    <style>
        .custom-sidebar { background-color: #0b2219; }
        .active-nav { background-color: #14382a; border-radius: 0.5rem; }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased text-gray-800 flex h-screen overflow-hidden">

    <!-- SIDEBAR -->
    <?php include 'components/sidebar.php'; ?>
    

    <!-- MAIN CONTENT AREA -->
    <main class="flex-1 flex flex-col overflow-y-auto">

        <!-- Top Navigation Bar -->
        <header class="bg-white border-b border-gray-200 px-8 py-4 flex justify-between items-center sticky top-0 z-10">
            <div class="flex items-center gap-2 text-xs text-gray-500">
                <span>Operations</span>
                <span>/</span>
                <span class="font-medium text-gray-800">Dashboard</span>
            </div>
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2 text-xs text-gray-600 bg-gray-100 px-3 py-1.5 rounded-lg border border-gray-200">
                    <i data-lucide="map-pin" class="w-3.5 h-3.5 text-gray-500"></i>
                    <span>Davao City Coverage Zone</span>[cite: 1]
                </div>
                <button class="relative p-2 text-gray-500 hover:text-gray-700 rounded-full hover:bg-gray-100">
                    <i data-lucide="bell" class="w-5 h-5"></i>
                    <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                </button>
            </div>
        </header>

        <div class="p-8 max-w-7xl w-full mx-auto space-y-6">

            <!-- Welcome Header & Quick Action Trigger -->
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Good day, <?= htmlspecialchars(explode(' ', $userFullName)[0]) ?>!</h2>
                    <p class="text-sm text-gray-500 mt-0.5">Here is today's status across bookings, payment clearances, and chemical inventory[cite: 1].</p>
                </div>
                <div class="flex gap-3">
                    <div class="bg-white border border-gray-200 text-gray-700 text-sm px-4 py-2 rounded-lg flex items-center gap-2 font-medium shadow-sm">
                        <i data-lucide="calendar" class="w-4 h-4 text-gray-500"></i>
                        <?= date('M d, Y') ?>
                    </div>
                    <button class="bg-emerald-700 hover:bg-emerald-800 text-white px-4 py-2 rounded-lg text-sm font-semibold flex items-center gap-2 shadow-sm transition">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        New Job Request
                    </button>
                </div>
            </div>

            <!-- KEY METRIC CARDS -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Today's Schedule -->
                <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex justify-between items-start">
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Jobs Today</p>
                        <h3 class="text-2xl font-bold text-gray-900 mt-1"><?= $todayJobsCount ?></h3>
                        <p class="text-xs text-emerald-600 mt-1 font-medium">4 dispatched • 2 pending balance</p>
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
                        <p class="text-xs text-amber-600 mt-1 font-medium">50% Deposit awaiting validation</p>[cite: 1]
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
                        <p class="text-xs text-gray-500 mt-1 font-medium">Residential & Commercial</p>[cite: 1]
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
                        <p class="text-xs text-red-600 mt-1 font-medium">Items below reorder point</p>[cite: 1]
                    </div>
                    <div class="p-2.5 bg-amber-50 text-amber-700 rounded-lg">
                        <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                    </div>
                </div>
            </div>

            <!-- DASHBOARD GRID: INQUIRIES & STATUS PANELS -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- LEFT COLUMN: Recent Inquiries & Schedules (2 Cols wide) -->
                <div class="lg:col-span-2 space-y-6">

                    <!-- Recent Client Inquiries Feed -->
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-gray-900 text-base">Recent Inquiries & Requests</h3>
                            <a href="#" class="text-xs font-semibold text-emerald-700 hover:text-emerald-800">View all</a>
                        </div>
                        
                        <div class="divide-y divide-gray-100">
                            <!-- Entry 1 -->
                            <div class="py-3 flex justify-between items-start">
                                <div class="flex gap-3">
                                    <div class="w-8 h-8 rounded-full bg-red-100 text-red-700 flex items-center justify-center font-bold text-xs mt-0.5">
                                        R
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-semibold text-gray-800">Marco Polo Hotel Davao</h4>
                                        <p class="text-xs text-gray-500 mt-0.5">Commercial Monthly Plan • Termite Treatment Inquiry</p>[cite: 1]
                                        <span class="inline-block mt-2 px-2 py-0.5 bg-red-50 text-red-700 border border-red-200 rounded text-[10px] font-semibold">Urgent Visit Requested</span>
                                    </div>
                                </div>
                                <span class="text-xs text-gray-400">12 min ago</span>
                            </div>

                            <!-- Entry 2 -->
                            <div class="py-3 flex justify-between items-start">
                                <div class="flex gap-3">
                                    <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-xs mt-0.5">
                                        G
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-semibold text-gray-800">Gonzales Residence (Lanang)</h4>
                                        <p class="text-xs text-gray-500 mt-0.5">1/2-Year Residential Contract • General Pest Control</p>[cite: 1]
                                        <span class="inline-block mt-2 px-2 py-0.5 bg-amber-50 text-amber-700 border border-amber-200 rounded text-[10px] font-semibold">50% Downpayment Verification</span>[cite: 1]
                                    </div>
                                </div>
                                <span class="text-xs text-gray-400">45 min ago</span>
                            </div>

                            <!-- Entry 3 -->
                            <div class="py-3 flex justify-between items-start">
                                <div class="flex gap-3">
                                    <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-xs mt-0.5">
                                        D
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-semibold text-gray-800">Davao General Store</h4>
                                        <p class="text-xs text-gray-500 mt-0.5">Monthly Commercial Contract • Rodent & Cockroach Control</p>[cite: 1]
                                        <span class="inline-block mt-2 px-2 py-0.5 bg-blue-50 text-blue-700 border border-blue-200 rounded text-[10px] font-semibold">Quote Sent</span>
                                    </div>
                                </div>
                                <span class="text-xs text-gray-400">2 hrs ago</span>
                            </div>
                        </div>
                    </div>

                    <!-- Upcoming Inspection & Treatment Schedules -->
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-gray-900 text-base">Today's Dispatch Schedule</h3>
                            <a href="#" class="text-xs font-semibold text-emerald-700 hover:text-emerald-800">Open schedule</a>
                        </div>
                        
                        <div class="space-y-3">
                            <div class="border-l-4 border-emerald-600 bg-gray-50 p-3 rounded-r-lg flex justify-between items-center">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold text-emerald-800">09:30 AM</span>
                                        <span class="text-xs text-gray-500">• Assigned Tech: John Rey (Field Technician)</span>[cite: 1]
                                    </div>
                                    <p class="text-sm font-semibold text-gray-800 mt-1">Villa Heights Subdivision Site Visit</p>
                                    <p class="text-xs text-gray-500">Chemical Dilution: Imidacloprid 0.05% Solution</p>[cite: 1]
                                </div>
                                <span class="px-2.5 py-1 bg-emerald-100 text-emerald-800 rounded-full text-xs font-semibold">Cleared / Paid 50%</span>[cite: 1]
                            </div>

                            <div class="border-l-4 border-amber-500 bg-gray-50 p-3 rounded-r-lg flex justify-between items-center">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold text-amber-800">01:30 PM</span>
                                        <span class="text-xs text-gray-500">• Assigned Tech: Jayjay (Field Technician)</span>[cite: 1]
                                    </div>
                                    <p class="text-sm font-semibold text-gray-800 mt-1">Torres St. Restaurant Facility</p>
                                    <p class="text-xs text-gray-500">Pending Billing Clearance verification with Billing Officer</p>[cite: 1]
                                </div>
                                <span class="px-2.5 py-1 bg-amber-100 text-amber-800 rounded-full text-xs font-semibold">Pending Payment</span>[cite: 1]
                            </div>
                        </div>
                    </div>

                </div>

                <!-- RIGHT COLUMN: Job Order Status, Inventory Alerts & Quick Actions -->
                <div class="space-y-6">

                    <!-- Job-order Status Progress -->
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-gray-900 text-base">Job Order Status</h3>
                            <span class="text-xs font-semibold text-gray-500">22 Open</span>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <div class="flex justify-between text-xs font-medium mb-1">
                                    <span class="text-gray-600">Scheduled Dispatch</span>
                                    <span class="font-bold text-gray-900">12</span>
                                </div>
                                <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden">
                                    <div class="bg-emerald-600 h-full" style="width: 55%"></div>
                                </div>
                            </div>

                            <div>
                                <div class="flex justify-between text-xs font-medium mb-1">
                                    <span class="text-gray-600">In Progress (Field Work)</span>
                                    <span class="font-bold text-gray-900">5</span>
                                </div>
                                <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden">
                                    <div class="bg-blue-600 h-full" style="width: 25%"></div>
                                </div>
                            </div>

                            <div>
                                <div class="flex justify-between text-xs font-medium mb-1">
                                    <span class="text-gray-600">Awaiting Billing Review</span>
                                    <span class="font-bold text-gray-900">3</span>
                                </div>
                                <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden">
                                    <div class="bg-amber-500 h-full" style="width: 15%"></div>
                                </div>
                            </div>

                            <div>
                                <div class="flex justify-between text-xs font-medium mb-1">
                                    <span class="text-gray-600">30-Day Overdue Receivables</span>[cite: 1]
                                    <span class="font-bold text-red-600">2</span>
                                </div>
                                <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden">
                                    <div class="bg-red-500 h-full" style="width: 10%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Chemical Inventory Low Stock Warnings -->
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-gray-900 text-base">Chemical Stock Alerts</h3>
                            <a href="#" class="text-xs font-semibold text-emerald-700 hover:text-emerald-800">Manage</a>
                        </div>

                        <div class="space-y-3">
                            <div class="p-3 bg-red-50 border border-red-100 rounded-lg flex items-start gap-3">
                                <i data-lucide="alert-circle" class="w-5 h-5 text-red-600 mt-0.5"></i>
                                <div>
                                    <h4 class="text-xs font-bold text-gray-800">Fipronil Termiticide Liquid</h4>
                                    <p class="text-[11px] text-red-700 font-semibold mt-0.5">2 Liters Remaining (Below min 5L threshold)</p>[cite: 1]
                                </div>
                            </div>

                            <div class="p-3 bg-amber-50 border border-amber-100 rounded-lg flex items-start gap-3">
                                <i data-lucide="alert-triangle" class="w-5 h-5 text-amber-600 mt-0.5"></i>
                                <div>
                                    <h4 class="text-xs font-bold text-gray-800">Bromadiolone Bait Blocks</h4>
                                    <p class="text-[11px] text-amber-700 font-semibold mt-0.5">8 Packs Remaining (Below min 15 packs)</p][cite: 1]
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions Grid -->
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                        <h3 class="font-bold text-gray-900 text-base mb-3">Quick Actions</h3>
                        <div class="grid grid-cols-2 gap-3">
                            <button class="p-3 bg-gray-50 hover:bg-emerald-50 hover:border-emerald-200 border border-gray-200 rounded-lg text-left transition flex flex-col items-center justify-center text-center">
                                <i data-lucide="file-plus" class="w-5 h-5 text-emerald-700 mb-1"></i>
                                <span class="text-xs font-medium text-gray-700">Create Job Order</span>
                            </button>
                            <button class="p-3 bg-gray-50 hover:bg-emerald-50 hover:border-emerald-200 border border-gray-200 rounded-lg text-left transition flex flex-col items-center justify-center text-center">
                                <i data-lucide="user-plus" class="w-5 h-5 text-emerald-700 mb-1"></i>
                                <span class="text-xs font-medium text-gray-700">Add New Client</span>
                            </button>
                            <button class="p-3 bg-gray-50 hover:bg-emerald-50 hover:border-emerald-200 border border-gray-200 rounded-lg text-left transition flex flex-col items-center justify-center text-center">
                                <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-700 mb-1"></i>
                                <span class="text-xs font-medium text-gray-700">Verify Payment</span>[cite: 1]
                            </button>
                            <button class="p-3 bg-gray-50 hover:bg-emerald-50 hover:border-emerald-200 border border-gray-200 rounded-lg text-left transition flex flex-col items-center justify-center text-center">
                                <i data-lucide="package-plus" class="w-5 h-5 text-emerald-700 mb-1"></i>
                                <span class="text-xs font-medium text-gray-700">Log Chemical Usage</span>[cite: 1]
                            </button>
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