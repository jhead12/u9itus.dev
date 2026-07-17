/**
 * Info panel — open/close and resize handling.
 */
import { resizeRenderer } from '../scene/setup.js';
import { mapMode } from '../state/map-state.js';
import { resetDistrictSelection } from '../scene/district-overlay.js';
import { handleBack } from '../navigation/mode-transitions.js';

const infoPanel = document.getElementById('info-panel');
const legend = document.getElementById('legend');

export function openInfoPanel() {
    infoPanel.classList.add('open');
    infoPanel.classList.remove('expanded');
    if (window.innerWidth <= 768 && legend) {
        legend.classList.add('legend-collapsed');
    }
    resizeRenderer();
}

export function initInfoPanel() {
    document.getElementById('panel-close').addEventListener('click', () => {
        infoPanel.classList.remove('open');
        resizeRenderer();
        if (mapMode === 'state') {
            resetDistrictSelection();
        } else {
            handleBack();
        }
    });
}