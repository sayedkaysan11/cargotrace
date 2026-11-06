const converter = document.getElementById('currency-converter');

if (converter) {
    const amountInput = document.getElementById('currency-amount');
    const fromSelect = document.getElementById('currency-from');
    const toSelect = document.getElementById('currency-to');
    const swapButton = document.getElementById('currency-swap');
    const result = document.getElementById('currency-result');
    const status = document.getElementById('currency-rate-status');
    const locale = document.documentElement.lang || navigator.language || 'en';

    const formatCurrency = (amount, currency) => new Intl.NumberFormat(locale, {
        style: 'currency',
        currency,
        maximumFractionDigits: amount !== 0 && Math.abs(amount) < 1 ? 8 : 2,
    }).format(amount);

    const currencyName = (code, displayNames) => {
        try {
            return displayNames.of(code) || code;
        } catch {
            return code;
        }
    };

    const convert = (rates) => {
        const amount = Number(amountInput.value);
        const fromRate = rates[fromSelect.value];
        const toRate = rates[toSelect.value];

        if (!Number.isFinite(amount) || amount < 0 || !fromRate || !toRate) {
            result.textContent = 'Enter a valid amount to convert.';
            return;
        }

        result.textContent = formatCurrency((amount / fromRate) * toRate, toSelect.value);
    };

    const loadRates = async () => {
        try {
            const response = await fetch(converter.dataset.endpoint, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });
            if (!response.ok) throw new Error(`Exchange-rate request failed (${response.status}).`);

            const data = await response.json();
            if (data.base !== 'USD' || !data.rates || typeof data.rates !== 'object') {
                throw new Error('Exchange-rate response did not contain usable rates.');
            }

            let displayNames;
            try {
                displayNames = new Intl.DisplayNames([locale], { type: 'currency' });
            } catch {
                displayNames = { of: (code) => code };
            }
            const currencies = Object.keys(data.rates)
                .sort((first, second) => currencyName(first, displayNames).localeCompare(currencyName(second, displayNames), locale));

            [fromSelect, toSelect].forEach((select) => {
                const fragment = document.createDocumentFragment();
                currencies.forEach((code) => {
                    const option = document.createElement('option');
                    option.value = code;
                    option.textContent = `${code} — ${currencyName(code, displayNames)}`;
                    fragment.append(option);
                });
                select.replaceChildren(fragment);
                select.disabled = false;
            });

            fromSelect.value = data.rates.IDR ? 'IDR' : data.base;
            toSelect.value = data.rates.USD ? 'USD' : data.base;
            swapButton.disabled = false;
            converter.setAttribute('aria-busy', 'false');
            status.textContent = data.is_stale
                ? `Provider unavailable; using previously synchronized rates from ${new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'short', timeZone: 'UTC' }).format(new Date(data.updated_at))} UTC.`
                : `Reference rates updated ${new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'short', timeZone: 'UTC' }).format(new Date(data.updated_at))} UTC.`;
            convert(data.rates);

            [amountInput, fromSelect, toSelect].forEach((control) => {
                control.addEventListener('input', () => convert(data.rates));
                control.addEventListener('change', () => convert(data.rates));
            });
            swapButton.addEventListener('click', () => {
                [fromSelect.value, toSelect.value] = [toSelect.value, fromSelect.value];
                convert(data.rates);
            });
        } catch (error) {
            converter.setAttribute('aria-busy', 'false');
            result.textContent = 'Conversion is unavailable right now.';
            status.textContent = 'Could not load exchange rates. Check your connection and try again later.';
            converter.classList.add('has-error');
            console.error(error);
        }
    };

    loadRates();
}
