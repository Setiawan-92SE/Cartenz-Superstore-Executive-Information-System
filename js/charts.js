function initCharts() {
    initDashboardCharts();
}

function initDashboardCharts() {
    // Regional Chart (sorted by sales desc)
    const regions = Object.keys(superstoreData).sort((a, b) => superstoreData[b].sales - superstoreData[a].sales);
    const regCtx = document.getElementById('regionOverviewChart').getContext('2d');
    if (charts.region) charts.region.destroy();
    charts.region = new Chart(regCtx, {
        type: 'bar',
        data: {
            labels: regions,
            datasets: [
                {
                    label: 'Sales ($)',
                    data: regions.map(r => superstoreData[r].sales),
                    backgroundColor: '#0284c7',
                    borderRadius: 6
                },
                {
                    label: 'Net Profit ($)',
                    data: regions.map(r => superstoreData[r].profit),
                    backgroundColor: '#10b981',
                    borderRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            onClick: (evt, activeEls, chart) => {
                if (!activeEls.length) return;
                const label = chart.data.labels[activeEls[0].index];
                drillFromSummary(label, null, null);
            },
            onHover: (evt, activeEls) => {
                evt.native.target.style.cursor = activeEls.length ? 'pointer' : 'default';
            },
            plugins: {
                legend: { labels: { color: '#94a3b8', font: { family: 'Inter', size: 11 } } }
            },
            scales: {
                x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.05)' } },
                y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.05)' } }
            }
        }
    });

    // Category Pie Chart (label mengikuti kategori yang ada di database)
    const categoryLabels = Object.keys(totals.catProfit || {}).sort();
    const categoryPalette = ['#0284c7', '#10b981', '#f43f5e', '#f59e0b', '#8b5cf6', '#14b8a6', '#ec4899'];
    const pieCtx = document.getElementById('categoryPieChart').getContext('2d');
    if (charts.categoryPie) charts.categoryPie.destroy();
    charts.categoryPie = new Chart(pieCtx, {
        type: 'doughnut',
        data: {
            labels: categoryLabels,
            datasets: [{
                data: categoryLabels.map(c => totals.catProfit[c]),
                backgroundColor: categoryLabels.map((_, i) => categoryPalette[i % categoryPalette.length]),
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            onClick: (evt, activeEls, chart) => {
                if (!activeEls.length) return;
                const label = chart.data.labels[activeEls[0].index];
                drillFromSummary(null, label, null);
            },
            onHover: (evt, activeEls) => {
                evt.native.target.style.cursor = activeEls.length ? 'pointer' : 'default';
            },
            plugins: {
                legend: { position: 'bottom', labels: { color: '#94a3b8', font: { family: 'Inter', size: 11 } } }
            }
        }
    });

    const insight = document.getElementById('categoryInsight');
    if (insight) {
        const categoryMargins = Object.keys(totals.catProfit || {}).map(name => ({
            name,
            margin: totals.catSales?.[name]
                ? (totals.catProfit[name] / totals.catSales[name]) * 100
                : 0
        })).sort((a, b) => a.margin - b.margin);

        if (categoryMargins.length) {
            insight.innerHTML = `<span class="text-rose-400 font-medium">${categoryMargins[0].name}</span> yields lowest profit margin (${categoryMargins[0].margin.toFixed(2)}%)`;
        } else {
            insight.textContent = '';
        }
    }

    // Sub-Category Bar Chart (all 17, sorted by profit desc)
    const subcats = Object.keys(totals.subProfit).sort((a, b) => totals.subProfit[b] - totals.subProfit[a]);
    const subCtx = document.getElementById('subCatBarChart').getContext('2d');
    if (charts.subCat) charts.subCat.destroy();
    charts.subCat = new Chart(subCtx, {
        type: 'bar',
        data: {
            labels: subcats,
            datasets: [{
                label: 'Profit ($)',
                data: subcats.map(s => totals.subProfit[s]),
                backgroundColor: (ctx) => {
                    const val = ctx.raw;
                    return val < 0 ? '#f43f5e' : '#0284c7';
                },
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            onClick: (evt, activeEls, chart) => {
                if (!activeEls.length) return;
                const label = chart.data.labels[activeEls[0].index];
                drillFromSummary(null, null, label);
            },
            onHover: (evt, activeEls) => {
                evt.native.target.style.cursor = activeEls.length ? 'pointer' : 'default';
            },
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { color: '#94a3b8' }, grid: { display: false } },
                y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.05)' } }
            }
        }
    });
}

function renderDrillChart(labels, sales, profit) {
    const ctx = document.getElementById('drillDownCanvas').getContext('2d');
    if (charts.drill) charts.drill.destroy();

    charts.drill = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Sales / Revenue ($)',
                    data: sales,
                    backgroundColor: '#0284c7',
                    borderRadius: 6
                },
                {
                    label: 'Net Profit / Loss ($)',
                    data: profit,
                    backgroundColor: (ctx) => ctx.raw < 0 ? '#f43f5e' : '#10b981',
                    borderRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            onClick: (evt, activeEls) => {
                if (activeEls.length > 0 && drillState.level < 4) {
                    const index = activeEls[0].index;
                    const selectedLabel = labels[index];
                    drillDownNext(selectedLabel);
                }
            },
            plugins: {
                legend: {
                    labels: {
                        color: '#94a3b8',
                        font: { family: 'Inter', size: 11 },
                        generateLabels: (chart) => {
                            const colors = ['#0284c7', '#10b981'];
                            return chart.data.datasets.map((ds, i) => ({
                                text: ds.label,
                                datasetIndex: i,
                                fillStyle: colors[i],
                                strokeStyle: colors[i],
                                fontColor: '#94a3b8',
                                lineWidth: 0,
                                hidden: !chart.isDatasetVisible(i)
                            }));
                        }
                    }
                }
            },
            scales: {
                x: { ticks: { color: '#94a3b8' }, grid: { display: false } },
                y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.05)' } }
            }
        }
    });
}

