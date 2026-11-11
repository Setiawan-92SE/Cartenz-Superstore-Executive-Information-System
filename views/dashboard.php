<?php
if (!isset($user) || !is_array($user)) {
    header('Location: ../login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Superstore Executive Information System (SIE) - Cartenz</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Inter Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#f0f7ff',
                            100: '#e0effe',
                            500: '#0284c7',
                            600: '#0369a1',
                            700: '#075985',
                            900: '#0c4a6e',
                        }
                    }
                }
            }
        }
    </script>
    <!-- CSS -->
    <link rel="stylesheet" href="style.css">
</head>
<body class="bg-slate-900 text-slate-100 font-sans antialiased min-h-screen flex flex-col selection:bg-sky-500 selection:text-white">

    <!-- Top Executive Header -->
    <header class="bg-slate-800/80 backdrop-blur border-b border-slate-700/60 sticky top-0 z-40 px-6 py-3.5 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <img src="public/logo.png" alt="Cartenz Superstore Logo" class="w-10 h-10 object-contain rounded-xl">
            <div>
                <h1 class="font-bold text-lg text-white tracking-tight">Cartenz Superstore <span class="text-sky-400 font-normal text-sm">| Executive Information System</span></h1>
                <p class="text-[11px] text-slate-400">Business Intelligence & Profitability Analytics</p>
            </div>
        </div>
        <div class="flex items-center gap-2 sm:gap-3">
            <div id="liveIndicator" class="hidden sm:flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-[11px] font-medium text-emerald-400 transition">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                Live
                <span id="lastSyncLabel" class="text-emerald-300/70 font-normal">• —</span>
            </div>
            <div class="flex items-center gap-2 px-2.5 py-1.5 rounded-xl border border-slate-700/60 bg-slate-900/40">
                <span class="w-8 h-8 rounded-full bg-sky-500/20 text-sky-400 flex items-center justify-center">
                    <i data-lucide="user-round" class="w-4 h-4"></i>
                </span>
                <div class="hidden sm:block leading-tight">
                    <p id="currentUserName" class="text-xs font-semibold text-slate-200"><?= htmlspecialchars($user['fullName'] ?? $user['username'] ?? 'https://github.com/Setiawan-92SE/Cartenz-Superstore-Executive-Information-Systemistrator', ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="text-[10px] text-slate-500">Administrator</p>
                </div>
            </div>
            <button onclick="logout(event)" class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-medium text-rose-300 hover:bg-rose-500/10 border border-rose-500/20 transition" title="Keluar">
                <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
                <span class="hidden sm:inline">Logout</span>
            </button>
        </div>
    </header>

    <!-- Main Workspace -->
    <div class="flex-1 flex overflow-hidden">

        <!-- Sidebar Navigation -->
        <aside class="w-64 bg-slate-800/40 border-r border-slate-800 p-4 hidden md:flex flex-col gap-6 shrink-0">
            <div>
                <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-2 px-3">Menus</p>
                <nav class="space-y-1">
                    <button onclick="switchTab('dashboard')" id="nav-dashboard" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium bg-sky-500/10 text-sky-400 border border-sky-500/20 transition">
                        <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Summary
                    </button>
                    <button onclick="switchTab('drilldown')" id="nav-drilldown" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-slate-400 hover:bg-slate-800/80 hover:text-slate-200 transition">
                        <i data-lucide="network" class="w-4 h-4"></i> Drill-Down
                    </button>
                    <button onclick="switchTab('whatif')" id="nav-whatif" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-slate-400 hover:bg-slate-800/80 hover:text-slate-200 transition">
                        <i data-lucide="sliders-horizontal" class="w-4 h-4"></i> What-If Simulator
                    </button>
                    <button onclick="switchTab('alerts')" id="nav-alerts" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-slate-400 hover:bg-slate-800/80 hover:text-slate-200 transition">
                        <i data-lucide="shield-alert" class="w-4 h-4"></i> Risk & Alerts
                        <span id="navAlertBadge" class="ml-auto bg-rose-500/20 text-rose-400 text-[10px] px-1.5 py-0.5 rounded-full font-bold">—</span>
                    </button>
                    <button onclick="switchTab('etl')" id="nav-etl" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-slate-400 hover:bg-slate-800/80 hover:text-slate-200 transition">
                        <i data-lucide="database" class="w-4 h-4"></i> Add Transaction
                    </button>
                </nav>
            </div>

            <!-- Group Member -->
            <div class="mt-auto bg-slate-900/80 border border-slate-800 rounded-xl p-3.5 text-xs text-slate-400">
                <p class="font-medium text-slate-300 mb-1">Cartenz SIE</p>
                <p class="text-[11px]">Sulthan V.O.R • Devano A.H • Ganesha S</p>
            </div>
        </aside>

        <!-- Main Content Scrollable Area -->
        <main class="flex-1 overflow-y-auto p-4 md:p-6 space-y-6">

            <!-- Connection / data error banner -->
            <div id="dataErrorBanner" class="hidden items-start gap-3 bg-rose-500/10 border border-rose-500/30 rounded-2xl px-4 py-3 text-xs text-rose-300">
                <i data-lucide="wifi-off" class="w-4 h-4 shrink-0 mt-0.5"></i>
                <div>
                    <p class="font-semibold text-rose-200">Koneksi data bermasalah</p>
                    <p data-error-text class="text-rose-300/90 mt-0.5">—</p>
                </div>
            </div>

            <!-- TOP KPI CARDS (Executive Metrics) -->
            <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <!-- KPI 1: Sales -->
                <div class="bg-slate-800/60 border border-slate-700/60 p-4 rounded-2xl relative overflow-hidden group hover:border-slate-600 transition">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-medium text-slate-400">Total Sales</span>
                        <div class="p-2 bg-blue-500/10 text-blue-400 rounded-lg">
                            <i data-lucide="dollar-sign" class="w-4 h-4"></i>
                        </div>
                    </div>
                    <h3 id="kpi-sales" class="text-2xl font-extrabold text-white">$2,297,201</h3>
                    <div class="absolute -right-2 -bottom-2 w-16 h-16 bg-blue-500/5 rounded-full blur-xl group-hover:bg-blue-500/10 transition"></div>
                </div>

                <!-- KPI 2: Net Profit -->
                <div class="bg-slate-800/60 border border-slate-700/60 p-4 rounded-2xl relative overflow-hidden group hover:border-slate-600 transition">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-medium text-slate-400">Net Profit</span>
                        <div class="p-2 bg-emerald-500/10 text-emerald-400 rounded-lg">
                            <i data-lucide="wallet" class="w-4 h-4"></i>
                        </div>
                    </div>
                    <h3 id="kpi-profit" class="text-2xl font-extrabold text-emerald-400"></h3>
                    <div class="absolute -right-2 -bottom-2 w-16 h-16 bg-emerald-500/5 rounded-full blur-xl group-hover:bg-emerald-500/10 transition"></div>
                </div>

                <!-- KPI 3: Profit Margin -->
                <div class="bg-slate-800/60 border border-slate-700/60 p-4 rounded-2xl relative overflow-hidden group hover:border-slate-600 transition">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-medium text-slate-400">Profit Margin</span>
                        <div class="p-2 bg-sky-500/10 text-sky-400 rounded-lg">
                            <i data-lucide="pie-chart" class="w-4 h-4"></i>
                        </div>
                    </div>
                    <h3 id="kpi-margin" class="text-2xl font-extrabold text-sky-400"></h3>
                    <p class="text-[11px] text-amber-400 flex items-center gap-1 mt-1">
                        <i data-lucide="alert-circle" class="w-3 h-3"></i> Target: 15.00%
                    </p>
                </div>

                <!-- KPI 4: Loss Transaction Rate -->
                <div class="bg-slate-800/60 border border-rose-500/30 p-4 rounded-2xl relative overflow-hidden bg-rose-950/10 group hover:border-rose-500/50 transition">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-medium text-rose-300">Loss Transaction Rate</span>
                        <div class="p-2 bg-rose-500/20 text-rose-400 rounded-lg">
                            <i data-lucide="trending-down" class="w-4 h-4"></i>
                        </div>
                    </div>
                    <h3 id="kpi-loss-rate" class="text-2xl font-extrabold text-rose-400"></h3>
                    <p id="kpi-loss-subtext" class="text-[11px] text-rose-300 mt-1"></p>
                </div>

                <!-- KPI 5: Avg Discount Rate -->
                <div class="bg-slate-800/60 border border-slate-700/60 p-4 rounded-2xl relative overflow-hidden group hover:border-slate-600 transition">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-medium text-slate-400">Avg Discount</span>
                        <div class="p-2 bg-amber-500/10 text-amber-400 rounded-lg">
                            <i data-lucide="percent" class="w-4 h-4"></i>
                        </div>
                    </div>
                    <h3 id="kpi-discount" class="text-2xl font-extrabold text-amber-400"></h3>
                </div>
            </section>

            <!-- TAB 1: EXECUTIVE VIEW DASHBOARD -->
            <div id="tab-dashboard" class="space-y-6">

                <!-- Regional & Category Overview Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Sales vs Profit by Region Chart -->
                    <div class="lg:col-span-2 bg-slate-800/60 border border-slate-700/60 rounded-2xl p-5">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-semibold text-base text-white">Regional Performance Matrix</h3>
                        </div>
                        <div class="h-72">
                            <canvas id="regionOverviewChart"></canvas>
                        </div>
                    </div>

                    <!-- Category Profitability Share -->
                    <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl p-5">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-semibold text-base text-white">Profit by Category</h3>
                        </div>
                        <div class="h-60 flex items-center justify-center">
                            <canvas id="categoryPieChart"></canvas>
                        </div>
                        <div id="categoryInsight" class="mt-3 text-xs text-slate-400 text-center">
                            —
                        </div>
                    </div>
                </div>

                <!-- Sub-Category Loss Leakage Leaderboard -->
                <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl p-5">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
                        <h3 class="font-semibold text-base text-white">Sub-Category Profitability</h3>
                        <button onclick="switchTab('drilldown')" class="text-xs bg-sky-600 hover:bg-sky-500 text-white font-medium px-3 py-1.5 rounded-lg transition flex items-center gap-1.5 self-start sm:self-auto">
                            Drill-Down Analysis <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>
                    <div class="h-80">
                        <canvas id="subCatBarChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- TAB 2: DRILL-DOWN ANALYSIS ENGINE (CORE REQUIREMENT) -->
            <div id="tab-drilldown" class="hidden space-y-6">
                <!-- Breadcrumb Navigation Bar -->
                <div class="bg-slate-800/90 border border-slate-700/80 rounded-2xl p-4 flex flex-wrap items-center justify-between gap-3 shadow-lg">
                    <div class="flex items-center gap-2 text-sm font-medium">
                        <span class="text-slate-400">Hierarchical Drill Path:</span>
                        <div id="breadcrumbPath" class="flex items-center gap-2 text-sky-400">
                            <!-- Populated dynamically -->
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button id="btnDrillUp" onclick="drillUp()" class="bg-slate-700 hover:bg-slate-600 text-slate-200 text-xs px-3 py-1.5 rounded-lg transition flex items-center gap-1 disabled:opacity-40 disabled:cursor-not-allowed">
                            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Drill Up (Back)
                        </button>
                        <button onclick="resetDrillDown()" class="bg-slate-700/60 hover:bg-slate-700 text-slate-300 text-xs px-2.5 py-1.5 rounded-lg transition">
                            Reset to Global
                        </button>
                    </div>
                </div>

                <!-- Active Level Banner -->
                <div class="bg-gradient-to-r from-sky-900/40 via-slate-800 to-indigo-900/40 border border-sky-500/30 rounded-2xl p-5">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <span id="drillLevelBadge" class="px-2.5 py-1 bg-sky-500/20 text-sky-300 font-bold text-xs rounded-full uppercase tracking-wider border border-sky-500/30 mb-2 inline-block">
                            </span>
                            <h2 id="drillLevelTitle" class="text-xl font-bold text-white"></h2>
                            <p id="drillLevelDesc" class="text-xs text-slate-300 mt-0.5"></p>
                        </div>
                        <div class="flex gap-4 text-left border-t md:border-t-0 border-slate-700/60 pt-3 md:pt-0">
                            <div>
                                <p class="text-[10px] text-slate-400 uppercase">Current Sales</p>
                                <p id="drillSubtotalSales" class="text-lg font-bold text-white"></p>
                            </div>
                            <div class="border-r border-slate-700 h-8 my-auto"></div>
                            <div>
                                <p class="text-[10px] text-slate-400 uppercase">Current Profit</p>
                                <p id="drillSubtotalProfit" class="text-lg font-bold text-emerald-400"></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Dynamic Drill-Down Visual Canvas -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Dynamic Drill Chart -->
                    <div class="lg:col-span-2 bg-slate-800/60 border border-slate-700/60 rounded-2xl p-5">
                        <div class="flex items-center justify-between mb-4">
                            <h3 id="drillChartHeader" class="font-semibold text-sm text-white">Visual Breakdown (Click bar to Zoom In)</h3>
                        </div>
                        <div class="h-80">
                            <canvas id="drillDownCanvas"></canvas>
                        </div>
                    </div>

                    <!-- Level Specific Breakdown Items Cards -->
                    <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl p-5 flex flex-col">
                        <h3 class="font-semibold text-sm text-white mb-3">Interactive Nodes</h3>
                        <p class="text-xs text-slate-400 mb-3">Select a node below to dive deeper</p>
                        <div id="drillNodesList" class="space-y-2 overflow-y-auto max-h-80 pr-1 flex-1">
                            <!-- Dynamic Cards Inserted Here -->
                        </div>
                    </div>
                </div>

                <!-- Detail Transaction Level Table (Level 4 Root Cause) -->
                <div id="drillRootCauseSection" class="bg-slate-800/60 border border-slate-700/60 rounded-2xl p-5 hidden">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="font-semibold text-base text-white">Level 4: Root Cause Transaction Audit</h3>
                            <p class="text-xs text-slate-400">Examining raw transactions where discount exceeds threshold resulting in negative profit leaks</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-900/80 text-slate-400 border-b border-slate-700">
                                    <th class="p-3">Order/Customer</th>
                                    <th class="p-3">Region & State</th>
                                    <th class="p-3">Sub-Category</th>
                                    <th class="p-3">Sales</th>
                                    <th class="p-3">Discount</th>
                                    <th class="p-3">Profit</th>
                                </tr>
                            </thead>
                            <tbody id="drillTxTableBody" class="divide-y divide-slate-800 text-slate-300">
                                <!-- Populated dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 3: WHAT-IF ANALYSIS SIMULATOR -->
            <div id="tab-whatif" class="hidden space-y-6">
                <div class="bg-slate-800/80 border border-slate-700 rounded-2xl p-6">
                    <div class="max-w-3xl">
                        <h2 class="text-xl font-bold text-white mt-2">What-If Analysis</h2>
                        <p class="text-xs text-slate-300 mt-1">Simulate corporate profit recovery by sliding the maximum discount slider.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
                        <!-- Slider Controls -->
                        <div class="md:col-span-2 bg-slate-900/80 border border-slate-800 p-5 rounded-xl space-y-5">
                            <div>
                                <div class="flex justify-between items-center mb-2">
                                    <label class="text-xs font-semibold text-slate-200">Maximum Discount Cap (%):</label>
                                    <span id="sliderCapVal" class="text-sm font-extrabold text-sky-400 bg-sky-500/10 px-3 py-1 rounded-lg border border-sky-500/20">20% Cap</span>
                                </div>
                                <input type="range" id="discountCapSlider" min="0" max="40" step="5" value="20" oninput="runWhatIfSimulation()" class="w-full h-2 bg-slate-700 rounded-lg appearance-none cursor-pointer accent-sky-500">
                                <div class="flex justify-between text-[10px] text-slate-400 mt-1">
                                    <span>0% (No Discount)</span>
                                    <span>10%</span>
                                    <span>20%</span>
                                    <span>30%</span>
                                    <span>40% (Current Baseline)</span>
                                </div>
                            </div>

                        </div>

                        <!-- Simulation Results Metrics -->
                        <div class="bg-gradient-to-b from-slate-900 to-sky-950/40 border border-sky-500/30 p-5 rounded-xl flex flex-col justify-between">
                            <div>
                                <p class="text-xs text-slate-400 font-medium">Projected Net Profit Increase</p>
                                <h3 id="whatifProfitGain" class="text-3xl font-extrabold text-emerald-400 mt-1">+$42,150</h3>
                                <p class="text-[11px] text-emerald-400/80 mt-0.5">Recovered from discount leaks</p>
                            </div>

                            <div class="space-y-2 py-3 border-y border-slate-800/80 my-3 text-xs">
                                <div class="flex justify-between">
                                    <span class="text-slate-400">Baseline Profit:</span>
                                    <span id="whatifBaselineProfit" class="text-slate-200 font-semibold">$0</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-400">Simulated New Profit:</span>
                                    <span id="whatifNewProfit" class="text-emerald-400 font-bold">$328,547</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-400">New Profit Margin:</span>
                                    <span id="whatifNewMargin" class="text-sky-400 font-bold">14.30%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Simulation Comparison Visual Chart -->
                <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl p-5">
                    <h3 class="font-semibold text-sm text-white mb-3">Actual vs Simulated Sub-Category Profitability Comparison</h3>
                    <div class="h-72">
                        <canvas id="whatIfChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- TAB 4: RISK & ALERTS RADAR -->
            <div id="tab-alerts" class="hidden space-y-6">
                <div class="bg-rose-950/20 border border-rose-500/30 rounded-2xl p-5">
                    <div class="flex items-center gap-3">
                        <div class="p-3 bg-rose-500/20 text-rose-400 rounded-xl">
                            <i data-lucide="shield-alert" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-white">Profitability Leak & Loss Early Warning System</h2>
                            <p class="text-xs text-slate-300">Automated radar detecting regions and products with profit margins below critical tolerance limit (-5%).</p>
                        </div>
                    </div>
                </div>

                <!-- Alert Cards Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Critical Alert 1 -->
                    <div class="bg-slate-800/80 border-l-4 border-l-rose-500 border-slate-700 p-4 rounded-xl flex items-start justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="bg-rose-500/20 text-rose-400 text-[10px] font-bold px-2 py-0.5 rounded">CRITICAL LOSS</span>
                                <h4 class="font-bold text-white text-sm">Tables (Central Region)</h4>
                            </div>
                            <p class="text-xs text-slate-300 mt-1">Total Sales: $39,154 | Net Loss: <span class="text-rose-400 font-bold">-$6,106</span></p>
                            <p class="text-[11px] text-slate-400 mt-2">Root Cause: 62% of transactions had discount >30%. Margin is -15.6%.</p>
                        </div>
                        <button onclick="drillToSpecific('Central', 'Furniture', 'Tables')" class="text-xs text-sky-400 hover:underline shrink-0">Investigate</button>
                    </div>

                    <!-- Critical Alert 2 -->
                    <div class="bg-slate-800/80 border-l-4 border-l-rose-500 border-slate-700 p-4 rounded-xl flex items-start justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="bg-rose-500/20 text-rose-400 text-[10px] font-bold px-2 py-0.5 rounded">HIGH LOSS</span>
                                <h4 class="font-bold text-white text-sm">Bookcases (East Region)</h4>
                            </div>
                            <p class="text-xs text-slate-300 mt-1">Total Sales: $43,828 | Net Loss: <span class="text-rose-400 font-bold">-$3,473</span></p>
                            <p class="text-[11px] text-slate-400 mt-2">Root Cause: Uncontrolled promotional bundling in Consumer Segment.</p>
                        </div>
                        <button onclick="drillToSpecific('East', 'Furniture', 'Bookcases')" class="text-xs text-sky-400 hover:underline shrink-0">Investigate</button>
                    </div>
                </div>
            </div>

            <!-- TAB 5: DATA MANAGEMENT & ETL -->
            <div id="tab-etl" class="hidden space-y-6">
                <!-- ETL status cards -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-slate-800/60 border border-slate-700/60 p-4 rounded-2xl">
                        <p class="text-[11px] uppercase tracking-wider text-slate-400">ETL Status</p>
                        <p id="etlStatusBadge" class="text-lg font-bold text-slate-300 mt-1">Loading…</p>
                        <p id="etlLastRun" class="text-[11px] text-slate-500 mt-1">—</p>
                    </div>
                    <div class="bg-slate-800/60 border border-slate-700/60 p-4 rounded-2xl">
                        <p class="text-[11px] uppercase tracking-wider text-slate-400">Source Rows</p>
                        <p id="etlSourceRows" class="text-lg font-bold text-white mt-1">—</p>
                        <p class="text-[11px] text-slate-500 mt-1">fact_sales</p>
                    </div>
                    <div class="bg-slate-800/60 border border-slate-700/60 p-4 rounded-2xl">
                        <p class="text-[11px] uppercase tracking-wider text-slate-400">Warehouse Rows</p>
                        <p id="etlWarehouseRows" class="text-lg font-bold text-sky-400 mt-1">—</p>
                        <p class="text-[11px] text-slate-500 mt-1">dw_sales_summary</p>
                    </div>
                    <div class="bg-slate-800/60 border border-slate-700/60 p-4 rounded-2xl">
                        <p class="text-[11px] uppercase tracking-wider text-slate-400">Rejected Rows</p>
                        <p id="etlRejected" class="text-lg font-bold text-amber-400 mt-1">—</p>
                        <p id="etlDuration" class="text-[11px] text-slate-500 mt-1">—</p>
                    </div>
                </div>

                <!-- Pipeline information -->
                <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl p-5 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h3 class="font-semibold text-base text-white">ETL Pipeline: Extract → Transform → Load</h3>
                        <p class="text-xs text-slate-400 mt-1">Every create/update/delete automatically rebuild the warehouse. Use "Run ETL" button in case of automatic sync doesn't work</p>
                        <p id="etlFingerprint" class="text-[10px] text-slate-500 mt-2 font-mono break-all">—</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button onclick="refreshEtlWorkspace()" class="flex items-center gap-2 bg-slate-700 hover:bg-slate-600 text-slate-200 text-xs font-medium px-3 py-2 rounded-xl transition">
                            <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i> Refresh
                        </button>
                        <button onclick="runEtlNow()" id="btnRunEtl" class="flex items-center gap-2 bg-sky-600 hover:bg-sky-500 text-white text-xs font-semibold px-3 py-2 rounded-xl transition shadow-lg shadow-sky-900/30">
                            <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i> Run ETL
                        </button>
                    </div>
                </div>

                <!-- Transaction CRUD -->
                <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl p-5">
                    <div class="flex flex-col xl:flex-row xl:items-end justify-between gap-3 mb-4">
                        <div>
                            <h3 class="font-semibold text-base text-white">Transaction CRUD</h3>
                            <p class="text-xs text-slate-400">Managing <span class="font-mono text-slate-300">fact_sales</span> rows. Any changes are synced with the dashboard.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <input id="txSearch" type="search" placeholder="Find ID / location / sub-category"
                                   class="bg-slate-900/70 border border-slate-700 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 text-xs text-slate-100 placeholder-slate-500 rounded-xl px-3 py-2 outline-none transition w-52">
                            <select id="filterRegion" class="bg-slate-900/70 border border-slate-700 focus:border-sky-500 text-xs text-slate-200 rounded-xl px-3 py-2 outline-none transition"></select>
                            <select id="filterCategory" class="bg-slate-900/70 border border-slate-700 focus:border-sky-500 text-xs text-slate-200 rounded-xl px-3 py-2 outline-none transition"></select>
                            <select id="filterProfit" class="bg-slate-900/70 border border-slate-700 focus:border-sky-500 text-xs text-slate-200 rounded-xl px-3 py-2 outline-none transition">
                                <option value="">All Profit</option>
                                <option value="loss">Loss Only</option>
                                <option value="profit">Profit Only</option>
                            </select>
                            <button onclick="resetTxFilters()" class="bg-slate-700 hover:bg-slate-600 text-slate-200 text-xs font-medium px-3 py-2 rounded-xl transition">Reset</button>
                            <button onclick="openTransactionModal()" class="flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold px-3 py-2 rounded-xl transition shadow-lg shadow-emerald-900/30">
                                <i data-lucide="plus" class="w-3.5 h-3.5"></i> Transaction
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-900/80 text-slate-400 border-b border-slate-700">
                                    <th class="p-3">ID / Ship</th>
                                    <th class="p-3">Region / Location</th>
                                    <th class="p-3">Category / Sub</th>
                                    <th class="p-3 text-right">Sales</th>
                                    <th class="p-3 text-right">Qty</th>
                                    <th class="p-3 text-right">Discount</th>
                                    <th class="p-3 text-right">Profit</th>
                                    <th class="p-3 text-right">Status</th>
                                    <th class="p-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="txTableBody" class="divide-y divide-slate-800 text-slate-300">
                                <tr><td colspan="9" class="p-6 text-center text-slate-400">Memuat transaksi…</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mt-4">
                        <p id="txPaginationInfo" class="text-[11px] text-slate-400">—</p>
                        <div class="flex gap-2">
                            <button id="txPrev" onclick="changeTxPage(-1)" class="bg-slate-700 hover:bg-slate-600 text-slate-200 text-xs font-medium px-3 py-1.5 rounded-lg transition disabled:opacity-40 disabled:cursor-not-allowed">Prev</button>
                            <button id="txNext" onclick="changeTxPage(1)" class="bg-slate-700 hover:bg-slate-600 text-slate-200 text-xs font-medium px-3 py-1.5 rounded-lg transition disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <!-- Notification Modal for What-If Apply -->
    <div id="modalToast" class="fixed bottom-5 right-5 bg-emerald-600 text-white text-xs px-4 py-3 rounded-xl shadow-xl flex items-center gap-3 translate-y-20 opacity-0 transition-all duration-300 z-50">
        <i data-lucide="check-circle-2" class="w-5 h-5"></i>
        <div>
            <p class="font-bold">Strategy Policy Updated</p>
            <p class="text-[11px] opacity-90">Discount cap applied to executive forecasting models.</p>
        </div>
    </div>

    <!-- Global toast for CRUD/ETL feedback -->
    <div id="appToast" class="fixed bottom-5 left-1/2 -translate-x-1/2 z-[60] bg-emerald-600 text-white text-xs px-4 py-3 rounded-xl shadow-xl flex items-center gap-3 translate-y-24 opacity-0 pointer-events-none transition-all duration-300">
        <i data-lucide="check-circle-2" class="w-5 h-5"></i>
        <p id="appToastText" class="font-medium">—</p>
    </div>

    <!-- Transaction CRUD modal -->
    <div id="txModal" class="hidden fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm items-center justify-center p-4">
        <div class="bg-slate-800 border border-slate-700 rounded-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto shadow-2xl">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-700">
                <div>
                    <h3 id="txModalTitle" class="font-semibold text-white">Tambah Transaksi</h3>
                    <p class="text-[11px] text-slate-400 mt-0.5">Simpan perubahan untuk memicu ETL otomatis.</p>
                </div>
                <button onclick="closeTransactionModal()" class="p-2 rounded-lg hover:bg-slate-700 text-slate-400 hover:text-white transition" aria-label="Tutup">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <form id="txForm" onsubmit="submitTransaction(event)" class="p-6 space-y-4">
                <input type="hidden" id="txId">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-medium text-slate-300 mb-1.5">Region</label>
                        <select id="txRegion" onchange="handleTxRegionChange()" required
                                class="w-full bg-slate-900/70 border border-slate-700 focus:border-sky-500 text-xs text-slate-200 rounded-xl px-3 py-2.5 outline-none transition"></select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-slate-300 mb-1.5">Lokasi (State / City)</label>
                        <select id="txLocation" required
                                class="w-full bg-slate-900/70 border border-slate-700 focus:border-sky-500 text-xs text-slate-200 rounded-xl px-3 py-2.5 outline-none transition"></select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-slate-300 mb-1.5">Kategori</label>
                        <select id="txCategory" onchange="handleTxCategoryChange()" required
                                class="w-full bg-slate-900/70 border border-slate-700 focus:border-sky-500 text-xs text-slate-200 rounded-xl px-3 py-2.5 outline-none transition"></select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-slate-300 mb-1.5">Sub-Kategori</label>
                        <select id="txSubcategory" required
                                class="w-full bg-slate-900/70 border border-slate-700 focus:border-sky-500 text-xs text-slate-200 rounded-xl px-3 py-2.5 outline-none transition"></select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-slate-300 mb-1.5">Ship Mode</label>
                        <select id="txShipMode" required
                                class="w-full bg-slate-900/70 border border-slate-700 focus:border-sky-500 text-xs text-slate-200 rounded-xl px-3 py-2.5 outline-none transition"></select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-slate-300 mb-1.5">Segment</label>
                        <select id="txSegment" required
                                class="w-full bg-slate-900/70 border border-slate-700 focus:border-sky-500 text-xs text-slate-200 rounded-xl px-3 py-2.5 outline-none transition"></select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-slate-300 mb-1.5">Sales ($)</label>
                        <input type="number" id="txSales" min="0" step="0.0001" required oninput="updateTxMarginPreview()"
                               class="w-full bg-slate-900/70 border border-slate-700 focus:border-sky-500 text-xs text-slate-100 rounded-xl px-3 py-2.5 outline-none transition">
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-slate-300 mb-1.5">Quantity</label>
                        <input type="number" id="txQuantity" min="1" step="1" value="1" required oninput="updateTxMarginPreview()"
                               class="w-full bg-slate-900/70 border border-slate-700 focus:border-sky-500 text-xs text-slate-100 rounded-xl px-3 py-2.5 outline-none transition">
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-slate-300 mb-1.5">Discount (%)</label>
                        <input type="number" id="txDiscount" min="0" max="100" step="1" value="0" required oninput="updateTxMarginPreview()"
                               class="w-full bg-slate-900/70 border border-slate-700 focus:border-sky-500 text-xs text-slate-100 rounded-xl px-3 py-2.5 outline-none transition">
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-slate-300 mb-1.5">Profit ($)</label>
                        <input type="number" id="txProfit" step="0.0001" required oninput="updateTxMarginPreview()"
                               class="w-full bg-slate-900/70 border border-slate-700 focus:border-sky-500 text-xs text-slate-100 rounded-xl px-3 py-2.5 outline-none transition">
                    </div>
                </div>

                <div class="flex items-center justify-between bg-slate-900/60 border border-slate-700/60 rounded-xl px-4 py-3">
                    <span class="text-[11px] text-slate-400">Projected Margin</span>
                    <span id="txMarginPreview" class="text-sm font-bold text-emerald-400">0.00%</span>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="closeTransactionModal()" class="bg-slate-700 hover:bg-slate-600 text-slate-200 text-xs font-medium px-4 py-2.5 rounded-xl transition">Batal</button>
                    <button type="submit" id="txSubmitBtn" class="bg-sky-600 hover:bg-sky-500 text-white text-xs font-semibold px-4 py-2.5 rounded-xl transition shadow-lg shadow-sky-900/30">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- JAVASCRIPT (Urut) -->
    <script src="js/api.js"></script>
    <script src="js/charts.js"></script>
    <script src="js/drilldown.js"></script>
    <script src="js/whatif.js"></script>
    <script src="js/etl.js"></script>
    <script src="js/app.js"></script>
</body>
</html>
