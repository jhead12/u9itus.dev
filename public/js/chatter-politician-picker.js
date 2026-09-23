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

        function render() {
            const words = normalize(search.value).trim().split(/\s+/).filter(Boolean);
            const matches = candidates.filter(candidate => words.every(word => candidate.search.includes(word)));
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
            render();
        }, 0));
        render();
    });
})();
