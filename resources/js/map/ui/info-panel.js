/**
 * Info panel — open/close, resize handling, and the mobile bottom sheet.
 *
 * On phones the panel is a bottom sheet with three heights: minimized (just
 * the header, so the map stays visible), peek (the default on open) and full.
 * The handle is a real control: tap/Enter/Space toggles full height, drag or
 * ArrowUp/ArrowDown steps between the three.
 */
import { resizeRenderer } from '../scene/setup.js';
import { mapMode, activeRegion, activeState } from '../state/map-state.js';
import { resetDistrictSelection } from '../scene/district-overlay.js';
import { handleBack } from '../navigation/mode-transitions.js';
import { clearOpenDistrict, getOpenDistrict } from './panel-district.js';
import { recordLocation } from '../navigation/history.js';
import { updateBreadcrumb } from './breadcrumb.js';

const infoPanel = document.getElementById('info-panel');
const legend = document.getElementById('legend');

const SHEET_STATES = ['minimized', 'peek', 'full'];
const DRAG_THRESHOLD_PX = 36;

const HANDLE_LABEL_MINIMIZED = 'Show details: expand panel';
const HANDLE_LABEL_OPEN = 'Resize panel: drag or press up and down arrows';

function setSheetState(state) {
    infoPanel.classList.toggle('collapsed', state === 'minimized');
    infoPanel.classList.toggle('expanded', state === 'full');
    const handle = document.getElementById('panel-drag-handle');
    handle?.setAttribute('aria-expanded', String(state !== 'minimized'));
    // Minimized hides everything but the header, so the handle says what it reveals.
    handle?.setAttribute('aria-label', state === 'minimized' ? HANDLE_LABEL_MINIMIZED : HANDLE_LABEL_OPEN);
}

function sheetState() {
    if (infoPanel.classList.contains('collapsed')) return 'minimized';
    return infoPanel.classList.contains('expanded') ? 'full' : 'peek';
}

function stepSheet(delta) {
    const i = SHEET_STATES.indexOf(sheetState());
    setSheetState(SHEET_STATES[Math.min(SHEET_STATES.length - 1, Math.max(0, i + delta))]);
}

/**
 * @param {object} [opts]
 * @param {'minimized'|'peek'|'full'} [opts.sheet]  mobile sheet height to open at:
 *   'full' features a tapped location's data, 'minimized' keeps the map in view.
 */
export function openInfoPanel({ sheet = 'peek' } = {}) {
    infoPanel.classList.add('open');
    infoPanel.inert = false;
    setSheetState(sheet);
    if (window.innerWidth <= 768 && legend) {
        legend.classList.add('legend-collapsed');
        document.getElementById('legend-toggle')?.setAttribute('aria-expanded', 'false');
    }
    resizeRenderer();
    // Move focus to the panel title so screen readers announce the new place.
    // Deferred: callers fill in the title after opening.
    requestAnimationFrame(() => {
        if (infoPanel.classList.contains('open')) {
            document.getElementById('panel-state')?.focus({ preventScroll: true });
        }
    });
}

/** Close the panel; while closed it is off screen, so keep it out of the tab and reading order. */
export function closeInfoPanel() {
    // Focus inside a panel that is going inert would be lost: hand it to the map.
    const hadFocus = infoPanel.contains(document.activeElement);
    infoPanel.classList.remove('open');
    infoPanel.inert = true;
    if (hadFocus) document.getElementById('map-canvas-region')?.focus({ preventScroll: true });
    resizeRenderer();
}

function initSheetHandle() {
    const handle = document.getElementById('panel-drag-handle');
    if (!handle) return;

    let startY = null;
    let dragged = false;

    handle.addEventListener('pointerdown', (e) => {
        startY = e.clientY;
        dragged = false;
        handle.setPointerCapture?.(e.pointerId);
    });
    handle.addEventListener('pointermove', (e) => {
        if (startY !== null && Math.abs(e.clientY - startY) > 4) dragged = true;
    });
    handle.addEventListener('pointerup', (e) => {
        if (startY === null) return;
        const dy = e.clientY - startY;
        startY = null;
        if (dragged && Math.abs(dy) >= DRAG_THRESHOLD_PX) {
            stepSheet(dy < 0 ? 1 : -1);   // drag up grows the sheet, down shrinks it
        } else if (!dragged) {
            // Tap: minimized → peek, otherwise flip between peek and full.
            const state = sheetState();
            setSheetState(state === 'peek' ? 'full' : 'peek');
        }
    });
    handle.addEventListener('pointercancel', () => { startY = null; });
    // Screen-reader double-tap and voice control send a click with no pointer
    // sequence (detail 0); treat it like a tap.
    handle.addEventListener('click', (e) => {
        if (e.detail !== 0) return;
        setSheetState(sheetState() === 'peek' ? 'full' : 'peek');
    });
    handle.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            setSheetState(sheetState() === 'peek' ? 'full' : 'peek');
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            stepSheet(1);
        } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            stepSheet(-1);
        }
    });
}

export function initInfoPanel() {
    initSheetHandle();
    infoPanel.inert = !infoPanel.classList.contains('open');
    document.getElementById('panel-close').addEventListener('click', () => {
        const closingDistrict = !!getOpenDistrict();
        closeInfoPanel();
        clearOpenDistrict();
        if (mapMode === 'state') {
            resetDistrictSelection();
            // Closing a district is a step back to its state: Back reopens the district.
            if (closingDistrict) {
                recordLocation({ level: 'state', region: activeRegion, state: activeState });
                updateBreadcrumb();
            }
        } else {
            handleBack();
        }
    });
}
