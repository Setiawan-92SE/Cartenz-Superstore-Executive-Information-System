// --- DRILL-DOWN ENGINE (OLAP NAVIGATION LOGIC) ---
function getCategoryAggregate(category) {
    let sales = 0, profit = 0, subcategories = {};
    for (let r in superstoreData) {
        const cat = superstoreData[r].categories[category];
        if (!cat) continue;
        sales += cat.sales;
        profit += cat.profit;
        for (let s in cat.subcategories) {
            const sub = cat.subcategories[s];
            if (!subcategories[s]) subcategories[s] = { sales: 0, profit: 0, avgDiscount: 0, txCount: 0, _discWeight: 0 };
            const agg = subcategories[s];
            agg.sales += sub.sales;
            agg.profit += sub.profit;
            agg._discWeight += sub.avgDiscount * sub.txCount;
            agg.txCount += sub.txCount;
        }
    }
    for (let s in subcategories) {
        const agg = subcategories[s];
        agg.avgDiscount = agg.txCount ? agg._discWeight / agg.txCount : 0;
        delete agg._discWeight;
    }
    return { sales, profit, subcategories };
}

function getCategoryOfSub(subName) {
    for (let r in superstoreData)
        for (let c in superstoreData[r].categories)
            if (superstoreData[r].categories[c].subcategories[subName]) return c;
    return null;
}

