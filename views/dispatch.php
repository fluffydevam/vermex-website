<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/db.php';

$userFullName = $_SESSION['full_name'] ?? 'Operations Manager';
$userRole = $_SESSION['role'] ?? 'Admin';

// Fetch today's job orders
$stmt = $pdo->prepare("SELECT * FROM job_orders WHERE scheduled_date = CURDATE() ORDER BY service_window ASC");
$stmt->execute();
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Default mock data matching the screenshot if table is currently empty
if (empty($jobs)) {
    $jobs = [
        [
            'id' => 1,
            'client_name' => 'Apo View Hotel',
            'location' => 'J. Camus St., Poblacion, Davao City',
            'service_type' => 'Cockroach & Termite Treatment',
            'assigned_tech' => 'John Mark Arguilles',
            'service_window' => '08:00–09:30',
            'priority' => 'Urgent',
            'route_status' => 'En route',
            'payment_cleared' => 1
        ],
        [
            'id' => 2,
            'client_name' => 'Davao Doctors Hospital',
            'location' => 'Quirino Ave., Poblacion, Davao City',
            'service_type' => 'Rodent Control Monitoring',
            'assigned_tech' => 'John Rey Murillo',
            'service_window' => '09:00–10:00',
            'priority' => 'High',
            'route_status' => 'On site',
            'payment_cleared' => 1
        ],
        [
            'id' => 3,
            'client_name' => 'Seda Abreeza',
            'location' => 'J.P. Laurel Ave., Bajada, Davao City',
            'service_type' => 'Bed Bug Inspection',
            'assigned_tech' => 'Jayjay Baugbug',
            'service_window' => '10:30–12:00',
            'priority' => 'Standard',
            'route_status' => 'Scheduled',
            'payment_cleared' => 1
        ],
        [
            'id' => 4,
            'client_name' => 'Malagos Garden Resort',
            'location' => 'Calinan, Davao City',
            'service_type' => 'Mosquito Fogging & Misting',
            'assigned_tech' => 'Team South (Field Techs)',
            'service_window' => '13:00–14:30',
            'priority' => 'Standard',
            'route_status' => 'Scheduled',
            'payment_cleared' => 0
        ],
        [
            'id' => 5,
            'client_name' => 'SM Lanang Premier',
            'location' => 'J.P. Laurel Ave., Lanang, Davao City',
            'service_type' => 'Termite Follow-up Application',
            'assigned_tech' => 'John Mark Arguilles',
            'service_window' => '15:00–16:00',
            'priority' => 'High',
            'route_status' => 'Delayed',
            'payment_cleared' => 1
        ]
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dispatch & Field Jobs - Vermex Pest Solutions</title>
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

        <!-- Header Bar -->
        <header class="bg-white border-b border-gray-200 px-8 py-3.5 flex justify-between items-center sticky top-0 z-10">
            <div class="flex items-center gap-2 text-xs text-gray-500">
                <span>Operations</span>
                <span>/</span>
                <span class="font-medium text-gray-800">Dispatch & Field Jobs</span>
            </div>
            <div class="flex items-center gap-4">
                <button class="p-2 text-gray-500 hover:text-gray-700 rounded-full hover:bg-gray-100"><i data-lucide="search" class="w-4 h-4"></i></button>
                <button class="p-2 text-gray-500 hover:text-gray-700 rounded-full hover:bg-gray-100 relative">
                    <i data-lucide="bell" class="w-4 h-4"></i>
                    <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full"></span>
                </button>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-700 text-xs font-semibold rounded-full border border-emerald-200">
                    <span class="w-2 h-2 bg-emerald-500 rounded-full animate-ping"></span>
                    Operations Live
                </span>
            </div>
        </header>

        <div class="p-8 max-w-7xl w-full mx-auto space-y-6">

            <!-- Title & Top Filter Action Bar -->
            <div class="flex justify-between items-start">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Dispatch & Field Jobs</h2>
                    <p class="text-xs text-gray-500 mt-1">Coordinate today's teams, service windows, and field routes across Davao.</p>
                </div>
                <div class="flex gap-2">
                    <button class="bg-white border border-gray-300 text-gray-700 px-3.5 py-2 rounded-lg text-xs font-semibold flex items-center gap-2 hover:bg-gray-50">
                        <i data-lucide="user-check" class="w-4 h-4 text-gray-500"></i> Assign job
                    </button>
                    <button class="bg-emerald-700 hover:bg-emerald-800 text-white px-3.5 py-2 rounded-lg text-xs font-semibold flex items-center gap-2 shadow-sm transition">
                        <i data-lucide="plus" class="w-4 h-4"></i> Create job
                    </button>
                </div>
            </div>

            <!-- Toolbar Filters -->
            <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
                <div class="flex gap-2">
                    <button class="bg-white border border-gray-200 text-gray-800 px-3 py-1.5 rounded-lg text-xs font-medium flex items-center gap-2 shadow-sm">
                        <i data-lucide="calendar" class="w-3.5 h-3.5 text-gray-500"></i> Today - <?= date('M d, Y') ?>
                    </button>
                    <button class="bg-white border border-gray-200 text-gray-600 px-3 py-1.5 rounded-lg text-xs font-medium flex items-center gap-2 hover:bg-gray-50">
                        <i data-lucide="users" class="w-3.5 h-3.5 text-gray-500"></i> All technicians
                    </button>
                    <button class="bg-white border border-gray-200 text-gray-600 px-3 py-1.5 rounded-lg text-xs font-medium flex items-center gap-2 hover:bg-gray-50">
                        <i data-lucide="map" class="w-3.5 h-3.5 text-gray-500"></i> All teams
                    </button>
                </div>
                <button class="bg-white border border-gray-200 text-gray-600 px-3 py-1.5 rounded-lg text-xs font-medium flex items-center gap-2 hover:bg-gray-50">
                    <i data-lucide="sliders-horizontal" class="w-3.5 h-3.5 text-gray-500"></i> More filters
                </button>
            </div>

            <!-- TOP DISPATCH STAT CARDS -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex justify-between items-center">
                    <div>
                        <p class="text-[11px] font-semibold text-gray-500 uppercase">Jobs today</p>
                        <h3 class="text-2xl font-bold text-gray-900 mt-0.5">12</h3>
                        <p class="text-[11px] text-gray-500 mt-0.5">8 assigned • 4 pending</p>
                    </div>
                    <div class="p-2.5 bg-emerald-50 text-emerald-700 rounded-lg">
                        <i data-lucide="clipboard-list" class="w-5 h-5"></i>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex justify-between items-center">
                    <div>
                        <p class="text-[11px] font-semibold text-gray-500 uppercase">In progress</p>
                        <h3 class="text-2xl font-bold text-gray-900 mt-0.5">5</h3>
                        <p class="text-[11px] text-emerald-600 mt-0.5 font-medium">2 currently on site</p>
                    </div>
                    <div class="p-2.5 bg-blue-50 text-blue-700 rounded-lg">
                        <i data-lucide="zap" class="w-5 h-5"></i>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex justify-between items-center">
                    <div>
                        <p class="text-[11px] font-semibold text-gray-500 uppercase">Completed</p>
                        <h3 class="text-2xl font-bold text-gray-900 mt-0.5">4</h3>
                        <p class="text-[11px] text-gray-500 mt-0.5">33% completion rate</p>
                    </div>
                    <div class="p-2.5 bg-emerald-50 text-emerald-700 rounded-lg">
                        <i data-lucide="check-circle-2" class="w-5 h-5"></i>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex justify-between items-center">
                    <div>
                        <p class="text-[11px] font-semibold text-gray-500 uppercase">Needs attention</p>
                        <h3 class="text-2xl font-bold text-amber-600 mt-0.5">2</h3>
                        <p class="text-[11px] text-amber-600 mt-0.5 font-medium">1 delayed • 1 unassigned</p>
                    </div>
                    <div class="p-2.5 bg-amber-50 text-amber-700 rounded-lg">
                        <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                    </div>
                </div>
            </div>

            <!-- MAIN SECTION: DISPATCH SCHEDULE + LIVE ROUTES -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- LEFT COLUMN: Dispatch Schedule Table (2 Columns wide) -->
                <div class="lg:col-span-2 space-y-4">
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                        
                        <!-- Table Header Controls -->
                        <div class="p-4 border-b border-gray-200 flex justify-between items-center bg-gray-50/50">
                            <div>
                                <h3 class="font-bold text-gray-900 text-sm">Today's dispatch schedule</h3>
                                <p class="text-[11px] text-gray-500">12 jobs • 5 shown • Updated 8:42 AM</p>
                            </div>
                            <div class="flex bg-gray-200 p-0.5 rounded-lg text-xs font-semibold">
                                <button class="px-3 py-1 bg-white text-gray-800 rounded-md shadow-sm">Queue</button>
                                <button class="px-3 py-1 text-gray-500 hover:text-gray-800">Timeline</button>
                            </div>
                        </div>

                        <!-- Schedule Table -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-gray-200 bg-gray-50 text-[10px] font-bold uppercase text-gray-500 tracking-wider">
                                        <th class="py-3 px-4">Service Window</th>
                                        <th class="py-3 px-4">Client & Location</th>
                                        <th class="py-3 px-4">Service</th>
                                        <th class="py-3 px-4">Assigned</th>
                                        <th class="py-3 px-4">Priority</th>
                                        <th class="py-3 px-4">Route Status</th>
                                        <th class="py-3 px-4 text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-xs">
                                    <?php foreach ($jobs as $job): ?>
                                        <tr class="hover:bg-gray-50/80 transition">
                                            <!-- Window -->
                                            <td class="py-3.5 px-4 font-bold text-gray-800 whitespace-nowrap">
                                                <?= htmlspecialchars($job['service_window']) ?>
                                            </td>

                                            <!-- Client & Location -->
                                            <td class="py-3.5 px-4">
                                                <div class="font-bold text-gray-900"><?= htmlspecialchars($job['client_name']) ?></div>
                                                <div class="text-gray-400 text-[10px] truncate max-w-[180px]"><?= htmlspecialchars($job['location']) ?></div>
                                            </td>

                                            <!-- Service -->
                                            <td class="py-3.5 px-4 text-gray-700 font-medium">
                                                <?= htmlspecialchars($job['service_type']) ?>
                                            </td>

                                            <!-- Assigned -->
                                            <td class="py-3.5 px-4 text-gray-800 font-medium whitespace-nowrap">
                                                <?= htmlspecialchars($job['assigned_tech']) ?>
                                            </td>

                                            <!-- Priority -->
                                            <td class="py-3.5 px-4 whitespace-nowrap">
                                                <?php if ($job['priority'] === 'Urgent'): ?>
                                                    <span class="px-2 py-0.5 bg-red-100 text-red-700 rounded-full text-[10px] font-bold">Urgent</span>
                                                <?php elseif ($job['priority'] === 'High'): ?>
                                                    <span class="px-2 py-0.5 bg-amber-100 text-amber-800 rounded-full text-[10px] font-bold">High</span>
                                                <?php else: ?>
                                                    <span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded-full text-[10px] font-bold">Standard</span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Route Status -->
                                            <td class="py-3.5 px-4 whitespace-nowrap">
                                                <?php if ($job['route_status'] === 'En route'): ?>
                                                    <span class="px-2.5 py-1 bg-red-50 text-red-700 border border-red-200 rounded-full text-[10px] font-bold">En route</span>
                                                <?php elseif ($job['route_status'] === 'On site'): ?>
                                                    <span class="px-2.5 py-1 bg-amber-50 text-amber-700 border border-amber-200 rounded-full text-[10px] font-bold">On site</span>
                                                <?php elseif ($job['route_status'] === 'Delayed'): ?>
                                                    <span class="px-2.5 py-1 bg-amber-100 text-amber-900 border border-amber-300 rounded-full text-[10px] font-bold">Delayed</span>
                                                <?php else: ?>
                                                    <span class="px-2.5 py-1 bg-blue-50 text-blue-700 border border-blue-200 rounded-full text-[10px] font-bold">Scheduled</span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Action -->
                                            <td class="py-3.5 px-4 text-center">
                                                <button class="text-gray-400 hover:text-gray-600"><i data-lucide="more-vertical" class="w-4 h-4"></i></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Footer Link -->
                        <div class="p-3 border-t border-gray-200 flex justify-between items-center text-xs text-gray-500 bg-gray-50/50">
                            <span>Showing <?= count($jobs) ?> of 12 jobs</span>
                            <a href="#" class="text-emerald-700 font-bold hover:underline flex items-center gap-1">View full schedule <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i></a>
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN: Map Mockup & Active Field Personnel -->
                <div class="space-y-6">

                    <!-- Live Routes Graphic Card -->
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                        <div class="flex justify-between items-center mb-3">
                            <h3 class="font-bold text-gray-900 text-sm">Live routes</h3>
                            <span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 text-[10px] font-bold rounded-full">7 active</span>
                        </div>

                        <!-- Davao City Route Visual Graphic -->
                        <div class="relative bg-emerald-50/60 rounded-lg p-4 border border-emerald-100 h-44 flex flex-col justify-between overflow-hidden">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-900/60">Davao City Coverage Map</span>
                            
                            <!-- Simulated Route Path & Pins -->
                            <div class="absolute inset-0 flex items-center justify-center opacity-40">
                                <svg class="w-full h-full" viewBox="0 0 200 100">
                                    <path d="M 20 20 Q 80 50 140 30 T 180 80" fill="none" stroke="#059669" stroke-width="3" stroke-dasharray="4"/>
                                </svg>
                            </div>

                            <!-- Interactive Route Pins -->
                            <div class="relative z-10 flex justify-around items-center h-full">
                                <div class="w-6 h-6 bg-emerald-700 text-white rounded-full flex items-center justify-center text-[10px] font-bold shadow-md">1</div>
                                <div class="w-6 h-6 bg-emerald-700 text-white rounded-full flex items-center justify-center text-[10px] font-bold shadow-md">2</div>
                                <div class="w-6 h-6 bg-emerald-700 text-white rounded-full flex items-center justify-center text-[10px] font-bold shadow-md">3</div>
                                <div class="w-6 h-6 bg-amber-600 text-white rounded-full flex items-center justify-center text-[10px] font-bold shadow-md">4</div>
                            </div>
                        </div>
                    </div>

                    <!-- Active Technicians List -->
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                        <div class="flex justify-between items-center mb-3">
                            <h3 class="font-bold text-gray-900 text-sm">Active technicians</h3>
                            <a href="#" class="text-xs font-bold text-emerald-700 hover:underline">Manage</a>
                        </div>

                        <div class="space-y-3">
                            <!-- Tech 1 -->
                            <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded-lg transition">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-800 font-bold flex items-center justify-center text-xs">JM</div>
                                    <div>
                                        <p class="text-xs font-bold text-gray-800">John Mark Arguilles</p>
                                        <p class="text-[10px] text-gray-500">En route • Poblacion</p>
                                    </div>
                                </div>
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            </div>

                            <!-- Tech 2 -->
                            <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded-lg transition">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-800 font-bold flex items-center justify-center text-xs">JR</div>
                                    <div>
                                        <p class="text-xs font-bold text-gray-800">John Rey Murillo</p>
                                        <p class="text-[10px] text-gray-500">On site • Quirino Ave</p>
                                    </div>
                                </div>
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            </div>

                            <!-- Tech 3 -->
                            <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded-lg transition">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full bg-amber-100 text-amber-800 font-bold flex items-center justify-center text-xs">JB</div>
                                    <div>
                                        <p class="text-xs font-bold text-gray-800">Jayjay Baugbug</p>
                                        <p class="text-[10px] text-amber-700 font-semibold">Delayed • Lanang</p>
                                    </div>
                                </div>
                                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                            </div>
                        </div>
                    </div>

                </div>

            </div>

        </div>
    </main>

    <script>
        lucide.createIcons();
        document.getElementById('createJobOrderBtn').addEventListener('click', function() {
    // Grab client info from the open modal elements
    const clientName = document.querySelector('.company-account-name-field')?.innerText || '';
    const clientAddress = document.querySelector('.client-address-field')?.innerText || '';

    // Redirect or trigger modal for job order creation, passing client data
    // Example: Redirecting to dispatch page with query parameters
    const encodedName = encodeURIComponent(clientName);
    const encodedAddress = encodeURIComponent(clientAddress);
    
    window.location.href = `dispatch.php?client=${encodedName}&location=${encodedAddress}`;
});
    </script>
</body>
</html>