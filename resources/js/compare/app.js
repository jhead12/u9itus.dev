import './compare.css';
import { guideDate, guideFileName, renderPrintGuide, roleText } from './print-guide.js';
import { escapeComparisonText as esc, initialComparisonSelection, renderComparison } from '../map/ui/candidate-comparison.js';

const el = id => document.getElementById(`comparison-${id}`);
const fields = ['state', 'full_name', 'id', 'slug', 'office', 'district', 'city'];
let data = null;
let selected = [];
let context = new URLSearchParams();
let revision = 0;
let searchRevision = 0;

function cleanContext(params) {
    const clean = new URLSearchParams();
    fields.forEach(key => { if (params.get(key)) clean.set(key, params.get(key)); });
    return clean;
}
function updateUrl() {
    const params = new URLSearchParams(context);
    params.set('selected', selected.join(','));
    history.replaceState(null, '', `/compare?${params}`);
    el('url').textContent = location.href;
    el('link').value = location.href;
    el('qr').src = `/compare/qr?${new URLSearchParams({ query: params.toString() })}`;
}
function render() {
    el('content').innerHTML = renderComparison(data, selected, { basic: true });
    const role = roleText(data.seat?.role);
    if (role) {
        const note = role.sourceUrl ? `<a href="${esc(role.sourceUrl)}" target="_blank" rel="noopener noreferrer">${esc(role.note)}</a>` : esc(role.note);
        el('content').insertAdjacentHTML('afterbegin', `<aside class="comparison-role" aria-labelledby="comparison-role-heading"><h2 id="comparison-role-heading">What does this office do?</h2><p><strong>${esc(role.heading)}.</strong> ${esc(role.description)}</p><p class="comparison-role-note">${note}</p><p><a href="${esc(data.seat.role.glossary_url)}">See what each office does</a></p></aside>`);
    }
    const missing = selected.filter(key => !data.candidates.some(c => c.key === key));
    if (missing.length) {
        el('content').insertAdjacentHTML('afterbegin', `<p role="status" class="comparison-warning">Selected records unavailable: ${missing.map(esc).join(', ')}. No replacements have been selected. <button type="button" id="comparison-clear-missing">Remove unavailable selections</button></p>`);
        el('clear-missing').onclick = () => { selected = selected.filter(key => !missing.includes(key)); render(); };
    }
    const guide = renderPrintGuide(data, selected);
    el('print-guide').innerHTML = guide.html;
    el('source-heading').textContent = `${guide.sourceSection || 1}. Where can I check the sources?`;
    el('sources').innerHTML = guide.sources.length
        ? guide.sources.map(source => `<li><strong>${esc(source.label)}</strong><br>${esc(source.url)}</li>`).join('')
        : '<li class="guide-no-sources">No source links are recorded for the selected people yet. Each record\'s source name and update date are listed in section 1.</li>';
    el('actions').hidden = !data.candidates.length;
    el('print-footer').hidden = !guide.html;
    el('print').disabled = !data.candidates.some(c => selected.includes(c.key));
    updateUrl();
}
async function load(params) {
    const current = ++revision;
    ++searchRevision;
    context = cleanContext(params);
    el('state').value = context.get('state') || '';
    el('status').textContent = 'Loading public records…';
    el('content').innerHTML = '';
    el('print-guide').innerHTML = '';
    el('actions').hidden = true;
    el('print-footer').hidden = true;
    const query = new URLSearchParams(context);
    query.set('context', 'research');
    try {
        const response = await fetch(`/api/v1/map/candidate-comparison?${query}`, { headers: { Accept: 'application/json' }, signal: AbortSignal.timeout(15000) });
        if (!response.ok) throw new Error('Unable to load');
        const result = await response.json();
        if (current !== revision) return;
        data = result;
        for (const field of ['state', 'office', 'district', 'city']) {
            if (data.seat?.[field]) context.set(field, data.seat[field]);
        }
        selected = params.has('selected') ? [...new Set(params.get('selected').split(',').filter(Boolean))].slice(0, 3) : initialComparisonSelection(data);
        el('status').textContent = '';
        el('print-footer').hidden = false;
        render();
    } catch {
        if (current !== revision) return;
        el('status').textContent = 'Comparison data is unavailable right now.';
        el('content').innerHTML = '<button type="button" id="comparison-retry">Try again</button>';
        el('retry').onclick = () => load(params);
    }
}
// Candidate search mirrors the Web Reporter picker: live filtering by name,
// state, office, or party, plus a separate address finder for districts.
const DISTRICT = /^(?:[A-Z]{2}[- ]?)?(?:DISTRICT\s*|CD[- ]?)?\d{1,2}$|^[A-Z]{2}-(?:AL|AT[- ]LARGE)$/i;
let searchTimer = null;
let addressActive = false;

