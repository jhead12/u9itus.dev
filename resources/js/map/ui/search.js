/**
 * Search palette — state/district/politician search with keyboard navigation.
 * Politician results are fetched live from /api/v1/map/politician-search.
 */
import { stateMeshes } from '../scene/state-meshes.js';
import { enterRegionMode, enterStateMode } from '../navigation/mode-transitions.js';
import { STATE_ABBR_MAP, REGIONS, stateToRegion, DISTRICT_COUNTS } from '../config/constants.js';
import { districtMeshes } from '../scene/district-overlay.js';
import { activeState } from '../state/map-state.js';
import { trackEvent } from '../api/interaction.js';
import { flyToMeshesTopDown } from '../scene/camera-animation.js';
import { openDistrictPanel } from './panel-district.js';
import { openPolDrawer } from './politician-drawer.js';
import * as THREE from 'three';

const searchOverlay = document.getElementById('search-overlay');
const searchInput   = document.getElementById('search-input');
const searchResults = document.getElementById('search-results');
const searchEmpty   = document.getElementById('search-empty');
let searchActiveIdx = -1;

/* Reverse of STATE_ABBR_MAP: "CA" -> "California" */
const ABBR_TO_STATE = Object.fromEntries(
    Object.entries(STATE_ABBR_MAP).map(([name, abbr]) => [abbr, name])
);

/* Live politician search — debounced fetch against /api/v1/map/politician-search */
let polResults      = [];
let polResultsQuery = '';
let polFetchToken    = 0;
let polDebounceTimer = null;

function partyColor(party) {
    const p = (party || '').toLowerCase();
    if (p.includes('democrat'))   return '#3b82f6';
    if (p.includes('republican')) return '#ef4444';
    if (p.includes('libertarian')) return '#eab308';
    if (p.includes('green'))      return '#22c55e';
    return '#94a3b8';
}

function fetchPoliticians(q) {
    const trimmed = q.trim();
    if (trimmed.length < 2) {
        polResults = [];
        polResultsQuery = trimmed;
        renderSearchResults(searchInput.value);
        return;
    }
    const token = ++polFetchToken;
    fetch(`/api/v1/map/politician-search?q=${encodeURIComponent(trimmed)}`)
        .then(res => res.ok ? res.json() : { results: [] })
        .then(data => {
            if (token !== polFetchToken) return; // superseded by a newer keystroke
            polResults = Array.isArray(data.results) ? data.results : [];
            polResultsQuery = trimmed;
            renderSearchResults(searchInput.value);
        })
        .catch(() => {});
}

/* Build searchable index: states + every district per state */
const SEARCH_INDEX = [];

for (const [stateName, abbr] of Object.entries(STATE_ABBR_MAP)) {
    const regionName = stateToRegion[stateName];
    const region     = REGIONS[regionName];
    SEARCH_INDEX.push({
        type:       'state',
        label:      stateName,
        sub:        `${abbr} · ${regionName || ''} Region`,
        abbr,
        stateName,
        regionName,
        region,
        keywords:   [stateName.toLowerCase(), abbr.toLowerCase()],
        color:      region?.hex || '#6366f1',
    });
}

for (const [stateName, count] of Object.entries(DISTRICT_COUNTS)) {
    const abbr       = STATE_ABBR_MAP[stateName];
    const regionName = stateToRegion[stateName];
    const region     = REGIONS[regionName];
    if (!abbr || count === 0) continue;
    for (let d = 1; d <= count; d++) {
        const label = `${stateName} — District ${d}`;
        SEARCH_INDEX.push({
            type:        'district',
            label,
            sub:         `${abbr}-${String(d).padStart(2,'0')} · 119th Congress · U.S. House`,
            abbr,
            stateName,
            districtNum: String(d),
            regionName,
            region,
            color:       region?.hex || '#6366f1',
            keywords:    [
                stateName.toLowerCase(),
                abbr.toLowerCase(),
                `${abbr.toLowerCase()}-${d}`,
                `${abbr.toLowerCase()}${d}`,
                `district ${d}`,
                `${d}`,
            ],
        });
    }
    // At-large states
    if (count === 1) {
        SEARCH_INDEX.push({
            type:        'district',
            label:       `${stateName} — At-Large`,
            sub:         `${abbr}-AL · 119th Congress · U.S. House`,
            abbr,
            stateName,
            districtNum: 'AL',
            regionName,
            region,
            color:       region?.hex || '#6366f1',
            keywords:    [stateName.toLowerCase(), abbr.toLowerCase(), 'at-large', `${abbr.toLowerCase()}-al`],
        });
    }
}

