<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$userFullName = $_SESSION['full_name'] ?? 'Operations Manager';
$userRole = $_SESSION['role'] ?? 'Admin';

// Fetch inactive/archived clients (using created_at instead of updated_at to prevent column errors if updated_at is missing)
try {
    $stmt = $pdo->query("SELECT * FROM clients WHERE status = 'inactive' OR status = 'archived' ORDER BY created_at DESC");
    $archivedClients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $archivedClients = [];
    $errorMsg = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vermex - Archived Clients</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-[#f8faf9] text-slate-800 min-h-screen flex">

    <!-- Sidebar -->
    <?php include 'components/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 p-8 overflow-y-auto">
        
        <!-- Header & Breadcrumb -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <span class="text-xs font-medium text-slate-400">CRM & Operations / Clients / <span class="text-slate-700">Archived Records</span></span>
                <h1 class="text-2xl font-bold text-slate-900 mt-1">Archived Client Accounts</h1>
            </div>

            <a href="clients.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-medium px-4 py-2.5 rounded-lg flex items-center gap-2 shadow-sm transition">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Active Clients
            </a>
        </div>

        <!-- Session Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-xs flex justify-between items-center">
                <span><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-xs flex justify-between items-center">
                <span><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($errorMsg)): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-xs flex justify-between items-center">
                <span>Database Error: <?= htmlspecialchars($errorMsg); ?></span>
            </div>
        <?php endif; ?>

        <!-- Archived Table -->
        <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Inactive & Archived Client Directories</span>
                <span class="text-xs text-slate-400"><?= count($archivedClients) ?> archived record(s) found</span>
            </div>

            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 text-slate-500 uppercase font-semibold border-b border-slate-100">
                    <tr>
                        <th class="p-4">Client Name & Contact</th>
                        <th class="p-4">Type</th>
                        <th class="p-4">Standardized Address</th>
                        <th class="p-4">Status</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($archivedClients)): ?>
                        <tr>
                            <td colspan="5" class="p-8 text-center text-slate-400 italic">No archived clients found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($archivedClients as $client): ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="p-4">
                                    <p class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($client['company_name'] ?? $client['client_name'] ?? 'Unnamed Client') ?></p>
                                    <p class="text-slate-400 text-[11px]"><?= htmlspecialchars($client['contact_person'] ?? '') ?> • <?= htmlspecialchars($client['phone_number'] ?? $client['contact_number'] ?? '') ?></p>
                                </td>
                                <td class="p-4">
                                    <span class="bg-slate-100 text-slate-700 font-semibold px-2.5 py-1 rounded-md text-[11px] border border-slate-200">
                                        <?= htmlspecialchars($client['account_type'] ?? $client['client_type'] ?? 'Residential') ?>
                                    </span>
                                </td>
                                <td class="p-4 text-slate-600"><?= htmlspecialchars($client['address'] ?? ($client['street_address'] . ', ' . $client['barangay'] . ', ' . $client['city'])) ?></td>
                                <td class="p-4">
                                    <span class="inline-flex items-center gap-1 text-amber-700 font-medium text-[11px] bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Inactive / Archived
                                    </span>
                                </td>
                                <td class="p-4 text-right space-x-2">
                                    <!-- Action 2: View contract history / profile without activating -->
                                    <a href="client-profile.php?id=<?= $client['id'] ?>" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-1.5 rounded-lg font-medium transition inline-block">
                                        View History
                                    </a>
                                    <!-- Action 1: Restore account back to active and put back to client dashboard -->
                                    <a href="../controllers/restoreClient.php?id=<?= $client['id'] ?>" onclick="return confirm('Are you sure you want to restore this client back to active status?');" class="bg-[#007a55] hover:bg-[#006344] text-white px-3 py-1.5 rounded-lg font-medium transition inline-block">
                                        Restore Client
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>