function showResults(result, emptyMessage) {
    const results = result.results ?? [];
    el('results').replaceChildren(...results.map(candidate => {
        const button = document.createElement('button');
        button.type = 'button';
        const context = [candidate.state, candidate.office, candidate.district, candidate.party].filter(Boolean).join(' · ');
        button.textContent = `${candidate.full_name}${context ? ` — ${context}` : ''}`;
        button.onclick = () => {
            el('results').replaceChildren();
            el('search-status').textContent = '';
            const params = new URLSearchParams();
            fields.forEach(key => { if (candidate[key]) params.set(key, candidate[key]); });
            load(params);
        };
        return button;
    }));
    const label = result.district_label ? ` (${result.district_label})` : '';
    el('search-status').textContent = result.message ? result.message
        : results.length ? `${results.length} candidate${results.length === 1 ? '' : 's'} found${label}. Select a result to compare their seat.`
        : emptyMessage;
}
async function search(mode, q) {
    const current = ++searchRevision;
    el('search-status').textContent = mode === 'address' ? 'Looking up district…' : 'Searching candidates…';
    try {
        const params = new URLSearchParams({ q, mode, limit: '25' });
        if (el('state').value) params.set('state', el('state').value);
        const response = await fetch(`/api/v1/map/politician-search?${params}`, { signal: AbortSignal.timeout(mode === 'address' ? 30000 : 10000), headers: { Accept: 'application/json' } });
        const result = await response.json().catch(() => ({}));
        if (current !== searchRevision) return;
        if (!response.ok) {
            el('results').replaceChildren();
            el('search-status').textContent = result.message || (mode === 'address' ? 'District lookup failed. Try again.' : 'Search is unavailable. Please try again.');
            return;
        }
        showResults(result, mode === 'name' ? 'No matching candidates. Try another name or state abbreviation.' : 'No published candidates found for that district yet.');
    } catch {
        if (current === searchRevision) el('search-status').textContent = mode === 'address' ? 'District lookup failed. Try again.' : 'Search is unavailable. Please try again.';
    }
}
/** With no search typed, list the state's seats that have running candidates. */
async function showRaces() {
    const current = ++searchRevision;
    const state = el('state').value;
    el('results').replaceChildren();
    if (!state) { el('search-status').textContent = 'Choose a state to see races with running candidates, or search by name or address.'; return; }
    el('search-status').textContent = `Loading races in ${state}…`;
    try {
        const response = await fetch(`/api/v1/map/candidate-races?${new URLSearchParams({ state })}`, { signal: AbortSignal.timeout(15000), headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Unable to load races');
        const { races = [] } = await response.json();
        if (current !== searchRevision) return;
        el('results').replaceChildren(...races.map(race => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'comparison-race';
            const date = race.election?.date ? guideDate(race.election.date) : null;
            const names = race.running.map(c => `${esc(c.full_name)}${c.party ? ` <span>(${esc(c.party)})</span>` : ''}`).join(', ');
            button.innerHTML = `<strong>${esc(race.label)}</strong><small>${date ? `${esc(race.election.stage || 'Election')} · ${esc(date)}` : 'Election date not confirmed'}</small><span class="comparison-race-names">${race.running.length} running: ${names}</span>`;
            button.onclick = () => {
                el('results').replaceChildren();
                el('search-status').textContent = '';
                load(new URLSearchParams(race.params));
            };
            return button;
        }));
        el('search-status').textContent = races.length
            ? `${races.length} race${races.length === 1 ? '' : 's'} in ${state} with running candidates. Select a race to compare the candidates.`
            : `No races with confirmed running candidates are recorded for ${state} yet. You can still search by name or address.`;
    } catch {
        if (current === searchRevision) el('search-status').textContent = 'Races are unavailable right now. You can still search by name or address.';
    }
}
function searchText() {
    clearTimeout(searchTimer);
    const q = el('query').value.trim();
    if (DISTRICT.test(q)) return search('district', q);
    if (q.length < 2) return showRaces();
    return search('name', q);
}
function findAddress() {
    const address = el('address').value.trim();
    if (!address) { el('search-status').textContent = 'Enter an address to look up its district.'; return; }
    addressActive = true;
    el('address-clear').hidden = false;
    el('address-find').disabled = true;
    Promise.resolve(search('address', address)).finally(() => { el('address-find').disabled = false; });
}
function clearAddress() {
    addressActive = false;
    el('address').value = '';
    el('address-clear').hidden = true;
    searchText();
}
el('search').addEventListener('submit', event => { event.preventDefault(); addressActive ? findAddress() : searchText(); });
el('query').addEventListener('input', () => {
    if (addressActive) { addressActive = false; el('address-clear').hidden = true; }
    clearTimeout(searchTimer);
    searchTimer = setTimeout(searchText, 250);
});
el('query').addEventListener('keydown', event => {
    if (event.key === 'ArrowDown') { event.preventDefault(); el('results').querySelector('button')?.focus(); }
    if (event.key === 'Escape') { el('query').value = ''; searchText(); }
});
el('results').addEventListener('keydown', event => {
    const buttons = [...el('results').querySelectorAll('button')];
    const index = buttons.indexOf(document.activeElement);
    if (event.key === 'ArrowDown') { event.preventDefault(); buttons[index + 1]?.focus(); }
    if (event.key === 'ArrowUp') { event.preventDefault(); (buttons[index - 1] ?? el('query')).focus(); }
});
el('address').addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); findAddress(); } });
el('address-find').addEventListener('click', findAddress);
el('address-clear').addEventListener('click', clearAddress);
el('state').addEventListener('change', () => (addressActive ? findAddress() : searchText()));
el('content').addEventListener('change', event => {
    const input = event.target.closest('[data-compare-key]');
    if (!input) return;
    const key = input.dataset.compareKey;
    if (input.checked && selected.length < 3) selected.push(key);
    else selected = selected.filter(value => value !== key);
    render();
    [...el('content').querySelectorAll('[data-compare-key]')].find(input => input.dataset.compareKey === key)?.focus();
});
el('share').onclick = async () => {
    try { await navigator.clipboard.writeText(location.href); el('status').textContent = 'Comparison link copied.'; }
    catch { el('link-fallback').hidden = false; el('link').focus(); el('link').select(); }
};
// Name the saved PDF after the compared people and today's date; restore afterwards.
const pageTitle = document.title;
window.addEventListener('afterprint', () => { document.title = pageTitle; });
window.addEventListener('beforeprint', () => {
    if (data && selected.length) document.title = guideFileName(data, selected);
    const generated = `Prepared ${new Date().toLocaleString('en-US', { dateStyle: 'long', timeStyle: 'short' })}. Source update dates are listed separately.`;
    el('generated').textContent = generated;
    document.querySelectorAll('[data-guide-generated]').forEach(node => { node.textContent = generated; });
});
el('print').onclick = async () => {
    try { await el('qr').decode(); window.print(); }
    catch { el('status').textContent = 'The print QR code could not load. Please retry printing.'; }
};
window.addEventListener('popstate', () => load(new URLSearchParams(location.search)));
const initial = new URLSearchParams(location.search);
if (initial.has('state')) load(initial);
