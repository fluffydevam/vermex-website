<?php
$currentPage = basename($_SERVER['PHP_SELF']);

$userFullName = $_SESSION['full_name'] ?? 'Billing Officer';
$userRole = $_SESSION['role'] ?? 'Billing Officer';

// Billing Officer Navigation Items
$navItems = [
    ['label' => 'Payment Ledger & Logger', 'file' => 'payments.php', 'icon' => 'credit-card'],
    ['label' => 'Client Accounts', 'file' => 'accounts.php', 'icon' => 'users'],
    ['label' => 'Financial Reports', 'file' => 'reports.php', 'icon' => 'bar-chart-3'],
];
?>

<!-- Mobile Sidebar Backdrop -->
<div id="sidebarBackdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 z-40 hidden md:hidden"></div>

<aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 min-w-[16rem] max-w-[16rem] bg-[#2b110d] text-white flex flex-col justify-between p-4 flex-shrink-0 transform -translate-x-full md:translate-x-0 md:static transition-transform duration-300 ease-in-out shadow-2xl md:shadow-none h-full md:h-screen select-none">
    <div>
        <!-- Brand Header -->
        <div class="flex items-center justify-between px-2 py-3 mb-6 border-b border-[#4d1f18] h-14">
            <div class="flex items-center gap-3">
                <div class="bg-red-600 p-2 rounded-lg text-white flex-shrink-0">
                    <i data-lucide="wallet" class="w-5 h-5"></i>
                </div>
                <div class="overflow-hidden">
                    <h1 class="font-bold text-sm tracking-wider uppercase text-white leading-none">VERMEX</h1>
                    <p class="text-[10px] text-red-400 font-medium tracking-tight mt-1">BILLING PORTAL</p>
                </div>
            </div>
            <button onclick="toggleSidebar()" class="md:hidden text-red-400 hover:text-white p-1">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Navigation Menu -->
        <div class="text-[11px] font-semibold text-[#c8887b] uppercase tracking-wider mb-2 px-3">Financials</div>
        <nav class="space-y-1">
            <?php foreach ($navItems as $item): ?>
                <?php
                $isActive = ($currentPage === $item['file']);
                $baseClasses = "flex items-center gap-3 px-3 py-2.5 text-xs font-medium rounded-lg transition-colors duration-150 w-full";
                $activeClasses = $isActive
                    ? "bg-[#431913] text-white shadow-sm border-l-4 border-red-500 font-semibold"
                    : "text-[#c5b8b5] hover:bg-[#3d1712] hover:text-white";
                $iconColor = $isActive ? "text-[#ff6b5a]" : "text-[#b08d87]";
                ?>
                <a href="<?= $item['file'] ?>" class="<?= $baseClasses ?> <?= $activeClasses ?>">
                    <i data-lucide="<?= $item['icon'] ?>" class="w-4 h-4 flex-shrink-0 <?= $iconColor ?>"></i>
                    <span class="truncate"><?= $item['label'] ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>

    <!-- Bottom Widget & Profile Footer -->
    <div class="space-y-3">
        <div class="bg-[#3d1712] p-3 rounded-lg border border-[#4d1f18] hidden sm:block">
            <div class="flex items-center gap-2 text-red-400 text-xs font-semibold mb-1">
                <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
                Account Active
            </div>
            <p class="text-[11px] text-[#c5b8b5] leading-snug">Verify payments, View Client Accounts, and Financial Reports</p>
        </div>

        <div class="flex items-center justify-between pt-3 border-t border-[#4d1f18]">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-8 h-8 rounded-full bg-red-600 flex items-center justify-center font-bold text-xs text-white flex-shrink-0">
                    <?= strtoupper(substr($userFullName, 0, 1)) ?>
                </div>
                <div class="truncate">
                    <p class="text-xs font-semibold text-white truncate leading-tight"><?= htmlspecialchars($userFullName) ?></p>
                    <p class="text-[10px] text-[#c5b8b5] truncate"><?= htmlspecialchars($userRole) ?></p>
                </div>
            </div>
            <a href="../../auth/logout.php" title="Logout" class="text-[#b08d87] hover:text-red-400 transition p-1.5 flex-shrink-0">
                <i data-lucide="log-out" class="w-4 h-4"></i>
            </a>
        </div>
    </div>
</aside>