function scoreMatch(item, q) {
    const terms = q.toLowerCase().trim().split(/\s+/);
    let score = 0;
    for (const term of terms) {
        if (!item.keywords.some(k => k.includes(term))) return 0;
        if (item.keywords.some(k => k === term)) score += 10;
        if (item.label.toLowerCase().includes(term)) score += 5;
    }
    if (item.type === 'state' && q.length <= 4) score += 3;
    return score;
}

function renderSearchResults(q) {
    searchActiveIdx = -1;
    searchResults.innerHTML = '';

    if (!q.trim()) {
        searchEmpty.style.display = 'none';
        const ql = document.createElement('div');
        ql.className = 'sr-group-label';
        ql.textContent = 'Quick picks — regions';
        searchResults.appendChild(ql);
        for (const [rName, r] of Object.entries(REGIONS)) {
            appendResult({
                type: 'region', label: rName + ' Region',
                sub: `${r.states.length} states`, color: r.hex, regionName: rName, region: r,
                keywords: [],
            });
        }
        return;
    }

    const scored = SEARCH_INDEX
        .map(item => ({ item, score: scoreMatch(item, q) }))
        .filter(x => x.score > 0)
        .sort((a, b) => b.score - a.score)
        .slice(0, 12);

    const politicians = (polResultsQuery === q.trim()) ? polResults : [];

    if (!scored.length && !politicians.length) {
        searchEmpty.style.display = 'block';
        return;
    }
    searchEmpty.style.display = 'none';

    const states    = scored.filter(x => x.item.type === 'state');
    const districts = scored.filter(x => x.item.type === 'district');

    if (states.length) {
        const gl = document.createElement('div');
        gl.className = 'sr-group-label'; gl.textContent = 'States';
        searchResults.appendChild(gl);
        states.forEach(x => appendResult(x.item));
    }
    if (districts.length) {
        const gl = document.createElement('div');
        gl.className = 'sr-group-label'; gl.textContent = 'Congressional Districts';
        searchResults.appendChild(gl);
        districts.forEach(x => appendResult(x.item));
    }
    if (politicians.length) {
        const gl = document.createElement('div');
        gl.className = 'sr-group-label'; gl.textContent = 'Politicians';
        searchResults.appendChild(gl);
        politicians.forEach(pol => {
            const color = partyColor(pol.party);
            appendResult({
                type:  'politician',
                label: pol.full_name,
                sub:   [pol.office, pol.state, pol.party].filter(Boolean).join(' · '),
                abbr:  pol.state || '',
                color,
                data:  pol,
            });
        });
    }
}

function appendResult(item) {
    const el = document.createElement('div');
    el.className   = 'sr-item';
    el.setAttribute('role', 'option');
    el.dataset.idx = searchResults.querySelectorAll('.sr-item').length;

    const icon = item.type === 'state'      ? '🏛'
               : item.type === 'district'   ? '📍'
               : item.type === 'politician' ? '👤'
               : '🗺';

    el.innerHTML = `
        <div class="sr-icon" style="background:${item.color}18;border:1px solid ${item.color}33;">${icon}</div>
        <div class="sr-main">
            <div class="sr-name">${item.label}</div>
            <div class="sr-sub">${item.sub}</div>
        </div>
        <span class="sr-badge" style="background:${item.color}22;color:${item.color};border:1px solid ${item.color}44;">${item.abbr || item.regionName?.slice(0,2) || ''}</span>`;

    el.addEventListener('click', () => activateResult(item));
    el.addEventListener('mouseenter', () => {
        setActiveIdx(parseInt(el.dataset.idx));
    });
    searchResults.appendChild(el);
}

function setActiveIdx(idx) {
    const items = searchResults.querySelectorAll('.sr-item');
    items.forEach((el, i) => el.classList.toggle('active', i === idx));
    searchActiveIdx = idx;
}

