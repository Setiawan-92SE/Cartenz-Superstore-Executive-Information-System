/* ============================================================================
 * Data & ETL workspace: status pipeline, transaction CRUD, filter & pagination.
 * ==========================================================================*/

const etlState = {
    initialized: false,
    master: null,
    rows: [],
    page: 1,
    perPage: 20,
    total: 0,
    totalPages: 1,
    modalOpen: false,
    busy: false,
    searchTimer: null,
};

/* ------------------------------- Bootstrap -------------------------------- */

async function initEtlWorkspace() {
    try {
        if (!etlState.initialized) {
            await loadMasterData();
            attachEtlListeners();
            etlState.initialized = true;
        }

        await Promise.all([loadEtlStatus(), loadTransactions()]);
        lucide.createIcons();
    } catch (error) {
        console.error(error);
        showToast(error.message || 'Gagal memuat ETL workspace.', 'error');
    }
}

async function refreshEtlPanel() {
    if (!etlState.initialized) return;

    try {
        await loadEtlStatus();
        if (!etlState.modalOpen && !etlState.busy) {
            await loadTransactions();
        }
    } catch (error) {
        console.warn('ETL refresh:', error.message);
    }
}

async function refreshEtlWorkspace() {
    try {
        await Promise.all([loadEtlStatus(), loadTransactions()]);
        showToast('ETL workspace diperbarui.', 'success');
    } catch (error) {
        showToast(error.message, 'error');
    }
}

/* ------------------------------ Master data ------------------------------- */

async function loadMasterData() {
    const payload = await apiFetch('api/master.php');
    etlState.master = payload;

    fillSelect('filterRegion', payload.regions, 'region_name', 'region_name', 'Semua Region');
    fillSelect('filterCategory', payload.categories, 'category_name', 'category_name', 'Semua Kategori');
    fillSelect('txShipMode', payload.shipModes, 'ship_mode_id', 'ship_mode_name', 'Pilih ship mode');
    fillSelect('txSegment', payload.segments, 'segment_id', 'segment_name', 'Pilih segment');
    fillSelect('txRegion', payload.regions, 'region_id', 'region_name', 'Pilih region');
    fillSelect('txCategory', payload.categories, 'category_id', 'category_name', 'Pilih kategori');

    populateTxSubcategories('');
    populateTxLocations('');
}

function fillSelect(id, items, valueKey, labelKey, placeholder, selected = '') {
    const select = document.getElementById(id);
    if (!select) return;

    let html = placeholder ? `<option value="">${escapeHtml(placeholder)}</option>` : '';

    (items || []).forEach((item) => {
        const value = item[valueKey];
        const label = item[labelKey];
        html += `<option value="${escapeHtml(value)}"${String(selected) === String(value) ? ' selected' : ''}>${escapeHtml(label)}</option>`;
    });

    select.innerHTML = html;
}

function populateTxLocations(regionId, selected = '') {
    const select = document.getElementById('txLocation');
    if (!select || !etlState.master) return;

    const locations = (etlState.master.locations || []).filter((location) => {
        return regionId === '' || regionId === null || String(location.region_id) === String(regionId);
    });

    let html = '<option value="">Pilih lokasi</option>';
    locations.forEach((location) => {
        const label = `${location.city}, ${location.state} (${location.postal_code})`;
        html += `<option value="${location.location_id}"${String(selected) === String(location.location_id) ? ' selected' : ''}>${escapeHtml(label)}</option>`;
    });
    select.innerHTML = html;
}

function populateTxSubcategories(categoryId, selected = '') {
    const select = document.getElementById('txSubcategory');
    if (!select || !etlState.master) return;

    const categories = (etlState.master.categories || []).filter((category) => {
        return categoryId === '' || categoryId === null || String(category.category_id) === String(categoryId);
    });

    let html = '<option value="">Pilih sub-kategori</option>';
    categories.forEach((category) => {
        html += `<optgroup label="${escapeHtml(category.category_name)}">`;
        (category.subcategories || []).forEach((sub) => {
            html += `<option value="${sub.subcategory_id}"${String(selected) === String(sub.subcategory_id) ? ' selected' : ''}>${escapeHtml(sub.subcategory_name)}</option>`;
        });
        html += '</optgroup>';
    });
    select.innerHTML = html;
}

function handleTxRegionChange() {
    const regionId = document.getElementById('txRegion')?.value || '';
    populateTxLocations(regionId);
}

function handleTxCategoryChange() {
    const categoryId = document.getElementById('txCategory')?.value || '';
    populateTxSubcategories(categoryId);
}

/* ------------------------------- ETL status ------------------------------- */

async function loadEtlStatus() {
    const payload = await apiFetch('api/etl.php?action=status');
    renderEtlStatus(payload.status);
}

