/**
 * Controls dropdown menu — view reset, district toggle, party colors, zoom, keyboard help.
 */
import { toggleNationalBoundaries } from '../scene/national-boundaries.js';
import { ensureGovernorParties, applyColorMode } from '../api/governor-parties.js';
import { colorMode, ACTIVE_LAYERS, showSmallCities, setShowSmallCities } from '../state/map-state.js';
import { stepZoom } from './keyboard.js';
import { findMyDistrict } from './location-button.js';
import { enterOverviewMode } from '../navigation/mode-transitions.js';
import { openInfoPanel } from './info-panel.js';
import { toggleKbHelp } from './keyboard.js';
import { syncLayerChip } from './layers-panel.js';
import { buildCityMarkers } from './markers.js';
import { activeState } from '../state/map-state.js';

const ctrlWrap    = document.getElementById('controls-wrap');
const ctrlMenu    = document.getElementById('controls-menu');
const btnControls = document.getElementById('btn-controls');

/**
 * Open or close the controls dropdown.
 */
export function openControlsMenu(open) {
    ctrlMenu.classList.toggle('open', open);
    btnControls.setAttribute('aria-expanded', String(open));
}

export function updateRotateBtn(_on) { /* rotation disabled */ }

export function updateDistrictsBtn(on) {
    document.getElementById('cm-btn-districts')?.classList.toggle('active', on);
    syncLayerChip('districts', on);
}

/**
 * Set up controls menu event listeners.
 */
export function initControlsMenu() {
    btnControls.addEventListener('click', e => {
        e.stopPropagation();
        openControlsMenu(!ctrlMenu.classList.contains('open'));
    });

    // Hover-to-open on desktop
    if (matchMedia('(hover: hover) and (pointer: fine)').matches) {
        let ctrlCloseTimer = null;
        const ctrlOpenOnHover  = () => { clearTimeout(ctrlCloseTimer); openControlsMenu(true); };
        const ctrlCloseOnLeave = () => { clearTimeout(ctrlCloseTimer); ctrlCloseTimer = setTimeout(() => openControlsMenu(false), 200); };
        ctrlWrap.addEventListener('mouseenter', ctrlOpenOnHover);
        ctrlWrap.addEventListener('mouseleave', ctrlCloseOnLeave);
    }

    // Close on click outside
    document.addEventListener('click', e => {
        if (!ctrlWrap.contains(e.target)) openControlsMenu(false);
    });

    // Close on Escape
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && ctrlMenu.classList.contains('open')) {
            openControlsMenu(false);
            btnControls.focus();
        }
    });

    document.getElementById('cm-btn-reset')?.addEventListener('click', () => {
        openControlsMenu(false);
        enterOverviewMode();
    });

    document.getElementById('cm-btn-districts')?.addEventListener('click', () => {
        toggleNationalBoundaries();
    });

    document.getElementById('cm-btn-party-colors')?.addEventListener('click', () => {
        const newMode = colorMode === 'party' ? 'region' : 'party';
        document.getElementById('cm-btn-party-colors').classList.toggle('active', newMode === 'party');
        syncLayerChip('party', newMode === 'party');
        if (newMode === 'party') {
            ensureGovernorParties().then(() => applyColorMode());
        } else {
            applyColorMode();
        }
    });

    document.getElementById('cm-btn-small-cities')?.addEventListener('click', () => {
        setShowSmallCities(!showSmallCities);
        document.getElementById('cm-btn-small-cities').classList.toggle('active', showSmallCities);
        if (activeState && ACTIVE_LAYERS.has('topcities')) {
            buildCityMarkers(activeState);
        }
        try { localStorage.setItem('u9map_small_cities', showSmallCities ? '1' : '0'); } catch (_) {}
    });

    document.getElementById('cm-btn-kb-help')?.addEventListener('click', () => {
        openControlsMenu(false);
        toggleKbHelp(true);
    });

    document.getElementById('cm-btn-tutorial')?.addEventListener('click', () => {
        openControlsMenu(false);
        setTimeout(() => window.startTutorial?.(true), 150);
    });

    document.getElementById('cm-btn-zoomin')?.addEventListener('click',  () => stepZoom(0.8));
    document.getElementById('cm-btn-zoomout')?.addEventListener('click', () => stepZoom(1.25));

    document.getElementById('cm-btn-find-district')?.addEventListener('click', () => {
        openControlsMenu(false);
        findMyDistrict();
    });
}