<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$userFullName = $_SESSION['first_name'] ?? $_SESSION['username'] ?? 'Custodian';
$userRole = $_SESSION['role'] ?? 'Chemical Custodian';

$navItems = [
    ['label' => 'Warehouse Stock', 'file' => 'dashboard.php', 'icon' => 'package'],
    ['label' => 'Master Inventory', 'file' => 'inventory.php', 'icon' => 'layers'],
];
?>

<!-- Mobile Sidebar Backdrop -->
<div id="sidebarBackdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 z-40 hidden md:hidden"></div>

<!-- Sidebar Drawer -->
<aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-[#0b2219] text-white flex flex-col justify-between p-4 flex-shrink-0 transform -translate-x-full md:translate-x-0 md:static transition-transform duration-300 ease-in-out shadow-2xl md:shadow-none h-full md:h-screen select-none">
    <div>
        <!-- Brand Header -->
        <div class="flex items-center justify-between px-2 py-3 mb-6 border-b border-emerald-900/60 h-14">
            <div class="flex items-center gap-3">
                <div class="bg-emerald-600 p-2 rounded-lg text-white flex-shrink-0 font-bold">V</div>
                <div class="overflow-hidden">
                    <h2 class="font-bold text-sm tracking-wide text-emerald-100 leading-none">Vermex Custodian</h2>
                    <p class="text-[10px] text-emerald-400 font-medium tracking-tight mt-1">Inventory & Warehouse</p>
                </div>
            </div>
            <button onclick="toggleSidebar()" class="md:hidden text-emerald-400 hover:text-white p-1">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Navigation Menu -->
        <div class="text-[11px] font-semibold text-emerald-500 uppercase tracking-wider mb-2 px-3">Workspace</div>
        <nav class="space-y-1">
            <?php foreach ($navItems as $item): ?>
                <?php
                $isActive = ($currentPage === basename($item['file']));
                $baseClasses = "flex items-center gap-3 px-3 py-2.5 text-xs font-medium rounded-lg transition-colors duration-150 w-full";
                $activeClasses = $isActive
                    ? "bg-[#14382a] text-white shadow-sm font-semibold"
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

    <!-- Bottom Profile Footer -->
    <div class="space-y-4">
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
            <a href="../../auth/logout.php" title="Logout" class="text-emerald-400 hover:text-red-400 transition p-1.5 flex-shrink-0">
                <i data-lucide="log-out" class="w-4 h-4"></i>
            </a>
        </div>
    </div>
</aside>