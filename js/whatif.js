function runWhatIfSimulation() {
    const capVal = parseInt(document.getElementById('discountCapSlider').value);
    document.getElementById('sliderCapVal').textContent = `${capVal}% Cap`;

    // Baseline from data.js totals
    let baselineProfit = totals.totalProfit;

    // Recover $1,450 per discount point cut on loss-makers, keep 90% after ~10% customer volume drop
    let recoveredAmount = (40 - capVal) * 1450 * 0.90;

    if (capVal >= 40) recoveredAmount = 0;

    const newProfit = baselineProfit + recoveredAmount;
    const newMargin = ((newProfit / totals.totalSales) * 100).toFixed(2);

    document.getElementById('whatifProfitGain').textContent = `+$${Math.round(recoveredAmount).toLocaleString()}`;
    document.getElementById('whatifNewProfit').textContent = `$${Math.round(newProfit).toLocaleString()}`;
    document.getElementById('whatifNewMargin').textContent = `${newMargin}%`;

    const baselineEl = document.getElementById('whatifBaselineProfit');
    if (baselineEl) baselineEl.textContent = `$${Math.round(baselineProfit).toLocaleString()}`;

    renderWhatIfChart(capVal, recoveredAmount);
}