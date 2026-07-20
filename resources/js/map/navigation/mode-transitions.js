/**
 * Mode transitions — overview, region, state navigation.
 * This is the core interaction module that wires click/hover events
 * to map state changes, panel updates, and camera animations.
 */
import * as THREE from 'three';
import {
    mapMode, activeRegion, activeState, selectedState, stateData, statePanelRequestId, colorMode, ACTIVE_LAYERS, DISTRICT_CONFIG,
    setMapMode, setActiveRegion, setActiveState, setSelectedState, nextRequestId, setStateData, setColorMode,
} from '../state/map-state.js';
import { stateMeshes } from '../scene/state-meshes.js';
import { flyTo, flyToMeshes, flyToMeshesTopDown } from '../scene/camera-animation.js';
import { camera, controls, renderer, mapGroup, resizeRenderer } from '../scene/setup.js';
import { REGIONS, STATE_ABBR_MAP, PARTY_HEX, PARTY_LABEL, DISTRICT_COUNTS } from '../config/constants.js';
import { clearDistricts, buildDistrictOverlay, resetDistrictSelection, districtMeshes, hoveredDistrict, setHoveredDistrict } from '../scene/district-overlay.js';
import { openStatePanel, partyClass } from '../ui/panel-state.js';
import { openDistrictPanel } from '../ui/panel-district.js';
import { showRegionLegend, showPartyLegend } from '../ui/legend.js';
import { clearDistrictLabels, buildDistrictLabels } from '../ui/labels-overlay.js';
import { clearCityMarkers, buildCityMarkers, clearGovMarkers, loadCityBoundaries } from '../ui/markers.js';
import { closePolDrawer } from '../ui/politician-drawer.js';
import { closePopup } from '../ui/popup.js';
import { initDistrictConfig } from '../api/district-config.js';
import { ensureGovernorParties, applyColorMode } from '../api/governor-parties.js';
import { applyPopulationDensity } from '../ui/layers-panel.js';
import { trackEvent } from '../api/interaction.js';
import { updateBreadcrumb } from '../ui/breadcrumb.js';
import { updateDistrictLabels, updateCityDots } from '../render-loop.js';
import { openInfoPanel } from '../ui/info-panel.js';

/* ── Colour helpers ── */
export function lighten(hex, amt = 55) {
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
        if (m !== hoveredMesh && m.userData.name !== selectedState) m.material.color.setHex(m.userData.originalColor);
    }
}

/* ── Mode transitions ── */

export function enterOverviewMode() {
    nextRequestId();
    setStateData(null);
    setMapMode('overview'); setActiveRegion(null); setActiveState(null); setSelectedState(null);
    clearDim(); clearDistricts(); clearDistrictLabels(); clearCityMarkers(); clearGovMarkers(); closePolDrawer();
    document.getElementById('info-panel').classList.remove('open');
    resizeRenderer();
    document.getElementById('btn-back').style.display = 'none';
    document.getElementById('hint').innerHTML = 'Scroll to zoom &nbsp;·&nbsp; ↑↓ tilt &nbsp;·&nbsp; drag to pan &nbsp;·&nbsp; Click a state';
    for (const m of stateMeshes) {
        m.material.transparent = false;
        m.material.opacity = 1.0;
        m.material.color.setHex(m.userData.originalColor);
        m.parent.position.z = 0;
    }
    showRegionLegend();
    flyTo(new THREE.Vector3(0, 5.4, 10.2), new THREE.Vector3(0, 0, 0));
    updateBreadcrumb();
    _syncNatDistVisibility();
}

export function enterRegionMode(regionName, region) {
    nextRequestId();
    setStateData(null);
    setMapMode('region'); setActiveRegion(regionName); setActiveState(null); setSelectedState(null);
    clearDistricts(); clearDistrictLabels(); clearCityMarkers(); clearGovMarkers(); closePolDrawer();
    document.getElementById('info-panel').classList.remove('open');
    resizeRenderer();
    document.getElementById('btn-back').style.display = '';
    document.getElementById('hint').innerHTML = `Click a state in the <span style="color:${region.hex}">${regionName}</span> region`;
    for (const m of stateMeshes) {
        m.material.transparent = false;
        m.material.opacity = 1.0;
        m.parent.position.z = 0;
    }
    dimExcept(regionName);
    showRegionLegend();
    const rMeshes = stateMeshes.filter(m => m.userData.regionName === regionName);
    flyToMeshes(rMeshes, 1.35);
    updateBreadcrumb();
    trackEvent('region_click', { region: regionName });
    _syncNatDistVisibility();
}

