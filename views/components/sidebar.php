<?php
// Detect current page file name automatically
$currentPage = basename($_SERVER['PHP_SELF']);

$userFullName = $_SESSION['full_name'] ?? 'Operations Manager';
$userRole = $_SESSION['role'] ?? 'Admin';

// Centralized Navigation Items
$navItems = [
    ['label' => 'Overview', 'file' => 'dashboard.php', 'icon' => 'layout-dashboard'],
    ['label' => 'Dispatch & Field Jobs', 'file' => 'dispatch.php', 'icon' => 'calendar'],
    ['label' => 'Clients & Contracts', 'file' => 'clients.php', 'icon' => 'users'],
    ['label' => 'Pest Operations', 'file' => 'pest-operations.php', 'icon' => 'bug'],
    ['label' => 'Inventory', 'file' => 'inventory.php', 'icon' => 'flask-conical'],
    ['label' => 'Reports & Analytics', 'file' => 'reports.php', 'icon' => 'bar-chart-3'],
    ['label' => 'User Management', 'file' => 'users.php', 'icon' => 'shield-check'],
    ['label' => 'Settings', 'file' => 'settings.php', 'icon' => 'settings'],
];
?>

<!-- FIXED-WIDTH SIDEBAR CONTAINER -->
<aside class="w-64 min-w-[16rem] max-w-[16rem] bg-[#0b2219] text-white flex flex-col justify-between p-4 flex-shrink-0 h-screen select-none">

    <div>
        <!-- Brand Header (Fixed height & padding) -->
        <div class="flex items-center gap-3 px-2 py-3 mb-6 border-b border-emerald-900/60 h-14">
            <div class="bg-emerald-600 p-2 rounded-lg text-white flex-shrink-0">
                <i data-lucide="shield-check" class="w-5 h-5"></i>
            </div>
            <div class="overflow-hidden">
                <h1 class="font-bold text-sm tracking-wider uppercase text-emerald-100 leading-none">VERMEX</h1>
                <p class="text-[10px] text-emerald-400 font-medium tracking-tight mt-1">PEST SOLUTIONS</p>
            </div>
        </div>

        <!-- Navigation Menu -->
        <div class="text-[11px] font-semibold text-emerald-500 uppercase tracking-wider mb-2 px-3">Workspace</div>
        <nav class="space-y-1">
            <?php foreach ($navItems as $item): ?>
                <?php
                // Check if current loop item matches active page
                $isActive = ($currentPage === $item['file']);

                // Enforce identical padding, height, and border radius across all links
                $baseClasses = "flex items-center gap-3 px-3 py-2.5 text-xs font-medium rounded-lg transition-colors duration-150 w-full";
                $activeClasses = $isActive
                    ? "bg-[#14382a] text-white shadow-sm"
                    : "text-emerald-300 hover:bg-emerald-900/40 hover:text-white";
                $iconColor = $isActive ? "text-emerald-400" : "text-emerald-400/70";
                ?>
                <a href="<?= $item['file'] ?>" class="<?= $baseClasses ?> <?= $activeClasses ?>">
                    <i data-lucide="<?= $item['icon'] ?>" class="w-4 h-4 flex-shrink-0 <?= $iconColor ?>"></i>
                    <span class="truncate"><?= $item['label'] ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>

    <!-- Bottom Widget & Profile Footer -->
    <div class="space-y-4">
        <div class="bg-emerald-900/30 p-3 rounded-lg border border-emerald-800/40">
            <div class="flex items-center gap-2 text-emerald-400 text-xs font-semibold mb-1">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                System Access
            </div>
            <p class="text-[11px] text-emerald-200/80 leading-snug">18 Accounts secured • MFA ready</p>
        </div>

        <div class="flex items-center justify-between pt-3 border-t border-emerald-900/60">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-8 h-8 rounded-full bg-emerald-700 flex items-center justify-center font-bold text-xs text-white flex-shrink-0">
                    <?= strtoupper(substr($userFullName, 0, 1)) ?>
                </div>
                <div class="truncate">
                    <p class="text-xs font-semibold text-emerald-100 truncate leading-tight"><?= htmlspecialchars($userFullName) ?></p>
                    <p class="text-[10px] text-emerald-400 truncate"><?= htmlspecialchars($userRole) ?></p>
                </div>
            </div>
            <a href="../auth/logout.php" title="Logout" class="text-emerald-400 hover:text-red-400 transition p-1.5 flex-shrink-0">
                <i data-lucide="log-out" class="w-4 h-4"></i>
            </a>
        </div>
    </div>

</aside>