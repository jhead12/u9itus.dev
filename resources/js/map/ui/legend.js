/**
 * Legend — region and party color legend.
 */
import { REGIONS, PARTY_HEX, PARTY_LABEL } from '../config/constants.js';
import { stateMeshes } from '../scene/state-meshes.js';
import { mapMode, activeRegion } from '../state/map-state.js';

/**
 * Show the region legend (overview / region mode).
 * Each row zooms to its region on click.
 */
export function showRegionLegend(breakdown) {
    document.getElementById('legend').querySelector('h3').textContent = 'U.S. Regions';
    const el = document.getElementById('legend-items');
    el.innerHTML = '';
    for (const [name, data] of Object.entries(REGIONS)) {
        const row = document.createElement('div');
        row.className = 'legend-row';
        row.innerHTML = `<span class="legend-swatch" style="background:${data.hex};"></span>
            <span class="legend-name">${name}</span>
            <span class="legend-count">(${data.states.length})</span>`;
        row.title = `Click to zoom into the ${name} region`;
        row.addEventListener('mouseenter', () => dimExcept(name));
        row.addEventListener('mouseleave', () => { if (mapMode === 'overview') clearDim(); });
        row.addEventListener('click', () => {
            // Dynamic import: mode-transitions.js statically imports showRegionLegend
            // from this module, so a static reverse import would create a cycle.
            import('../navigation/mode-transitions.js').then(({ enterRegionMode }) => {
                enterRegionMode(name, data);
            });
        });
        el.appendChild(row);
    }
}

/**
 * Show the party-control legend (used in state mode).
 * @param {Object} breakdown  e.g. { R: 220, D: 215, I: 0 }
 */
export function showPartyLegend(breakdown = {}) {
    document.getElementById('legend').querySelector('h3').textContent = 'Party Control';
    const el = document.getElementById('legend-items');
    el.innerHTML = '';
    const order = ['R', 'D', 'I', 'U'];
    for (const code of order) {
        const count = breakdown[code] || 0;
        if (!count && code === 'U') continue;
        if (!count && code === 'I') continue;
        const row = document.createElement('div');
        row.className = 'legend-row';
        row.style.cursor = 'default';
        row.innerHTML = `<span class="legend-swatch" style="background:${PARTY_HEX[code]};"></span>
            <span class="legend-name">${PARTY_LABEL[code]}</span>
            ${count ? `<span class="legend-count">(${count} seat${count !== 1 ? 's' : ''})</span>` : ''}`;
        el.appendChild(row);
    }
}

/** Alias — called on map load */
export function buildLegend() { showRegionLegend(); }

/* ── Colour helpers (local to legend) ── */
function lighten(hex, amt = 55) {
    const r = Math.min(255, ((hex >> 16) & 0xff) + amt);
    const g = Math.min(255, ((hex >> 8) & 0xff) + amt);
    const b = Math.min(255, (hex & 0xff) + amt);
    return (r << 16) | (g << 8) | b;
}

function dimExcept(regionName) {
    for (const m of stateMeshes) {
        m.material.color.setHex(m.userData.regionName !== regionName ? 0x1a2240 : lighten(m.userData.originalColor, 30));
    }
}

function clearDim() {
    for (const m of stateMeshes) {
        if (m.userData.name !== activeRegion) {
            m.material.color.setHex(m.userData.originalColor);
        }
    }
}