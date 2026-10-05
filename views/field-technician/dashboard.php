<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

// Security check: Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit;
}

$technicianName = $_SESSION['full_name'] ?? '';

// Fallback: If session full_name isn't set, try constructing it from first/last name
if (empty($technicianName) && isset($_SESSION['first_name'], $_SESSION['last_name'])) {
    $technicianName = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
}

$assignedTasks = [];
try {
    // 1. Fetch Job Orders assigned to this technician
    $jobStmt = $pdo->prepare("
        SELECT 
            j.id, 
            'Job Order' as task_type, 
            j.client_name, 
            j.location as location, 
            j.scheduled_date as task_date, 
            j.route_status as status, 
            j.assigned_tech as technician_name,
            CONCAT('CONT-', LPAD(COALESCE(j.contract_id, 0), 4, '0')) as contract_number
        FROM job_orders j
        WHERE j.assigned_tech = ? OR j.assigned_tech LIKE ?
    ");
    $jobStmt->execute([$technicianName, '%' . $technicianName . '%']);
    $jobOrders = $jobStmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch Site Inspections assigned to this technician
    $inspStmt = $pdo->prepare("
        SELECT 
            i.id, 
            'Inspection' as task_type, 
            i.client_name, 
            i.service_address as location, 
            i.inspection_date as task_date, 
            i.inspection_status as status, 
            i.technician_name,
            CONCAT('CONT-', LPAD(COALESCE(i.contract_id, 0), 4, '0')) as contract_number
        FROM site_inspections i
        WHERE i.technician_name = ? OR i.technician_name LIKE ?
    ");
    $inspStmt->execute([$technicianName, '%' . $technicianName . '%']);
    $inspections = $inspStmt->fetchAll(PDO::FETCH_ASSOC);

    // Combine both lists
    $assignedTasks = array_merge($jobOrders, $inspections);

    // Sort by date descending
    usort($assignedTasks, function($a, $b) {
        return strtotime($b['task_date'] ?? '0') - strtotime($a['task_date'] ?? '0');
    });

} catch (Exception $e) {
    $assignedTasks = [];
}

// Calculate summary stats
$totalTasks = count($assignedTasks);
$completedTasks = count(array_filter($assignedTasks, function($t) { 
    return stripos($t['status'], 'Complete') !== false; 
}));
$pendingTasks = $totalTasks - $completedTasks;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Field Technician Portal - Vermex Pest Solutions</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-[#071811] text-gray-100 font-sans antialiased min-h-screen flex flex-col selection:bg-emerald-500 selection:text-white">

    <!-- Top Navigation Bar -->
    <header class="bg-[#0d2e21]/90 backdrop-blur-md border-b border-[#164a34] px-6 py-4 flex justify-between items-center sticky top-0 z-50 shadow-xl">
        <div class="flex items-center space-x-3">
            <div class="bg-gradient-to-br from-emerald-500 to-[#007a55] p-2.5 rounded-xl text-white font-bold shadow-lg shadow-emerald-900/30 flex items-center justify-center w-10 h-10">
                <i data-lucide="shield" class="w-5 h-5"></i>
            </div>
            <div>
                <h1 class="text-xs font-black tracking-wider uppercase text-emerald-400">Vermex Field Portal</h1>
                <p class="text-sm font-bold text-white"><?php echo htmlspecialchars($technicianName); ?></p>
            </div>
        </div>
        <div class="flex items-center space-x-4">
            <a href="../../auth/logout.php" class="bg-red-500/10 hover:bg-red-500/20 text-red-400 text-xs px-4 py-2 rounded-xl transition border border-red-500/20 font-semibold flex items-center gap-2 shadow-inner">
                <i data-lucide="log-out" class="w-3.5 h-3.5"></i> Logout
            </a>
        </div>
    </header>

    <!-- Main Dashboard Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        
        <!-- Quick Stats Overview Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-gradient-to-br from-[#0f3627] to-[#0a271c] p-6 rounded-2xl border border-[#18523b] shadow-xl relative overflow-hidden group hover:border-emerald-500/50 transition duration-300">
                <div class="absolute -right-4 -bottom-4 bg-emerald-500/5 rounded-full w-32 h-32 group-hover:scale-110 transition duration-500 pointer-events-none"></div>
                <div class="flex items-center justify-between relative z-10">
                    <div>
                        <p class="text-[11px] text-emerald-400 font-bold uppercase tracking-widest">Assigned Workload</p>
                        <h3 class="text-3xl font-black text-white mt-1"><?php echo $totalTasks; ?> <span class="text-xs font-normal text-gray-400">tasks</span></h3>
                        <p class="text-[11px] text-gray-400 mt-1"><?php echo $pendingTasks; ?> pending review / visits</p>
                    </div>
                    <div class="bg-emerald-500/20 p-3.5 rounded-xl text-emerald-400 border border-emerald-500/30 shadow-inner">
                        <i data-lucide="clipboard-list" class="w-6 h-6"></i>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-[#0f3627] to-[#0a271c] p-6 rounded-2xl border border-[#18523b] shadow-xl relative overflow-hidden group hover:border-emerald-500/50 transition duration-300">
                <div class="absolute -right-4 -bottom-4 bg-emerald-500/5 rounded-full w-32 h-32 group-hover:scale-110 transition duration-500 pointer-events-none"></div>
                <div class="flex items-center justify-between relative z-10">
                    <div>
                        <p class="text-[11px] text-emerald-400 font-bold uppercase tracking-widest">Compliance & Safety</p>
                        <h3 class="text-2xl font-black text-emerald-400 mt-1">Active Status</h3>
                        <p class="text-[11px] text-gray-400 mt-1">All protocols verified</p>
                    </div>
                    <div class="bg-emerald-500/20 p-3.5 rounded-xl text-emerald-400 border border-emerald-500/30 shadow-inner">
                        <i data-lucide="shield-check" class="w-6 h-6"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assigned Tasks Table Section -->
        <section class="bg-[#0e3023] rounded-2xl border border-[#18523b] p-6 shadow-2xl space-y-6">
            
            <!-- Header and Filter Toolbar -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 border-b border-[#18523b] pb-5">
                <div>
                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                        <i data-lucide="calendar-days" class="w-5 h-5 text-emerald-400"></i> Assigned Inspections & Job Orders
                    </h2>
                    <p class="text-xs text-gray-400 mt-0.5">Filter, search, and select any task to update findings or log chemical usage on-site.</p>
                </div>
                
                <!-- Search & Filters Toolbar -->
                <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                    <div class="relative flex-1 md:w-64">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                            <i data-lucide="search" class="w-4 h-4"></i>
                        </span>
                        <input type="text" id="taskSearch" placeholder="Search client or location..." 
                            class="w-full pl-9 pr-4 py-2 bg-[#082218] border border-[#1b5840] rounded-xl text-xs text-white placeholder-gray-400 focus:outline-none focus:border-emerald-500 transition shadow-inner">
                    </div>

                    <select id="typeFilter" class="bg-[#082218] border border-[#1b5840] rounded-xl px-3 py-2 text-xs text-gray-300 focus:outline-none focus:border-emerald-500 transition shadow-inner cursor-pointer">
                        <option value="">All Types</option>
                        <option value="Job Order">Job Order</option>
                        <option value="Inspection">Inspection</option>
                    </select>

                    <select id="statusFilter" class="bg-[#082218] border border-[#1b5840] rounded-xl px-3 py-2 text-xs text-gray-300 focus:outline-none focus:border-emerald-500 transition shadow-inner cursor-pointer">
                        <option value="">All Statuses</option>
                        <option value="Scheduled">Scheduled</option>
                        <option value="Completed">Completed</option>
                        <option value="Pending">Pending Visit</option>
                    </select>
                </div>
            </div>

            <!-- Table Container -->
            <div class="overflow-x-auto rounded-xl border border-[#174e37]">
                <table class="w-full text-left border-collapse" id="tasksTable">
                    <thead>
                        <tr class="border-b border-[#174e37] text-[10px] uppercase tracking-wider text-emerald-400/80 bg-[#09261b]">
                            <th class="py-3.5 px-4 font-bold">Type</th>
                            <th class="py-3.5 px-4 font-bold">Client / Contract & Location</th>
                            <th class="py-3.5 px-4 font-bold">Schedule Date</th>
                            <th class="py-3.5 px-4 font-bold">Status</th>
                            <th class="py-3.5 px-4 font-bold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#174e37] text-xs">
                        <?php if (empty($assignedTasks)): ?>
                            <tr>
                                <td colspan="5" class="py-12 text-center text-gray-400 italic">No tasks currently assigned to you. Check back with dispatch.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($assignedTasks as $task): ?>
                                <?php 
                                    $isInspection = ($task['task_type'] === 'Inspection');
                                    $redirectUrl = $isInspection ? "pre_inspection.php?id=" . $task['id'] : "job_orders.php?id=" . $task['id'];
                                ?>
                                <tr class="hover:bg-[#123e2e] transition group task-row" 
                                    data-type="<?php echo htmlspecialchars($task['task_type']); ?>"
                                    data-status="<?php echo htmlspecialchars($task['status'] ?? 'Pending'); ?>"
                                    data-search="<?php echo htmlspecialchars(strtolower($task['client_name'] . ' ' . $task['location'] . ' ' . $task['contract_number'])); ?>">
                                    
                                    <td class="py-4 px-4 font-semibold">
                                        <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold tracking-wide shadow-sm <?php echo $isInspection ? 'bg-purple-500/20 text-purple-300 border border-purple-500/40' : 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40'; ?>">
                                            <?php echo htmlspecialchars($task['task_type']); ?>
                                        </span>
                                    </td>
                                    
                                    <td class="py-4 px-4">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-bold text-white text-sm group-hover:text-emerald-300 transition"><?php echo htmlspecialchars($task['client_name']); ?></span>
                                            <?php if (!empty($task['contract_number']) && $task['contract_number'] !== 'CONT-0000' && $task['contract_number'] !== 'N/A'): ?>
                                                <span class="px-2 py-0.5 rounded-md text-[10px] bg-emerald-950/80 text-emerald-300 border border-emerald-700/60 font-mono tracking-tight shadow-inner">
                                                    <?php echo htmlspecialchars($task['contract_number']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-[11px] text-gray-400 truncate max-w-md mt-0.5 flex items-center gap-1">
                                            <i data-lucide="map-pin" class="w-3 h-3 text-emerald-500 shrink-0"></i>
                                            <?php echo htmlspecialchars($task['location']); ?>
                                        </div>
                                    </td>
                                    
                                    <td class="py-4 px-4 text-gray-300 font-medium">
                                        <div class="flex items-center gap-1.5">
                                            <i data-lucide="clock" class="w-3.5 h-3.5 text-gray-400"></i>
                                            <?php echo htmlspecialchars($task['task_date'] ?? 'Unscheduled'); ?>
                                        </div>
                                    </td>
                                    
                                    <td class="py-4 px-4">
                                        <?php 
                                            $statusText = $task['status'] ?? 'Pending Visit'; 
                                            $statusClass = 'bg-yellow-500/20 text-yellow-300 border-yellow-500/30';
                                            if (stripos($statusText, 'Complete') !== false) {
                                                $statusClass = 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30';
                                            } elseif (stripos($statusText, 'Scheduled') !== false) {
                                                $statusClass = 'bg-blue-500/20 text-blue-300 border-blue-500/30';
                                            }
                                        ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-semibold border shadow-sm inline-flex items-center gap-1 <?php echo $statusClass; ?>">
                                            <span class="w-1.5 h-1.5 rounded-full bg-current animate-pulse"></span>
                                            <?php echo htmlspecialchars($statusText); ?>
                                        </span>
                                    </td>
                                    
                                    <td class="py-4 px-4 text-right">
                                        <a href="<?php echo $redirectUrl; ?>" 
                                           class="bg-gradient-to-r from-[#007a55] to-[#006243] hover:from-[#008f63] hover:to-[#007a55] text-white px-3.5 py-2 rounded-xl text-xs font-semibold transition shadow-md shadow-emerald-950 inline-flex items-center gap-1.5 group-hover:scale-105 duration-200 cursor-pointer">
                                            <i data-lucide="file-text" class="w-3.5 h-3.5"></i> Update Form
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div id="noResultsRow" class="hidden py-8 text-center text-gray-400 text-xs italic">
                No matching assigned tasks found for your search criteria.
            </div>
        </section>

    </main>

    <!-- Client-side Scripts -->
    <script>
        lucide.createIcons();

        const searchInput = document.getElementById('taskSearch');
        const typeFilter = document.getElementById('typeFilter');
        const statusFilter = document.getElementById('statusFilter');
        const rows = document.querySelectorAll('.task-row');
        const noResultsRow = document.getElementById('noResultsRow');

        function filterTasks() {
            const query = searchInput.value.toLowerCase().trim();
            const selectedType = typeFilter.value;
            const selectedStatus = statusFilter.value.toLowerCase();
            let visibleCount = 0;

            rows.forEach(row => {
                const searchText = row.getAttribute('data-search');
                const rowType = row.getAttribute('data-type');
                const rowStatus = row.getAttribute('data-status').toLowerCase();

                const matchesSearch = searchText.includes(query);
                const matchesType = !selectedType || rowType === selectedType;
                const matchesStatus = !selectedStatus || rowStatus.includes(selectedStatus);

                if (matchesSearch && matchesType && matchesStatus) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (visibleCount === 0) {
                noResultsRow.classList.remove('hidden');
            } else {
                noResultsRow.classList.add('hidden');
            }
        }

        if (searchInput) searchInput.addEventListener('input', filterTasks);
        if (typeFilter) typeFilter.addEventListener('change', filterTasks);
        if (statusFilter) statusFilter.addEventListener('change', filterTasks);
    </script>
</body>
</html>