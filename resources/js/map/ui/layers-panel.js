/**
 * Layers panel — multi-select data overlay controls.
 */
import { ACTIVE_LAYERS, showSmallCities, colorMode, stateData, activeState, setColorMode, setShowSmallCities } from '../state/map-state.js';
import { districtMeshes } from '../scene/district-overlay.js';
import { toggleNationalBoundaries, _syncNatDistVisibility } from '../scene/national-boundaries.js';
import { ensureGovernorParties, applyColorMode } from '../api/governor-parties.js';
import { clearCityMarkers, buildCityMarkers, clearGovMarkers, loadCityBoundaries, clearCityLayer } from './markers.js';
import { refreshPostPins, clearPostPins } from './post-pins.js';
import { STATE_ABBR_MAP } from '../config/constants.js';
import { trackEvent } from '../api/interaction.js';
import * as THREE from 'three';

const layersWrap  = document.getElementById('layers-wrap');
const layersPanel = document.getElementById('layers-panel');
const btnLayers   = document.getElementById('btn-layers');

/**
 * Toggle a data layer on or off.
 */
export function toggleLayer(layerKey) {
    const isActive = !ACTIVE_LAYERS.has(layerKey);
    syncLayerChip(layerKey, isActive);
    trackEvent('layer_toggle', {
        state: activeState || null,
        meta:  { layer: layerKey, active: isActive },
    });
    switch (layerKey) {
        case 'districts':
            toggleNationalBoundaries();
            break;
        case 'party':
            setColorMode(isActive ? 'party' : 'region');
            document.getElementById('cm-btn-party-colors')?.classList.toggle('active', isActive);
            if (isActive) {
                ensureGovernorParties().then(() => applyColorMode());
            } else {
                applyColorMode();
            }
            break;
        case 'population':
            if (isActive) {
                applyPopulationDensity();
            } else {
                for (const d of districtMeshes) d.material.color.setHex(d.userData.originalColor);
            }
            break;
        case 'cities':
            if (isActive && activeState) loadCityBoundaries(activeState);
            else clearCityLayer();
            break;
        case 'topcities':
            if (isActive && activeState) { buildCityMarkers(activeState); buildGovMarkers(); }
            else { clearCityMarkers(); clearGovMarkers(); }
            break;
        case 'content':
            if (isActive) { refreshPostPins(true); }
            else { clearPostPins(); }
            break;
    }
}

/**
 * Sync a layer chip's visual state and the ACTIVE_LAYERS set.
 */
export function syncLayerChip(layerKey, isActive) {
    const chip = document.querySelector(`[data-layer="${layerKey}"]`);
    if (chip) {
        chip.classList.toggle('active', isActive);
        chip.setAttribute('aria-checked', String(isActive));
    }
    if (isActive) ACTIVE_LAYERS.add(layerKey);
    else ACTIVE_LAYERS.delete(layerKey);
    try { localStorage.setItem('u9map_layers', JSON.stringify([...ACTIVE_LAYERS])); } catch (_) {}
}

/**
 * Apply population-density shading to district meshes.
 */
export function applyPopulationDensity() {
    if (!districtMeshes.length) return;
    const popMap = stateData?.district_populations;
    if (!popMap) return;
    const abbr = activeState ? STATE_ABBR_MAP[activeState] : null;
    const vals  = Object.values(popMap).map(d => d.total || 0).filter(v => v > 0);
    if (!vals.length) return;
    const minP = Math.min(...vals), maxP = Math.max(...vals), range = maxP - minP || 1;
    const low  = new THREE.Color(0x0f2040);
    const high = new THREE.Color(0x06b6d4);
    for (const d of districtMeshes) {
        const dn  = d.userData.districtNum;
        const key = dn === 'AL' ? `${abbr}-AL` : `${abbr}-${dn}`;
        const rec = popMap[key];
        if (!rec) continue;
        const t = (rec.total - minP) / range;
        d.material.color.copy(low.clone().lerp(high, t));
    }
}

/**
 * Open or close the layers panel.
 */
function openLayersPanel(open) {
    layersPanel.classList.toggle('open', open);
    btnLayers.setAttribute('aria-expanded', String(open));
    btnLayers.classList.toggle('active', open);
}

/**
 * Set up layers panel event listeners.
 */
export function initLayersPanel() {
    // Dynamic import: controls-menu.js statically imports syncLayerChip from
    // this module, so a static reverse import would create an evaluation cycle.
    const openCtrlMenu = () => import('./controls-menu.js').then(({ openControlsMenu }) => openControlsMenu(false));

    btnLayers.addEventListener('click', e => {
        e.stopPropagation();
        openCtrlMenu();
        openLayersPanel(!layersPanel.classList.contains('open'));
    });

    // Hover-to-open
    if (matchMedia('(hover: hover) and (pointer: fine)').matches) {
        let layersCloseTimer = null;
        const layersOpenOnHover  = () => {
            clearTimeout(layersCloseTimer);
            openCtrlMenu();
            openLayersPanel(true);
        };
        const layersCloseOnLeave = () => {
            clearTimeout(layersCloseTimer);
            layersCloseTimer = setTimeout(() => openLayersPanel(false), 200);
        };
        layersWrap.addEventListener('mouseenter', layersOpenOnHover);
        layersWrap.addEventListener('mouseleave', layersCloseOnLeave);
    }

    document.addEventListener('click', e => {
        if (!layersWrap.contains(e.target)) openLayersPanel(false);
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && layersPanel.classList.contains('open')) {
            openLayersPanel(false); btnLayers.focus();
        }
    });

    layersPanel.querySelectorAll('.lp-chip').forEach(chip => {
        chip.addEventListener('click', e => { e.stopPropagation(); toggleLayer(chip.dataset.layer); });
    });

    // Restore persisted layer state
    try {
        const saved = JSON.parse(localStorage.getItem('u9map_layers') || '[]');
        for (const key of saved) syncLayerChip(key, true);
    } catch (_) {}

    try {
        if (localStorage.getItem('u9map_small_cities') === '1') {
            setShowSmallCities(true);
            document.getElementById('cm-btn-small-cities').classList.add('active');
        }
    } catch (_) {}
}