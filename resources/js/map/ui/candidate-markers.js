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
import { mapGroup, renderer, camera, leftInset } from '../scene/setup.js';
import { mapLabelsLayer } from './labels-overlay.js';
import { stateData, activeState } from '../state/map-state.js';
import { openPolDrawer } from './politician-drawer.js';
import { trackEvent } from '../api/interaction.js';
import { rectsOverlap } from '../scene/overlay-collision.js';

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

    // Small 2D grid around the capital point (world-space), roughly square,
    // so candidates start visually separated on-screen before the
    // screen-space collision pass in updateCandidateMarkers() runs as a
    // safety net for extreme zoom-outs. A single-axis fan (the old
    // approach) couldn't keep 15-20+ statewide candidates apart with a
    // world-space spread small enough to still read as "at the capital".
    const count = sorted.length;
    const cols = Math.ceil(Math.sqrt(count));
    const rows = Math.ceil(count / cols);
    const cellSize = 0.045;

    sorted.forEach((cand, i) => {
        const col = i % cols;
        const row = Math.floor(i / cols);
        const ox = count > 1 ? (col - (cols - 1) / 2) * cellSize : 0;
        const oy = count > 1 ? ((rows - 1) / 2 - row) * cellSize : 0;
        const worldPos = new THREE.Vector3(xy[0] + ox, xy[1] + oy, 0.42 + i * 0.001);

        const el = document.createElement('button');
        el.className = 'candidate-marker';
        const safeName = cand.full_name || 'Candidate';
        // Native title tooltip — same hover idiom used for the boundary
        // favorite star and the map's (i) info badge — instead of an
        // always-on name pill that can't fit 15-20+ candidates legibly.
        el.title = `${safeName} — ${cand.officeLabel}`;
        el.setAttribute('aria-label', `${safeName} — ${cand.office} — ${capitalName}`);
        const partyDot = cand.party ? cand.party.charAt(0).toUpperCase() : 'U';
        const dotColor = PARTY_HEX[partyDot] || '#a7b4c7';
        const statusClass = cand.status === 'seated' ? 'seated' : 'running';
        el.innerHTML =
            `<span class="cand-dot-ring" style="--cand-color:${dotColor}">` +
            `<span class="cand-dot-core ${statusClass}"></span></span>`;

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
 * Project all candidate markers to screen coordinates each frame, then
 * suppress any that would visually overlap — either each other or an
 * already-placed city/capital/district marker (occupiedRects, from
 * render-loop.js). candidateSprites is already in sortCandidates() priority
 * order (seated > running, verified first), so earlier entries win ties.
 * A hidden candidate is still reachable via the "Statewide Executive
 * Offices" side-panel list, same fallback district labels rely on.
 *
 * @param {Array<{left,right,top,bottom}>} occupiedRects
 */
const CAND_HALF = 13; // half-width/height of a dot's collision box, incl. gap

export function updateCandidateMarkers(occupiedRects = []) {
    if (!candidateSprites.length) return;
    const W = renderer.domElement.clientWidth;
    const H = renderer.domElement.clientHeight;
    const _vec = new THREE.Vector3();

    const candidates = [];
    for (const dot of candidateSprites) {
        _vec.copy(dot.worldPos);
        _vec.applyMatrix4(mapGroup.matrixWorld);
        _vec.project(camera);
        const sx = (_vec.x * 0.5 + 0.5) * W;
        const sy = (-_vec.y * 0.5 + 0.5) * H;
        const behind = _vec.z > 1;
        const outside = sx < -80 || sx > W + 80 || sy < 30 || sy > H + 80;
        if (behind || outside) { dot.el.style.display = 'none'; continue; }
        candidates.push({ dot, sx, sy });
    }

    // .candidate-marker is center-anchored (translate(-50%,-50%) — map.css).
    const placed = [...occupiedRects];
    for (const { dot, sx, sy } of candidates) {
        const rect = { left: sx - CAND_HALF, right: sx + CAND_HALF, top: sy - CAND_HALF, bottom: sy + CAND_HALF };
        const collides = placed.some(p => rectsOverlap(rect, p));
        if (collides) { dot.el.style.display = 'none'; continue; }
        placed.push(rect);
        dot.el.style.display = 'flex';
        dot.el.style.left = (sx + leftInset()) + 'px';
        dot.el.style.top = sy + 'px';
    }
}
