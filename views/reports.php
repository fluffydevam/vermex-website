<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vermex - Reports & Analytics</title>
    
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
            
            <!-- Page Header -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-200 pb-5">
                <div>
                    <div class="flex items-center gap-2 text-[11px] text-[#007a55] font-semibold tracking-wider uppercase mb-1">
                        <span>Management</span>
                        <span>•</span>
                        <span>Service Reports</span>
                    </div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Reports & Analytics</h1>
                    <p class="text-xs text-slate-500 mt-1">Summary of field job completions, technician performance, and chemical consumption.</p>
                </div>
                
                <div class="flex items-center gap-3">
                    <select class="bg-white border border-slate-200 text-slate-700 text-xs rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55] transition shadow-sm font-medium">
                        <option value="this-month">This Month</option>
                        <option value="last-month">Last Month</option>
                        <option value="year-to-date">Year to Date</option>
                    </select>
                    <button class="bg-[#007a55] hover:bg-[#006344] text-white font-medium text-xs px-4 py-2 rounded-lg flex items-center gap-2 transition shadow-sm">
                        <i data-lucide="download" class="w-4 h-4"></i>
                        <span>Export Summary Report</span>
                    </button>
                </div>
            </div>

            <!-- Basic Summary Statistics -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white border border-slate-200/80 rounded-xl p-4 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Job Orders Completed</p>
                        <p class="text-2xl font-bold text-slate-900 mt-1">146</p>
                        <p class="text-[11px] text-emerald-600 font-medium mt-1">100% Client Sign-off</p>
                    </div>
                    <div class="bg-emerald-50 p-2.5 rounded-lg text-[#007a55] border border-emerald-100">
                        <i data-lucide="clipboard-check" class="w-5 h-5"></i>
                    </div>
                </div>

                <div class="bg-white border border-slate-200/80 rounded-xl p-4 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Chemicals Used</p>
                        <p class="text-2xl font-bold text-slate-900 mt-1">18,450 <span class="text-xs font-normal text-slate-500">mL</span></p>
                        <p class="text-[11px] text-blue-600 font-medium mt-1">Across 6 Treatments</p>
                    </div>
                    <div class="bg-blue-50 p-2.5 rounded-lg text-blue-600 border border-blue-100">
                        <i data-lucide="flask-conical" class="w-5 h-5"></i>
                    </div>
                </div>

                <div class="bg-white border border-slate-200/80 rounded-xl p-4 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Inspections Conducted</p>
                        <p class="text-2xl font-bold text-slate-900 mt-1">28</p>
                        <p class="text-[11px] text-amber-600 font-medium mt-1">3 Entry Points Flagged</p>
                    </div>
                    <div class="bg-amber-50 p-2.5 rounded-lg text-amber-600 border border-amber-100">
                        <i data-lucide="search" class="w-5 h-5"></i>
                    </div>
                </div>

                <div class="bg-white border border-slate-200/80 rounded-xl p-4 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Active Field Technicians</p>
                        <p class="text-2xl font-bold text-slate-900 mt-1">3</p>
                        <p class="text-[11px] text-emerald-600 font-medium mt-1">All Tasks Up to Date</p>
                    </div>
                    <div class="bg-purple-50 p-2.5 rounded-lg text-purple-600 border border-purple-100">
                        <i data-lucide="users" class="w-5 h-5"></i>
                    </div>
                </div>
            </div>

            <!-- Operational Visual Breakdown Cards -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                
                <!-- Target Pest Type Summary -->
                <div class="bg-white border border-slate-200/80 rounded-xl p-5 space-y-4 shadow-sm">
                    <div class="border-b border-slate-100 pb-3">
                        <h2 class="text-base font-semibold text-slate-900">Target Pest Distribution</h2>
                        <p class="text-[11px] text-slate-400 mt-0.5">Frequency of target pests specified in Job Orders.</p>
                    </div>
                    
                    <div class="space-y-3.5 text-xs">
                        <div>
                            <div class="flex justify-between text-slate-600 mb-1 font-medium">
                                <span>Rodents (Rats / Mice)</span>
                                <span class="font-bold text-slate-800">42% (61 Jobs)</span>
                            </div>
                            <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                <div class="bg-purple-600 h-full w-[42%] rounded-full"></div>
                            </div>
                        </div>

                        <div>
                            <div class="flex justify-between text-slate-600 mb-1 font-medium">
                                <span>Termites</span>
                                <span class="font-bold text-slate-800">28% (41 Jobs)</span>
                            </div>
                            <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                <div class="bg-amber-500 h-full w-[28%] rounded-full"></div>
                            </div>
                        </div>

                        <div>
                            <div class="flex justify-between text-slate-600 mb-1 font-medium">
                                <span>Cockroaches & Ants</span>
                                <span class="font-bold text-slate-800">18% (26 Jobs)</span>
                            </div>
                            <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                <div class="bg-blue-600 h-full w-[18%] rounded-full"></div>
                            </div>
                        </div>

                        <div>
                            <div class="flex justify-between text-slate-600 mb-1 font-medium">
                                <span>Other / Flying Insects</span>
                                <span class="font-bold text-slate-800">12% (18 Jobs)</span>
                            </div>
                            <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                <div class="bg-emerald-600 h-full w-[12%] rounded-full"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chemical Consumption Overview -->
                <div class="bg-white border border-slate-200/80 rounded-xl p-5 space-y-4 shadow-sm">
                    <div class="border-b border-slate-100 pb-3">
                        <h2 class="text-base font-semibold text-slate-900">Top Chemical Consumption</h2>
                        <p class="text-[11px] text-slate-400 mt-0.5">Total chemical volume applied during treatments.</p>
                    </div>

                    <div class="space-y-3.5 text-xs">
                        <div>
                            <div class="flex justify-between text-slate-600 mb-1 font-medium">
                                <span>Termidor HE (Termiticide)</span>
                                <span class="font-bold text-[#007a55] font-mono">12,400 mL</span>
                            </div>
                            <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                <div class="bg-[#007a55] h-full w-[65%] rounded-full"></div>
                            </div>
                        </div>

                        <div>
                            <div class="flex justify-between text-slate-600 mb-1 font-medium">
                                <span>Advion Gel Bait</span>
                                <span class="font-bold text-[#007a55] font-mono">3,200 mL</span>
                            </div>
                            <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                <div class="bg-[#007a55] h-full w-[30%] rounded-full"></div>
                            </div>
                        </div>

                        <div>
                            <div class="flex justify-between text-slate-600 mb-1 font-medium">
                                <span>Contrac Blox</span>
                                <span class="font-bold text-[#007a55] font-mono">2,850 mL / Bait Units</span>
                            </div>
                            <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                <div class="bg-[#007a55] h-full w-[25%] rounded-full"></div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Field Technician Performance Table -->
            <div class="bg-white border border-slate-200/80 rounded-xl p-5 space-y-4 shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Technician Activity Summary</h2>
                        <p class="text-[11px] text-slate-400 mt-0.5">Jobs logged and chemical volumes applied per technician.</p>
                    </div>
                    <span class="bg-emerald-50 text-[#007a55] text-[10px] font-semibold px-2 py-0.5 rounded-full border border-emerald-100">3 Active Staff</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-[10px] uppercase text-slate-400 border-b border-slate-100">
                                <th class="pb-2 font-semibold">Technician Name</th>
                                <th class="pb-2 font-semibold">Assigned Sector</th>
                                <th class="pb-2 font-semibold">Completed Jobs</th>
                                <th class="pb-2 font-semibold">Total Chemical Applied</th>
                                <th class="pb-2 font-semibold text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3 font-bold text-slate-800">Rodel Mamparil</td>
                                <td class="py-3 text-slate-500">Sector 04 (Roxas, Davao City)</td>
                                <td class="py-3 text-slate-700 font-medium">58 Jobs</td>
                                <td class="py-3 text-[#007a55] font-mono font-medium">7,800 mL</td>
                                <td class="py-3 text-right">
                                    <span class="bg-emerald-50 text-[#007a55] border border-emerald-200/60 px-2 py-0.5 rounded text-[10px] font-semibold">Active</span>
                                </td>
                            </tr>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3 font-bold text-slate-800">John Doe</td>
                                <td class="py-3 text-slate-500">Sector 02 (Downtown)</td>
                                <td class="py-3 text-slate-700 font-medium">46 Jobs</td>
                                <td class="py-3 text-[#007a55] font-mono font-medium">5,650 mL</td>
                                <td class="py-3 text-right">
                                    <span class="bg-emerald-50 text-[#007a55] border border-emerald-200/60 px-2 py-0.5 rounded text-[10px] font-semibold">Active</span>
                                </td>
                            </tr>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3 font-bold text-slate-800">Mark Santos</td>
                                <td class="py-3 text-slate-500">Sector 01 (Industrial)</td>
                                <td class="py-3 text-slate-700 font-medium">42 Jobs</td>
                                <td class="py-3 text-[#007a55] font-mono font-medium">5,000 mL</td>
                                <td class="py-3 text-right">
                                    <span class="bg-emerald-50 text-[#007a55] border border-emerald-200/60 px-2 py-0.5 rounded text-[10px] font-semibold">Active</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
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