function renderWhatIfChart(capVal, recovered) {
    const actualLabels = Object.keys(totals.subProfit || {})
        .sort((a, b) => totals.subProfit[a] - totals.subProfit[b])
        .slice(0, 6);
    const actual = actualLabels.map(s => totals.subProfit[s]);
    const lossWeights = actual.map(v => Math.max(0, -v));
    const weightTotal = lossWeights.reduce((sum, v) => sum + v, 0);
    const shares = weightTotal > 0
        ? lossWeights.map(v => v / weightTotal)
        : actual.map(() => (actual.length ? 1 / actual.length : 0));
    const ctx = document.getElementById('whatIfChart').getContext('2d');
    if (charts.whatif) charts.whatif.destroy();

    charts.whatif = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: actualLabels,
            datasets: [
                {
                    label: 'Actual Profit ($)',
                    data: actual,
                    backgroundColor: (ctx) => ctx.raw < 0 ? '#f43f5e' : '#10b981',
                    borderRadius: 4
                },
                {
                    label: `Simulated Profit with ${capVal}% Cap ($)`,
                    data: actual.map((v, i) => v + recovered * shares[i]),
                    backgroundColor: (ctx) => ctx.raw < 0 ? '#f43f5e' : '#10b981',
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: {
                        color: '#94a3b8',
                        generateLabels: (chart) => {
                            const colors = ['#0284c7', '#10b981'];
                            return chart.data.datasets.map((ds, i) => ({
                                text: ds.label,
                                datasetIndex: i,
                                fillStyle: colors[i],
                                strokeStyle: colors[i],
                                fontColor: '#94a3b8',
                                lineWidth: 0,
                                hidden: !chart.isDatasetVisible(i)
                            }));
                        }
                    }
                }
            },
            scales: {
                x: { ticks: { color: '#94a3b8' }, grid: { display: false } },
                y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.05)' } }
            }
        }
    });
}
