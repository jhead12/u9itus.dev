/**
 * Local Businesses panel — opened from the "Local Businesses" stat card in the
 * state panel (renderStateStatsSection). Swaps the info-panel body for a list
 * of map-visible businesses in that state (GET /api/v1/map/state-businesses),
 * with a back button that restores the candidates view.
 *
 * Kept separate from the candidates rollup: businesses are opt-in Citizen rows
 * (show_on_map), categorised rather than tiered, and have their own pin layer.
 */
import { escapeHtml } from '../utils/html.js';

/** Panel sections hidden while the businesses list is showing. */
const HIDDEN_WHILE_OPEN = [
    'panel-running-candidates',
    'offices-toggle',
    'panel-candidates',
    'panel-topics',
    'panel-ballot-measures',
];

const CATEGORY_ICON = {
    food: '🍴', retail: '🛍️', service: '🔧', nonprofit: '🤝', other: '📍',
};

let isOpen = false;

function setSiblingsHidden(hidden) {
    for (const id of HIDDEN_WHILE_OPEN) {
        const el = document.getElementById(id);
        if (!el) continue;
        el.hidden = hidden;
        // #offices-toggle is a .panel-label-toggle (display:flex), which beats
        // the [hidden] UA style — force it explicitly so it actually hides.
        el.style.display = hidden ? 'none' : '';
    }
}

export function closeBusinessesPanel() {
    const host = document.getElementById('panel-businesses');
    if (host) { host.innerHTML = ''; host.hidden = true; }
    if (isOpen) setSiblingsHidden(false);
    isOpen = false;
}

function renderList(state, businesses, color) {
    if (!businesses.length) {
        return `<p class="biz-empty">No map-listed businesses in ${escapeHtml(state)} yet.</p>`;
    }
    return businesses.map(b => {
        const icon = CATEGORY_ICON[b.category] || CATEGORY_ICON.other;
        const cat = b.category ? `<span class="biz-cat">${escapeHtml(b.category)}</span>` : '';
        const verified = b.verified ? `<span class="biz-verified">✓ Verified</span>` : '';
        const site = b.website
            ? `<a class="biz-site" href="${escapeHtml(b.website)}" target="_blank" rel="noopener" onclick="event.stopPropagation()">Website ↗</a>`
            : '';
        return `<div class="biz-card">
            <span class="biz-icon" aria-hidden="true">${icon}</span>
            <div style="flex:1;min-width:0;">
                <div class="biz-name">${escapeHtml(b.name || 'Unnamed business')}</div>
                <div class="biz-meta">${cat}${verified}</div>
                ${b.address ? `<div class="biz-addr">${escapeHtml(b.address)}</div>` : ''}
                ${site}
            </div>
        </div>`;
    }).join('');
}

export async function openBusinessesPanel(stateAbbr, color) {
    stateAbbr = (stateAbbr || '').toUpperCase();
    if (stateAbbr.length !== 2) return;
    color = color || '#6366f1';

    const host = document.getElementById('panel-businesses');
    if (!host) return;

    isOpen = true;
    setSiblingsHidden(true);
    host.hidden = false;
    host.innerHTML = `
        <button type="button" class="biz-back" data-biz-back>← Back to candidates</button>
        <div class="office-section" style="border-color:${color}22;">
            <div class="office-title" style="background:${color}18;border-left:3px solid ${color};color:${color};cursor:default;">
                <span>🏪&nbsp;Local Businesses · ${escapeHtml(stateAbbr)}</span>
            </div>
            <div class="office-body">
                <div class="biz-list"><div class="panel-spinner" style="padding:12px 0;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="animation:spin 1s linear infinite;color:${color};"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4" stroke-dashoffset="10" stroke-linecap="round"/></svg>
                    &nbsp;Loading businesses…</div></div>
            </div>
        </div>`;

    let payload;
    try {
        const res = await fetch(`/api/v1/map/state-businesses?state=${stateAbbr}`, { headers: { Accept: 'application/json' } });
        payload = res.ok ? await res.json() : null;
    } catch {
        payload = null;
    }
    if (!isOpen || !host.isConnected) return;

    const listEl = host.querySelector('.biz-list');
    if (!listEl) return;

    if (!payload) {
        listEl.innerHTML = `<p class="biz-empty">Couldn’t load businesses right now. Try again shortly.</p>`;
        return;
    }

    host.querySelector('.office-title span').innerHTML =
        `🏪&nbsp;Local Businesses · ${escapeHtml(stateAbbr)} <span style="opacity:.7;font-weight:400;">(${payload.total ?? 0})</span>`;
    listEl.innerHTML = renderList(stateAbbr, payload.businesses || [], color);
}

export function initBusinessesPanel() {
    const panel = document.getElementById('info-panel');
    if (!panel) return;

    panel.addEventListener('click', e => {
        const trigger = e.target.closest('[data-open-businesses]');
        if (trigger) {
            e.stopPropagation();
            openBusinessesPanel(trigger.dataset.openBusinesses, trigger.dataset.bizColor || '#6366f1');
            return;
        }
        if (e.target.closest('[data-biz-back]')) {
            e.stopPropagation();
            closeBusinessesPanel();
        }
    });
}
