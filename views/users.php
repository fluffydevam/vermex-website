<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Fetch Search and Filter parameters
$search = trim($_GET['search'] ?? '');
$filterRole = trim($_GET['role'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');

// Build Dynamic SQL Query for User List
$query = "SELECT * FROM users WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (full_name LIKE :search OR email LIKE :search OR username LIKE :search)";
    $params['search'] = "%{$search}%";
}

if (!empty($filterRole)) {
    $query .= " AND role = :role";
    $params['role'] = $filterRole;
}

if (!empty($filterStatus)) {
    $query .= " AND status = :status";
    $params['status'] = $filterStatus;
}

$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// KPI Calculations
$totalUsers = count($users);
$activeUsersCount = 0;
$techCount = 0;

foreach ($users as $u) {
    if ($u['status'] === 'active') {
        $activeUsersCount++;
    }
    if ($u['role'] === 'Field Technician') {
        $techCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vermex - User Role & Access Management</title>

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
                        <span>Administration</span>
                        <span>•</span>
                        <span>Secure Access</span>
                    </div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">User Role & Access Management</h1>
                    <p class="text-xs text-slate-500 mt-1">Create accounts, manage system roles, and configure feature permissions for office staff and field technicians.</p>
                </div>

                <div class="flex items-center gap-3">
                    <button onclick="toggleUserForm()" class="bg-[#007a55] hover:bg-[#006344] text-white font-medium text-xs px-4 py-2 rounded-lg flex items-center gap-2 transition shadow-sm">
                        <i data-lucide="user-plus" class="w-4 h-4"></i>
                        <span>Create New User Account</span>
                    </button>
                </div>
            </div>

            <!-- Summary KPI Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white border border-slate-200/80 rounded-xl p-4 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total Users</p>
                        <p class="text-2xl font-bold text-slate-900 mt-1"><?= $totalUsers ?> <span class="text-xs font-normal text-slate-500">Accounts</span></p>
                        <p class="text-[11px] text-emerald-600 font-medium mt-1"><?= $activeUsersCount ?> Active Accounts</p>
                    </div>
                    <div class="bg-emerald-50 p-2.5 rounded-lg text-[#007a55] border border-emerald-100">
                        <i data-lucide="users" class="w-5 h-5"></i>
                    </div>
                </div>

                <div class="bg-white border border-slate-200/80 rounded-xl p-4 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Field Technicians</p>
                        <p class="text-2xl font-bold text-slate-900 mt-1"><?= $techCount ?> <span class="text-xs font-normal text-slate-500">Techs</span></p>
                        <p class="text-[11px] text-blue-600 font-medium mt-1">Linked to Dispatch App</p>
                    </div>
                    <div class="bg-blue-50 p-2.5 rounded-lg text-blue-600 border border-blue-100">
                        <i data-lucide="truck" class="w-5 h-5"></i>
                    </div>
                </div>

                <div class="bg-white border border-slate-200/80 rounded-xl p-4 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Pending Access</p>
                        <p class="text-2xl font-bold text-slate-900 mt-1">0 <span class="text-xs font-normal text-slate-500">Pending</span></p>
                        <p class="text-[11px] text-emerald-600 font-medium mt-1">No pending approvals</p>
                    </div>
                    <div class="bg-emerald-50 p-2.5 rounded-lg text-[#007a55] border border-emerald-100">
                        <i data-lucide="user-check" class="w-5 h-5"></i>
                    </div>
                </div>

                <div class="bg-white border border-slate-200/80 rounded-xl p-4 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">System Roles</p>
                        <p class="text-2xl font-bold text-slate-900 mt-1">4 <span class="text-xs font-normal text-slate-500">Roles</span></p>
                        <p class="text-[11px] text-purple-600 font-medium mt-1">Admin, Tech, Billing, Custodian</p>
                    </div>
                    <div class="bg-purple-50 p-2.5 rounded-lg text-purple-600 border border-purple-100">
                        <i data-lucide="key" class="w-5 h-5"></i>
                    </div>
                </div>
            </div>

            <!-- Creation Form Panel (Initially Hidden) -->
            <div id="userCreationSection" class="hidden grid grid-cols-1 gap-6">
                <div class="bg-white border border-slate-200/80 rounded-xl p-5 space-y-4 shadow-sm">
                    <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-2 text-[10px] text-[#007a55] font-semibold tracking-wider uppercase">
                                <i data-lucide="lock" class="w-3 h-3"></i> Secure Provisioning
                            </div>
                            <h2 class="text-base font-semibold text-slate-900 mt-0.5">Create New User Account</h2>
                            <p class="text-[11px] text-slate-400">Provision secure access and assign role-based permissions.</p>
                        </div>
                        <button type="button" onclick="toggleUserForm()" class="text-slate-400 hover:text-slate-700 transition"><i data-lucide="x" class="w-5 h-5"></i></button>
                    </div>

                    <form action="../controllers/createUser.php" method="POST" class="space-y-3.5 text-xs">
                        <!-- Row 1: First Name & Last Name -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-slate-600 font-medium mb-1">First Name *</label>
                                <input type="text" name="first_name" required placeholder="e.g. John" class="w-full bg-white border border-slate-200 text-slate-800 placeholder-slate-400 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55] transition">
                            </div>
                            <div>
                                <label class="block text-slate-600 font-medium mb-1">Last Name *</label>
                                <input type="text" name="last_name" required placeholder="e.g. Doe" class="w-full bg-white border border-slate-200 text-slate-800 placeholder-slate-400 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55] transition">
                            </div>
                        </div>

                        <!-- Row 2: Username & Email Address -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-slate-600 font-medium mb-1">Username *</label>
                                <input type="text" name="username" required placeholder="e.g. jdoe" class="w-full bg-white border border-slate-200 text-slate-800 placeholder-slate-400 font-mono rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55] transition">
                            </div>
                            <div>
                                <label class="block text-slate-600 font-medium mb-1">Email Address *</label>
                                <input type="email" name="email" required placeholder="name@vermexpest.com" class="w-full bg-white border border-slate-200 text-slate-800 placeholder-slate-400 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55] transition">
                            </div>
                        </div>

                        <!-- Row 3: Contact Number, Assigned Role, & Sector/Station -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-slate-600 font-medium mb-1">Contact Number</label>
                                <input type="text" name="phone" placeholder="+63 9XX XXX XXXX" class="w-full bg-white border border-slate-200 text-slate-800 placeholder-slate-400 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55] transition">
                            </div>
                            <div>
                                <label class="block text-slate-600 font-medium mb-1">Assigned Role *</label>
                                <select name="role" required class="w-full bg-white border border-slate-200 text-slate-800 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55] transition">
                                    <option value="Field Technician">Field Technician</option>
                                    <option value="Admin">Admin</option>
                                    <option value="Billing Officer">Billing Officer</option>
                                    <option value="Chemical Custodian">Chemical Custodian</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-slate-600 font-medium mb-1">Sector / Station</label>
                                <select name="sector_region" class="w-full bg-white border border-slate-200 text-slate-800 rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55] transition">
                                    <option value="Davao Head Office">Davao Head Office</option>
                                    <option value="Sector 01 - Industrial Zone">Sector 01 - Industrial Zone</option>
                                    <option value="Sector 02 - Downtown Davao">Sector 02 - Downtown Davao</option>
                                    <option value="Sector 04 - Roxas, Davao City">Sector 04 - Roxas, Davao City</option>
                                </select>
                            </div>
                        </div>

                        <!-- Temporary Password -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="text-slate-600 font-medium">Temporary Password *</label>
                                <button type="button" onclick="generatePassword()" class="text-[10px] text-[#007a55] hover:underline transition font-semibold">Generate Random Password</button>
                            </div>
                            <div class="relative">
                                <input id="passInput" type="text" name="password" required value="VmX-84F2-Kp7!" class="w-full bg-slate-50 border border-slate-200 text-slate-900 font-mono rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55] transition">
                            </div>
                        </div>

                        <!-- Actions Footer -->
                        <div class="flex items-center justify-end gap-3 pt-2">
                            <button type="button" onclick="toggleUserForm()" class="bg-white hover:bg-slate-50 text-slate-600 px-4 py-2 rounded-lg border border-slate-200 transition font-medium">Cancel</button>
                            <button type="submit" class="bg-[#007a55] hover:bg-[#006344] text-white font-medium px-4 py-2 rounded-lg transition flex items-center gap-1.5 shadow-sm">
                                <i data-lucide="send" class="w-3.5 h-3.5"></i>
                                Create Account
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Existing User Accounts Table -->
            <div class="bg-white border border-slate-200/80 rounded-xl p-5 space-y-4 shadow-sm">
                <form method="GET" action="users.php" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">User Accounts</h2>
                        <p class="text-[11px] text-slate-400 mt-0.5"><?= $totalUsers ?> accounts found • <?= $activeUsersCount ?> active</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="relative flex-1 sm:w-64">
                            <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, email, or username..." class="w-full bg-white border border-slate-200 text-slate-800 placeholder-slate-400 text-xs rounded-lg pl-8 pr-3 py-1.5 focus:outline-none focus:border-[#007a55] transition">
                        </div>
                        <select name="role" onchange="this.form.submit()" class="bg-white border border-slate-200 text-slate-700 text-xs rounded-lg px-2.5 py-1.5 focus:outline-none focus:border-[#007a55] transition font-medium">
                            <option value="">Role: All</option>
                            <option value="Admin" <?= $filterRole === 'Admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="Field Technician" <?= $filterRole === 'Field Technician' ? 'selected' : '' ?>>Field Technician</option>
                            <option value="Billing Officer" <?= $filterRole === 'Billing Officer' ? 'selected' : '' ?>>Billing Officer</option>
                            <option value="Chemical Custodian" <?= $filterRole === 'Chemical Custodian' ? 'selected' : '' ?>>Chemical Custodian</option>
                        </select>
                        <select name="status" onchange="this.form.submit()" class="bg-white border border-slate-200 text-slate-700 text-xs rounded-lg px-2.5 py-1.5 focus:outline-none focus:border-[#007a55] transition font-medium">
                            <option value="">Status: All</option>
                            <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="disabled" <?= $filterStatus === 'disabled' ? 'selected' : '' ?>>Disabled</option>
                        </select>
                    </div>
                </form>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-[10px] uppercase text-slate-400 border-b border-slate-100">
                                <th class="pb-2 font-semibold">User Name & Email</th>
                                <th class="pb-2 font-semibold">System Role</th>
                                <th class="pb-2 font-semibold">Assigned Station</th>
                                <th class="pb-2 font-semibold">Last Active</th>
                                <th class="pb-2 font-semibold">Status</th>
                                <th class="pb-2 font-semibold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="6" class="py-6 text-center text-slate-400">No user accounts found matching your filters.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <?php
                                    // Initials
                                    $initials = strtoupper(substr($user['first_name'] ?? '', 0, 1) . substr($user['last_name'] ?? '', 0, 1));
                                    // Role badge style
                                    $roleBadgeClass = "bg-emerald-50 text-[#007a55] border-emerald-200/60";
                                    if ($user['role'] === 'Field Technician') {
                                        $roleBadgeClass = "bg-blue-50 text-blue-700 border-blue-200/60";
                                    } elseif ($user['role'] === 'Billing Officer') {
                                        $roleBadgeClass = "bg-purple-50 text-purple-700 border-purple-200/60";
                                    } elseif ($user['role'] === 'Chemical Custodian') {
                                        $roleBadgeClass = "bg-amber-50 text-amber-700 border-amber-200/60";
                                    }

                                    // Status badge style
                                    $statusBadge = $user['status'] === 'active'
                                        ? '<span class="bg-emerald-50 text-[#007a55] border border-emerald-200/60 px-2 py-0.5 rounded text-[10px] font-semibold">Active</span>'
                                        : '<span class="bg-slate-100 text-slate-500 border border-slate-200 px-2 py-0.5 rounded text-[10px] font-semibold">Disabled</span>';
                                    ?>
                                    <tr class="hover:bg-slate-50/80 transition">
                                        <td class="py-3 px-1">
                                            <div class="flex items-center gap-3">
                                                <!-- Avatar Circle -->
                                                <div class="w-8 h-8 rounded-full bg-emerald-50 border border-emerald-200/80 flex items-center justify-center text-xs font-bold text-[#007a55]">
                                                    <?= $initials ?>
                                                </div>
                                                <div>
                                                    <!-- Name & Username Tag -->
                                                    <div class="flex items-center gap-2">
                                                        <span class="font-semibold text-slate-800 text-xs"><?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?></span>
                                                        <?php if (!empty($user['username'])): ?>
                                                            <span class="text-[10px] font-mono text-[#007a55] bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-100 font-medium">
                                                                @<?= htmlspecialchars($user['username']) ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <!-- Email -->
                                                    <div class="text-[11px] text-slate-400 font-mono mt-0.5">
                                                        <?= htmlspecialchars($user['email']) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3">
                                            <span class="<?= $roleBadgeClass ?> border px-2 py-0.5 rounded text-[10px] font-medium">
                                                <?= htmlspecialchars($user['role']) ?>
                                            </span>
                                        </td>
                                        <td class="py-3 text-slate-600 font-medium"><?= htmlspecialchars($user['sector_region'] ?? 'Davao Head Office') ?></td>
                                        <td class="py-3 text-slate-500 font-mono text-[11px]">
                                            <?= $user['last_active'] ? date('M d, Y h:i A', strtotime($user['last_active'])) : 'Never' ?>
                                        </td>
                                        <td class="py-3"><?= $statusBadge ?></td>
                                        <td class="py-3 text-right relative">
                                            <!-- Toggle Dropdown Button -->
                                            <button onclick="toggleActionMenu(<?= $user['id'] ?>)" class="text-slate-400 hover:text-slate-700 p-1.5 rounded-lg hover:bg-slate-100 transition">
                                                <i data-lucide="more-vertical" class="w-4 h-4"></i>
                                            </button>

                                            <!-- Contextual Action Menu Dropdown -->
                                            <div id="action-menu-<?= $user['id'] ?>" class="hidden absolute right-0 mt-1 w-44 bg-white border border-slate-200 rounded-lg shadow-lg z-20 py-1 text-left">
                                                <!-- Edit Profile -->
                                                <button onclick='openEditModal(<?= json_encode($user) ?>)' class="w-full px-3 py-2 text-xs text-slate-700 hover:bg-slate-50 flex items-center gap-2 transition font-medium">
                                                    <i data-lucide="edit-3" class="w-3.5 h-3.5 text-[#007a55]"></i> Edit User
                                                </button>

                                                <!-- Reset Password -->
                                                <button onclick='openResetModal(<?= $user["id"] ?>, "<?= htmlspecialchars(trim(($user["first_name"] ?? "") . " " . ($user["last_name"] ?? "")), ENT_QUOTES) ?>")' class="w-full px-3 py-2 text-xs text-slate-700 hover:bg-slate-50 flex items-center gap-2 transition font-medium">
                                                    <i data-lucide="key-round" class="w-3.5 h-3.5 text-amber-400"></i> Reset Password
                                                </button>

                                                <div class="border-t border-slate-100 my-1"></div>

                                                <!-- Toggle Status Form -->
                                                <form action="../controllers/toggleUserStatus.php" method="POST" onsubmit="return confirm('Are you sure you want to change this user\'s access status?');">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <input type="hidden" name="current_status" value="<?= $user['status'] ?>">
                                                    <button type="submit" class="w-full px-3 py-2 text-xs flex items-center gap-2 transition font-medium <?= $user['status'] === 'active' ? 'text-rose-600 hover:bg-rose-50' : 'text-[#007a55] hover:bg-emerald-50' ?>">
                                                        <i data-lucide="<?= $user['status'] === 'active' ? 'user-x' : 'user-check' ?>" class="w-3.5 h-3.5"></i>
                                                        <?= $user['status'] === 'active' ? 'Disable Account' : 'Enable Account' ?>
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

        <!-- 1. Edit User Modal -->
        <div id="editModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4 overflow-y-auto">
            <div class="bg-white border border-slate-200 rounded-xl p-6 max-w-lg w-full space-y-4 shadow-xl relative">

                <!-- Modal Header -->
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-semibold text-slate-900 flex items-center gap-2">
                        <i data-lucide="user-cog" class="w-4 h-4 text-[#007a55]"></i> Edit User Account
                    </h3>
                    <button type="button" onclick="closeEditModal()" class="text-slate-400 hover:text-slate-700 text-lg">&times;</button>
                </div>

                <!-- Form Wrapper -->
                <form action="../controllers/updateUser.php" method="POST" class="space-y-4 text-xs">
                    <input type="hidden" id="edit_user_id" name="user_id">

                    <!-- Edit User Form Fields -->
                    <div class="space-y-4">
                        <!-- Row 1: First Name & Last Name -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-slate-700 font-medium mb-1 text-xs uppercase tracking-wider">First Name *</label>
                                <input type="text" id="edit_first_name" name="first_name" required class="w-full bg-white border border-slate-200 text-slate-800 rounded-lg px-3.5 py-2 text-sm focus:outline-none focus:border-[#007a55]">
                            </div>
                            <div>
                                <label class="block text-slate-700 font-medium mb-1 text-xs uppercase tracking-wider">Last Name *</label>
                                <input type="text" id="edit_last_name" name="last_name" required class="w-full bg-white border border-slate-200 text-slate-800 rounded-lg px-3.5 py-2 text-sm focus:outline-none focus:border-[#007a55]">
                            </div>
                        </div>

                        <!-- Row 2: Username & Email Address -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-slate-700 font-medium mb-1 text-xs uppercase tracking-wider">Username *</label>
                                <input type="text" id="edit_username" name="username" required class="w-full bg-white border border-slate-200 text-slate-800 font-mono rounded-lg px-3.5 py-2 text-sm focus:outline-none focus:border-[#007a55]">
                            </div>
                            <div>
                                <label class="block text-slate-700 font-medium mb-1 text-xs uppercase tracking-wider">Email Address *</label>
                                <input type="email" id="edit_email" name="email" required class="w-full bg-white border border-slate-200 text-slate-800 rounded-lg px-3.5 py-2 text-sm focus:outline-none focus:border-[#007a55]">
                            </div>
                        </div>

                        <!-- Row 3: Phone Number & System Role -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-slate-700 font-medium mb-1 text-xs uppercase tracking-wider">Phone Number</label>
                                <input type="text" id="edit_phone" name="phone" class="w-full bg-white border border-slate-200 text-slate-800 rounded-lg px-3.5 py-2 text-sm focus:outline-none focus:border-[#007a55]">
                            </div>
                            <div>
                                <label class="block text-slate-700 font-medium mb-1 text-xs uppercase tracking-wider">System Role *</label>
                                <select id="edit_role" name="role" required class="w-full bg-white border border-slate-200 text-slate-800 rounded-lg px-3.5 py-2 text-sm focus:outline-none focus:border-[#007a55]">
                                    <option value="Admin">Admin</option>
                                    <option value="Billing Officer">Billing Officer</option>
                                    <option value="Field Technician">Field Technician</option>
                                    <option value="Chemical Custodian">Chemical Custodian</option>
                                </select>
                            </div>
                        </div>

                        <!-- Row 4: Station / Region -->
                        <div>
                            <label class="block text-slate-700 font-medium mb-1 text-xs uppercase tracking-wider">Station / Region *</label>
                            <select id="edit_sector_region" name="sector_region" required class="w-full bg-white border border-slate-200 text-slate-800 rounded-lg px-3.5 py-2 text-sm focus:outline-none focus:border-[#007a55]">
                                <option value="Davao Head Office">Davao Head Office</option>
                                <option value="Tagum Branch">Tagum Branch</option>
                                <option value="Panabo Station">Panabo Station</option>
                                <option value="Digos Branch">Digos Branch</option>
                            </select>
                        </div>
                    </div>

                    <!-- Actions Footer -->
                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                        <button type="button" onclick="closeEditModal()" class="bg-white text-slate-600 px-4 py-2 rounded-lg border border-slate-200 hover:bg-slate-50 transition font-medium">Cancel</button>
                        <button type="submit" class="bg-[#007a55] hover:bg-[#006344] text-white font-medium px-4 py-2 rounded-lg transition shadow-sm">Save Changes</button>
                    </div>
                </form>

            </div>
        </div>

        <!-- 2. Password Reset Modal -->
        <div id="resetModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
            <div class="bg-white border border-slate-200 rounded-xl p-5 max-w-md w-full space-y-4 shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-semibold text-slate-900 flex items-center gap-2">
                        <i data-lucide="key-round" class="w-4 h-4 text-amber-500"></i> Reset Password
                    </h3>
                    <button onclick="closeResetModal()" class="text-slate-400 hover:text-slate-700 text-lg">&times;</button>
                </div>

                <form action="../controllers/resetPassword.php" method="POST" class="space-y-3.5 text-xs">
                    <input type="hidden" id="reset_user_id" name="user_id">

                    <p class="text-slate-500">Issue a new temporary password for <span id="reset_user_name" class="font-bold text-slate-800"></span>.</p>

                    <div>
                        <label class="block text-slate-600 font-medium mb-1">New Password *</label>
                        <input type="text" id="reset_new_password" name="new_password" required placeholder="Enter new password" class="w-full bg-slate-50 border border-slate-200 text-slate-900 font-mono rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55] transition">
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                        <button type="button" onclick="closeResetModal()" class="bg-white text-slate-600 px-4 py-2 rounded-lg border border-slate-200 hover:bg-slate-50 transition font-medium">Cancel</button>
                        <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-medium px-4 py-2 rounded-lg transition shadow-sm">Update Password</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <!-- 3. Helper Scripts -->
    <script>
        lucide.createIcons();

        function toggleUserForm() {
            const section = document.getElementById('userCreationSection');
            section.classList.toggle('hidden');
        }

        function generatePassword() {
            const chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%";
            let pass = "VmX-";
            for (let i = 0; i < 8; i++) {
                pass += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            document.getElementById('passInput').value = pass;
        }

        // Toggle dropdown action menu
        function toggleActionMenu(id) {
            document.querySelectorAll('[id^="action-menu-"]').forEach(el => {
                if (el.id !== `action-menu-${id}`) el.classList.add('hidden');
            });
            document.getElementById(`action-menu-${id}`).classList.toggle('hidden');
        }

        // Close action menus when clicking outside
        window.addEventListener('click', function(e) {
            if (!e.target.closest('.relative')) {
                document.querySelectorAll('[id^="action-menu-"]').forEach(el => el.classList.add('hidden'));
            }
        });

        // Edit Modal Handlers
        function openEditModal(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_first_name').value = user.first_name || '';
            document.getElementById('edit_last_name').value = user.last_name || '';
            document.getElementById('edit_username').value = user.username || '';
            document.getElementById('edit_email').value = user.email || '';
            document.getElementById('edit_phone').value = user.phone || '';
            document.getElementById('edit_role').value = user.role || 'Field Technician';
            document.getElementById('edit_sector_region').value = user.sector_region || 'Davao Head Office';
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        // Password Reset Modal Handlers
        function openResetModal(id, name) {
            document.getElementById('reset_user_id').value = id;
            document.getElementById('reset_user_name').innerText = name;
            document.getElementById('reset_new_password').value = 'VmX-' + Math.random().toString(36).substring(2, 8).toUpperCase();
            document.getElementById('resetModal').classList.remove('hidden');
        }

        function closeResetModal() {
            document.getElementById('resetModal').classList.add('hidden');
        }
    </script>
</body>

</html>