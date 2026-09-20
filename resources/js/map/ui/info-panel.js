/**
 * Info panel — open/close, resize handling, and the mobile bottom sheet.
 *
 * On phones the panel is a bottom sheet with three heights: minimized (just
 * the header, so the map stays visible), peek (the default on open) and full.
 * The handle is a real control: tap/Enter/Space toggles full height, drag or
 * ArrowUp/ArrowDown steps between the three.
 */
import { resizeRenderer } from '../scene/setup.js';
import { mapMode } from '../state/map-state.js';
import { resetDistrictSelection } from '../scene/district-overlay.js';
import { handleBack } from '../navigation/mode-transitions.js';
import { clearOpenDistrict } from './panel-district.js';

const infoPanel = document.getElementById('info-panel');
const legend = document.getElementById('legend');

const SHEET_STATES = ['minimized', 'peek', 'full'];
const DRAG_THRESHOLD_PX = 36;

function setSheetState(state) {
    infoPanel.classList.toggle('collapsed', state === 'minimized');
    infoPanel.classList.toggle('expanded', state === 'full');
    document.getElementById('panel-drag-handle')?.setAttribute('aria-expanded', String(state !== 'minimized'));
}

function sheetState() {
    if (infoPanel.classList.contains('collapsed')) return 'minimized';
    return infoPanel.classList.contains('expanded') ? 'full' : 'peek';
}

function stepSheet(delta) {
    const i = SHEET_STATES.indexOf(sheetState());
    setSheetState(SHEET_STATES[Math.min(SHEET_STATES.length - 1, Math.max(0, i + delta))]);
}

export function openInfoPanel() {
    infoPanel.classList.add('open');
    setSheetState('peek');
    if (window.innerWidth <= 768 && legend) {
        legend.classList.add('legend-collapsed');
    }
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
    document.getElementById('panel-close').addEventListener('click', () => {
        infoPanel.classList.remove('open');
        resizeRenderer();
        clearOpenDistrict();
        if (mapMode === 'state') {
            resetDistrictSelection();
        } else {
            handleBack();
        }
    });
}
