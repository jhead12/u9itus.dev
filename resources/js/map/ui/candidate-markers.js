/**
 * Statewide candidate markers rendered at each state capital.
 *
 * When a user drills into a state, we place one marker per statewide
 * candidate (Governor, AG, etc.) at the capital coordinate. Clicking a
 * marker opens the politician drawer for that candidate.
 */
import * as THREE from 'three';
import { STATE_CAPITALS } from '../config/city-data.js';
import { STATE_ABBR_MAP, PARTY_HEX } from '../config/constants.js';
import { project } from '../scene/projection.js';
import { mapGroup } from '../scene/setup.js';
import { renderer, camera } from '../scene/setup.js';
import { mapLabelsLayer } from './labels-overlay.js';
import { stateData, activeState } from '../state/map-state.js';
import { openPolDrawer } from './politician-drawer.js';
import { trackEvent } from '../api/interaction.js';

export let candidateSprites = [];

const STATUS_ORDER = { seated: 0, active: 1, running: 2 };

/**
 * Build a short, stable marker label from an office name.
 */
function markerLabelForOffice(office) {
    if (!office) return '';
    const lower = office.toLowerCase();
    if (lower.includes('governor') && !lower.includes('lieutenant')) return 'Governor';
    if (lower.includes('lieutenant')) return 'Lt. Gov';
    if (lower.includes('attorney general')) return 'Attorney Gen';
    if (lower.includes('treasurer')) return 'Treasurer';
    if (lower.includes('controller') || lower.includes('comptroller')) return 'Controller';
    if (lower.includes('secretary of state')) return 'Sec. of State';
    return office.replace(/^State\s+/i, '').slice(0, 18);
}

/**
 * Determine the dominant party among a list of candidates.
 */
function dominantParty(candidates) {
    const counts = {};
    for (const c of candidates) {
        const p = c.party?.charAt(0)?.toUpperCase() || 'U';
        counts[p] = (counts[p] || 0) + 1;
    }
    let best = 'U', bestCount = 0;
    for (const [p, n] of Object.entries(counts)) {
        if (n > bestCount) { best = p; bestCount = n; }
    }
    return best;
}

/**
 * Sort candidates so seated/verified appear first.
 */
function sortCandidates(candidates) {
    return [...candidates].sort((a, b) => {
        const sa = STATUS_ORDER[a.status] ?? 99;
        const sb = STATUS_ORDER[b.status] ?? 99;
        if (sa !== sb) return sa - sb;
        if (a.verified && !b.verified) return -1;
        if (!a.verified && b.verified) return 1;
        return (a.full_name || '').localeCompare(b.full_name || '');
    });
}

/**
 * Build candidate markers at the state capital for the active state.
 */
export function buildCandidateMarkers(stateName) {
    clearCandidateMarkers();
    const abbr = STATE_ABBR_MAP[stateName];
    const cap = STATE_CAPITALS[abbr];
    if (!cap || !stateData?.offices?.length) return;

    const [capitalName, lat, lng] = cap;
    const xy = project([lng, lat]);
    if (!xy) return;

    // Flatten all statewide candidates; they share the capital coordinate.
    const allCandidates = [];
    for (const group of stateData.offices) {
        const office = group.office || 'Statewide';
        for (const c of group.candidates || []) {
            allCandidates.push({ ...c, office, officeLabel: markerLabelForOffice(office) });
        }
    }
    if (!allCandidates.length) return;

    const sorted = sortCandidates(allCandidates);
    const party = dominantParty(sorted);
    const accent = PARTY_HEX[party] || '#6366f1';

    // Spread markers in a small vertical fan so multiple candidates don't overlap.
    const count = sorted.length;
    const spread = Math.min(0.18, 0.04 * count);
    const startY = count > 1 ? spread * 0.5 : 0;

    sorted.forEach((cand, i) => {
        const offset = count > 1 ? startY - (i * (spread / (count - 1))) : 0;
        const worldPos = new THREE.Vector3(xy[0], xy[1] + offset, 0.42 + i * 0.01);

        const el = document.createElement('button');
        el.className = 'candidate-marker';
        const safeName = cand.full_name || 'Candidate';
        el.setAttribute('aria-label', `${safeName} — ${cand.office} — ${capitalName}`);
        const partyDot = cand.party ? cand.party.charAt(0).toUpperCase() : 'U';
        const dotColor = PARTY_HEX[partyDot] || '#a7b4c7';
        const statusClass = cand.status === 'seated' ? 'seated' : 'running';
        el.innerHTML =
            `<span class="cand-dot-ring" style="--cand-color:${dotColor}">` +
            `<span class="cand-dot-core ${statusClass}"></span></span>` +
            `<span class="cand-name-tag" style="--cand-color:${dotColor}">` +
            `<span class="cand-office">${cand.officeLabel}</span>` +
            `<span class="cand-name">${safeName}</span>` +
            `</span>`;

        el.addEventListener('click', (e) => {
            e.stopPropagation();
            trackEvent('candidate_marker_click', {
                state: activeState || null,
                state_abbr: abbr || null,
                meta: {
                    candidate: cand.full_name,
                    office: cand.office,
                    party: cand.party,
                },
            });
            openPolDrawer(
                { ...cand, office: `${cand.office} — ${stateName}` },
                dotColor,
                { capitalName, isStatewide: true }
            );
        });

        mapLabelsLayer.appendChild(el);
        candidateSprites.push({ el, worldPos, name: safeName, office: cand.office });
        requestAnimationFrame(() => el.classList.add('visible'));
    });
}

/**
 * Remove all candidate markers from the DOM and clear the sprite array.
 */
export function clearCandidateMarkers() {
    for (const s of candidateSprites) s.el.remove();
    candidateSprites = [];
}

/**
 * Project all candidate markers to screen coordinates each frame.
 */
export function updateCandidateMarkers() {
    if (!candidateSprites.length) return;
    const W = renderer.domElement.clientWidth;
    const H = renderer.domElement.clientHeight;
    const _vec = new THREE.Vector3();
    for (const dot of candidateSprites) {
        _vec.copy(dot.worldPos);
        _vec.applyMatrix4(mapGroup.matrixWorld);
        _vec.project(camera);
        const sx = (_vec.x * 0.5 + 0.5) * W;
        const sy = (-_vec.y * 0.5 + 0.5) * H;
        const behind = _vec.z > 1;
        const outside = sx < -80 || sx > W + 80 || sy < 30 || sy > H + 80;
        if (behind || outside) {
            dot.el.style.display = 'none';
        } else {
            dot.el.style.display = 'flex';
            dot.el.style.left = sx + 'px';
            dot.el.style.top = sy + 'px';
        }
    }
}
