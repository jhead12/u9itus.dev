/**
 * Region panel — cities within a region plus Census ACS demographics
 * (poverty rate, educational attainment, median household income).
 */
import { openInfoPanel } from './info-panel.js';
import { noDataNotice } from './panel-state.js';
import { renderCityCard, wireCityCardClicks } from './city-demographics-card.js';

let _regionReqSeq = 0;

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
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

    wireCityCardClicks(candEl);
}
