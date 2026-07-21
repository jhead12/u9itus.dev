/**
 * Region panel — cities within a region plus Census ACS demographics
 * (poverty rate, educational attainment, median household income).
 */
import { openInfoPanel } from './info-panel.js';
import { noDataNotice } from './panel-state.js';

let _regionReqSeq = 0;
const toastEl = document.getElementById('map-toast');

function showToast(message, type = 'info') {
    if (!toastEl) return;
    toastEl.textContent = message;
    toastEl.className = 'map-toast visible ' + type;
    setTimeout(() => toastEl.classList.remove('visible'), 3500);
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function fmtMoney(n) {
    if (n === null || n === undefined) return '—';
    return '$' + Number(n).toLocaleString('en-US');
}

function fmtPct(n) {
    if (n === null || n === undefined) return '—';
    return Number(n).toFixed(1) + '%';
}

function renderCityCard(city, state, color) {
    const hasDistrict = !!city.district_number;
    const cursor = hasDistrict ? 'cursor:pointer;' : '';
    return `<div class="region-city-card" data-state="${escapeHtml(state)}" data-city="${escapeHtml(city.city)}" data-district="${escapeHtml(city.district_number ?? '')}"
            style="margin-bottom:10px;padding:10px 12px;border-radius:8px;border:1px solid rgba(148,163,184,0.2);background:rgba(15,23,42,0.5);${cursor}"
            ${hasDistrict ? `title="View ${escapeHtml(city.district_code)}"` : ''}>
        <div style="display:flex;justify-content:space-between;align-items:baseline;gap:8px;">
            <span style="font-size:13px;font-weight:700;color:#e2e8f0;">${escapeHtml(city.city)}${hasDistrict ? ` <span style="opacity:.5;font-weight:400;">→ ${escapeHtml(city.district_code)}</span>` : ''}</span>
            <span style="font-size:10px;color:#64748b;white-space:nowrap;">${city.population ? Number(city.population).toLocaleString('en-US') : '—'} people</span>
        </div>
        <div style="display:flex;gap:6px;margin-top:8px;flex-wrap:wrap;">
            <span style="font-size:10px;padding:3px 8px;border-radius:999px;background:${color}18;border:1px solid ${color}44;color:${color};">Poverty ${fmtPct(city.poverty_rate)}</span>
            <span style="font-size:10px;padding:3px 8px;border-radius:999px;background:${color}18;border:1px solid ${color}44;color:${color};">Bachelor's+ ${fmtPct(city.pct_bachelors_or_higher)}</span>
            <span style="font-size:10px;padding:3px 8px;border-radius:999px;background:${color}18;border:1px solid ${color}44;color:${color};">Median income ${fmtMoney(city.median_household_income)}</span>
        </div>
    </div>`;
}

/** Navigate to a city's congressional district — precomputed at sync time (geo:sync-census-demographics), no live geocode call needed. */
function goToCityDistrict(state, districtNumber, cityName) {
    if (!districtNumber || typeof window.__mapGoTo !== 'function') return;
    showToast(`Opening ${cityName}'s congressional district…`, 'info');
    window.__mapGoTo(state, districtNumber, null);
}

export async function openRegionPanel(regionName, region) {
    const reqSeq = ++_regionReqSeq;
    const color = region?.hex || '#6366f1';

    document.getElementById('panel-state').textContent = `${regionName} Region`;
    const badge = document.getElementById('panel-badge');
    badge.textContent = `${(region?.states || []).length} states`;
    badge.style.cssText = `display:inline-block;padding:3px 12px;border-radius:999px;font-size:11px;font-weight:600;background:${color}22;color:${color};border:1px solid ${color}55;`;
    document.getElementById('panel-states').innerHTML = '';

    // #panel-candidates sits under a collapsible "Statewide Executive Offices"
    // toggle that remembers the user's state-mode collapse preference — that
    // label/preference doesn't apply to region content, so relabel it and
    // force it open (restored when entering state mode again, see
    // mode-transitions.js::enterStateMode).
    const officesToggle = document.getElementById('offices-toggle');
    const officesLabel = officesToggle?.querySelector('span');
    if (officesLabel) officesLabel.textContent = 'Cities & Demographics';
    officesToggle?.classList.remove('collapsed');
    officesToggle?.setAttribute('aria-expanded', 'true');
    document.getElementById('panel-candidates')?.classList.remove('section-collapsed');

    openInfoPanel();

    const candEl = document.getElementById('panel-candidates');
    candEl.innerHTML = `<div class="panel-spinner">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="animation:spin 1s linear infinite;color:${color};">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4" stroke-dashoffset="10" stroke-linecap="round"/>
        </svg>&nbsp;Loading cities…</div>`;

    let data = null;
    try {
        const res = await fetch(`/api/v1/map/region-demographics?region=${encodeURIComponent(regionName)}`);
        if (reqSeq !== _regionReqSeq) return;
        if (res.ok) data = await res.json();
    } catch (err) {
        console.warn('[map] region-demographics fetch failed:', err);
    }
    if (reqSeq !== _regionReqSeq) return;

    const states = data?.states ?? [];
    const hasAnyCities = states.some(s => s.cities?.length);

    if (!hasAnyCities) {
        candEl.innerHTML = noDataNotice('City demographics for this region are not available yet.');
        return;
    }

    let html = '';
    for (const s of states) {
        if (!s.cities?.length) continue;
        html += `<div style="border-top:1px solid ${color}20;margin:14px 0 10px;display:flex;align-items:center;gap:8px;">
            <span style="color:${color};font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;white-space:nowrap;">${escapeHtml(s.state)}</span>
            <div style="flex:1;border-top:1px solid ${color}20;"></div>
        </div>`;
        html += s.cities.map(c => renderCityCard(c, s.state, color)).join('');
    }
    candEl.innerHTML = html;

    candEl.querySelectorAll('.region-city-card[data-district]').forEach(card => {
        if (!card.dataset.district) return;
        card.addEventListener('click', () => goToCityDistrict(card.dataset.state, card.dataset.district, card.dataset.city));
    });
}
