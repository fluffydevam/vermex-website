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

<!-- Link Master CSS (Two Levels Up) -->
<link rel="stylesheet" href="../../assets/css/style.css">

<!-- Mobile Sidebar Backdrop -->
<div id="sidebarBackdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 z-40 hidden md:hidden"></div>

<aside id="sidebar" class="vermex-sidebar fixed inset-y-0 left-0 z-50 flex flex-col justify-between transform -translate-x-full md:translate-x-0 md:static transition-transform duration-300 ease-in-out shadow-2xl md:shadow-none h-full md:h-screen select-none">
    <div>
        <!-- Brand Header -->
        <div class="sidebar-brand-header">
            <div class="sidebar-brand-icon">
                <i data-lucide="wallet" class="w-5 h-5"></i>
            </div>
            <div class="overflow-hidden">
                <h1 class="sidebar-brand-title">VERMEX</h1>
                <p class="sidebar-brand-subtitle">BILLING PORTAL</p>
            </div>
            <button onclick="toggleSidebar()" class="md:hidden text-slate-400 hover:text-white p-1 ml-auto">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Navigation Menu -->
        <div class="sidebar-section-title">Financials</div>
        <nav class="space-y-1">
            <?php foreach ($navItems as $item): ?>
                <?php $isActive = ($currentPage === $item['file']); ?>
                <a href="<?= $item['file'] ?>" class="sidebar-nav-item <?= $isActive ? 'active' : '' ?>">
                    <i data-lucide="<?= $item['icon'] ?>"></i>
                    <span class="truncate"><?= $item['label'] ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>

    <!-- Bottom Widget & Profile Footer -->
    <div class="space-y-3">
        <div class="bg-[#3d1712] p-3 rounded-lg border border-[#4d1f18] hidden sm:block">
            <div class="flex items-center gap-2 text-[#ff6b5a] text-xs font-semibold mb-1">
                <span class="w-2 h-2 rounded-full bg-[#ff6b5a] animate-pulse"></span>
                Account Active
            </div>
            <p class="text-[11px] text-[#c5b8b5] leading-snug">Verify payments, View Client Accounts, and Financial Reports</p>
        </div>

        <div class="sidebar-footer">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="sidebar-avatar">
                    <?= strtoupper(substr($userFullName, 0, 1)) ?>
                </div>
                <div class="truncate">
                    <p class="text-xs font-semibold text-white truncate leading-tight"><?= htmlspecialchars($userFullName) ?></p>
                    <p class="text-[10px] text-[#e0a89e] truncate"><?= htmlspecialchars($userRole) ?></p>
                </div>
            </div>
            <a href="../../auth/logout.php" title="Logout" class="sidebar-logout-btn">
                <i data-lucide="log-out" class="w-4 h-4"></i>
            </a>
        </div>
    </div>
</aside>