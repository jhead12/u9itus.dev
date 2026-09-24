import { escapeComparisonText as esc, initialComparisonSelection, renderComparison, comparisonPageUrl } from './candidate-comparison.js';

const section = document.getElementById('saved-election-comparisons');
const dialog = document.getElementById('saved-comparison-dialog');
const select = document.getElementById('saved-comparison-seat');
const body = document.getElementById('saved-comparison-body');
let revision = 0;
let races = [];
const selections = new Map();

function renderSeat() {
    const race = races.find(r => r.key === select.value);
    if (!race) return;
    if (!selections.has(race.key)) selections.set(race.key, initialComparisonSelection(race.data));
    body.innerHTML = renderComparison(race.data, selections.get(race.key));
    const url = comparisonPageUrl(race.data, selections.get(race.key));
    if (url) body.insertAdjacentHTML('beforeend', `<p><a href="${esc(url)}">Open full comparison</a></p>`);
}

/** Uses the same server election gate as the map tab, for voters and guests. */
export async function refreshSavedComparisons(boundaries) {
    if (!section || !dialog) return;
    const current = ++revision;
    section.hidden = true;
    const districts = boundaries.filter(b => b.type === 'district');
    const fullLinks = districts.map(b => `<p><a href="${esc('/compare?' + new URLSearchParams({ state: b.stateAbbr, district: `${b.stateAbbr}-${b.districtNum}`, office: 'U.S. Representative' }))}">Open full comparison · ${esc(b.label)}</a></p>`).join('');
    section.innerHTML = fullLinks;
    section.hidden = !districts.length;
    const results = await Promise.all(districts.map(async b => {
        const params = new URLSearchParams({ state: b.stateAbbr, district: `${b.stateAbbr}-${b.districtNum}`, office: 'U.S. Representative' });
        try {
            const response = await fetch(`/api/v1/map/candidate-comparison?${params}`, {
                headers: { Accept: 'application/json' }, signal: AbortSignal.timeout(10000),
            });
            if (!response.ok) return null;
            const data = await response.json();
            return data.available === true ? { key: `${b.stateAbbr}-${b.districtNum}`, label: b.label, data } : null;
        } catch { return null; }
    }));
    if (current !== revision) return;
    races = results.filter(Boolean);
    if (!races.length) {
        if (dialog.open) dialog.close();
        return;
    }
    const previous = select.value;
    select.innerHTML = races.map(r => `<option value="${esc(r.key)}">${esc(r.label)} · ${esc(r.data.election.date)}</option>`).join('');
    if (races.some(r => r.key === previous)) select.value = previous;
    section.innerHTML = `<div class="lp-section">Compare candidates</div><p class="lp-saved-empty">${races.length} saved district${races.length === 1 ? '' : 's'} with an election in the next 90 days.</p><button type="button" class="lp-chip" id="open-saved-comparison">Compare saved districts</button>${fullLinks}`;
    section.hidden = false;
    document.getElementById('open-saved-comparison').addEventListener('click', () => {
        renderSeat();
        dialog.showModal();
    });
    if (dialog.open) renderSeat();
}

select?.addEventListener('change', renderSeat);
document.getElementById('saved-comparison-close')?.addEventListener('click', () => dialog.close());
body?.addEventListener('change', event => {
    const input = event.target.closest('[data-compare-key]');
    if (!input) return;
    const selected = new Set(selections.get(select.value) || []);
    if (input.checked && selected.size < 3) selected.add(input.dataset.compareKey);
    else selected.delete(input.dataset.compareKey);
    selections.set(select.value, [...selected]);
    renderSeat();
    [...body.querySelectorAll('[data-compare-key]')].find(el => el.dataset.compareKey === input.dataset.compareKey)?.focus();
});
// Keep map shortcuts from consuming typing, arrows, or Escape inside the modal.
dialog?.addEventListener('keydown', event => event.stopPropagation());
