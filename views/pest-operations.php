<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vermex - Pest Operations</title>
    
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
            
            <!-- Page Title & Top Action Header -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-200 pb-5">
                <div>
                    <div class="flex items-center gap-2 text-[11px] text-[#007a55] font-semibold tracking-wider uppercase mb-1">
                        <span>Live Operations</span>
                        <span>•</span>
                        <span>Sector 04</span>
                    </div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Pest Operations</h1>
                    <p class="text-xs text-slate-500 mt-1">Monitor active infestations, dispatch field crews, track chemical usage, and review job order reports.</p>
                </div>
                
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                        <input type="text" placeholder="Search jobs, clients, or pests..." class="bg-white border border-slate-200 text-slate-800 placeholder-slate-400 text-xs rounded-lg pl-9 pr-4 py-2 w-64 focus:outline-none focus:border-[#007a55] transition shadow-sm">
                    </div>
                    <button class="bg-[#007a55] hover:bg-[#006344] text-white font-medium text-xs px-4 py-2 rounded-lg flex items-center gap-2 transition shadow-sm">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        <span>Create Job</span>
                    </button>
                </div>
            </div>

            <!-- Top KPI Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white border border-slate-200/80 rounded-xl p-4 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Active Infestations</p>
                        <p class="text-2xl font-bold text-slate-900 mt-1">27</p>
                        <p class="text-[11px] text-rose-600 font-medium mt-1">5 critical sites</p>
                    </div>
                    <div class="bg-rose-50 p-2.5 rounded-lg text-rose-600 border border-rose-100">
                        <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                    </div>
                </div>

                <div class="bg-white border border-slate-200/80 rounded-xl p-4 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">High-Priority Pests</p>
                        <p class="text-2xl font-bold text-slate-900 mt-1">9</p>
                        <p class="text-[11px] text-amber-600 font-medium mt-1">3 escalated today</p>
                    </div>
                    <div class="bg-amber-50 p-2.5 rounded-lg text-amber-600 border border-amber-100">
                        <i data-lucide="bug" class="w-5 h-5"></i>
                    </div>
                </div>

                <div class="bg-white border border-slate-200/80 rounded-xl p-4 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Completed Today</p>
                        <p class="text-2xl font-bold text-slate-900 mt-1">146</p>
                        <p class="text-[11px] text-emerald-600 font-medium mt-1">+12.4% this month</p>
                    </div>
                    <div class="bg-emerald-50 p-2.5 rounded-lg text-[#007a55] border border-emerald-100">
                        <i data-lucide="check-circle-2" class="w-5 h-5"></i>
                    </div>
                </div>

                <div class="bg-white border border-slate-200/80 rounded-xl p-4 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Safety & Compliance</p>
                        <p class="text-2xl font-bold text-slate-900 mt-1">3</p>
                        <p class="text-[11px] text-amber-600 font-medium mt-1">1 review pending</p>
                    </div>
                    <div class="bg-emerald-50 p-2.5 rounded-lg text-[#007a55] border border-emerald-100">
                        <i data-lucide="shield-alert" class="w-5 h-5"></i>
                    </div>
                </div>
            </div>

            <!-- Main Workspace Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Primary Active Jobs Table (Left Column) -->
                <div class="lg:col-span-2 bg-white border border-slate-200/80 rounded-xl p-5 space-y-4 shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-base font-semibold text-slate-900">Active Pest Jobs</h2>
                                <span class="bg-emerald-50 text-[#007a55] text-[10px] font-semibold px-2 py-0.5 rounded-full border border-emerald-100">27 live</span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-0.5">Last sync 14:32:08 • auto-refresh 30s</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button class="bg-slate-50 hover:bg-slate-100 text-slate-600 text-xs px-3 py-1.5 rounded-lg border border-slate-200 transition flex items-center gap-1.5 font-medium">
                                <i data-lucide="filter" class="w-3.5 h-3.5"></i>
                                Filter
                            </button>
                            <button class="bg-slate-50 hover:bg-slate-100 text-slate-600 text-xs px-3 py-1.5 rounded-lg border border-slate-200 transition flex items-center gap-1.5 font-medium">
                                <i data-lucide="download" class="w-3.5 h-3.5"></i>
                                Export
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="text-[10px] uppercase text-slate-400 border-b border-slate-100">
                                    <th class="pb-3 font-semibold">Job ID</th>
                                    <th class="pb-3 font-semibold">Client / Site</th>
                                    <th class="pb-3 font-semibold">Target Pest</th>
                                    <th class="pb-3 font-semibold">Chemical / Dosage</th>
                                    <th class="pb-3 font-semibold">Severity</th>
                                    <th class="pb-3 font-semibold text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-3 font-mono text-slate-800 font-bold">PO-2841</td>
                                    <td class="py-3">
                                        <p class="font-bold text-slate-800">Harborview Hotel</p>
                                        <p class="text-[10px] text-slate-400">Downtown • B2 Kitchen</p>
                                    </td>
                                    <td class="py-3"><span class="bg-purple-50 text-purple-700 border border-purple-200/60 px-2 py-0.5 rounded text-[10px] font-medium">Rodents</span></td>
                                    <td class="py-3 text-slate-700">Contrac Blox <span class="text-slate-400">(12 x 28g)</span></td>
                                    <td class="py-3"><span class="bg-rose-50 text-rose-700 border border-rose-200/60 px-2 py-0.5 rounded text-[10px] font-semibold">Critical</span></td>
                                    <td class="py-3 text-right">
                                        <a href="#" class="bg-emerald-50 hover:bg-emerald-100 text-[#007a55] border border-emerald-100 px-2.5 py-1 rounded text-[11px] font-semibold inline-flex items-center gap-1 transition">
                                            Open <i data-lucide="arrow-up-right" class="w-3 h-3"></i>
                                        </a>
                                    </td>
                                </tr>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-3 font-mono text-slate-800 font-bold">PO-2838</td>
                                    <td class="py-3">
                                        <p class="font-bold text-slate-800">Eastgate Logistics</p>
                                        <p class="text-[10px] text-slate-400">Warehouse 4 • Dock C</p>
                                    </td>
                                    <td class="py-3"><span class="bg-amber-50 text-amber-700 border border-amber-200/60 px-2 py-0.5 rounded text-[10px] font-medium">Termites</span></td>
                                    <td class="py-3 text-slate-700">Termidor HE <span class="text-slate-400">(0.125% / 84L)</span></td>
                                    <td class="py-3"><span class="bg-amber-50 text-amber-700 border border-amber-200/60 px-2 py-0.5 rounded text-[10px] font-semibold">High</span></td>
                                    <td class="py-3 text-right">
                                        <a href="#" class="bg-emerald-50 hover:bg-emerald-100 text-[#007a55] border border-emerald-100 px-2.5 py-1 rounded text-[11px] font-semibold inline-flex items-center gap-1 transition">
                                            Open <i data-lucide="arrow-up-right" class="w-3 h-3"></i>
                                        </a>
                                    </td>
                                </tr>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-3 font-mono text-slate-800 font-bold">PO-2834</td>
                                    <td class="py-3">
                                        <p class="font-bold text-slate-800">Pinecrest School</p>
                                        <p class="text-[10px] text-slate-400">Cafeteria • Zone A</p>
                                    </td>
                                    <td class="py-3"><span class="bg-blue-50 text-blue-700 border border-blue-200/60 px-2 py-0.5 rounded text-[10px] font-medium">Insects</span></td>
                                    <td class="py-3 text-slate-700">Advion Ant Gel <span class="text-slate-400">(18 x 0.5g)</span></td>
                                    <td class="py-3"><span class="bg-blue-50 text-blue-700 border border-blue-200/60 px-2 py-0.5 rounded text-[10px] font-semibold">Medium</span></td>
                                    <td class="py-3 text-right">
                                        <a href="#" class="bg-emerald-50 hover:bg-emerald-100 text-[#007a55] border border-emerald-100 px-2.5 py-1 rounded text-[11px] font-semibold inline-flex items-center gap-1 transition">
                                            Open <i data-lucide="arrow-up-right" class="w-3 h-3"></i>
                                        </a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Right Side Control Panel (Operations & Digitized Forms) -->
                <div class="space-y-4">

                    <!-- 1. Field Technician Dispatch Tracker -->
                    <div class="bg-white border border-slate-200/80 rounded-xl p-4 space-y-3 shadow-sm">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                            <h3 class="text-xs font-semibold text-slate-800 flex items-center gap-2">
                                <i data-lucide="navigation" class="w-3.5 h-3.5 text-[#007a55]"></i>
                                Technician Dispatch
                            </h3>
                            <span class="text-[10px] text-slate-500 font-mono font-medium">14/16 Active</span>
                        </div>
                        <div class="space-y-2 text-xs">
                            <div class="p-2.5 bg-slate-50/80 rounded-lg border border-slate-100 flex items-center justify-between">
                                <div>
                                    <p class="font-bold text-slate-800">Rodel Mamparil</p>
                                    <p class="text-[10px] text-slate-400">PO-2841 • Harborview Hotel</p>
                                </div>
                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded bg-emerald-50 text-[#007a55] border border-emerald-200/60">On Site</span>
                            </div>
                            <div class="p-2.5 bg-slate-50/80 rounded-lg border border-slate-100 flex items-center justify-between">
                                <div>
                                    <p class="font-bold text-slate-800">John Doe</p>
                                    <p class="text-[10px] text-slate-400">PO-2838 • Eastgate Logistics</p>
                                </div>
                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded bg-blue-50 text-blue-700 border border-blue-200/60">En Route</span>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Daily Chemical & Material Consumption -->
                    <div class="bg-white border border-slate-200/80 rounded-xl p-4 space-y-3 shadow-sm">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                            <h3 class="text-xs font-semibold text-slate-800 flex items-center gap-2">
                                <i data-lucide="flask-conical" class="w-3.5 h-3.5 text-[#007a55]"></i>
                                Chemical Usage (Today)
                            </h3>
                            <span class="text-[10px] bg-amber-50 text-amber-700 border border-amber-200/60 px-1.5 py-0.5 rounded font-semibold">Low Stock</span>
                        </div>
                        <div class="space-y-2.5 text-xs">
                            <div>
                                <div class="flex justify-between text-[11px] mb-1">
                                    <span class="text-slate-600 font-medium">Termidor HE</span>
                                    <span class="text-slate-800 font-mono font-semibold">160 ml used</span>
                                </div>
                                <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                                    <div class="bg-[#007a55] h-full w-[82%] rounded-full"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between text-[11px] mb-1">
                                    <span class="text-slate-600 font-medium">Contrac Blox / Glue Traps</span>
                                    <span class="text-slate-800 font-mono font-semibold">2 units used</span>
                                </div>
                                <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                                    <div class="bg-amber-500 h-full w-[45%] rounded-full"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Digitized Job Order & Inspection Review -->
                    <div class="bg-white border border-slate-200/80 rounded-xl p-4 space-y-3 shadow-sm">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                            <h3 class="text-xs font-semibold text-slate-800 flex items-center gap-2">
                                <i data-lucide="file-check-2" class="w-3.5 h-3.5 text-[#007a55]"></i>
                                Field Job Order & Inspection Review
                            </h3>
                            <span class="bg-emerald-50 text-[#007a55] text-[10px] font-semibold px-2 py-0.5 rounded-full border border-emerald-100">1 Pending</span>
                        </div>

                        <!-- Digitized Form Record -->
                        <div class="p-3 bg-slate-50/80 rounded-lg border border-slate-200/80 space-y-2 text-xs">
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-[#007a55] font-bold">JO #L-8152</span>
                                        <span class="text-[10px] text-slate-400">• 10:10 AM</span>
                                    </div>
                                    <p class="font-bold text-slate-800 text-xs mt-0.5">ANNIPIL (Roxas, Davao City)</p>
                                </div>
                                <span class="bg-amber-50 text-amber-700 border border-amber-200/60 text-[10px] px-1.5 py-0.5 rounded font-semibold">Review</span>
                            </div>

                            <!-- Summary details from physical Job Order & Inspection forms -->
                            <div class="text-[11px] text-slate-600 bg-white p-2.5 rounded border border-slate-200/60 space-y-1 shadow-2xs">
                                <p><strong class="text-slate-800">Tech:</strong> Rodel Mamparil</p>
                                <p><strong class="text-slate-800">Treatment:</strong> General Pest Control</p>
                                <p><strong class="text-slate-800">Chemicals:</strong> Termidor HE (160ml / 20ml/L)</p>
                                <p><strong class="text-rose-600">Flagged Finding:</strong> Hole on wall/ceiling (Entry Point)</p>
                                <p><strong class="text-slate-800">Devices Logged:</strong> 1 Rat Cage, 2 Glue Traps</p>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex gap-2 pt-1">
                                <button class="flex-1 bg-[#007a55] hover:bg-[#006344] text-white font-medium text-[11px] py-1.5 rounded transition flex items-center justify-center gap-1 shadow-sm">
                                    <i data-lucide="check" class="w-3 h-3"></i>
                                    Verify & Approve
                                </button>
                                <button class="bg-white hover:bg-slate-100 text-slate-600 border border-slate-200 text-[11px] font-medium px-2.5 py-1.5 rounded transition shadow-2xs">
                                    View Slip
                                </button>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </main>

    <!-- 3. Initialize Lucide Icons -->
    <script>
        lucide.createIcons();
    </script>
</body>
</html>