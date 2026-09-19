/**
 * "Districts & representatives" list at the top of the state panel: the state's
 * U.S. Senators and every congressional district with its current
 * representative. It is the alternative to clicking the map — small and dense
 * districts are hard to hit — so it is searchable (number, representative,
 * candidate, party), previews a district on the map as you hover a row, and is
 * fully keyboard-navigable.
 *
 * Lives outside #panel-candidates so it stays put while a district's own
 * details load below it, and clicking a row does exactly what clicking the
 * district on the map does. Rows render from static district counts, so the
 * list is usable before the Census boundaries (or the candidate payload) load;
 * the status strip (#pd-status, top of the panel) says what is still loading and
 * offers Retry if it fails.
 */
import { STATE_ABBR_MAP, PARTY_HEX, DISTRICT_COUNTS, DISTRICT_PARTY_MAP, REGIONS, stateToRegion } from '../config/constants.js';
import { selectDistrict, meshesForDistrict, previewDistrict, DISTRICT_MIN_DIST } from '../scene/district-overlay.js';
import { flyToMeshesTopDown } from '../scene/camera-animation.js';
import { openDistrictPanel } from './panel-district.js';
import { renderCandidate } from './panel-state.js';
import { districtCode, houseCandidatesFor, seatedMember } from '../utils/district-keys.js';

/** Districts shown before "Show all" — keeps senators, stats and offices in reach on big states. */
const VISIBLE_ROWS = 8;
/** Below this many districts a search box is just clutter. */
const SEARCH_MIN_DISTRICTS = 6;

const PARTY_NAMES = { D: 'democratic democrat', R: 'republican', I: 'independent', L: 'libertarian', G: 'green' };

/** 'idle' | 'loading' | 'ready' | 'error' — survives re-renders of the panel. */
let boundaryStatus = 'idle';