async function activateResult(item) {
    closeSearch();
    trackEvent('search_result_select', {
        state:      item.stateName  || null,
        state_abbr: item.abbr       || null,
        region:     item.regionName || null,
        district:   item.type === 'district' ? `${item.abbr}-${item.districtNum}` : null,
        meta:       { resultType: item.type, label: item.label, politicianSlug: item.data?.slug || null },
    });
    if (item.type === 'politician') {
        const pol       = item.data;
        const stateName = pol.state ? ABBR_TO_STATE[pol.state.toUpperCase()] : null;
        if (stateName) {
            const mesh = stateMeshes.find(m => m.userData.name === stateName);
            if (mesh) await enterStateMode(stateName, mesh.userData.regionName, mesh.userData.region);
        }
        openPolDrawer({
            full_name:       pol.full_name,
            office:          pol.office,
            party:           pol.party,
            photo:           pol.photo,
            slug:            pol.slug,
            status:          pol.status,
            is_running:      pol.is_running,
            verified:        pol.verified,
            ballotpedia_url: pol.ballotpedia_url,
            website:         pol.website,
            profile_url:     pol.profile_url,
            bio_excerpt:     pol.bio_excerpt,
        }, item.color);
        return;
    }
    if (item.type === 'region') {
        enterRegionMode(item.regionName, item.region);
        return;
    }
    const mesh = stateMeshes.find(m => m.userData.name === item.stateName);
    if (!mesh) return;
    await enterStateMode(item.stateName, mesh.userData.regionName, mesh.userData.region);
    if (item.type === 'district' && item.districtNum !== 'AL') {
        await new Promise(r => setTimeout(r, 800));
        const target = districtMeshes.find(m =>
            m.userData.stateName === item.stateName &&
            m.userData.districtNum === item.districtNum
        );
        if (target) {
            for (const d of districtMeshes) {
                d.material.color.setHex(d.userData.originalColor);
                d.material.opacity = 0.45;
                d.position.z       = 0.255;
            }
            const bright = new THREE.Color(target.userData.regionHex || '#6366f1')
                .lerp(new THREE.Color(0xffffff), 0.72);
            target.material.color.setHex(bright.getHex());
            target.material.opacity = 1.0;
            target.position.z       = 0.31;
            flyToMeshesTopDown([target], 2.6);
            openDistrictPanel(target.userData.districtNum, target.userData.districtLabel, target.userData.stateName, target.userData.regionHex, target.userData.party);
        }
    }
}

export function openSearch() {
    searchOverlay.classList.add('open');
    searchInput.value = '';
    polResults = [];
    polResultsQuery = '';
    clearTimeout(polDebounceTimer);
    renderSearchResults('');
    setTimeout(() => searchInput.focus(), 40);
    trackEvent('search_opened', { state: activeState || null });
}

export function closeSearch() {
    searchOverlay.classList.remove('open');
}

/**
 * Set up search event listeners.
 */
export function initSearch() {
    searchInput.addEventListener('input', () => {
        const q = searchInput.value;
        renderSearchResults(q);
        clearTimeout(polDebounceTimer);
        polDebounceTimer = setTimeout(() => fetchPoliticians(q), 220);
    });
    searchInput.addEventListener('keydown', e => {
        const items = searchResults.querySelectorAll('.sr-item');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            const next = Math.min(searchActiveIdx + 1, items.length - 1);
            setActiveIdx(next);
            items[next]?.scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            const prev = Math.max(searchActiveIdx - 1, 0);
            setActiveIdx(prev);
            items[prev]?.scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const active = searchResults.querySelector('.sr-item.active');
            if (active) active.click();
            else if (items.length > 0) items[0].click();
        } else if (e.key === 'Escape') {
            closeSearch();
        }
    });

    // Open via button or "/" key
    document.getElementById('btn-search')?.addEventListener('click', openSearch);
    document.addEventListener('keydown', e => {
        if (e.key === '/' && !e.ctrlKey && !e.metaKey &&
            !['INPUT','TEXTAREA'].includes(document.activeElement?.tagName)) {
            e.preventDefault();
            openSearch();
        }
        if (e.key === 'Escape' && searchOverlay.classList.contains('open')) {
            closeSearch();
        }
    });

    // Click backdrop to close
    searchOverlay.addEventListener('click', e => {
        if (e.target === searchOverlay) closeSearch();
    });
}