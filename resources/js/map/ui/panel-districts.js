/**
 * "Representatives" section at the top of the state panel: the state's U.S.
 * Senators and every congressional district with its current representative.
 * Lives outside #panel-candidates so it stays put while a district's own
 * details load below it (same pattern as the stats / running-candidates
 * sections), and clicking a row does exactly what clicking the district on
 * the map does.
 */
import { STATE_ABBR_MAP, PARTY_HEX, DISTRICT_COUNTS, DISTRICT_PARTY_MAP, REGIONS, stateToRegion } from '../config/constants.js';
import { districtMeshes, selectDistrict } from '../scene/district-overlay.js';
import { openDistrictPanel } from './panel-district.js';
import { renderCandidate } from './panel-state.js';

/** Districts shown before "Show all" — keeps senators, stats and offices in reach on big states. */
const VISIBLE_ROWS = 8;

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

/** house_candidates keys arrive as "CA-33" or "CA-3" depending on the source row. */
function houseCandidates(data, abbr, num) {
    const map = data?.house_candidates;
    if (!map) return [];
    if (num === 'AL') return map[`${abbr}-AL`] ?? [];
    return map[`${abbr}-${String(num).padStart(2, '0')}`] ?? map[`${abbr}-${num}`] ?? [];
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
    const cands = houseCandidates(data, abbr, num);
    const rep = cands.find(c => c.status === 'seated') ?? cands[0] ?? null;
    const party = DISTRICT_PARTY_MAP[`${abbr}-${num}`] || partyCode(rep?.party) || 'U';
    const code = num === 'AL' ? `${abbr}-AL` : `${abbr}-${String(num).padStart(2, '0')}`;
    const label = num === 'AL' ? 'At-Large' : `District ${num}`;
    return `<button type="button" class="dist-row" data-district="${num}" data-label="${esc(label)}" data-party="${party}"
            aria-label="${esc(`${code}${rep ? ' — ' + rep.full_name : ''}`)}">
        <span class="dist-dot" style="background:${PARTY_HEX[party] || PARTY_HEX.U};"></span>
        <span class="dist-code">${code}</span>
        <span class="dist-rep${rep ? '' : ' dist-rep-empty'}">${rep ? esc(rep.full_name) : 'No record yet'}</span>
        <span class="dist-party" style="color:${PARTY_HEX[party] || PARTY_HEX.U};">${party === 'U' ? '' : party}</span>
    </button>`;
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

    const senators = seatedSenators(data);
    const senatorsHtml = senators.length
        ? `<p class="pd-label">U.S. Senators</p>${senators.map(c => renderCandidate({ ...c, office: 'U.S. Senator' }, color)).join('')}`
        : '';

    const noun = nums.length === 1 ? 'At-large district' : `${nums.length} districts`;
    const rows = nums.map(n => districtRow(abbr, n, data)).join('');
    const needsToggle = nums.length > VISIBLE_ROWS;

    el.innerHTML = `<div class="pd-section" data-state="${esc(stateName)}" style="--pd-accent:${color};">
        <h3 class="pd-title">Districts &amp; representatives</h3>
        ${senatorsHtml}
        <p class="pd-label">U.S. House · ${noun}</p>
        <div class="pd-list${needsToggle ? ' pd-limited' : ''}" id="pd-list">${rows}</div>
        ${needsToggle ? `<button type="button" class="pd-more" id="pd-more" aria-expanded="false" aria-controls="pd-list">Show all ${nums.length} districts</button>` : ''}
    </div>`;
}

export function clearDistrictsPanel() {
    const el = document.getElementById('panel-districts');
    if (el) el.innerHTML = '';
}

/** Highlight a district's row in the list (called for every way a district gets opened). */
export function markActiveDistrictRow(districtNum) {
    for (const row of document.querySelectorAll('#panel-districts .dist-row')) {
        const on = row.dataset.district === String(districtNum);
        row.classList.toggle('active', on);
        if (on) row.setAttribute('aria-current', 'true'); else row.removeAttribute('aria-current');
    }
}

/**
 * Wire row + "Show all" clicks once (delegated on #panel-districts, so the
 * innerHTML re-renders above never need re-binding).
 */
export function initDistrictsPanel() {
    const host = document.getElementById('panel-districts');
    if (!host) return;

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
        const meshes = districtMeshes.filter(m => m.userData.districtNum === num);
        const stateName = row.closest('.pd-section').dataset.state;
        if (meshes.length) selectDistrict(meshes);
        const regionHex = meshes[0]?.userData.regionHex ?? REGIONS[stateToRegion[stateName]]?.hex;
        openDistrictPanel(num, row.dataset.label, stateName, regionHex, row.dataset.party);
    });
}
