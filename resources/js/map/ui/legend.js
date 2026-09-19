/**
 * Legend — region and party color legend.
 */
import { REGIONS, PARTY_HEX, PARTY_LABEL } from '../config/constants.js';
import { stateMeshes } from '../scene/state-meshes.js';
import { mapMode, activeRegion, colorMode } from '../state/map-state.js';

/**
 * Shared legend shell. Every legend states what its colors mean; the
 * Regions / Party control switch shows only where it applies (overview and
 * region views — inside a state, districts are always party-colored).
 */
function setLegend({ title, note = '', showModeSwitch = false }) {
    document.getElementById('legend-title').textContent = title;
    const noteEl = document.getElementById('legend-note');
    noteEl.textContent = note;
    noteEl.hidden = !note;
    document.getElementById('legend-mode').hidden = !showModeSwitch;
    syncLegendModeSwitch();
}

/** Reflect the active colorMode on the Regions / Party control switch. */
export function syncLegendModeSwitch() {
    for (const btn of document.querySelectorAll('#legend-mode [data-color-mode]')) {
        const on = btn.dataset.colorMode === colorMode;
        btn.classList.toggle('active', on);
        btn.setAttribute('aria-pressed', String(on));
    }
}

let _modeSwitchWired = false;
function wireModeSwitch() {
    if (_modeSwitchWired) return;
    _modeSwitchWired = true;
    document.getElementById('legend-mode').addEventListener('click', (e) => {
        const btn = e.target.closest('[data-color-mode]');
        if (!btn) return;
        // Dynamic import: governor-parties.js statically imports this module.
        import('../api/governor-parties.js').then(({ setOverviewColorMode }) => {
            setOverviewColorMode(btn.dataset.colorMode);
        });
    });
}

/**
 * Show the region legend (overview / region mode).
 * Each row zooms to its region on click.
 */
export function showRegionLegend(breakdown) {
    wireModeSwitch();
    setLegend({
        title: 'Regions',
        note: 'Colors show geographic regions — not party.',
        showModeSwitch: true,
    });
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
    setLegend({
        title: 'Party control · U.S. House',
        note: 'Colors show which party holds each district (119th Congress).',
    });
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

/**
 * Show the overview party-control legend: each state's governor's party.
 * @param {Object} counts  e.g. { D: 23, R: 27, I: 0, U: 1 } — states per party
 */
export function showGovernorPartyLegend(counts = {}) {
    wireModeSwitch();
    setLegend({
        title: 'Party control',
        note: "Colors show each state's governor's party.",
        showModeSwitch: true,
    });
    const el = document.getElementById('legend-items');
    el.innerHTML = '';
    for (const code of ['D', 'R', 'I', 'U']) {
        const count = counts[code] || 0;
        if (!count && code !== 'D' && code !== 'R') continue;
        const row = document.createElement('div');
        row.className = 'legend-row';
        row.style.cursor = 'default';
        const label = code === 'U' ? 'No governor / no data' : PARTY_LABEL[code];
        row.innerHTML = `<span class="legend-swatch" style="background:${PARTY_HEX[code]};"></span>
            <span class="legend-name">${label}</span>
            <span class="legend-count">(${count} state${count !== 1 ? 's' : ''})</span>`;
        el.appendChild(row);
    }
}

/**
 * Show a continuous low->high color-scale legend, for any layer that shades
 * by a numeric value rather than a discrete category (Poverty Rate today;
 * Population Density could adopt this too instead of shipping with none).
 * @param {Object} opts
 * @param {string} opts.title
 * @param {string} opts.lowHex
 * @param {string} opts.highHex
 * @param {string} opts.minLabel
 * @param {string} opts.maxLabel
 */
export function showGradientLegend({ title, lowHex, highHex, minLabel, maxLabel, note = '' }) {
    wireModeSwitch();
    setLegend({ title, note, showModeSwitch: true });
    const el = document.getElementById('legend-items');
    el.innerHTML = `
        <div style="height:10px;border-radius:5px;margin-bottom:6px;background:linear-gradient(to right, ${lowHex}, ${highHex});"></div>
        <div style="display:flex;justify-content:space-between;color:#94a3b8;font-size:11px;">
            <span>${minLabel}</span><span>${maxLabel}</span>
        </div>`;
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