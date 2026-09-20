<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/db.php';

$userFullName = $_SESSION['full_name'] ?? 'Operations Manager';
$userRole = $_SESSION['role'] ?? 'Admin';

// Fetch Clients along with their Active Contracts from Database
$query = "
    SELECT 
        c.id AS client_id,
        c.client_name,
        c.contact_person,
        c.phone_number,
        c.property_address,
        c.client_type,
        ct.contract_type,
        ct.contract_value,
        ct.downpayment_status,
        ct.balance_status,
        ct.start_date,
        ct.end_date,
        ct.contract_status
    FROM clients c
    LEFT JOIN contracts ct ON c.id = ct.client_id
    ORDER BY c.created_at DESC
";
$stmt = $pdo->query($query);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If database is empty, provide sample records aligned with Vermex operations
if (empty($records)) {
    $records = [
        [
            'client_id' => 1,
            'client_name' => 'Gonzales Residence',
            'contact_person' => 'Maria Gonzales',
            'phone_number' => '0917-123-4567',
            'property_address' => 'Villa Heights Subd., Lanang, Davao City',
            'client_type' => 'Residential',
            'contract_type' => '1/2-Year Residential',
            'contract_value' => 18000.00,
            'downpayment_status' => 'Pending Verification',
            'balance_status' => 'Pending 30-Day Window',
            'start_date' => '2026-09-01',
            'end_date' => '2027-03-01',
            'contract_status' => 'Pending Clearance'
        ],
        [
            'client_id' => 2,
            'client_name' => 'Marco Polo Hotel Davao',
            'contact_person' => 'Roberto Santos',
            'phone_number' => '0982-987-6543',
            'property_address' => 'C.M. Recto Ave, Davao City',
            'client_type' => 'Commercial',
            'contract_type' => 'Monthly Commercial',
            'contract_value' => 145000.00,
            'downpayment_status' => 'Verified / Cleared',
            'balance_status' => 'Cleared',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'contract_status' => 'Active'
        ],
        [
            'client_id' => 3,
            'client_name' => 'Davao General Commercial Store',
            'contact_person' => 'Arthur Chua',
            'phone_number' => '0919-555-0192',
            'property_address' => 'Torres St., Davao City',
            'client_type' => 'Commercial',
            'contract_type' => 'Monthly Commercial',
            'contract_value' => 64000.00,
            'downpayment_status' => 'Verified / Cleared',
            'balance_status' => 'Overdue',
            'start_date' => '2026-05-15',
            'end_date' => '2027-05-15',
            'contract_status' => 'Active'
        ]
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clients & Contracts - Vermex Pest Solutions</title>
    <script src="https://cdn.tailwindcss.com"></script>
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

        <!-- Top Header -->
        <header class="bg-white border-b border-gray-200 px-8 py-4 flex justify-between items-center sticky top-0 z-10">
            <div class="flex items-center gap-2 text-xs text-gray-500">
                <span>Operations</span>
                <span>/</span>
                <span class="font-medium text-gray-800">Clients & Contracts</span>
            </div>
            <div class="flex items-center gap-3">
                <button class="bg-gray-100 hover:bg-gray-200 text-gray-700 border border-gray-300 px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-2">
                    <i data-lucide="printer" class="w-4 h-4"></i>
                    Print List
                </button>
            </div>
        </header>

        <div class="p-8 max-w-7xl w-full mx-auto space-y-6">

            <!-- Title & Actions -->
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Clients & Contracts</h2>
                    <p class="text-sm text-gray-500 mt-0.5">Manage customer profiles, 1/2-year residential & monthly commercial service agreements[cite: 1].</p>
                </div>
                <div class="flex gap-3">
                    <button class="bg-emerald-700 hover:bg-emerald-800 text-white px-4 py-2 rounded-lg text-sm font-semibold flex items-center gap-2 shadow-sm transition">
                        <i data-lucide="user-plus" class="w-4 h-4"></i>
                        Add Client
                    </button>
                    <button class="bg-emerald-900 hover:bg-emerald-950 text-white px-4 py-2 rounded-lg text-sm font-semibold flex items-center gap-2 shadow-sm transition">
                        <i data-lucide="file-signature" class="w-4 h-4"></i>
                        Create Contract
                    </button>
                </div>
            </div>

            <!-- TOP METRIC WIDGET CARDS -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                    <p class="text-xs font-semibold text-gray-500 uppercase">Total Clients</p>
                    <h3 class="text-2xl font-bold text-gray-900 mt-1">248</h3>
                    <p class="text-[11px] text-emerald-600 mt-1 font-medium">+12 this month</p>
                </div>

                <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                    <p class="text-xs font-semibold text-gray-500 uppercase">Active Contracts</p>
                    <h3 class="text-2xl font-bold text-gray-900 mt-1">196</h3>
                    <p class="text-[11px] text-gray-500 mt-1 font-medium">1/2-Yr & Monthly</p>[cite: 1]
                </div>

                <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                    <p class="text-xs font-semibold text-gray-500 uppercase">Pending Downpayments</p>
                    <h3 class="text-2xl font-bold text-amber-600 mt-1">4</h3>
                    <p class="text-[11px] text-amber-600 mt-1 font-medium">Awaiting 50% deposit</p>[cite: 1]
                </div>

                <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                    <p class="text-xs font-semibold text-gray-500 uppercase">Overdue Balances</p>
                    <h3 class="text-2xl font-bold text-red-600 mt-1">3</h3>
                    <p class="text-[11px] text-red-600 mt-1 font-medium">Past 30-day window</p>[cite: 1]
                </div>

                <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                    <p class="text-xs font-semibold text-gray-500 uppercase">Contract Portfolio</p>
                    <h3 class="text-2xl font-bold text-emerald-800 mt-1">₱1.84M</h3>
                    <p class="text-[11px] text-gray-500 mt-1 font-medium">Annual recurring</p>
                </div>
            </div>

            <!-- CLEANED & PROPERLY ALIGNED RECORDS TABLE -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                
                <!-- Search & Filters Header -->
                <div class="p-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-4 bg-gray-50/50">
                    <div class="relative flex-1 min-w-[280px]">
                        <i data-lucide="search" class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                        <input type="text" placeholder="Search clients, contracts, addresses..." 
                            class="w-full pl-9 pr-4 py-2 border rounded-lg text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:border-transparent bg-white">
                    </div>
                    <div class="flex gap-2">
                        <select class="border border-gray-300 bg-white text-gray-700 py-2 px-3 rounded-lg text-xs font-medium focus:outline-none">
                            <option>All Contract Types</option>
                            <option>1/2-Year Residential</option>[cite: 1]
                            <option>Monthly Commercial</option>[cite: 1]
                        </select>
                        <select class="border border-gray-300 bg-white text-gray-700 py-2 px-3 rounded-lg text-xs font-medium focus:outline-none">
                            <option>All Payment Clearance Statuses</option>
                            <option>Pending 50% Downpayment</option>[cite: 1]
                            <option>30-Day Balance Overdue</option>[cite: 1]
                            <option>Fully Cleared</option>
                        </select>
                    </div>
                </div>

                <!-- Structured Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-100/70 text-[11px] font-bold uppercase text-gray-600 tracking-wider">
                                <th class="py-3.5 px-4">Client Name & Address</th>
                                <th class="py-3.5 px-4">Agreement Plan</th>
                                <th class="py-3.5 px-4">50% Downpayment Clearance</th>[cite: 1]
                                <th class="py-3.5 px-4">30-Day Balance Status</th>[cite: 1]
                                <th class="py-3.5 px-4">Contract Value</th>
                                <th class="py-3.5 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 text-xs">
                            <?php foreach ($records as $row): ?>
                                <tr class="hover:bg-gray-50/80 transition">
                                    <!-- Client Name & Location -->
                                    <td class="py-4 px-4">
                                        <div class="font-bold text-gray-900 text-sm"><?= htmlspecialchars($row['client_name']) ?></div>
                                        <div class="text-gray-500 text-[11px] flex items-center gap-1 mt-0.5">
                                            <i data-lucide="map-pin" class="w-3 h-3 text-gray-400"></i>
                                            <?= htmlspecialchars($row['property_address']) ?>
                                        </div>
                                        <div class="text-gray-400 text-[10px] mt-0.5">Contact: <?= htmlspecialchars($row['contact_person']) ?> (<?= htmlspecialchars($row['phone_number']) ?>)</div>
                                    </td>

                                    <!-- Agreement Plan -->
                                    <td class="py-4 px-4 whitespace-nowrap">
                                        <span class="font-semibold text-gray-800 block"><?= htmlspecialchars($row['contract_type']) ?></span>
                                        <span class="text-[10px] text-gray-500"><?= date('M d, Y', strtotime($row['start_date'])) ?> - <?= date('M d, Y', strtotime($row['end_date'])) ?></span>
                                    </td>

                                    <!-- 50% Downpayment Status -->
                                    <td class="py-4 px-4 whitespace-nowrap">
                                        <?php if ($row['downpayment_status'] === 'Verified / Cleared'): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-emerald-100 text-emerald-800 rounded-full text-[11px] font-semibold">
                                                <i data-lucide="check-circle" class="w-3 h-3"></i> Verified Deposit
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-100 text-amber-800 rounded-full text-[11px] font-semibold">
                                                <i data-lucide="clock" class="w-3 h-3"></i> Deposit Pending
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Balance Tracking Status -->
                                    <td class="py-4 px-4 whitespace-nowrap">
                                        <?php if ($row['balance_status'] === 'Cleared'): ?>
                                            <span class="px-2.5 py-1 bg-gray-100 text-gray-700 rounded-md text-[11px] font-medium">Fully Settled</span>
                                        <?php elseif ($row['balance_status'] === 'Overdue'): ?>
                                            <span class="px-2.5 py-1 bg-red-100 text-red-700 rounded-md text-[11px] font-bold">30-Day Overdue</span>[cite: 1]
                                        <?php else: ?>
                                            <span class="px-2.5 py-1 bg-blue-50 text-blue-700 rounded-md text-[11px] font-medium">Active 30-Day Window</span>[cite: 1]
                                        <?php endif; ?>
                                    </td>

                                    <!-- Contract Value -->
                                    <td class="py-4 px-4 whitespace-nowrap">
                                        <div class="font-bold text-gray-900 text-sm">₱<?= number_format($row['contract_value'], 2) ?></div>
                                    </td>

                                    <!-- Actions -->
                                    <td class="py-4 px-4 text-right whitespace-nowrap">
                                        <button class="text-emerald-700 hover:text-emerald-900 font-semibold mr-3">View</button>
                                        <button class="text-gray-600 hover:text-gray-900 font-semibold">Edit</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div class="p-4 border-t border-gray-200 flex items-center justify-between text-xs text-gray-500 bg-gray-50/50">
                    <span>Showing 3 of 248 clients</span>
                    <div class="flex gap-1">
                        <button class="px-3 py-1 border rounded bg-white font-medium hover:bg-gray-100 disabled:opacity-50">Previous</button>
                        <button class="px-3 py-1 border rounded bg-emerald-700 text-white font-medium">1</button>
                        <button class="px-3 py-1 border rounded bg-white font-medium hover:bg-gray-100">2</button>
                        <button class="px-3 py-1 border rounded bg-white font-medium hover:bg-gray-100">Next</button>
                    </div>
                </div>

            </div>

        </div>
    </main>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>