export async function enterStateMode(stateName, regionName, region) {
    const requestId = nextRequestId();
    setMapMode('state'); setActiveRegion(regionName); setActiveState(stateName); setSelectedState(stateName);
    _syncNatDistVisibility();
    document.getElementById('btn-back').style.display = '';
    document.getElementById('hint').innerHTML = 'Click a congressional district to see candidates';
    trackEvent('state_click', { state: stateName, state_abbr: STATE_ABBR_MAP[stateName] || null, region: regionName });

    for (const m of stateMeshes) {
        if (m.userData.name !== stateName) {
            m.material.color.setHex(0x0a0f22);
            m.material.transparent = true;
            m.material.opacity = 0.6;
            m.parent.position.z = 0;
        } else {
            m.material.color.setHex(0x1a2a1a);
            m.material.transparent = true;
            m.material.opacity = 0.25;
            m.parent.position.z = 0;
        }
    }

    const sMeshes = stateMeshes.filter(m => m.userData.name === stateName);
    flyToMeshesTopDown(sMeshes, 1.1);

    document.getElementById('panel-candidates').innerHTML = `<div class="panel-spinner">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="animation:spin 1s linear infinite;color:${region?.hex || '#6366f1'};">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4" stroke-dashoffset="10" stroke-linecap="round"/>
        </svg>&nbsp;Loading districts…</div>`;
    openInfoPanel();

    document.getElementById('panel-state').textContent = stateName;
    const badge = document.getElementById('panel-badge');
    badge.textContent = (regionName || '') + ' Region';
    badge.style.cssText = `display:inline-block;padding:3px 12px;border-radius:999px;font-size:11px;font-weight:600;background:${(region?.hex || '#888')}22;color:${region?.hex || '#888'};border:1px solid ${region?.hex || '#888'}55;cursor:pointer;`;
    badge.title = `Back to ${regionName} region`;
    badge.onclick = () => enterRegionMode(regionName, REGIONS[regionName]);

    const statesEl = document.getElementById('panel-states');
    statesEl.innerHTML = '';
    for (const s of (region?.states || [])) {
        const chip = document.createElement('span');
        chip.className = 'state-chip' + (s === stateName ? ' active' : '');
        chip.textContent = s;
        chip.title = s === stateName ? 'Currently viewing' : `Switch to ${s}`;
        if (s !== stateName) {
            chip.addEventListener('click', () => {
                closePopup();
                const mesh = stateMeshes.find(m => m.userData.name === s);
                if (mesh) enterStateMode(s, mesh.userData.regionName, mesh.userData.region);
            });
        }
        statesEl.appendChild(chip);
    }

    let distCount = 0;
    try {
        distCount = await buildDistrictOverlay(stateName, region?.hex);
        if (requestId !== statePanelRequestId) return;
    } catch (err) {
        console.warn(`District overlay failed for ${stateName}:`, err);
        document.getElementById('panel-candidates').innerHTML =
            `<p style="color:#ef444488;font-size:11px;margin:0 0 12px;">⚠ District boundaries unavailable (${err.message}). Retry by clicking the state again.</p>`;
    }

    let nextStateData = null;
    const abbr = STATE_ABBR_MAP[stateName];
    let apiStatus = 'unreachable';
    if (abbr) {
        try {
            const apiRes = await fetch(`/api/v1/map/state-candidates?state=${abbr}`);
            if (requestId !== statePanelRequestId) return;
            if (apiRes.ok) {
                nextStateData = await apiRes.json();
                apiStatus = nextStateData?.offices?.length ? 'live' : 'empty';
            } else {
                apiStatus = 'unreachable';
            }
        } catch (e) {
            console.warn('state-candidates API unavailable:', e.message);
            apiStatus = 'unreachable';
        }
    }
    if (requestId !== statePanelRequestId) return;
    if (nextStateData) nextStateData._apiStatus = apiStatus;
    else nextStateData = { _apiStatus: apiStatus };
    setStateData(nextStateData);

    await openStatePanel(stateName, regionName, region, distCount, nextStateData);
    if (requestId !== statePanelRequestId) return;

    if (ACTIVE_LAYERS.has('population')) applyPopulationDensity();
    if (ACTIVE_LAYERS.has('cities')) loadCityBoundaries(stateName);

    const breakdown = {};
    for (const m of districtMeshes) { const p = m.userData.party || 'U'; breakdown[p] = (breakdown[p] || 0) + 1; }
    showPartyLegend(breakdown);
    buildDistrictLabels(stateName);
    if (ACTIVE_LAYERS.has('topcities')) { buildCityMarkers(stateName); buildGovMarkers(stateName); }
    updateBreadcrumb();
}

