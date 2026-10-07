<?php
session_start();
// 1. Load database connection first
require_once __DIR__ . '/../config/db.php';

// 2. Load auth middleware
require_once __DIR__ . '/../middleware/auth.php';

// 3. Restrict access strictly to Admin role
requireRole(['Admin']);

$userFullName = $_SESSION['full_name'] ?? 'Operations Manager';
$userRole = $_SESSION['role'] ?? 'Admin';

// Fetch archived clients along with their contracts
try {
    $stmt = $pdo->query("SELECT * FROM clients WHERE status = 'archived' ORDER BY created_at DESC");
    $archivedClients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch contracts for each archived client
    foreach ($archivedClients as &$client) {
        $cStmt = $pdo->prepare("SELECT * FROM contracts WHERE client_id = ? ORDER BY created_at DESC");
        $cStmt->execute([$client['id']]);
        $client['contracts'] = $cStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($client);
} catch (Exception $e) {$archivedClients = [];
    $errorMsg =$e->getMessage();
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
    <main class="flex-1 min-w-0 overflow-y-auto">

        <!-- Mobile top bar with menu button -->
        <div class="lg:hidden sticky top-0 z-10 bg-white border-b border-slate-200 px-4 py-3 flex items-center gap-3">
            <button type="button" onclick="toggleAppSidebar()" class="p-2 -ml-2 text-slate-500 hover:text-slate-700 rounded-lg hover:bg-slate-100" aria-label="Open menu">
                <i data-lucide="menu" class="w-5 h-5"></i>
            </button>
            <span class="text-sm font-bold text-slate-900 tracking-tight">Archived Clients</span>
        </div>

        <div class="p-4 sm:p-6 lg:p-8">

        <!-- Header & Breadcrumb -->
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 mb-8">
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
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Archived Client Directory</span>
                <span class="text-xs text-slate-400"><?= count($archivedClients) ?> archived record(s) found</span>
            </div>

            <div class="overflow-x-auto">
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
                        <?php foreach ($archivedClients as$client): ?>
                            <?php 
                                $statusVal = strtolower($client['status'] ?? 'archived');
                                $encodedClient = htmlspecialchars(json_encode($client), ENT_QUOTES, 'UTF-8');
                                
                                $clientDisplayName =$client['client_name'] ?? $client['company_name'] ?? 'Unnamed Client';$contactPerson = trim(($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? ''));
                                if (empty($contactPerson)) {
                                    $contactPerson =$client['contact_person'] ?? 'N/A';
                                }
                                $phoneNumber = $client['phone_number'] ?? $client['contact_number'] ?? 'N/A';
                                $clientType =$client['client_type'] ?? $client['account_type'] ?? 'Residential';$fullAddress = $client['address'] ?? trim(($client['street_address'] ?? '') . ', ' . ($client['barangay'] ?? '') . ', ' . ($client['city'] ?? 'Davao City'), ', ');
                            ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="p-4">
                                    <p class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($clientDisplayName) ?></p>
                                    <p class="text-slate-400 text-[11px]"><?= htmlspecialchars($contactPerson) ?> • <?= htmlspecialchars($phoneNumber) ?></p>
                                </td>
                                <td class="p-4">
                                    <span class="bg-slate-100 text-slate-700 font-semibold px-2.5 py-1 rounded-md text-[11px] border border-slate-200">
                                        <?= htmlspecialchars($clientType) ?>
                                    </span>
                                </td>
                                <td class="p-4 text-slate-600"><?= htmlspecialchars($fullAddress) ?></td>
                                <td class="p-4">
                                    <span class="inline-flex items-center gap-1 text-slate-700 font-medium text-[11px] bg-slate-100 px-2.5 py-0.5 rounded-full border border-slate-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-500"></span> Archived
                                    </span>
                                </td>
                                <td class="p-4 text-right space-x-2">
                                    <!-- View History Modal Button -->
                                    <button type="button" onclick='openArchivedProfileModal(<?= $encodedClient ?>)' class="px-3 py-1.5 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition inline-block">
                                        View History
                                    </button>
                                    <!-- Restore Account Link -->
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
        </div>

        </div>

    </main>

    <!-- Client Profile & Contracts History Modal -->
    <div id="archivedProfileModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden animate-in fade-in zoom-in duration-150">
            
            <!-- Modal Header -->
            <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-white sticky top-0 z-10">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 bg-red-50 text-red-600 rounded-xl">
                        <i data-lucide="building" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Client Profile & Contracts History</h3>
                        <p class="text-xs text-slate-400">Manage account information and active/past contracts</p>
                    </div>
                </div>
                <button onclick="closeArchivedProfileModal()" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 overflow-y-auto space-y-6">
                
                <!-- Client Details Box -->
                <div class="p-5 border border-slate-200/80 rounded-xl bg-white space-y-4">
                    <div class="flex justify-between items-center pb-3 border-b border-slate-100">
                        <div class="flex items-center gap-2">
                            <i data-lucide="user" class="w-4 h-4 text-red-500"></i>
                            <span class="text-xs font-bold text-slate-800">Client Details</span>
                            <span id="modalClientStatus" class="bg-slate-100 text-slate-600 border border-slate-200 text-[10px] font-semibold px-2 py-0.5 rounded-md">Archived</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6 text-xs">
                        <div>
                            <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Company / Name</p>
                            <p id="modalClientName" class="font-bold text-slate-800 text-sm mt-0.5">-</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Contact Person</p>
                            <p id="modalContactPerson" class="font-medium text-slate-700 mt-0.5">-</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Account Type</p>
                            <p id="modalAccountType" class="font-medium text-slate-700 mt-0.5">-</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Email Address</p>
                            <p id="modalEmail" class="font-medium text-slate-700 mt-0.5">-</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Phone Number</p>
                            <p id="modalPhone" class="font-medium text-slate-700 mt-0.5">-</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Service Address</p>
                            <p id="modalAddress" class="font-medium text-slate-700 mt-0.5">-</p>
                        </div>
                    </div>
                </div>

                <!-- Contracts Summary & History -->
                <div class="p-5 border border-slate-200/80 rounded-xl bg-white space-y-4">
                    <div class="flex justify-between items-center pb-3 border-b border-slate-100">
                        <div class="flex items-center gap-2">
                            <i data-lucide="file-text" class="w-4 h-4 text-red-500"></i>
                            <span class="text-xs font-bold text-slate-800">Contracts Summary & History</span>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 text-slate-400 uppercase font-semibold text-[10px]">
                                <tr>
                                    <th class="p-3">Contract ID</th>
                                    <th class="p-3">Contract Name</th>
                                    <th class="p-3">Status</th>
                                    <th class="p-3">Start - Expiry Date</th>
                                    <th class="p-3 text-right">Contract Value</th>
                                </tr>
                            </thead>
                            <tbody id="modalContractsTable" class="divide-y divide-slate-100">
                                <!-- Populated dynamically via JS -->
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <!-- Modal Footer -->
            <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex justify-end">
                <button onclick="closeArchivedProfileModal()" class="px-4 py-2 bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 rounded-lg text-xs font-semibold transition shadow-sm">
                    Close Profile
                </button>
            </div>

        </div>
    </div>

    <script>
        lucide.createIcons();

        function openArchivedProfileModal(client) {
            document.getElementById('modalClientName').textContent = client.client_name || client.company_name || 'Unnamed';
            
            let contactName = ((client.first_name || '') + ' ' + (client.last_name || '')).trim();
            document.getElementById('modalContactPerson').textContent = contactName || client.contact_person || 'N/A';
            
            document.getElementById('modalAccountType').textContent = client.client_type || client.account_type || 'Residential';
            document.getElementById('modalEmail').textContent = client.email || 'N/A';
            document.getElementById('modalPhone').textContent = client.phone_number || client.contact_number || 'N/A';
            
            let fullAddr = client.address || [client.street_address, client.barangay, client.city].filter(Boolean).join(', ');
            document.getElementById('modalAddress').textContent = fullAddr || 'N/A';

            // Populate contracts table
            const tbody = document.getElementById('modalContractsTable');
            tbody.innerHTML = '';

            if (!client.contracts || client.contracts.length === 0) {
                tbody.innerHTML = `<tr><td colspan="5" class="p-4 text-center text-slate-400 italic">No contracts found for this client.</td></tr>`;
            } else {
                client.contracts.forEach(contract => {
                    const row = document.createElement('tr');
                    row.className = 'hover:bg-slate-50/50 transition';
                    
                    const valueFormatted = contract.contract_value ? '₱' + parseFloat(contract.contract_value).toLocaleString('en-US', {minimumFractionDigits: 2}) : '₱0.00';
                    
                    // Column mapping matching database schema
                    const cStatus = contract.contract_status || contract.status || 'Archived';
                    const startDate = contract.contract_start_date || contract.start_date || 'N/A';
                    const endDate = contract.contract_end_date || contract.end_date || 'N/A';
                    const contractName = contract.contract_name || contract.title || client.client_name;
                    
                    row.innerHTML = `
                        <td class="p-3 font-semibold text-slate-700">#CONTRACT-${contract.id}</td>
                        <td class="p-3 font-bold text-slate-800">${contractName}</td>
                        <td class="p-3">
                            <span class="bg-red-50 text-red-700 border border-red-200 text-[10px] font-semibold px-2 py-0.5 rounded-md capitalize">
                                ${cStatus.replace('_', ' ')}
                            </span>
                        </td>
                        <td class="p-3 text-slate-600">${startDate} to ${endDate}</td>
                        <td class="p-3 text-right font-bold text-slate-800">${valueFormatted}</td>
                    `;
                    tbody.appendChild(row);
                });
            }

            document.getElementById('archivedProfileModal').classList.remove('hidden');
            lucide.createIcons();
        }

        function closeArchivedProfileModal() {
            document.getElementById('archivedProfileModal').classList.add('hidden');
        }
    </script>
</body>

</html>