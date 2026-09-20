<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vermex - Inventory Management</title>
    
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
            
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-200 pb-5">
                <div>
                    <div class="flex items-center gap-2 text-[11px] text-[#007a55] font-semibold tracking-wider uppercase mb-1">
                        <span>Operations</span>
                        <span>•</span>
                        <span>Warehouse & Stock Control</span>
                    </div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Inventory Management</h1>
                    <p class="text-xs text-slate-500 mt-1">Track chemical volumes, trap deployment counts, PPE stock, and technician van allocations.</p>
                </div>
                
                <div class="flex items-center gap-3">
                    <button class="bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 font-medium text-xs px-3.5 py-2 rounded-lg flex items-center gap-2 transition shadow-sm">
                        <i data-lucide="truck" class="w-4 h-4 text-[#007a55]"></i>
                        <span>Van Stock Audit</span>
                    </button>
                    <button class="bg-[#007a55] hover:bg-[#006344] text-white font-medium text-xs px-4 py-2 rounded-lg flex items-center gap-2 transition shadow-sm">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        <span>Add Stock Item</span>
                    </button>
                </div>
            </div>

            <!-- KPI Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white border border-slate-200/80 rounded-xl p-4 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total Chemical Stock</p>
                        <p class="text-2xl font-bold text-slate-900 mt-1">124,500 <span class="text-xs font-normal text-slate-500">mL</span></p>
                        <p class="text-[11px] text-emerald-600 font-medium mt-1">6 Active Formulations</p>
                    </div>
                    <div class="bg-emerald-50 p-2.5 rounded-lg text-[#007a55] border border-emerald-100">
                        <i data-lucide="flask-conical" class="w-5 h-5"></i>
                    </div>
                </div>

                <div class="bg-white border border-slate-200/80 rounded-xl p-4 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Deployed Field Traps</p>
                        <p class="text-2xl font-bold text-slate-900 mt-1">418 <span class="text-xs font-normal text-slate-500">units</span></p>
                        <p class="text-[11px] text-blue-600 font-medium mt-1">Rat Cages, Glue Traps & ILTs</p>
                    </div>
                    <div class="bg-blue-50 p-2.5 rounded-lg text-blue-600 border border-blue-100">
                        <i data-lucide="box" class="w-5 h-5"></i>
                    </div>
                </div>

                <div class="bg-white border border-slate-200/80 rounded-xl p-4 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Low Stock Alerts</p>
                        <p class="text-2xl font-bold text-slate-900 mt-1">2 <span class="text-xs font-normal text-amber-600">items</span></p>
                        <p class="text-[11px] text-amber-600 font-medium mt-1">Below minimum threshold</p>
                    </div>
                    <div class="bg-amber-50 p-2.5 rounded-lg text-amber-600 border border-amber-100">
                        <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                    </div>
                </div>

                <div class="bg-white border border-slate-200/80 rounded-xl p-4 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Expiring Chemicals</p>
                        <p class="text-2xl font-bold text-slate-900 mt-1">1 <span class="text-xs font-normal text-rose-600">batch</span></p>
                        <p class="text-[11px] text-rose-600 font-medium mt-1">Expiring within 60 days</p>
                    </div>
                    <div class="bg-rose-50 p-2.5 rounded-lg text-rose-600 border border-rose-100">
                        <i data-lucide="calendar-x" class="w-5 h-5"></i>
                    </div>
                </div>
            </div>

            <!-- Filters & Search Toolbar -->
            <div class="bg-white border border-slate-200/80 rounded-xl p-4 flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-sm">
                <div class="flex flex-1 items-center gap-3">
                    <div class="relative flex-1 max-w-xs">
                        <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                        <input type="text" placeholder="Search SKU, chemical, or trap type..." class="w-full bg-slate-50 border border-slate-200 text-slate-800 placeholder-slate-400 text-xs rounded-lg pl-9 pr-4 py-2 focus:outline-none focus:border-[#007a55] transition">
                    </div>
                    <select class="bg-slate-50 border border-slate-200 text-slate-600 text-xs rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55] transition">
                        <option value="">All Categories</option>
                        <option value="chemical">Chemicals & Concentrates</option>
                        <option value="traps">Traps & Devices (Cages, Glue, ILT)</option>
                        <option value="ppe">PPE & Safety Gear</option>
                        <option value="equipment">Sprayers & Applicators</option>
                    </select>
                    <select class="bg-slate-50 border border-slate-200 text-slate-600 text-xs rounded-lg px-3 py-2 focus:outline-none focus:border-[#007a55] transition">
                        <option value="">All Locations</option>
                        <option value="warehouse">Main Warehouse (Davao)</option>
                        <option value="van-rodel">Rodel's Service Van</option>
                        <option value="van-john">John's Service Van</option>
                        <option value="deployed">Deployed at Client Sites</option>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <button class="bg-slate-50 hover:bg-slate-100 text-slate-600 text-xs px-3.5 py-2 rounded-lg border border-slate-200 transition flex items-center gap-1.5 font-medium">
                        <i data-lucide="download" class="w-3.5 h-3.5"></i>
                        Export Inventory
                    </button>
                </div>
            </div>

            <!-- Main Inventory Data Table -->
            <div class="bg-white border border-slate-200/80 rounded-xl p-5 space-y-4 shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Stock Items & Device Inventory</h2>
                        <p class="text-[11px] text-slate-400 mt-0.5">Live stock counts synced with field Job Orders and Inspection Reports.</p>
                    </div>
                    <span class="bg-emerald-50 text-[#007a55] text-[10px] font-semibold px-2 py-0.5 rounded-full border border-emerald-100">24 items tracked</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-[10px] uppercase text-slate-400 border-b border-slate-100">
                                <th class="pb-2.5 font-semibold">Item / SKU</th>
                                <th class="pb-2.5 font-semibold">Category</th>
                                <th class="pb-2.5 font-semibold">Available Stock</th>
                                <th class="pb-2.5 font-semibold">Standard Dilution / Usage</th>
                                <th class="pb-2.5 font-semibold">Status</th>
                                <th class="pb-2.5 font-semibold">Primary Location</th>
                                <th class="pb-2.5 font-semibold text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            
                            <!-- Chemical 1 -->
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3">
                                    <p class="font-bold text-slate-800">Termidor HE Termiticide</p>
                                    <p class="text-[10px] text-slate-400 font-mono">CHM-1048 • Lot #TH-2026-A</p>
                                </td>
                                <td class="py-3"><span class="bg-emerald-50 text-[#007a55] border border-emerald-200/60 px-2 py-0.5 rounded text-[10px] font-medium">Chemical</span></td>
                                <td class="py-3">
                                    <p class="font-bold text-slate-800 font-mono">42,000 mL <span class="text-[10px] font-normal text-slate-400">(42 L)</span></p>
                                    <p class="text-[10px] text-slate-400">Deducted via Job Orders</p>
                                </td>
                                <td class="py-3 text-slate-700 font-mono">20 mL / 1 L water</td>
                                <td class="py-3"><span class="bg-emerald-50 text-[#007a55] border border-emerald-200/60 px-2 py-0.5 rounded text-[10px] font-semibold">In Stock</span></td>
                                <td class="py-3 text-slate-700">Chemical Cage A</td>
                                <td class="py-3 text-right">
                                    <button class="text-[#007a55] hover:text-[#006344] bg-emerald-50 hover:bg-emerald-100 border border-emerald-100 px-2.5 py-1 rounded text-[11px] font-semibold transition">Adjust</button>
                                </td>
                            </tr>

                            <!-- Chemical 2 -->
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3">
                                    <p class="font-bold text-slate-800">Advion Cockroach Gel Bait</p>
                                    <p class="text-[10px] text-slate-400 font-mono">CHM-1182 • Lot #ADV-9921</p>
                                </td>
                                <td class="py-3"><span class="bg-emerald-50 text-[#007a55] border border-emerald-200/60 px-2 py-0.5 rounded text-[10px] font-medium">Chemical</span></td>
                                <td class="py-3">
                                    <p class="font-bold text-amber-600 font-mono">4 x 30g tubes</p>
                                    <p class="text-[10px] text-amber-600/80">Reorder point: 10 tubes</p>
                                </td>
                                <td class="py-3 text-slate-700 font-mono">Spot treatment (0.5g)</td>
                                <td class="py-3"><span class="bg-amber-50 text-amber-700 border border-amber-200/60 px-2 py-0.5 rounded text-[10px] font-semibold">Low Stock</span></td>
                                <td class="py-3 text-slate-700">Chemical Cage B</td>
                                <td class="py-3 text-right">
                                    <button class="text-[#007a55] hover:text-[#006344] bg-emerald-50 hover:bg-emerald-100 border border-emerald-100 px-2.5 py-1 rounded text-[11px] font-semibold transition">Adjust</button>
                                </td>
                            </tr>

                            <!-- Trap Item 1 -->
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3">
                                    <p class="font-bold text-slate-800">Mechanical Rat Cages</p>
                                    <p class="text-[10px] text-slate-400 font-mono">TRP-2011 • Heavy Duty Steel</p>
                                </td>
                                <td class="py-3"><span class="bg-blue-50 text-blue-700 border border-blue-200/60 px-2 py-0.5 rounded text-[10px] font-medium">Traps & Devices</span></td>
                                <td class="py-3">
                                    <p class="font-bold text-slate-800 font-mono">18 Warehouse <span class="text-[10px] font-normal text-slate-400">(84 Deployed)</span></p>
                                    <p class="text-[10px] text-slate-400">Tracked in Inspection Slips</p>
                                </td>
                                <td class="py-3 text-slate-700">Reusable Station</td>
                                <td class="py-3"><span class="bg-emerald-50 text-[#007a55] border border-emerald-200/60 px-2 py-0.5 rounded text-[10px] font-semibold">In Stock</span></td>
                                <td class="py-3 text-slate-700">Rack 02 • Bay 1</td>
                                <td class="py-3 text-right">
                                    <button class="text-[#007a55] hover:text-[#006344] bg-emerald-50 hover:bg-emerald-100 border border-emerald-100 px-2.5 py-1 rounded text-[11px] font-semibold transition">Adjust</button>
                                </td>
                            </tr>

                            <!-- Trap Item 2 -->
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3">
                                    <p class="font-bold text-slate-800">Rodent Glue Board Traps</p>
                                    <p class="text-[10px] text-slate-400 font-mono">TRP-2167 • Heavy Duty Glue</p>
                                </td>
                                <td class="py-3"><span class="bg-blue-50 text-blue-700 border border-blue-200/60 px-2 py-0.5 rounded text-[10px] font-medium">Traps & Devices</span></td>
                                <td class="py-3">
                                    <p class="font-bold text-rose-600 font-mono">0 Packs in Warehouse</p>
                                    <p class="text-[10px] text-rose-600/80">2 units on Rodel's Van</p>
                                </td>
                                <td class="py-3 text-slate-700">Single-use Disposable</td>
                                <td class="py-3"><span class="bg-rose-50 text-rose-700 border border-rose-200/60 px-2 py-0.5 rounded text-[10px] font-semibold">Out of Stock</span></td>
                                <td class="py-3 text-slate-700">Rack 04 • Bay 1</td>
                                <td class="py-3 text-right">
                                    <button class="text-[#007a55] hover:text-[#006344] bg-emerald-50 hover:bg-emerald-100 border border-emerald-100 px-2.5 py-1 rounded text-[11px] font-semibold transition">Adjust</button>
                                </td>
                            </tr>

                            <!-- Device Item 3 -->
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3">
                                    <p class="font-bold text-slate-800">Insect Light Trap (ILT) Units</p>
                                    <p class="text-[10px] text-slate-400 font-mono">DEV-3002 • UV Fly Trap</p>
                                </td>
                                <td class="py-3"><span class="bg-blue-50 text-blue-700 border border-blue-200/60 px-2 py-0.5 rounded text-[10px] font-medium">Traps & Devices</span></td>
                                <td class="py-3">
                                    <p class="font-bold text-slate-800 font-mono">6 Warehouse <span class="text-[10px] font-normal text-slate-400">(42 Deployed)</span></p>
                                    <p class="text-[10px] text-slate-400">Commercial sites</p>
                                </td>
                                <td class="py-3 text-slate-700">220V Electric Hardware</td>
                                <td class="py-3"><span class="bg-emerald-50 text-[#007a55] border border-emerald-200/60 px-2 py-0.5 rounded text-[10px] font-semibold">In Stock</span></td>
                                <td class="py-3 text-slate-700">Equipment Shelf C</td>
                                <td class="py-3 text-right">
                                    <button class="text-[#007a55] hover:text-[#006344] bg-emerald-50 hover:bg-emerald-100 border border-emerald-100 px-2.5 py-1 rounded text-[11px] font-semibold transition">Adjust</button>
                                </td>
                            </tr>

                            <!-- PPE Item -->
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3">
                                    <p class="font-bold text-slate-800">Nitrile Gloves (XL - 100/box)</p>
                                    <p class="text-[10px] text-slate-400 font-mono">PPE-5089 • Chemical Grade</p>
                                </td>
                                <td class="py-3"><span class="bg-purple-50 text-purple-700 border border-purple-200/60 px-2 py-0.5 rounded text-[10px] font-medium">PPE & Safety</span></td>
                                <td class="py-3">
                                    <p class="font-bold text-slate-800 font-mono">24 Boxes</p>
                                    <p class="text-[10px] text-slate-400">Distributed per technician</p>
                                </td>
                                <td class="py-3 text-slate-700">Mandatory PPE</td>
                                <td class="py-3"><span class="bg-emerald-50 text-[#007a55] border border-emerald-200/60 px-2 py-0.5 rounded text-[10px] font-semibold">In Stock</span></td>
                                <td class="py-3 text-slate-700">PPE Cabinet 2</td>
                                <td class="py-3 text-right">
                                    <button class="text-[#007a55] hover:text-[#006344] bg-emerald-50 hover:bg-emerald-100 border border-emerald-100 px-2.5 py-1 rounded text-[11px] font-semibold transition">Adjust</button>
                                </td>
                            </tr>

                        </tbody>
                    </table>
                </div>

                <!-- Table Pagination -->
                <div class="flex items-center justify-between border-t border-slate-100 pt-3 text-xs text-slate-500">
                    <p>Showing 1–6 of 24 items</p>
                    <div class="flex items-center gap-1">
                        <button class="px-2.5 py-1 rounded bg-slate-50 border border-slate-200 text-slate-600 hover:bg-slate-100 disabled:opacity-50 font-medium">Prev</button>
                        <button class="px-2.5 py-1 rounded bg-[#007a55] text-white font-semibold">1</button>
                        <button class="px-2.5 py-1 rounded bg-slate-50 border border-slate-200 text-slate-600 hover:bg-slate-100 font-medium">2</button>
                        <button class="px-2.5 py-1 rounded bg-slate-50 border border-slate-200 text-slate-600 hover:bg-slate-100 font-medium">Next</button>
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