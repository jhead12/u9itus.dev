/**
 * Layers panel — multi-select data overlay controls.
 */
import { ACTIVE_LAYERS, showSmallCities, colorMode, stateData, activeState, setColorMode, setShowSmallCities, favoriteBoundaries } from '../state/map-state.js';
import { districtMeshes } from '../scene/district-overlay.js';
import { toggleNationalBoundaries, _syncNatDistVisibility } from '../scene/national-boundaries.js';
import { ensureGovernorParties, applyColorMode } from '../api/governor-parties.js';
import { clearCityMarkers, buildCityMarkers, buildGovMarkers, clearGovMarkers, loadCityBoundaries, clearCityLayer, openCityDrawer } from './markers.js';
import { clearCandidateMarkers, buildCandidateMarkers } from './candidate-markers.js';
import { refreshPostPins, clearPostPins } from './post-pins.js';
import { refreshBusinessPins, clearBusinessPins, setBusinessCategoryFilter } from './business-pins.js';
import { STATE_ABBR_MAP } from '../config/constants.js';
import { TOP_CITIES } from '../config/city-data.js';
import { trackEvent } from '../api/interaction.js';
import { flyToPoint } from '../scene/camera-animation.js';
import { project } from '../scene/projection.js';
import { removeBoundary } from '../api/favorites.js';
import * as THREE from 'three';

const layersWrap  = document.getElementById('layers-wrap');
const layersPanel = document.getElementById('layers-panel');
const btnLayers   = document.getElementById('btn-layers');

const favChipsEl = document.getElementById('favorite-boundary-chips');
const favEmptyEl = document.getElementById('favorite-boundary-empty');
const favCountEl = document.querySelector('.lp-saved-count');

const ABBR_TO_NAME = Object.fromEntries(
    Object.entries(STATE_ABBR_MAP).map(([name, abbr]) => [abbr, name])
);

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
            if (isActive && activeState) { buildCityMarkers(activeState); buildGovMarkers(activeState); }
            else { clearCityMarkers(); clearGovMarkers(); }
            break;
        case 'candidates':
            if (isActive && activeState) buildCandidateMarkers(activeState);
            else clearCandidateMarkers();
            break;
        case 'content':
            if (isActive) { refreshPostPins(true); }
            else { clearPostPins(); }
            break;
        case 'businesses': {
            const categoryWrap = document.getElementById('business-category-wrap');
            if (categoryWrap) categoryWrap.style.display = isActive ? '' : 'none';
            if (isActive) { refreshBusinessPins(true); }
            else { clearBusinessPins(); }
            break;
        }
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
 * Resolve a top city's population (in thousands) from the TOP_CITIES dataset,
 * for the city drawer when a saved-city chip is clicked. 0 if not found.
 */
function cityPopK(abbr, name) {
    const list = TOP_CITIES[abbr];
    if (!list) return 0;
    const found = list.find(c => c[0] === name);
    return found ? found[3] : 0;
}

/**
 * Fly the map to a saved boundary and open its panel/drawer.
 * Districts reuse window.__mapGoTo (flies + opens the district panel).
 * Cities enter the state via __mapGoTo, then fly to the cached point and
 * open the city drawer.
 */
function flyToFavorite(b) {
    if (b.type === 'district') {
        window.__mapGoTo?.(b.stateAbbr, b.districtNum, null);
        trackEvent('boundary_chip_click', { meta: { type: 'district', state: b.stateAbbr, district: b.districtNum } });
        return;
    }
    const stateName = ABBR_TO_NAME[b.stateAbbr] || b.stateAbbr;
    window.__mapGoTo?.(b.stateAbbr, null, null);
    trackEvent('boundary_chip_click', { meta: { type: 'city', state: b.stateAbbr, city: b.cityName } });
    if (b.lat == null || b.lng == null) return;
    // Wait for state mode + city markers to settle before flying + opening.
    setTimeout(() => {
        flyToPoint(b.lat, b.lng);
        const xy = project([b.lng, b.lat]);
        if (!xy) return;
        const worldPos = new THREE.Vector3(xy[0], xy[1], 0.35);
        openCityDrawer(b.cityName, cityPopK(b.stateAbbr, b.cityName), stateName, worldPos);
    }, 700);
}

/**
 * Render the voter's saved-boundary chips in the Layers → Boundaries panel.
 * Called at boot (after fetchBoundaries) and after each save/remove.
 */
export function renderFavoriteChips() {
    if (!favChipsEl) return;
    favChipsEl.innerHTML = '';
    const entries = [...favoriteBoundaries.values()];

    for (const b of entries) {
        const chip = document.createElement('button');
        chip.type = 'button';
        chip.className = 'lp-chip lp-chip-saved';
        chip.setAttribute('title', `Fly to ${b.label}`);
        chip.innerHTML =
            `<span class="lp-saved-icon">${b.type === 'city' ? '📍' : '🗺️'}</span>` +
            `<span class="lp-saved-label">${b.label}</span>` +
            `<span class="lp-saved-remove" role="button" aria-label="Remove ${b.label}" title="Remove">×</span>`;
        chip.addEventListener('click', (e) => {
            if (e.target.closest('.lp-saved-remove')) {
                e.stopPropagation();
                removeBoundary(b.id).then((ok) => {
                    if (ok) {
                        renderFavoriteChips();
                        trackEvent('boundary_unsave', { meta: { id: b.id, type: b.type } });
                    }
                });
                return;
            }
            flyToFavorite(b);
        });
        favChipsEl.appendChild(chip);
    }

    const count = entries.length;
    if (favCountEl) favCountEl.textContent = count > 0 ? `(${count})` : '';
    if (favEmptyEl) {
        favEmptyEl.textContent = 'Save districts and cities you care about to pin them here.';
        favEmptyEl.style.display = count > 0 ? 'none' : '';
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

    document.getElementById('business-category-filter')?.addEventListener('change', e => {
        e.stopPropagation();
        setBusinessCategoryFilter(e.target.value);
        trackEvent('map_business_category_filter', { meta: { category: e.target.value || 'all' } });
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

    // Initial Saved Boundaries empty-state (re-rendered after fetchBoundaries
    // resolves for voters, and after each save/remove).
    renderFavoriteChips();

    // Re-render chips when a star button (district panel / city drawer) saves
    // or removes a boundary. Decoupled via event to avoid an import cycle.
    window.addEventListener('u9:favorites-changed', renderFavoriteChips);
}