function renderEtlStatus(status) {
    if (!status) return;

    const state = status.state || {};
    const lastRun = status.lastRun || {};

    setText('etlStatusBadge', status.inSync ? 'In Sync' : 'Out of Sync');
    const badge = document.getElementById('etlStatusBadge');
    if (badge) {
        badge.className = `text-lg font-bold mt-1 ${status.inSync ? 'text-emerald-400' : 'text-amber-400'}`;
    }

    setText('etlLastRun', state.last_run_at ? `Terakhir: ${formatDateTime(state.last_run_at)}` : 'Belum pernah dijalankan');
    setText('etlSourceRows', formatNumber(state.source_rows || 0));
    setText('etlWarehouseRows', formatNumber(status.summaryRows || 0));
    setText('etlRejected', formatNumber(status.rejectRows || 0));
    setText(
        'etlDuration',
        lastRun.id
            ? `${formatNumber(lastRun.duration_ms || 0)} ms · trigger: ${lastRun.trigger_source || '—'}`
            : '—'
    );
    setText(
        'etlFingerprint',
        `fingerprint: ${status.fingerprint || '—'} · run #${lastRun.id || '—'} · status: ${lastRun.status || 'idle'}`
    );
}

function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
}

async function runEtlNow() {
    if (etlState.busy) return;

    const button = document.getElementById('btnRunEtl');
    etlState.busy = true;
    if (button) {
        button.disabled = true;
        button.classList.add('opacity-60', 'cursor-not-allowed');
        button.innerHTML = 'Menjalankan ETL...';
    }

    try {
        const payload = await apiFetch('api/etl.php?action=run', { method: 'POST', body: '{}' });
        showToast(payload.message || 'ETL selesai.', 'success');

        await refreshLiveData();
        await Promise.all([loadEtlStatus(), loadTransactions()]);
    } catch (error) {
        showToast(error.message, 'error');
    } finally {
        etlState.busy = false;
        if (button) {
            button.disabled = false;
            button.classList.remove('opacity-60', 'cursor-not-allowed');
            button.innerHTML = '<i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i> Run ETL';
            lucide.createIcons();
        }
    }
}

/* ------------------------------ Transactions ------------------------------ */

async function loadTransactions() {
    const params = new URLSearchParams({
        action: 'list',
        page: String(etlState.page),
        per_page: String(etlState.perPage),
    });

    const search = document.getElementById('txSearch')?.value?.trim() || '';
    const region = document.getElementById('filterRegion')?.value || '';
    const category = document.getElementById('filterCategory')?.value || '';
    const profit = document.getElementById('filterProfit')?.value || '';

    if (search) params.set('search', search);
    if (region) params.set('region', region);
    if (category) params.set('category', category);
    if (profit) params.set('profit', profit);

    const payload = await apiFetch(`api/sales.php?${params.toString()}`);

    etlState.rows = payload.rows || [];
    etlState.total = payload.pagination?.total || 0;
    etlState.totalPages = payload.pagination?.totalPages || 1;
    etlState.page = payload.pagination?.page || 1;

    renderTransactionRows();
    renderPagination();
    lucide.createIcons();
}

