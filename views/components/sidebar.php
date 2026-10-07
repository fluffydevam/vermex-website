<?php
// Detect current page file name automatically
$currentPage = basename($_SERVER['PHP_SELF']);

$firstName = $_SESSION['first_name'] ?? 'Operations';
$lastName  = $_SESSION['last_name'] ?? 'Manager';
$userFullName = trim($firstName . ' ' . $lastName);
$userRole  = $_SESSION['role'] ?? 'Admin';
$userInitial = !empty($firstName) ? strtoupper(substr($firstName, 0, 1)) : 'O';

// Centralized Navigation Items
$navItems = [
    ['label' => 'Overview', 'file' => 'dashboard.php', 'icon' => 'layout-dashboard'],
    ['label' => 'Dispatch & Field Jobs', 'file' => 'dispatch.php', 'icon' => 'calendar'],
    ['label' => 'Inspections', 'file' => 'inspections.php', 'icon' => 'clipboard-check'],
    ['label' => 'Clients & Contracts', 'file' => 'clients.php', 'icon' => 'users'],
    ['label' => 'Payments & Billing', 'file' => 'payments.php', 'icon' => 'credit-card'],
    ['label' => 'Inventory', 'file' => 'inventory.php', 'icon' => 'flask-conical'],
    ['label' => 'Reports & Analytics', 'file' => 'reports.php', 'icon' => 'bar-chart-3'],
    ['label' => 'User Management', 'file' => 'users.php', 'icon' => 'shield-check'],
    
];
?>

<!-- LINK EXTERNAL MASTER CSS (Going up 2 levels from views/components to root) -->
<link rel="stylesheet" href="../../assets/css/style.css">

<!-- FIXED-WIDTH SIDEBAR CONTAINER (off-canvas drawer on mobile, static on lg+) -->
<aside id="appSidebar" class="w-64 min-w-[16rem] max-w-[16rem] bg-[#2b110d] text-white flex flex-col justify-between p-4 flex-shrink-0 h-screen select-none fixed inset-y-0 left-0 z-40 -translate-x-full transition-transform duration-200 ease-in-out overflow-y-auto lg:static lg:translate-x-0 lg:transition-none lg:overflow-visible">

    <div>
        <!-- Brand Header -->
        <div class="flex items-center gap-3 px-2 py-3 mb-6 border-b border-[#4d1f18] h-14">
            <div class="bg-[#d32f2f] p-2 rounded-lg text-white flex-shrink-0 shadow-sm">
                <i data-lucide="shield-check" class="w-5 h-5"></i>
            </div>
            <div class="overflow-hidden">
                <h1 class="font-bold text-sm tracking-wider uppercase text-white leading-none">VERMEX</h1>
                <p class="text-[10px] text-[#e0a89e] font-medium tracking-tight mt-1">PEST SOLUTIONS</p>
            </div>
            <!-- Mobile close button -->
            <button type="button" onclick="closeAppSidebar()" class="lg:hidden ml-auto text-[#b08d87] hover:text-white p-1.5 flex-shrink-0" aria-label="Close menu">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Navigation Menu -->
        <div class="text-[11px] font-semibold text-[#c8887b] uppercase tracking-wider mb-2 px-3">Workspace</div>
        <nav class="space-y-1">
            <?php foreach ($navItems as $item): ?>
                <?php
                $isActive = ($currentPage === $item['file']);
                $baseClasses = "flex items-center gap-3 px-3 py-2.5 text-xs font-medium rounded-lg transition-colors duration-150 w-full";
                $activeClasses = $isActive
                    ? "bg-[#431913] text-white shadow-sm border-l-4 border-[#e53935]"
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
    <div class="space-y-4">
        <div class="flex items-center justify-between pt-3 border-t border-[#4d1f18]">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-8 h-8 rounded-full bg-[#d32f2f] flex items-center justify-center font-bold text-xs text-white flex-shrink-0">
                    <?= $userInitial ?>
                </div>
                <div class="truncate">
                    <p class="text-xs font-semibold text-white truncate leading-tight"><?= htmlspecialchars($userFullName) ?></p>
                    <p class="text-[10px] text-[#e0a89e] truncate"><?= htmlspecialchars($userRole) ?></p>
                </div>
            </div>
            <a href="../auth/logout.php" title="Logout" class="text-[#b08d87] hover:text-[#ff6b5a] transition p-1.5 flex-shrink-0">
                <i data-lucide="log-out" class="w-4 h-4"></i>
            </a>
        </div>
    </div>

</aside>

<!-- Mobile backdrop overlay (tap to close the drawer) -->
<div id="appSidebarOverlay" onclick="closeAppSidebar()" class="fixed inset-0 bg-black/50 z-30 hidden lg:hidden"></div>

<!-- Mobile drawer toggle logic (shared by all pages via the hamburger button) -->
<script>
    function openAppSidebar() {
        var sidebar = document.getElementById('appSidebar');
        var overlay = document.getElementById('appSidebarOverlay');
        if (sidebar) sidebar.classList.remove('-translate-x-full');
        if (overlay) overlay.classList.remove('hidden');
    }
    function closeAppSidebar() {
        var sidebar = document.getElementById('appSidebar');
        var overlay = document.getElementById('appSidebarOverlay');
        if (sidebar) sidebar.classList.add('-translate-x-full');
        if (overlay) overlay.classList.add('hidden');
    }
    function toggleAppSidebar() {
        var sidebar = document.getElementById('appSidebar');
        if (sidebar && sidebar.classList.contains('-translate-x-full')) {
            openAppSidebar();
        } else {
            closeAppSidebar();
        }
    }
    // Auto-close the drawer after tapping a navigation link on mobile
    document.addEventListener('DOMContentLoaded', function () {
        var sidebar = document.getElementById('appSidebar');
        if (!sidebar) return;
        sidebar.querySelectorAll('nav a').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth < 1024) closeAppSidebar();
            });
        });
    });
</script>