<?php
session_start();
// Optional: Include your database connection here
// include '../config/database.php';

$userFullName = $_SESSION['full_name'] ?? 'Operations Manager';
$userRole = $_SESSION['role'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vermex - Site Inspections</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-[#f8faf9] text-slate-800 min-h-screen flex">

    <!-- SIDEBAR CONTAINER -->
    <?php include 'components/sidebar.php'; ?>

    <!-- MAIN CONTENT AREA -->
    <main class="flex-1 flex flex-col min-h-screen overflow-y-auto">
        
        <!-- Top White Header Bar (Matching your exact reference style) -->
        <header class="bg-white border-b border-slate-200/80 px-8 py-3.5 flex justify-between items-center shadow-xs">
            <!-- Breadcrumbs -->
            <div class="flex items-center gap-2 text-xs font-medium text-slate-500">
                <span class="text-slate-400">Operations</span>
                <span class="text-slate-300">/</span>
                <span class="text-slate-900 font-semibold">Site Inspections</span>
            </div>

            <!-- Right Header Icons & Live Status -->
            <div class="flex items-center gap-4">
                <button class="text-slate-400 hover:text-slate-600 transition">
                    <i data-lucide="search" class="w-4 h-4"></i>
                </button>
                <button class="text-slate-400 hover:text-slate-600 relative transition">
                    <i data-lucide="bell" class="w-4 h-4"></i>
                    <span class="absolute -top-0.5 -right-0.5 w-2 h-2 bg-red-500 rounded-full"></span>
                </button>
                
                <div class="inline-flex items-center gap-2 bg-emerald-50/80 border border-emerald-200/60 px-3 py-1.5 rounded-full shadow-xs">
                    <span class="relative flex h-2 w-2">
                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                      <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                    <span class="text-xs font-semibold text-emerald-800 tracking-wide">Operations Live</span>
                </div>
            </div>
        </header>

        <!-- Page Inner Content Container -->
        <div class="p-8 flex-1">
            
            <!-- Page Title & Action Button Row -->
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Pre-Contract Site Inspections</h1>
                    <p class="text-xs text-slate-500 mt-1">Manage client site visits, log pest findings, and prepare service contracts.</p>
                </div>
                <button onclick="openNewInspectionModal()" class="bg-[#007a55] hover:bg-[#006344] text-white text-xs font-medium px-4 py-2.5 rounded-lg flex items-center gap-2 shadow-sm transition">
                    <i data-lucide="plus" class="w-4 h-4"></i> New Inspection Record
                </button>
            </div>

            <!-- Metric Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white p-5 rounded-xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Pending Site Visits</p>
                        <h3 class="text-2xl font-bold text-slate-900 mt-1">12</h3>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                        <i data-lucide="clock" class="w-5 h-5"></i>
                    </div>
                </div>
                <div class="bg-white p-5 rounded-xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Inspections Completed</p>
                        <h3 class="text-2xl font-bold text-slate-900 mt-1">48</h3>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-emerald-50 text-[#007a55] flex items-center justify-center">
                        <i data-lucide="clipboard-check" class="w-5 h-5"></i>
                    </div>
                </div>
                <div class="bg-white p-5 rounded-xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Ready For Contract</p>
                        <h3 class="text-2xl font-bold text-slate-900 mt-1">8</h3>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                        <i data-lucide="file-text" class="w-5 h-5"></i>
                    </div>
                </div>
            </div>

            <!-- Main Client Table -->
            <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="p-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Client Inquiries & Inspection Pipeline</span>
                    <input type="text" placeholder="Search client or address..." class="text-xs border border-slate-200 rounded-lg px-3 py-1.5 w-64 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                </div>

                <table class="w-full text-left text-xs text-slate-600">
                    <thead class="bg-slate-50 text-slate-500 uppercase font-semibold border-b border-slate-100">
                        <tr>
                            <th class="p-4">Client / Company Name</th>
                            <th class="p-4">Account Type</th>
                            <th class="p-4">Service Address</th>
                            <th class="p-4">Inspection Status</th>
                            <th class="p-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <!-- Sample Row 1 -->
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="p-4">
                                <p class="font-bold text-slate-800 text-sm">Marco Polo Hotel</p>
                                <p class="text-slate-400 text-[11px]">contact@marcopolodavao.com</p>
                            </td>
                            <td class="p-4">
                                <span class="bg-blue-50 text-blue-700 font-semibold px-2.5 py-1 rounded-md text-[11px] border border-blue-100">
                                    Commercial
                                </span>
                            </td>
                            <td class="p-4 text-slate-600">CM Recto Ave, Davao City</td>
                            <td class="p-4">
                                <span class="inline-flex items-center gap-1 text-amber-600 font-medium text-[11px] bg-amber-50 px-2 py-0.5 rounded-full border border-amber-100">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Pending Visit
                                </span>
                            </td>
                            <td class="p-4 text-right space-x-2">
                                <button onclick="openAssignModal('Marco Polo Hotel')" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-1.5 rounded-lg font-medium transition">
                                    Assign Tech
                                </button>
                                <button onclick="openInspectionFormModal('Marco Polo Hotel')" class="bg-[#007a55] hover:bg-[#006344] text-white px-3 py-1.5 rounded-lg font-medium transition">
                                    Inspection Form
                                </button>
                            </td>
                        </tr>
                        <!-- Sample Row 2 -->
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="p-4">
                                <p class="font-bold text-slate-800 text-sm">Juan Dela Cruz</p>
                                <p class="text-slate-400 text-[11px]">juan.dc@gmail.com</p>
                            </td>
                            <td class="p-4">
                                <span class="bg-emerald-50 text-[#007a55] font-semibold px-2.5 py-1 rounded-md text-[11px] border border-emerald-100">
                                    Residential
                                </span>
                            </td>
                            <td class="p-4 text-slate-600">Bajada, Davao City</td>
                            <td class="p-4">
                                <span class="inline-flex items-center gap-1 text-emerald-600 font-medium text-[11px] bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-100">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Inspected (Pest Found)
                                </span>
                            </td>
                            <td class="p-4 text-right space-x-2">
                                <button onclick="openAssignModal('Juan Dela Cruz')" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-1.5 rounded-lg font-medium transition">
                                    Re-assign
                                </button>
                                <button onclick="openInspectionFormModal('Juan Dela Cruz')" class="bg-emerald-700 hover:bg-emerald-800 text-white px-3 py-1.5 rounded-lg font-medium transition">
                                    View Report
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </main>

    <!-- ASSIGN TECHNICIAN MODAL -->
    <div id="assignModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
        <div class="bg-white rounded-xl max-w-md w-full p-6 shadow-xl border border-slate-100">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold text-slate-900 text-base">Assign Field Technician</h3>
                <button onclick="closeAssignModal()" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <p class="text-xs text-slate-500 mb-4">Select a technician to perform the free site inspection for <span id="assignClientName" class="font-semibold text-slate-800"></span>.</p>
            
            <div class="space-y-3 mb-6">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Select Technician *</label>
                    <select class="w-full text-xs border border-slate-200 rounded-lg p-2.5 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        <option>Tech. Michael Reyes (Senior Inspector)</option>
                        <option>Tech. Dave Alcantara</option>
                        <option>Tech. Samuel Lim</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Inspection Date & Time *</label>
                    <input type="datetime-local" class="w-full text-xs border border-slate-200 rounded-lg p-2.5 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <button onclick="closeAssignModal()" class="px-4 py-2 rounded-lg text-xs font-medium border border-slate-200 text-slate-600 hover:bg-slate-50">Cancel</button>
                <button onclick="alert('Technician assigned successfully!'); closeAssignModal();" class="px-4 py-2 rounded-lg text-xs font-medium bg-[#007a55] text-white hover:bg-[#006344]">Confirm Assignment</button>
            </div>
        </div>
    </div>

    <!-- INSPECTION FORM MODAL -->
    <div id="inspectionFormModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
        <div class="bg-white rounded-xl max-w-2xl w-full p-6 shadow-xl border border-slate-100 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold text-slate-900 text-base">Site Inspection Report Form</h3>
                <button onclick="closeInspectionFormModal()" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <p class="text-xs text-slate-500 mb-4">Logging inspection findings for <span id="formClientName" class="font-semibold text-slate-800"></span>.</p>
            
            <div class="space-y-4 mb-6 text-xs">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Target Pests Observed *</label>
                        <div class="space-y-1.5 border border-slate-200 rounded-lg p-3">
                            <label class="flex items-center gap-2"><input type="checkbox" class="rounded text-emerald-600"> Subterranean Termites</label>
                            <label class="flex items-center gap-2"><input type="checkbox" class="rounded text-emerald-600"> German / American Cockroaches</label>
                            <label class="flex items-center gap-2"><input type="checkbox" class="rounded text-emerald-600"> Rodents (Rats / Mice)</label>
                            <label class="flex items-center gap-2"><input type="checkbox" class="rounded text-emerald-600"> Mosquitoes / Flies</label>
                        </div>
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Infestation Severity *</label>
                        <select class="w-full border border-slate-200 rounded-lg p-2.5 mb-3 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                            <option>Low / Minor Risk</option>
                            <option>Moderate Infestation</option>
                            <option>Severe / Critical Infestation</option>
                        </select>
                        <label class="block font-semibold text-slate-700 mb-1">Recommended Trap/Device Count</label>
                        <input type="text" placeholder="e.g., 4 Rat Cages, 2 Glue Boards" class="w-full border border-slate-200 rounded-lg p-2.5 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Property Findings & Contributing Conditions</label>
                    <textarea rows="3" placeholder="Describe entry points, moisture issues, or harborage areas found during site inspection..." class="w-full border border-slate-200 rounded-lg p-2.5 focus:outline-none focus:ring-1 focus:ring-emerald-500"></textarea>
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Recommended Service Contract Plan</label>
                    <select class="w-full border border-slate-200 rounded-lg p-2.5 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        <option>Residential 1-Year Agreement</option>
                        <option>Residential 2-Year Agreement</option>
                        <option>Commercial Monthly Recurring Plan</option>
                        <option>One-Time Corrective Treatment</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <button onclick="closeInspectionFormModal()" class="px-4 py-2 rounded-lg text-xs font-medium border border-slate-200 text-slate-600 hover:bg-slate-50">Cancel</button>
                <button onclick="alert('Inspection report saved! Ready for contract generation.'); closeInspectionFormModal();" class="px-4 py-2 rounded-lg text-xs font-medium bg-[#007a55] text-white hover:bg-[#006344]">Save & Proceed to Contract</button>
            </div>
        </div>
    </div>

    <!-- NEW INSPECTION RECORD MODAL -->
    <div id="newInspectionModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
        <div class="bg-white rounded-xl max-w-md w-full p-6 shadow-xl border border-slate-100">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold text-slate-900 text-base">New Inspection Lead</h3>
                <button onclick="closeNewInspectionModal()" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            
            <div class="space-y-3 mb-6 text-xs">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Client / Company Name *</label>
                    <input type="text" placeholder="e.g., Seda Hotel Davao" class="w-full border border-slate-200 rounded-lg p-2.5 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Account Type *</label>
                    <select class="w-full border border-slate-200 rounded-lg p-2.5 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        <option>Commercial</option>
                        <option>Residential</option>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Service Address *</label>
                    <input type="text" placeholder="Street, Building, Barangay, City" class="w-full border border-slate-200 rounded-lg p-2.5 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <button onclick="closeNewInspectionModal()" class="px-4 py-2 rounded-lg text-xs font-medium border border-slate-200 text-slate-600 hover:bg-slate-50">Cancel</button>
                <button onclick="alert('New inspection record added to queue!'); closeNewInspectionModal();" class="px-4 py-2 rounded-lg text-xs font-medium bg-[#007a55] text-white hover:bg-[#006344]">Add to Queue</button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        lucide.createIcons();

        function openAssignModal(clientName) {
            document.getElementById('assignClientName').innerText = clientName;
            document.getElementById('assignModal').classList.remove('hidden');
            document.getElementById('assignModal').classList.add('flex');
        }
        function closeAssignModal() {
            document.getElementById('assignModal').classList.remove('flex');
            document.getElementById('assignModal').classList.add('hidden');
        }

        function openInspectionFormModal(clientName) {
            document.getElementById('formClientName').innerText = clientName;
            document.getElementById('inspectionFormModal').classList.remove('hidden');
            document.getElementById('inspectionFormModal').classList.add('flex');
        }
        function closeInspectionFormModal() {
            document.getElementById('inspectionFormModal').classList.remove('flex');
            document.getElementById('inspectionFormModal').classList.add('hidden');
        }

        function openNewInspectionModal() {
            document.getElementById('newInspectionModal').classList.remove('hidden');
            document.getElementById('newInspectionModal').classList.add('flex');
        }
        function closeNewInspectionModal() {
            document.getElementById('newInspectionModal').classList.remove('flex');
            document.getElementById('newInspectionModal').classList.add('hidden');
        }
    </script>
</body>
</html>