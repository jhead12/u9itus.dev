(() => {
    const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

    document.querySelectorAll('[data-politician-picker]').forEach(picker => {
        const search = picker.querySelector('[data-politician-search]');
        const select = picker.querySelector('[data-politician-results]');
        const status = picker.querySelector('[data-politician-status]');
        const candidates = Array.from(select.options).filter(option => option.value).map(option => ({
            value: option.value, label: option.textContent, search: normalize(option.textContent),
        }));
        const initialSelection = select.value;
        let selected = initialSelection;
        let districtFilterIds = null;

        function render() {
            const words = normalize(search.value).trim().split(/\s+/).filter(Boolean);
            let matches = candidates.filter(candidate => words.every(word => candidate.search.includes(word)));
            if (districtFilterIds) matches = matches.filter(candidate => districtFilterIds.has(candidate.value));
            const current = candidates.find(candidate => candidate.value === selected);
            const pinned = current && !matches.includes(current);
            const visible = pinned ? [current, ...matches] : matches;
            select.replaceChildren(new Option('Select a politician', ''));
            visible.forEach(candidate => select.add(new Option(
                `${candidate.label}${pinned && candidate === current ? ' (selected)' : ''}`, candidate.value,
            )));
            select.value = selected;
            // Multi-row native selects treat the empty option as a selection.
            select.setCustomValidity(selected ? '' : 'Choose a politician from the results.');
            status.textContent = matches.length
                ? `${matches.length} candidate${matches.length === 1 ? '' : 's'} found. Select a result below the search field.`
                : 'No matching candidates. Try another name or state abbreviation.';
            if (pinned) status.textContent += ' Your selected candidate is kept at the top.';
        }

        picker.querySelector('[data-politician-search-label]').hidden = false;
        select.size = 5;
        search.addEventListener('input', render);
        search.addEventListener('keydown', event => {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                select.focus();
            }
            if (event.key === 'Enter') event.preventDefault();
            if (event.key === 'Escape') {
                search.value = '';
                render();
            }
        });
        select.addEventListener('change', () => { selected = select.value; render(); });
        picker.closest('form')?.addEventListener('reset', () => setTimeout(() => {
            selected = initialSelection;
            districtFilterIds = null;
            if (districtClear) districtClear.hidden = true;
            if (districtStatus) districtStatus.textContent = '';
            render();
        }, 0));

        const lookupUrl = picker.dataset.districtLookupUrl;
        const districtCity = picker.querySelector('[data-district-city]');
        const districtState = picker.querySelector('[data-district-state]');
        const districtFind = picker.querySelector('[data-district-find]');
        const districtClear = picker.querySelector('[data-district-clear]');
        const districtStatus = picker.querySelector('[data-district-status]');

        if (lookupUrl && districtFind) {
            async function findByAddress() {
                const address = districtCity.value.trim();
                if (!address) {
                    districtStatus.textContent = 'Enter an address to look up its district.';
                    return;
                }
                districtFind.disabled = true;
                districtStatus.textContent = 'Looking up district…';
                try {
                    const params = new URLSearchParams({ city: address });
                    const state = districtState.value.trim();
                    if (state) params.set('state', state);
                    const response = await fetch(`${lookupUrl}?${params}`, { headers: { Accept: 'application/json' } });
                    const data = await response.json();
                    if (!data.resolved) {
                        districtFilterIds = null;
                        districtClear.hidden = true;
                        districtStatus.textContent = data.message || "Could not resolve that address to a district.";
                        render();
                        return;
                    }
                    districtFilterIds = new Set((data.ids || []).map(String));
                    districtClear.hidden = false;
                    const label = data.district_label ? ` (${data.district_label})` : '';
                    districtStatus.textContent = data.message ? data.message + label : `Showing candidates for that address's district${label}.`;
                    render();
                } catch (error) {
                    districtStatus.textContent = 'District lookup failed. Try again.';
                } finally {
                    districtFind.disabled = false;
                }
            }
            districtFind.addEventListener('click', findByAddress);
            districtCity.addEventListener('keydown', event => {
                if (event.key === 'Enter') { event.preventDefault(); findByAddress(); }
            });
            districtClear.addEventListener('click', () => {
                districtFilterIds = null;
                districtCity.value = '';
                districtState.value = '';
                districtClear.hidden = true;
                districtStatus.textContent = '';
                render();
            });
        }

        render();
    });
})();