function renderTransactionRows() {
    const tbody = document.getElementById('txTableBody');
    if (!tbody) return;

    if (!etlState.rows.length) {
        tbody.innerHTML = `<tr><td colspan="9" class="p-6 text-center text-slate-400">Belum ada transaksi yang cocok dengan filter.</td></tr>`;
        return;
    }

    tbody.innerHTML = etlState.rows.map((row) => {
        const loss = row.profit < 0;
        return `
            <tr class="hover:bg-slate-800/50 transition align-top">
                <td class="p-3">
                    <p class="font-bold text-white">#${row.sale_id}</p>
                    <p class="text-[10px] text-slate-500">${escapeHtml(row.ship_mode_name)}</p>
                </td>
                <td class="p-3">
                    <p class="text-slate-200">${escapeHtml(row.region_name)}</p>
                    <p class="text-[10px] text-slate-500">${escapeHtml(row.state)} · ${escapeHtml(row.city)}</p>
                </td>
                <td class="p-3">
                    <p class="text-slate-200">${escapeHtml(row.subcategory_name)}</p>
                    <p class="text-[10px] text-slate-500">${escapeHtml(row.category_name)} · ${escapeHtml(row.segment_name)}</p>
                </td>
                <td class="p-3 text-right text-slate-200">${formatCurrency(row.sales, 2)}</td>
                <td class="p-3 text-right text-slate-300">${formatNumber(row.quantity)}</td>
                <td class="p-3 text-right">
                    <span class="${row.discount >= 0.3 ? 'bg-rose-500/15 text-rose-300 border-rose-500/30' : 'bg-slate-700/40 text-slate-300 border-slate-600/40'} border px-2 py-0.5 rounded text-[10px] font-semibold">
                        ${formatPercent(row.discount, 0)}
                    </span>
                </td>
                <td class="p-3 text-right font-bold ${loss ? 'text-rose-400' : 'text-emerald-400'}">${formatCurrency(row.profit, 2)}</td>
                <td class="p-3 text-right">
                    <span class="text-[10px] px-2 py-1 rounded ${loss ? 'bg-rose-500/10 text-rose-300' : 'bg-emerald-500/10 text-emerald-300'}">
                        ${loss ? 'Loss' : 'Profit'}
                    </span>
                </td>
                <td class="p-3">
                    <div class="flex items-center justify-end gap-1">
                        <button onclick="editTransaction(${row.sale_id})" title="Edit"
                                class="p-1.5 rounded-lg bg-slate-700/50 hover:bg-sky-500/20 text-slate-300 hover:text-sky-300 transition">
                            <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                        </button>
                        <button onclick="deleteTransaction(${row.sale_id})" title="Hapus"
                                class="p-1.5 rounded-lg bg-slate-700/50 hover:bg-rose-500/20 text-slate-300 hover:text-rose-300 transition">
                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function renderPagination() {
    const info = document.getElementById('txPaginationInfo');
    const prev = document.getElementById('txPrev');
    const next = document.getElementById('txNext');

    if (info) {
        const from = etlState.total === 0 ? 0 : (etlState.page - 1) * etlState.perPage + 1;
        const to = Math.min(etlState.total, etlState.page * etlState.perPage);
        info.textContent = `Menampilkan ${formatNumber(from)}–${formatNumber(to)} dari ${formatNumber(etlState.total)} transaksi`;
    }

    if (prev) {
        prev.disabled = etlState.page <= 1;
        prev.classList.toggle('opacity-40', prev.disabled);
        prev.classList.toggle('cursor-not-allowed', prev.disabled);
    }
    if (next) {
        next.disabled = etlState.page >= etlState.totalPages;
        next.classList.toggle('opacity-40', next.disabled);
        next.classList.toggle('cursor-not-allowed', next.disabled);
    }
}

function changeTxPage(delta) {
    const nextPage = Math.min(etlState.totalPages, Math.max(1, etlState.page + delta));
    if (nextPage === etlState.page) return;
    etlState.page = nextPage;
    loadTransactions().catch((error) => showToast(error.message, 'error'));
}

function resetTxFilters() {
    const search = document.getElementById('txSearch');
    if (search) search.value = '';
    ['filterRegion', 'filterCategory', 'filterProfit'].forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    etlState.page = 1;
    loadTransactions().catch((error) => showToast(error.message, 'error'));
}

function attachEtlListeners() {
    const search = document.getElementById('txSearch');
    if (search) {
        search.addEventListener('input', () => {
            clearTimeout(etlState.searchTimer);
            etlState.searchTimer = setTimeout(() => {
                etlState.page = 1;
                loadTransactions().catch((error) => showToast(error.message, 'error'));
            }, 350);
        });
    }

    ['filterRegion', 'filterCategory', 'filterProfit'].forEach((id) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('change', () => {
            etlState.page = 1;
            loadTransactions().catch((error) => showToast(error.message, 'error'));
        });
    });
}

/* -------------------------------- CRUD modal ------------------------------ */

function openTransactionModal() {
    const form = document.getElementById('txForm');
    if (form) form.reset();

    setText('txModalTitle', 'Tambah Transaksi');
    const idInput = document.getElementById('txId');
    if (idInput) idInput.value = '';

    const region = etlState.master?.regions?.[0]?.region_id ?? '';
    const category = etlState.master?.categories?.[0]?.category_id ?? '';
    const shipMode = etlState.master?.shipModes?.[0]?.ship_mode_id ?? '';
    const segment = etlState.master?.segments?.[0]?.segment_id ?? '';

    setValue('txRegion', region);
    populateTxLocations(region);
    setValue('txCategory', category);
    populateTxSubcategories(category);
    setValue('txShipMode', shipMode);
    setValue('txSegment', segment);
    setValue('txSales', '0');
    setValue('txQuantity', '1');
    setValue('txDiscount', '0');
    setValue('txProfit', '0');

    updateTxMarginPreview();
    showTxModal();
}

async function editTransaction(saleId) {
    try {
        const payload = await apiFetch(`api/sales.php?action=get&id=${encodeURIComponent(saleId)}`);
        const sale = payload.sale;
        if (!sale) throw new Error('Transaksi tidak ditemukan.');

        const form = document.getElementById('txForm');
        if (form) form.reset();

        setText('txModalTitle', `Edit Transaksi #${sale.sale_id}`);
        setValue('txId', sale.sale_id);
        setValue('txRegion', sale.region_id);
        populateTxLocations(sale.region_id, sale.location_id);

        const category = (etlState.master?.categories || []).find((item) => {
            return (item.subcategories || []).some((sub) => Number(sub.subcategory_id) === Number(sale.subcategory_id));
        });
        setValue('txCategory', category?.category_id ?? '');
        populateTxSubcategories(category?.category_id ?? '', sale.subcategory_id);

        setValue('txShipMode', sale.ship_mode_id);
        setValue('txSegment', sale.segment_id);
        setValue('txSales', sale.sales);
        setValue('txQuantity', sale.quantity);
        setValue('txDiscount', (Number(sale.discount) * 100).toFixed(2));
        setValue('txProfit', sale.profit);

        updateTxMarginPreview();
        showTxModal();
    } catch (error) {
        showToast(error.message, 'error');
    }
}

function setValue(id, value) {
    const el = document.getElementById(id);
    if (el) el.value = value ?? '';
}

function showTxModal() {
    const modal = document.getElementById('txModal');
    if (!modal) return;
    etlState.modalOpen = true;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    lucide.createIcons();
}

function closeTransactionModal() {
    const modal = document.getElementById('txModal');
    if (!modal) return;
    etlState.modalOpen = false;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function updateTxMarginPreview() {
    const sales = Number(document.getElementById('txSales')?.value || 0);
    const profit = Number(document.getElementById('txProfit')?.value || 0);
    const margin = sales !== 0 ? (profit / sales) * 100 : 0;

    const preview = document.getElementById('txMarginPreview');
    if (!preview) return;

    preview.textContent = `${margin.toFixed(2)}%`;
    preview.className = `text-sm font-bold ${margin < 0 ? 'text-rose-400' : 'text-emerald-400'}`;
}

async function submitTransaction(event) {
    event.preventDefault();
    if (etlState.busy) return;

    const saleId = Number(document.getElementById('txId')?.value || 0);
    const payload = {
        ship_mode_id: Number(document.getElementById('txShipMode')?.value || 0),
        segment_id: Number(document.getElementById('txSegment')?.value || 0),
        location_id: Number(document.getElementById('txLocation')?.value || 0),
        subcategory_id: Number(document.getElementById('txSubcategory')?.value || 0),
        sales: Number(document.getElementById('txSales')?.value || 0),
        quantity: Number(document.getElementById('txQuantity')?.value || 0),
        discount: Number(document.getElementById('txDiscount')?.value || 0) / 100,
        profit: Number(document.getElementById('txProfit')?.value || 0),
    };

    if (!payload.ship_mode_id || !payload.segment_id || !payload.location_id || !payload.subcategory_id) {
        showToast('Lengkapi ship mode, segment, lokasi, dan sub-kategori.', 'warning');
        return;
    }
    if (payload.sales < 0) {
        showToast('Sales tidak boleh negatif.', 'warning');
        return;
    }
    if (payload.quantity < 1) {
        showToast('Quantity minimal 1.', 'warning');
        return;
    }
    if (payload.discount < 0 || payload.discount > 1) {
        showToast('Discount harus di antara 0% dan 100%.', 'warning');
        return;
    }

    const action = saleId > 0 ? 'update' : 'create';
    if (saleId > 0) payload.sale_id = saleId;

    const submitBtn = document.getElementById('txSubmitBtn');
    etlState.busy = true;
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.classList.add('opacity-60', 'cursor-not-allowed');
        submitBtn.textContent = 'Menyimpan...';
    }

    try {
        const result = await apiFetch(`api/sales.php?action=${action}`, {
            method: 'POST',
            body: JSON.stringify(payload),
        });

        closeTransactionModal();
        showToast(result.message || 'Transaksi berhasil disimpan.', 'success');

        await refreshLiveData();
        await Promise.all([loadEtlStatus(), loadTransactions()]);
    } catch (error) {
        showToast(error.message, 'error');
    } finally {
        etlState.busy = false;
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.classList.remove('opacity-60', 'cursor-not-allowed');
            submitBtn.textContent = 'Simpan';
        }
    }
}

async function deleteTransaction(saleId) {
    if (!window.confirm(`Hapus transaksi #${saleId}? ETL akan dijalankan ulang dan chart langsung diperbarui.`)) {
        return;
    }

    try {
        const result = await apiFetch('api/sales.php?action=delete', {
            method: 'POST',
            body: JSON.stringify({ sale_id: saleId }),
        });

        showToast(result.message || 'Transaksi berhasil dihapus.', 'success');
        await refreshLiveData();
        await Promise.all([loadEtlStatus(), loadTransactions()]);
    } catch (error) {
        showToast(error.message, 'error');
    }
}