function renderDrillDown() {
    updateBreadcrumbs();

    const level = drillState.level;
    const badge = document.getElementById('drillLevelBadge');
    const title = document.getElementById('drillLevelTitle');
    const desc = document.getElementById('drillLevelDesc');
    const btnDrillUp = document.getElementById('btnDrillUp');
    const txSection = document.getElementById('drillRootCauseSection');

    btnDrillUp.disabled = (level === 1);
    txSection.classList.add('hidden');

    let labels = [], salesData = [], profitData = [];
    let nodesHtml = '';
    let totalS = 0, totalP = 0;

    if (level === 1) {
        // LEVEL 1: Global (Regions)
        badge.textContent = "Global View";
        title.textContent = "Global Region Performance";
        desc.textContent = "Click any region or chart bar below to drill-down into Categories.";

        for (let r in superstoreData) {
            const reg = superstoreData[r];
            labels.push(r);
            salesData.push(reg.sales);
            profitData.push(reg.profit);
            totalS += reg.sales;
            totalP += reg.profit;

            const margin = ((reg.profit / reg.sales) * 100).toFixed(1);
            nodesHtml += `
                <div onclick="drillDownNext('${r}')" class="bg-slate-900/80 border border-slate-700/60 hover:border-sky-500/80 p-3 rounded-xl cursor-pointer transition flex items-center justify-between group">
                    <div>
                        <h4 class="font-bold text-white text-xs group-hover:text-sky-400">${r} Region</h4>
                        <p class="text-[11px] text-slate-400">Sales: $${reg.sales.toLocaleString()}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs font-bold ${reg.profit >= 0 ? 'text-emerald-400' : 'text-rose-400'}">$${reg.profit.toLocaleString()}</p>
                        <span class="text-[10px] text-slate-400">${margin}% Margin</span>
                    </div>
                </div>
            `;
        }
    } else if (level === 2) {
        // LEVEL 2: Categories within Region
        const regObj = superstoreData[drillState.region];
        badge.textContent = `${drillState.region} Region`;
        title.textContent = `Categories in ${drillState.region} Region`;
        desc.textContent = `Click any Category to drill-down into Sub-Categories.`;

        totalS = regObj.sales;
        totalP = regObj.profit;

        for (let c in regObj.categories) {
            const cat = regObj.categories[c];
            labels.push(c);
            salesData.push(cat.sales);
            profitData.push(cat.profit);

            const margin = ((cat.profit / cat.sales) * 100).toFixed(1);
            nodesHtml += `
                <div onclick="drillDownNext('${c}')" class="bg-slate-900/80 border border-slate-700/60 hover:border-sky-500/80 p-3 rounded-xl cursor-pointer transition flex items-center justify-between group">
                    <div>
                        <h4 class="font-bold text-white text-xs group-hover:text-sky-400">${c}</h4>
                        <p class="text-[11px] text-slate-400">Sales: $${cat.sales.toLocaleString()}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs font-bold ${cat.profit >= 0 ? 'text-emerald-400' : 'text-rose-400'}">$${cat.profit.toLocaleString()}</p>
                        <span class="text-[10px] ${cat.profit < 0 ? 'text-rose-400 font-bold' : 'text-slate-400'}">${margin}% Margin</span>
                    </div>
                </div>
            `;
        }
    } else if (level === 3) {
        // LEVEL 3: Sub-Categories
        const catObj = drillState.region
            ? superstoreData[drillState.region].categories[drillState.category]
            : getCategoryAggregate(drillState.category);
        badge.textContent = drillState.region ? `${drillState.category}` : `${drillState.category} (All Regions)`;
        title.textContent = drillState.region
            ? `Sub-Categories in ${drillState.category} (${drillState.region})`
            : `Sub-Categories in ${drillState.category} — All Regions`;
        desc.textContent = `Click a Sub-Category (e.g. Tables) to audit individual toxic transactions.`;

        totalS = catObj.sales;
        totalP = catObj.profit;

        for (let sc in catObj.subcategories) {
            const sub = catObj.subcategories[sc];
            labels.push(sc);
            salesData.push(sub.sales);
            profitData.push(sub.profit);

            const margin = ((sub.profit / sub.sales) * 100).toFixed(1);
            nodesHtml += `
                <div onclick="drillDownNext('${sc}')" class="bg-slate-900/80 border ${sub.profit < 0 ? 'border-rose-500/40 bg-rose-950/10' : 'border-slate-700/60'} hover:border-sky-500/80 p-3 rounded-xl cursor-pointer transition flex items-center justify-between group">
                    <div>
                        <div class="flex items-center gap-1.5">
                            <h4 class="font-bold text-white text-xs group-hover:text-sky-400">${sc}</h4>
                            ${sub.profit < 0 ? '<span class="bg-rose-500/20 text-rose-400 text-[9px] px-1.5 py-0.2 rounded">LOSS</span>' : ''}
                        </div>
                        <p class="text-[11px] text-slate-400">Avg Disc: ${(sub.avgDiscount*100).toFixed(0)}%</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs font-bold ${sub.profit >= 0 ? 'text-emerald-400' : 'text-rose-400'}">$${sub.profit.toLocaleString()}</p>
                        <span class="text-[10px] text-slate-400">$${sub.sales.toLocaleString()} Sales</span>
                    </div>
                </div>
            `;
        }
    } else if (level === 4) {
        // LEVEL 4: Root Cause Audit
        const subObj = drillState.region
            ? superstoreData[drillState.region].categories[drillState.category].subcategories[drillState.subcategory]
            : getCategoryAggregate(drillState.category).subcategories[drillState.subcategory];
        badge.textContent = drillState.region ? `Root Cause Audit` : `Root Cause Audit (All Regions)`;
        title.textContent = `Transaction Audit: ${drillState.subcategory}`;
        desc.textContent = `Showing transaction-level profit leaks caused by high discount rates.`;

        totalS = subObj.sales;
        totalP = subObj.profit;

        labels = ['Actual Yield'];
        salesData = [0];
        profitData = [subObj.profit];

        txSection.classList.remove('hidden');
        renderRootCauseTable(drillState.region, drillState.subcategory);
    }

    document.getElementById('drillSubtotalSales').textContent = `$${totalS.toLocaleString()}`;
    const pEl = document.getElementById('drillSubtotalProfit');
    pEl.textContent = `$${totalP.toLocaleString()}`;
    pEl.classList.toggle('text-rose-400', totalP < 0);
    pEl.classList.toggle('text-emerald-400', totalP >= 0);
    document.getElementById('drillNodesList').innerHTML = nodesHtml;

    renderDrillChart(labels, salesData, profitData);
}

function drillDownNext(nodeName) {
    if (drillState.level === 1) {
        drillState.region = nodeName;
        drillState.level = 2;
    } else if (drillState.level === 2) {
        drillState.category = nodeName;
        drillState.level = 3;
    } else if (drillState.level === 3) {
        drillState.subcategory = nodeName;
        drillState.level = 4;
    }
    renderDrillDown();
}

