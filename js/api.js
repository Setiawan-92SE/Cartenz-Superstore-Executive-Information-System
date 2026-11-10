/* ============================================================================
 * Live data layer: session, dashboard payload, polling, toast & formatting.
 * ==========================================================================*/

let superstoreData = {};
let totals = {};
let meta = {};
let sampleLeakTransactions = [];
let dataVersion = null;
let currentUser = null;
let csrfToken = null;
let liveSyncMs = 5000;
let liveTimer = null;
let lastSyncAt = null;

async function apiFetch(path, options = {}) {
    const opts = { credentials: 'same-origin', cache: 'no-store', ...options };
    opts.headers = { ...(options.headers || {}) };

    const method = (opts.method || 'GET').toUpperCase();

    if (opts.body !== undefined && !opts.headers['Content-Type']) {
        opts.headers['Content-Type'] = 'application/json';
    }

    if (csrfToken && method !== 'GET' && method !== 'HEAD') {
        opts.headers['X-CSRF-Token'] = csrfToken;
    }

    const response = await fetch(path, opts);

    if (response.status === 401) {
        window.location.href = 'login.php';
        throw new Error('Sesi berakhir. Silakan login kembali.');
    }

    let payload;
    try {
        payload = await response.json();
    } catch {
        payload = { ok: false, message: 'Respons server tidak valid.' };
    }

    if (!response.ok || payload.ok === false) {
        throw new Error(payload.message || `Permintaan gagal (${response.status}).`);
    }

    return payload;
}

async function bootstrapSession() {
    const payload = await apiFetch('api/auth.php?action=check');

    if (!payload.authenticated) {
        window.location.href = 'login.php';
        throw new Error('Belum login.');
    }

    currentUser = payload.user;
    csrfToken = payload.csrfToken;

    if (payload.liveSyncMs) {
        const parsed = Number(payload.liveSyncMs);
        if (Number.isFinite(parsed) && parsed >= 1000) {
            liveSyncMs = parsed;
        }
    }

    const nameEl = document.getElementById('currentUserName');
    if (nameEl && currentUser) {
        nameEl.textContent = currentUser.fullName || currentUser.username || 'Administrator';
        nameEl.title = currentUser.username || '';
    }
}

function computeDerivedTotals() {
    totals.catProfit = {};
    totals.catSales = {};
    totals.subProfit = {};

    Object.values(superstoreData).forEach((region) => {
        Object.entries(region.categories || {}).forEach(([categoryName, category]) => {
            totals.catProfit[categoryName] = (totals.catProfit[categoryName] || 0) + Number(category.profit || 0);
            totals.catSales[categoryName] = (totals.catSales[categoryName] || 0) + Number(category.sales || 0);

            Object.entries(category.subcategories || {}).forEach(([subName, sub]) => {
                totals.subProfit[subName] = (totals.subProfit[subName] || 0) + Number(sub.profit || 0);
            });
        });
    });
}

function applyDashboardPayload(payload) {
    superstoreData = payload.superstoreData || {};
    totals = payload.totals || { totalSales: 0, totalProfit: 0, avgDiscount: 0 };
    meta = payload.meta || { totalOrders: 0, lossOrders: 0, totalQuantity: 0 };
    sampleLeakTransactions = payload.sampleLeakTransactions || [];
    dataVersion = payload.dataVersion || null;
    lastSyncAt = new Date();

    computeDerivedTotals();
    updateSyncIndicator();
}

function notifyDashboardUpdated(payload) {
    if (typeof window.onDashboardDataUpdated === 'function') {
        window.onDashboardDataUpdated(payload);
    }
}

async function loadDashboardData() {
    const payload = await apiFetch('api/dashboard.php');
    applyDashboardPayload(payload);
    notifyDashboardUpdated(payload);
    return payload;
}