export function handleBack() {
    if (mapMode === 'state' && activeRegion) enterRegionMode(activeRegion, REGIONS[activeRegion]);
    else enterOverviewMode();
}

/* ── Hover & Click ── */

let hoveredMesh = null;
const tooltip = document.getElementById('tooltip');
const districtTip = document.getElementById('district-tooltip');
const raycaster = new THREE.Raycaster();
const mouse = new THREE.Vector2();

function _syncNatDistVisibility() {
    // Import from national-boundaries is circular, so we use a late-binding pattern
    if (typeof window.__syncNatDistVisibility === 'function') {
        window.__syncNatDistVisibility();
    }
}

export function initHoverClick() {
    renderer.domElement.addEventListener('mousemove', (e) => {
        const rect = renderer.domElement.getBoundingClientRect();
        mouse.x = ((e.clientX - rect.left) / rect.width) * 2 - 1;
        mouse.y = -((e.clientY - rect.top) / rect.height) * 2 + 1;
        raycaster.setFromCamera(mouse, camera);

        // District hover
        if (mapMode === 'state' && districtMeshes.length) {
            const dHits = raycaster.intersectObjects(districtMeshes);
            if (dHits.length) {
                const dm = dHits[0].object;
                if (hoveredDistrict && hoveredDistrict !== dm)
                    hoveredDistrict.material.color.setHex(hoveredDistrict.userData.originalColor);
                setHoveredDistrict(dm);
                if (dm.position.z < 0.30 && !ACTIVE_LAYERS.has('population')) {
                    dm.material.color.setHex(lighten(dm.userData.originalColor, 90));
                    dm.material.opacity = 0.95;
                }
                districtTip.style.display = 'block';
                districtTip.style.left = (e.clientX + 14) + 'px';
                districtTip.style.top = (e.clientY - 10) + 'px';
                const _ph = dm.userData.partyHex || dm.userData.regionHex;
                const _pl = PARTY_LABEL[dm.userData.party || 'U'];
                districtTip.innerHTML = `<strong style="color:#e2e8f0">${dm.userData.districtLabel}</strong><br>
                    <span style="color:${_ph};font-size:11px">● ${_pl} · 119th Congress · Click for candidates</span>`;
                tooltip.style.display = 'none';
                renderer.domElement.style.cursor = 'pointer';
                return;
            }
            if (hoveredDistrict && hoveredDistrict.position.z < 0.30) {
                if (!ACTIVE_LAYERS.has('population'))
                    hoveredDistrict.material.color.setHex(hoveredDistrict.userData.originalColor);
                hoveredDistrict.material.opacity = 0.72;
            }
            setHoveredDistrict(null);
            districtTip.style.display = 'none';
        }

        // State hover (overview/region only)
        if (mapMode === 'state') {
            if (hoveredMesh) {
                if (!ACTIVE_LAYERS.has('party'))
                    hoveredMesh.material.color.setHex(hoveredMesh.userData.originalColor);
                hoveredMesh.parent.position.z = 0;
                hoveredMesh = null;
            }
            tooltip.style.display = 'none';
            districtTip.style.display = 'none';
            renderer.domElement.style.cursor = districtMeshes.length ? 'default' : 'default';
            return;
        }

        if (hoveredMesh && hoveredMesh.userData.name !== selectedState) {
            if (!ACTIVE_LAYERS.has('party'))
                hoveredMesh.material.color.setHex(hoveredMesh.userData.originalColor);
            hoveredMesh.parent.position.z = 0;
        }
        const sHits = raycaster.intersectObjects(stateMeshes);
        if (sHits.length) {
            const m = sHits[0].object;
            const outsideRegion = mapMode === 'region' && activeRegion && m.userData.regionName !== activeRegion;
            if (outsideRegion) {
                if (hoveredMesh && hoveredMesh.userData.name !== selectedState) {
                    if (!ACTIVE_LAYERS.has('party'))
                        hoveredMesh.material.color.setHex(hoveredMesh.userData.originalColor);
                    hoveredMesh.parent.position.z = 0;
                }
                hoveredMesh = null; tooltip.style.display = 'none';
                renderer.domElement.style.cursor = 'not-allowed';
            } else {
                hoveredMesh = m;
                if (m.userData.name !== selectedState && !ACTIVE_LAYERS.has('party'))
                    m.material.color.setHex(lighten(m.userData.originalColor, 50));
                m.parent.position.z = 0.04;
                tooltip.style.display = 'block';
                tooltip.style.left = (e.clientX + 16) + 'px';
                tooltip.style.top = (e.clientY - 14) + 'px';
                tooltip.innerHTML = `<strong style="color:#e2e8f0;display:block;margin-bottom:3px">${m.userData.name}</strong>
                    <span style="color:${m.userData.region?.hex || '#888'};font-size:12px">● ${m.userData.regionName || ''} Region</span>`;
                renderer.domElement.style.cursor = 'pointer';
            }
        } else {
            hoveredMesh = null; tooltip.style.display = 'none';
            renderer.domElement.style.cursor = 'default';
        }
    });

    renderer.domElement.addEventListener('mouseleave', () => {
        if (hoveredMesh && hoveredMesh.userData.name !== selectedState) {
            if (!ACTIVE_LAYERS.has('party'))
                hoveredMesh.material.color.setHex(hoveredMesh.userData.originalColor);
            hoveredMesh.parent.position.z = 0;
        }
        hoveredMesh = null; tooltip.style.display = 'none'; districtTip.style.display = 'none';
    });

    renderer.domElement.addEventListener('click', () => {
        raycaster.setFromCamera(mouse, camera);

        // District click
        if (mapMode === 'state' && districtMeshes.length) {
            const dHits = raycaster.intersectObjects(districtMeshes);
            if (dHits.length) {
                const dm = dHits[0].object;
                for (const d of districtMeshes) {
                    d.material.color.setHex(d.userData.originalColor);
                    d.material.opacity = 0.72;
                    d.position.z = 0.255;
                }
                const bright = new THREE.Color(dm.userData.partyHex || dm.userData.regionHex || '#6366f1')
                    .lerp(new THREE.Color(0xffffff), 0.55);
                dm.material.color.setHex(bright.getHex());
                dm.material.opacity = 1.0;
                dm.position.z = 0.31;
                openDistrictPanel(dm.userData.districtNum, dm.userData.districtLabel, dm.userData.stateName, dm.userData.regionHex, dm.userData.party);
                trackEvent('district_click', {
                    state: dm.userData.stateName,
                    state_abbr: STATE_ABBR_MAP[dm.userData.stateName] || null,
                    district: dm.userData.districtLabel,
                    party: dm.userData.party || null,
                    region: dm.userData.regionName || null,
                });
                return;
            }
        }

        // State click
        const sHits = raycaster.intersectObjects(stateMeshes);
        if (sHits.length) {
            const m = sHits[0].object;
            if (mapMode === 'region' && activeRegion && m.userData.regionName !== activeRegion) return;
            enterStateMode(m.userData.name, m.userData.regionName, m.userData.region);
        }
    });
}

export { hoveredMesh };