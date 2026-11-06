const estimator = document.getElementById('shipping-estimator');

if (estimator) {
    const form = estimator.closest('form');
    const mode = document.getElementById('estimate-mode');
    const weightOutput = document.getElementById('estimate-chargeable-weight');
    const volumeOutput = document.getElementById('estimate-chargeable-volume');
    const priceOutput = document.getElementById('estimate-price');
    const basisOutput = document.getElementById('estimate-basis');
    const scenario = document.getElementById('scenario');
    const priceRanges = {
        air: { low: 2, high: 5, factor: 'weight', label: 'chargeable kg' },
        sea: { low: 100, high: 250, factor: 'volume', label: 'billable CBM' },
    };
    const scenarioFactors = { A: 1, B: 1.35, C: 1.5 };
    const usd = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 2 });
    const total = (selector) => [...form.querySelectorAll(selector)]
        .reduce((sum, input) => sum + (Number(input.value) || 0), 0);

    const recalculate = () => {
        const weight = total('input[name$="[weight_kg]"]');
        const volume = total('input[name$="[volume_cbm]"]');
        const chargeableWeight = Math.max(weight, volume * 167);
        const billableVolume = Math.max(volume, weight / 1000);
        const selected = priceRanges[mode.value];
        const factor = scenarioFactors[scenario.value] || scenarioFactors.A;

        weightOutput.textContent = `${chargeableWeight.toLocaleString(undefined, { maximumFractionDigits: 2 })} kg`;
        volumeOutput.textContent = `${billableVolume.toLocaleString(undefined, { maximumFractionDigits: 3 })} CBM`;

        if (weight <= 0 && volume <= 0) {
            priceOutput.textContent = 'Add cargo weight or volume';
            basisOutput.textContent = 'Estimate appears when cargo details are entered.';
            return;
        }

        const units = selected.factor === 'weight'
            ? Math.max(chargeableWeight, 1)
            : Math.max(billableVolume, 1);
        const low = units * selected.low * factor;
        const high = units * selected.high * factor;

        priceOutput.textContent = `${usd.format(low)} – ${usd.format(high)}`;
        basisOutput.textContent = `${units.toLocaleString(undefined, { maximumFractionDigits: 2 })} ${selected.label} × indicative market range × service category.`;
    };

    mode.addEventListener('change', recalculate);
    scenario.addEventListener('change', recalculate);
    form.addEventListener('input', (event) => {
        if (event.target.matches('input[name$="[weight_kg]"], input[name$="[volume_cbm]"]')) recalculate();
    });

    recalculate();
}