async function refreshLiveData() {
    const query = dataVersion ? `?since=${encodeURIComponent(dataVersion)}` : '';
    const payload = await apiFetch(`api/dashboard.php${query}`);

    if (payload.unchanged) {
        lastSyncAt = new Date();
        updateSyncIndicator();
        return payload;
    }

    applyDashboardPayload(payload);
    notifyDashboardUpdated(payload);
    return payload;
}

function startLiveSync() {
    if (liveTimer) {
        clearInterval(liveTimer);
    }

    updateSyncIndicator();

    liveTimer = setInterval(async () => {
        try {
            await refreshLiveData();
            setConnectionState('live');
        } catch (error) {
            setConnectionState('offline', error.message);
        }
    }, liveSyncMs);
}

function updateSyncIndicator() {
    const label = document.getElementById('lastSyncLabel');
    if (!label) return;

    const time = lastSyncAt
        ? lastSyncAt.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
        : '—';
    label.textContent = `• ${time}`;
}

function setConnectionState(state, message = '') {
    const indicator = document.getElementById('liveIndicator');
    const label = document.getElementById('lastSyncLabel');
    if (!indicator) return;

    indicator.classList.remove('bg-emerald-500/10', 'border-emerald-500/20', 'text-emerald-400',
        'bg-rose-500/10', 'border-rose-500/20', 'text-rose-400');

    if (state === 'offline') {
        indicator.classList.add('bg-rose-500/10', 'border-rose-500/20', 'text-rose-400');
        if (label) label.textContent = message ? 'Offline' : 'Offline';
        showDataError(message || 'Koneksi ke server terputus. Mencoba lagi...');
    } else {
        indicator.classList.add('bg-emerald-500/10', 'border-emerald-500/20', 'text-emerald-400');
        hideDataError();
        updateSyncIndicator();
    }
}

function showDataError(message) {
    const banner = document.getElementById('dataErrorBanner');
    if (!banner) return;
    const text = banner.querySelector('[data-error-text]');
    if (text) text.textContent = message;
    banner.classList.remove('hidden');
    banner.classList.add('flex');
}

function hideDataError() {
    const banner = document.getElementById('dataErrorBanner');
    if (!banner) return;
    banner.classList.add('hidden');
    banner.classList.remove('flex');
}

async function logout(event) {
    if (event) event.preventDefault();

    try {
        await apiFetch('api/auth.php?action=logout', { method: 'POST', body: '{}' });
    } catch (error) {
        console.warn('Logout:', error.message);
    }

    window.location.href = 'login.php';
}

/* ----------------------------- Formatting utils --------------------------- */

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function formatNumber(value, digits = 0) {
    const number = Number(value) || 0;
    return number.toLocaleString('en-US', {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    });
}

function formatCurrency(value, digits = 0) {
    const number = Number(value) || 0;
    const sign = number < 0 ? '-' : '';
    return `${sign}$${Math.abs(number).toLocaleString('en-US', {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    })}`;
}

function formatPercent(fraction, digits = 2) {
    const number = Number(fraction) || 0;
    return `${(number * 100).toFixed(digits)}%`;
}

function formatDateTime(value) {
    if (!value) return '—';
    const date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return String(value);
    return date.toLocaleString('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit', second: '2-digit',
    });
}

let toastTimer = null;

function showToast(message, type = 'success') {
    const toast = document.getElementById('appToast');
    const text = document.getElementById('appToastText');
    if (!toast || !text) return;

    text.textContent = message;
    toast.classList.remove('bg-emerald-600', 'bg-rose-600', 'bg-amber-600');
    toast.classList.add(type === 'error' ? 'bg-rose-600' : type === 'warning' ? 'bg-amber-600' : 'bg-emerald-600');
    toast.classList.remove('translate-y-24', 'opacity-0', 'pointer-events-none');
    toast.classList.add('translate-y-0', 'opacity-100');

    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
        toast.classList.add('translate-y-24', 'opacity-0', 'pointer-events-none');
        toast.classList.remove('translate-y-0', 'opacity-100');
    }, 3500);
}