function esc(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function partyCode(partyName) {
    const p = (partyName || '').toLowerCase();
    if (p.includes('democrat')) return 'D';
    if (p.includes('republican')) return 'R';
    if (p.includes('libertarian')) return 'L';
    if (p.includes('green')) return 'G';
    if (p.includes('independent')) return 'I';
    return null;
}

function districtNumbers(stateName) {
    const count = DISTRICT_COUNTS[stateName] || 0;
    if (count === 1) return ['AL'];
    return Array.from({ length: count }, (_, i) => String(i + 1));
}

function seatedSenators(data) {
    const group = (data?.offices ?? []).find(g => g.office === 'U.S. Senators');
    return (group?.candidates ?? []).filter(c => c.status === 'seated' || (c.status === 'active' && !c.is_running));
}

function districtRow(abbr, num, data) {
    const cands = houseCandidatesFor(data, abbr, num);
    const rep = seatedMember(cands) ?? cands[0] ?? null;
    const party = DISTRICT_PARTY_MAP[`${abbr}-${num}`] || partyCode(rep?.party) || 'U';
    const code = districtCode(abbr, num);
    const label = num === 'AL' ? 'At-Large' : `District ${num}`;
    // Everything a person might type to find this row: code, number, every listed name, party.
    const haystack = [code, num === 'AL' ? 'at-large at large' : `district ${num}`, ...cands.map(c => c.full_name), PARTY_NAMES[party] || '']
        .join(' ').toLowerCase();
    return `<button type="button" class="dist-row" data-district="${num}" data-label="${esc(label)}" data-party="${party}"
            data-code="${code.toLowerCase()}" data-search="${esc(haystack)}"
            aria-label="${esc(`${code}${rep ? ' — ' + rep.full_name : ''}`)}">
        <span class="dist-dot" style="background:${PARTY_HEX[party] || PARTY_HEX.U};"></span>
        <span class="dist-code">${code}</span>
        <span class="dist-rep${rep ? '' : ' dist-rep-empty'}">${rep ? esc(rep.full_name) : 'No record yet'}</span>
        <span class="dist-party" style="color:${PARTY_HEX[party] || PARTY_HEX.U};">${party === 'U' ? '' : party}</span>
    </button>`;
}

const STATUS_COPY = {
    loading: 'Loading district boundaries — the list and candidates are ready now.',
    error: 'Couldn’t load the district boundaries, so the map shapes are missing. The list still works.',
};

function statusHtml() {
    if (boundaryStatus === 'loading') {
        return `<span class="pd-spinner" aria-hidden="true"></span><span>${STATUS_COPY.loading}</span>`;
    }
    if (boundaryStatus === 'error') {
        return `<span aria-hidden="true">⚠</span><span>${STATUS_COPY.error}</span><button type="button" class="pd-retry" id="pd-retry">Retry</button>`;
    }
    return '';
}

/**
 * Tell the list what the boundary layer is doing. 'ready' and 'idle' hide the strip.
 * @param {'idle'|'loading'|'ready'|'error'} status
 */
export function setBoundaryStatus(status) {
    boundaryStatus = status;
    const el = document.getElementById('pd-status');
    if (!el) return;
    el.innerHTML = statusHtml();
    el.hidden = !el.innerHTML;
    el.classList.toggle('pd-status-error', status === 'error');
}

/**
 * @param {string} stateName
 * @param {string} color  region accent
 * @param {Object|null} data  state-candidates payload, or null while it loads
 */
export function renderDistrictsPanel(stateName, color, data = null) {
    const el = document.getElementById('panel-districts');
    if (!el) return;
    const abbr = STATE_ABBR_MAP[stateName];
    const nums = districtNumbers(stateName);
    if (!abbr || !nums.length) { el.innerHTML = ''; return; }

    // A late payload re-renders the list; don't eat what the person was typing.
    const prevSearch = document.getElementById('pd-search');
    const prevQuery = prevSearch?.value ?? '';
    const hadFocus = !!prevSearch && document.activeElement === prevSearch;

    const senators = seatedSenators(data);
    const senatorsHtml = senators.length
        ? `<p class="pd-label">U.S. Senators</p>${senators.map(c => renderCandidate({ ...c, office: 'U.S. Senator' }, color)).join('')}`
        : '';

    const noun = nums.length === 1 ? 'At-large district' : `${nums.length} districts`;
    const rows = nums.map(n => districtRow(abbr, n, data)).join('');
    const needsToggle = nums.length > VISIBLE_ROWS;
    const searchable = nums.length >= SEARCH_MIN_DISTRICTS;
    const inDistrictView = document.getElementById('info-panel')?.dataset.view === 'district';

    el.innerHTML = `<details class="pd-section" data-state="${esc(stateName)}" style="--pd-accent:${color};"${inDistrictView ? '' : ' open'}>
        <summary class="pd-title">${inDistrictView ? 'Switch district' : 'Districts &amp; representatives'}</summary>
        ${senatorsHtml}
        <p class="pd-label">U.S. House · ${noun}</p>
        ${searchable ? `<div class="pd-search-wrap">
            <input type="search" id="pd-search" class="pd-search" placeholder="Search district, representative, or party"
                   aria-label="Search districts and representatives" aria-controls="pd-list" autocomplete="off" spellcheck="false">
        </div>
        <p class="pd-count" id="pd-count" aria-live="polite" hidden></p>` : ''}
        <div class="pd-list${needsToggle ? ' pd-limited' : ''}" id="pd-list">${rows}</div>
        ${needsToggle ? `<button type="button" class="pd-more" id="pd-more" aria-expanded="false" aria-controls="pd-list">Show all ${nums.length} districts</button>` : ''}
    </details>`;

    if (prevQuery) {
        const input = document.getElementById('pd-search');
        if (input) {
            input.value = prevQuery;
            applyDistrictFilter();
            if (hadFocus) input.focus();
        }
    }
}

export function clearDistrictsPanel() {
    const el = document.getElementById('panel-districts');
    if (el) el.innerHTML = '';
    setBoundaryStatus('idle');
}

/**
 * Highlight a district's row in the list (called for every way a district gets
 * opened). A district picked on the map may sit below the trimmed first rows;
 * expand the list so the highlighted row is never hidden.
 */
export function markActiveDistrictRow(districtNum) {
    let active = null;
    for (const row of document.querySelectorAll('#panel-districts .dist-row')) {
        const on = row.dataset.district === String(districtNum);
        row.classList.toggle('active', on);
        if (on) { row.setAttribute('aria-current', 'true'); active = row; } else row.removeAttribute('aria-current');
    }

    revealActiveRow();
}

/** Un-trim the list when the highlighted row would otherwise be hidden by the "Show all" limit. */
function revealActiveRow() {
    const list = document.getElementById('pd-list');
    const active = list?.querySelector('.dist-row.active');
    if (!active || !list.classList.contains('pd-limited') || list.classList.contains('pd-filtering')) return;
    if ([...list.children].indexOf(active) < VISIBLE_ROWS) return;
    list.classList.remove('pd-limited');
    const more = document.getElementById('pd-more');
    if (more) { more.setAttribute('aria-expanded', 'true'); more.textContent = 'Show fewer'; }
}

/**
 * Does a row match what was typed? A bare number means that district
 * ("3" → District 3, not 13/23/30…); "ca-38" and "d38" work too; anything else
 * is a substring match on every word (representative, candidate, party).
 */
function rowMatches(row, query) {
    const q = query.trim().toLowerCase();
    if (!q) return true;

    const num = /^(?:district\s*|dist\.?\s*|d\s*)?#?(\d{1,2})$/.exec(q);
    if (num) return String(parseInt(num[1], 10)) === row.dataset.district;
    if (/^[a-z]{2}-(?:\d{1,2}|al)$/.test(q)) {
        return row.dataset.code === q.replace(/-(\d)$/, '-0$1');
    }
    return q.split(/\s+/).every(word => row.dataset.search.includes(word));
}

function applyDistrictFilter() {
    const input = document.getElementById('pd-search');
    const list = document.getElementById('pd-list');
    const count = document.getElementById('pd-count');
    if (!input || !list) return;

    const query = input.value;
    const filtering = query.trim() !== '';
    list.classList.toggle('pd-filtering', filtering);

    let shown = 0;
    const rows = [...list.querySelectorAll('.dist-row')];
    for (const row of rows) {
        const ok = rowMatches(row, query);
        row.hidden = !ok;
        if (ok) shown++;
    }

    if (count) {
        count.hidden = !filtering;
        count.textContent = shown
            ? `${shown} of ${rows.length} districts`
            : `No district matches “${query.trim()}”. Try a number, a name, or a party.`;
        count.classList.toggle('pd-count-empty', filtering && !shown);
    }
    const more = document.getElementById('pd-more');
    if (more) more.hidden = filtering;
    if (!filtering) revealActiveRow();
}

function visibleRows() {
    return [...document.querySelectorAll('#pd-list .dist-row')].filter(r => !r.hidden && r.offsetParent !== null);
}

function meshesForRow(row) {
    return meshesForDistrict(row.dataset.district);
}

/**
 * Wire the list once (delegated on #panel-districts, so the innerHTML
 * re-renders above never need re-binding).
 */
export function initDistrictsPanel() {
    const host = document.getElementById('panel-districts');
    if (!host) return;

    // The status strip sits outside the list (it must stay visible when the list is folded).
    document.getElementById('pd-status')?.addEventListener('click', (e) => {
        if (e.target.closest('#pd-retry')) document.dispatchEvent(new CustomEvent('u9:retry-boundaries'));
    });

    host.addEventListener('click', (e) => {
        const more = e.target.closest('#pd-more');
        if (more) {
            const list = document.getElementById('pd-list');
            const open = list.classList.toggle('pd-limited') === false;
            more.setAttribute('aria-expanded', String(open));
            more.textContent = open ? 'Show fewer' : `Show all ${list.children.length} districts`;
            return;
        }

        const row = e.target.closest('.dist-row');
        if (!row) return;
        e.stopPropagation();

        const num = row.dataset.district;
        const meshes = meshesForRow(row);
        const stateName = row.closest('.pd-section').dataset.state;
        previewDistrict(null);
        if (meshes.length) {
            selectDistrict(meshes);
            // Frame the district: a list pick is usually a district that was too small to click.
            flyToMeshesTopDown(meshes, 2.6, DISTRICT_MIN_DIST);
        }
        const regionHex = meshes[0]?.userData.regionHex ?? REGIONS[stateToRegion[stateName]]?.hex;
        openDistrictPanel(num, row.dataset.label, stateName, regionHex, row.dataset.party);
    });

    host.addEventListener('input', (e) => {
        if (e.target.id === 'pd-search') applyDistrictFilter();
    });

    host.addEventListener('keydown', (e) => {
        if (e.target.id === 'pd-search') {
            if (e.key === 'Escape' && e.target.value) {
                e.target.value = '';
                applyDistrictFilter();
                e.stopPropagation();
            } else if (e.key === 'ArrowDown') {
                visibleRows()[0]?.focus();
                e.preventDefault();
            } else if (e.key === 'Enter') {
                visibleRows()[0]?.click();
            }
            return;
        }

        const row = e.target.closest?.('.dist-row');
        if (!row || (e.key !== 'ArrowDown' && e.key !== 'ArrowUp')) return;
        const rows = visibleRows();
        const at = rows.indexOf(row);
        const next = rows[at + (e.key === 'ArrowDown' ? 1 : -1)];
        if (next) next.focus();
        else if (e.key === 'ArrowUp') document.getElementById('pd-search')?.focus();
        e.preventDefault();
        e.stopPropagation(); // arrows also tilt the camera globally (keyboard.js)
    });

    // Hover/focus a row → light the district up on the map without selecting it.
    const preview = (e) => {
        const row = e.target.closest?.('.dist-row');
        if (row) previewDistrict(meshesForRow(row));
    };
    const unpreview = (e) => {
        if (e.target.closest?.('.dist-row')) previewDistrict(null);
    };
    host.addEventListener('mouseover', preview);
    host.addEventListener('focusin', preview);
    host.addEventListener('mouseout', unpreview);
    host.addEventListener('focusout', unpreview);
}
