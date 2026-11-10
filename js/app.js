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

// Set hook agar data layer bisa memicu render ulang saat data berubah.
window.onDashboardDataUpdated = function () {
    renderAllViews();
};

// --- INITIALIZATION ---
window.onload = async function () {
    try {
        lucide.createIcons();
    } catch (error) {
        console.warn('Lucide init:', error);
    }

    try {
        await bootstrapSession();
        await loadDashboardData();
        startLiveSync();
    } catch (error) {
        console.error(error);
        showDataError(error.message || 'Gagal memuat data dari server.');
    }
};

function renderAllViews() {
    updateKPIs();

    if (currentTab === 'dashboard') {
        initDashboardCharts();
    } else if (currentTab === 'drilldown') {
        renderDrillDown();
    } else if (currentTab === 'whatif') {
        runWhatIfSimulation();
    } else if (currentTab === 'etl' && typeof refreshEtlPanel === 'function') {
        refreshEtlPanel();
    }

    try {
        lucide.createIcons();
    } catch (error) {
        // Icons are non-critical.
    }
}

// KPI cards filled from live API totals.
function updateKPIs() {
    const setText = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    };

    setText('kpi-sales', formatCurrency(totals.totalSales));
    setText('kpi-profit', formatCurrency(totals.totalProfit));

    const margin = totals.totalSales ? (totals.totalProfit / totals.totalSales) * 100 : 0;
    setText('kpi-margin', `${margin.toFixed(2)}%`);

    const lossRate = meta.totalOrders ? (meta.lossOrders / meta.totalOrders) * 100 : 0;
    setText('kpi-loss-rate', `${lossRate.toFixed(2)}%`);
    setText('kpi-loss-subtext', `${formatNumber(meta.lossOrders)} of ${formatNumber(meta.totalOrders)} Transactions at Loss`);
    setText('kpi-discount', formatPercent(totals.avgDiscount));
    setText('navAlertBadge', `${lossRate.toFixed(1)}%`);
}

// Tab Switcher
function switchTab(tabId) {
    currentTab = tabId;

    ['dashboard', 'drilldown', 'whatif', 'alerts', 'etl'].forEach((tab) => {
        const el = document.getElementById(`tab-${tab}`);
        const nav = document.getElementById(`nav-${tab}`);
        if (!el || !nav) return;

        if (tab === tabId) {
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
        if (tabId === 'etl' && typeof initEtlWorkspace === 'function') initEtlWorkspace();
    }, 50);
}

function updateGlobalFilters() {
    const filter = document.getElementById('globalRegionFilter');
    if (!filter) return;

    const region = filter.value;
    if (region !== 'ALL') {
        drillState = { level: 2, region: region, category: null, subcategory: null };
        switchTab('drilldown');
    } else {
        resetDrillDown();
    }
}
