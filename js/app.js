// --- GLOBAL STATE ---
let currentTab = 'dashboard';
let drillState = {
    level: 1, // 1: Region, 2: Category, 3: Sub-Category, 4: Root Cause
    region: null,
    category: null,
    subcategory: null
};

// Chart Instances
let charts = {};

// --- INITIALIZATION ---
window.onload = function() {
    lucide.createIcons();
    updateKPIs();
    initCharts();
    renderDrillDown();
    runWhatIfSimulation();
};

// KPI cards filled from data.js totals
function updateKPIs() {
    document.getElementById('kpi-sales').textContent = `$${totals.totalSales.toLocaleString()}`;
    document.getElementById('kpi-profit').textContent = `$${totals.totalProfit.toLocaleString()}`;
    document.getElementById('kpi-margin').textContent = `${((totals.totalProfit / totals.totalSales) * 100).toFixed(2)}%`;
    document.getElementById('kpi-loss-rate').textContent = `${((meta.lossOrders / meta.totalOrders) * 100).toFixed(2)}%`;
    document.getElementById('kpi-loss-subtext').textContent = `${meta.lossOrders.toLocaleString()} of ${meta.totalOrders.toLocaleString()} Orders is at Loss`;
    document.getElementById('kpi-discount').textContent = `${(totals.avgDiscount * 100).toFixed(2)}%`;
}

// Theme Toggle (Light/Dark)
document.getElementById('themeToggle').addEventListener('click', () => {
    document.documentElement.classList.toggle('light');
});

// Tab Switcher
function switchTab(tabId) {
    currentTab = tabId;
    ['dashboard', 'drilldown', 'whatif', 'alerts'].forEach(t => {
        const el = document.getElementById(`tab-${t}`);
        const nav = document.getElementById(`nav-${t}`);
        if (t === tabId) {
            el.classList.remove('hidden');
            nav.classList.add('bg-sky-500/10', 'text-sky-400', 'border', 'border-sky-500/20');
            nav.classList.remove('text-slate-400');
        } else {
            el.classList.add('hidden');
            nav.classList.remove('bg-sky-500/10', 'text-sky-400', 'border', 'border-sky-500/20');
            nav.classList.add('text-slate-400');
        }
    });

    // Re-render charts when visible
    setTimeout(() => {
        if (tabId === 'dashboard') initDashboardCharts();
        if (tabId === 'drilldown') renderDrillDown();
        if (tabId === 'whatif') runWhatIfSimulation();
    }, 50);
}

function updateGlobalFilters() {
    const region = document.getElementById('globalRegionFilter').value;
    if (region !== 'ALL') {
        drillState = { level: 2, region: region, category: null, subcategory: null };
        switchTab('drilldown');
    } else {
        resetDrillDown();
    }
}