function drillUp() {
    if (drillState.level > 1) {
        drillState.level--;
        if (drillState.level === 1) drillState.region = null;
        if (drillState.level === 2) {
            drillState.category = null;
            if (!drillState.region) drillState.level = 1;
        }
        if (drillState.level === 3) drillState.subcategory = null;
        renderDrillDown();
    }
}

function resetDrillDown() {
    drillState = { level: 1, region: null, category: null, subcategory: null };
    renderDrillDown();
}

function drillToSpecific(region, category, subcategory) {
    drillState = { level: 4, region: region, category: category, subcategory: subcategory };
    switchTab('drilldown');
    renderDrillDown();
}

function drillFromSummary(region, category, subcategory) {
    if (subcategory) {
        drillState = { level: 4, region: null, subcategory: subcategory, category: category || getCategoryOfSub(subcategory) };
    } else if (category) {
        drillState = { level: 3, region: null, category: category, subcategory: null };
    } else {
        drillState = { level: 2, region: region, category: null, subcategory: null };
    }
    switchTab('drilldown');
}

function updateBreadcrumbs() {
    const container = document.getElementById('breadcrumbPath');
    let html = `<span onclick="resetDrillDown()" class="hover:underline cursor-pointer">Global</span>`;

    if (drillState.region) {
        html += ` <span class="text-slate-500">></span> <span onclick="drillState.level=2; renderDrillDown();" class="hover:underline cursor-pointer">${drillState.region}</span>`;
    }
    if (drillState.category) {
        html += ` <span class="text-slate-500">></span> <span onclick="drillState.level=3; renderDrillDown();" class="hover:underline cursor-pointer">${drillState.category}</span>`;
    }
    if (drillState.subcategory) {
        html += ` <span class="text-slate-500">></span> <span class="text-white font-bold">${drillState.subcategory}</span>`;
    }
    container.innerHTML = html;
}

async function renderRootCauseTable(region, subcategory) {
    const tbody = document.getElementById('drillTxTableBody');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="7" class="p-4 text-center text-slate-400">Memuat transaksi dari database…</td></tr>`;

    try {
        const params = new URLSearchParams({ subcategory });
        if (region) params.set('region', region);

        const payload = await apiFetch(`api/leaks.php?${params.toString()}`);
        const rows = payload.rows || [];

        let html = '';
        rows.forEach(t => {
            const isLoss = t.profit < 0;
            html += `
                <tr class="hover:bg-slate-800/50 transition">
                    <td class="p-3">
                        <p class="font-bold text-white">${escapeHtml(t.orderId)}</p>
                        <p class="text-[10px] text-slate-400">${escapeHtml(t.customer)}</p>
                    </td>
                    <td class="p-3 text-slate-300">${escapeHtml(t.region)} (${escapeHtml(t.state)})</td>
                    <td class="p-3 text-slate-300">${escapeHtml(t.subCat)}</td>
                    <td class="p-3 text-slate-300">$${t.sales.toFixed(2)}</td>
                    <td class="p-3">
                        <span class="${t.discount >= 0.3 ? 'bg-rose-500/20 text-rose-400 font-bold px-2 py-0.5 rounded border border-rose-500/30' : 'text-slate-300'}">
                            ${(t.discount * 100).toFixed(0)}%
                        </span>
                    </td>
                    <td class="p-3 font-bold ${isLoss ? 'text-rose-400' : 'text-emerald-400'}">
                        $${t.profit.toFixed(2)}
                    </td>
                    <td class="p-3">
                        ${isLoss ? '<span class="text-[10px] text-rose-400 bg-rose-500/10 px-2 py-1 rounded">Margin Leak</span>' : '<span class="text-[10px] text-emerald-400 bg-emerald-500/10 px-2 py-1 rounded">Healthy</span>'}
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html || `<tr><td colspan="7" class="p-4 text-center text-slate-400">No toxic discount transactions detected for this node.</td></tr>`;
    } catch (error) {
        tbody.innerHTML = `<tr><td colspan="7" class="p-4 text-center text-rose-400">Gagal memuat transaksi: ${escapeHtml(error.message)}</td></tr>`;
    }
}
