/**
 * "Running Candidates" rollup — a single filterable list of every declared /
 * running candidate, in one of two views:
 *
 *   • "This state" (default) — flattened from the state-candidates payload
 *     openStatePanel() already fetched (offices / house_candidates /
 *     city_officials), grouped Federal → Statewide → Local.
 *
 *   • "In the news"          — running candidates nationwide with a news
 *     article in the last 24h (GET /api/v1/map/candidates-in-news), grouped
 *     by state. A bounded, timely slice so a national list needs no paging.
 *
 * Cards are the shared .candidate-card markup, so the click-to-open-drawer
 * delegation on #info-panel (initCandidateCardClick in panel-state.js) works
 * here for free. On top of that, clicking a card also *syncs the map* — flies
 * to / highlights the candidate's district (U.S. House) or state.
 */
import * as THREE from 'three';
import { renderCandidate } from './panel-state.js';
import { PARTY_LABEL, STATE_ABBR_MAP } from '../config/constants.js';
import { activeState } from '../state/map-state.js';
import { districtMeshes, flyToDistrictTopDown } from '../scene/district-overlay.js';
import { openDistrictPanel } from './panel-district.js';

const TIER_ORDER = ['Federal', 'Statewide', 'Local'];
const VIEW_PREF_KEY = 'u9_map_rc_view';

/** 'state' | 'news' — persisted so it survives panel re-renders / reloads. */
let viewMode = (() => {
    try { return localStorage.getItem(VIEW_PREF_KEY) === 'news' ? 'news' : 'state'; }
    catch { return 'state'; }
})();

/** Last state payload + accent color, so the toggle can re-render in place. */
let lastCtx = { data: null, color: '#6366f1' };

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function isRunning(c) {
    if (!c) return false;
    if (c.status === 'seated' || c.status === 'lost') return false;
    if (c.status === 'active' && !c.is_running) return false;
    return c.is_running === true || (c.status && c.status !== 'seated' && c.status !== 'lost');
}

/** "Citizen" is a personal-account role, not an office — never list those. */
function isCitizenRole(office) {
    return (office || '').trim().toLowerCase() === 'citizen';
}

function partyKey(p) {
    const l = (p || '').toLowerCase();
    if (l.includes('democrat')) return 'D';
    if (l.includes('republican')) return 'R';
    if (l.includes('libertarian')) return 'L';
    if (l.includes('green')) return 'G';
    if (l.includes('independent')) return 'I';
    if (l.includes('nonpartisan') || l.includes('non-partisan')) return 'N';
    return 'U';
}

const PARTY_NAME = { ...PARTY_LABEL, N: 'Nonpartisan' };

/** Relative "3h ago" / "just now" for a news timestamp. */
function timeAgo(iso) {
    if (!iso) return '';
    const then = new Date(iso).getTime();
    if (isNaN(then)) return '';
    const mins = Math.round((Date.now() - then) / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.round(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    return `${Math.round(hrs / 24)}d ago`;
}

/* ── "This state" view: flatten the three payload buckets ─────────────────── */

function collectRunning(data) {
    const out = [];
    const stateAbbr = data?.state || null;

    for (const g of (data?.offices || [])) {
        if (isCitizenRole(g.office)) continue;
        const federal = /senat/i.test(g.office || '');
        for (const c of (g.candidates || [])) {
            if (!isRunning(c)) continue;
            out.push({ ...c, office: g.office, _tier: federal ? 'Federal' : 'Statewide', _scope: g.office, _state: stateAbbr, _district: null });
        }
    }

    for (const [distKey, arr] of Object.entries(data?.house_candidates || {})) {
        for (const c of (arr || [])) {
            if (!isRunning(c)) continue;
            out.push({
                ...c,
                office: `U.S. House · ${distKey}`,
                _tier: 'Federal',
                _scope: distKey,
                _state: stateAbbr,
                _district: distKey,
            });
        }
    }

    for (const [city, groups] of Object.entries(data?.city_officials || {})) {
        for (const g of (groups || [])) {
            if (isCitizenRole(g.office)) continue;
            for (const c of (g.candidates || [])) {
                if (!isRunning(c) || isCitizenRole(c.political_office)) continue;
                out.push({ ...c, office: g.office, _tier: 'Local', _scope: city, _state: stateAbbr, _district: null });
            }
        }
    }

    const seen = new Set();
    return out.filter(c => {
        const k = (c.full_name || '').toLowerCase() + '|' + (c.office || '').toLowerCase();
        if (seen.has(k)) return false;
        seen.add(k);
        return true;
    });
}

/* ── Shared row markup ───────────────────────────────────────────────────── */

function candidateRow(c, color, groupKey) {
    const pk = partyKey(c.party);
    const nameLower = (c.full_name || '').toLowerCase();
    const scope = c._tier === 'Statewide' ? '' : (c._scope || '');
    const caption = [c.office, scope && scope !== c.office ? scope : null].filter(Boolean).join(' · ');
    const newsLine = c.news?.headline
        ? `<a class="rc-news" href="${escapeHtml(c.news.source_url || '#')}" target="_blank" rel="noopener" onclick="event.stopPropagation()">
             📰 ${escapeHtml(c.news.headline)}
             <span class="rc-news-meta">${escapeHtml([c.news.source_name, timeAgo(c.news.published_at)].filter(Boolean).join(' · '))}</span>
           </a>`
        : '';
    return `<div class="rc-row"
        data-rc-group="${escapeHtml(groupKey)}"
        data-rc-tier="${escapeHtml(c._tier || '')}"
        data-rc-party="${pk}"
        data-rc-name="${escapeHtml(nameLower)}"
        data-rc-state="${escapeHtml(c._state || '')}"
        data-rc-district="${escapeHtml(c._district || '')}">
        <p class="rc-office">${escapeHtml(caption)}</p>
        ${renderCandidate(c, color)}
        ${newsLine}
    </div>`;
}

function filterBar(all, color, extraNote = '') {
    const partiesPresent = [...new Set(all.map(c => partyKey(c.party)))].sort();
    const partyOptions = ['<option value="">All parties</option>']
        .concat(partiesPresent.map(k => `<option value="${k}">${escapeHtml(PARTY_NAME[k] || k)}</option>`))
        .join('');

    const tiers = TIER_ORDER.filter(t => all.some(c => c._tier === t));
    const chips = ['All'].concat(tiers).map(t => {
        const n = t === 'All' ? all.length : all.filter(c => c._tier === t).length;
        return `<button type="button" class="rc-chip${t === 'All' ? ' rc-chip-active' : ''}" data-rc-tier="${t}">${t} <span class="rc-chip-count">${n}</span></button>`;
    }).join('');

    return `<div class="rc-filters">
        <div class="rc-chips">${chips}</div>
        <div class="rc-filter-row">
            <input type="search" class="rc-search" placeholder="Filter by name…" aria-label="Filter candidates by name" autocomplete="off" spellcheck="false">
            <select class="rc-party" aria-label="Filter candidates by party">${partyOptions}</select>
        </div>
        ${extraNote ? `<p class="rc-note">${extraNote}</p>` : ''}
    </div>`;
}

function sectionShell(title, bodyHtml, color) {
    const stateActive = viewMode === 'state';
    return `<div class="office-section" id="rc-section">
        <div class="office-title"
             style="background:${color}18;border-left:3px solid ${color};color:${color};"
             onclick="this.closest('.office-section').classList.toggle('collapsed')"
             role="button" aria-expanded="true" tabindex="0"
             onkeydown="if(event.key==='Enter'||event.key===' ')this.click()">
            <span>🗳️&nbsp;${title}</span>
            <span class="chevron">▾</span>
        </div>
        <div class="office-body">
            <div class="rc-viewtoggle" role="tablist" aria-label="Candidate list view">
                <button type="button" class="rc-viewtab${stateActive ? ' rc-viewtab-active' : ''}" data-rc-view="state" role="tab" aria-selected="${stateActive}">This state</button>
                <button type="button" class="rc-viewtab${stateActive ? '' : ' rc-viewtab-active'}" data-rc-view="news" role="tab" aria-selected="${!stateActive}">In the news</button>
            </div>
            ${bodyHtml}
        </div>
    </div>`;
}

/* ── Entry point ─────────────────────────────────────────────────────────── */

export function renderRunningCandidatesSection(data, color) {
    color = color || '#6366f1';
    lastCtx = { data, color };

    if (viewMode === 'news') {
        // Async — render a shell now, fill it when the fetch resolves.
        queueMicrotask(() => loadNewsView(color));
        return sectionShell(
            'Running Candidates <span style="opacity:.7;font-weight:400;">· in the news</span>',
            `<div class="rc-list"><div class="panel-spinner" style="padding:12px 0;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="animation:spin 1s linear infinite;color:${color};"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4" stroke-dashoffset="10" stroke-linecap="round"/></svg>
                &nbsp;Loading candidates in the news…</div></div>`,
            color
        );
    }

    const all = collectRunning(data);
    if (!all.length) {
        // Still show the shell so the "In the news" toggle is reachable.
        return sectionShell(
            'Running Candidates',
            `<p class="rc-empty-static">No running candidates on file for this state yet. Try “In the news”.</p>`,
            color
        );
    }

    const byTier = t => all.filter(c => c._tier === t);
    const groups = TIER_ORDER.map(tier => {
        const rows = byTier(tier);
        if (!rows.length) return '';
        return `<div class="rc-group" data-rc-group="${tier}">
            <p class="rc-group-head" style="color:${color};">${tier} <span style="opacity:.6;">(${rows.length})</span></p>
            ${rows.map(c => candidateRow(c, color, tier)).join('')}
        </div>`;
    }).join('');

    const body = `
        <p class="rc-intro">Everyone currently running for office in this state — federal, statewide, and local. Tap a name for details; the map follows.</p>
        ${filterBar(all, color)}
        <p class="rc-empty" hidden>No candidates match those filters.</p>
        <div class="rc-list">${groups}</div>`;

    return sectionShell(`Running Candidates <span style="opacity:.7;font-weight:400;">(${all.length})</span>`, body, color);
}

/* ── "In the news" async view ────────────────────────────────────────────── */

async function loadNewsView(color) {
    const section = document.getElementById('rc-section');
    if (!section || viewMode !== 'news') return;
    const list = section.querySelector('.rc-list');
    if (!list) return;

    let payload;
    try {
        const res = await fetch('/api/v1/map/candidates-in-news', { headers: { Accept: 'application/json' } });
        payload = res.ok ? await res.json() : null;
    } catch {
        payload = null;
    }
    if (!section.isConnected || viewMode !== 'news') return;

    const cands = (payload?.candidates || []).map(c => ({ ...c, _tier: c._tier || 'Statewide', _scope: c.state || '', _state: c.state || null, _district: c.district || null }));

    if (!cands.length) {
        // Replace the whole body below the toggle with an empty state.
        const body = section.querySelector('.office-body');
        body.querySelector('.rc-intro')?.remove();
        body.querySelector('.rc-filters')?.remove();
        body.querySelector('.rc-empty')?.remove();
        list.innerHTML = `<p class="rc-empty-static">No running candidate has made the news in the last ${payload?.window_hours ?? 24} hours. Check back later.</p>`;
        return;
    }

    // Group by state, alphabetical.
    const byState = new Map();
    for (const c of cands) {
        const k = c.state || '—';
        if (!byState.has(k)) byState.set(k, []);
        byState.get(k).push(c);
    }
    const groupsHtml = [...byState.keys()].sort().map(st => {
        const rows = byState.get(st);
        return `<div class="rc-group" data-rc-group="${escapeHtml(st)}">
            <p class="rc-group-head" style="color:${color};">${escapeHtml(st)} <span style="opacity:.6;">(${rows.length})</span></p>
            ${rows.map(c => candidateRow(c, color, st)).join('')}
        </div>`;
    }).join('');

    const body = section.querySelector('.office-body');
    body.querySelector('.rc-intro')?.remove();
    body.querySelector('.rc-filters')?.remove();
    body.querySelector('.rc-empty')?.remove();

    list.insertAdjacentHTML('beforebegin', `
        <p class="rc-intro">${cands.length} running ${cands.length === 1 ? 'candidate' : 'candidates'} with news in the last ${payload?.window_hours ?? 24}h, nationwide. Tap one to fly there.</p>
        ${filterBar(cands, color)}
        <p class="rc-empty" hidden>No candidates match those filters.</p>`);
    list.innerHTML = groupsHtml;
}

/* ── Filtering ───────────────────────────────────────────────────────────── */

function applyFilters(section) {
    if (!section) return;
    const tier = section.querySelector('.rc-chip-active')?.dataset.rcTier || 'All';
    const party = section.querySelector('.rc-party')?.value || '';
    const q = (section.querySelector('.rc-search')?.value || '').trim().toLowerCase();

    let visible = 0;
    section.querySelectorAll('.rc-row').forEach(row => {
        const okTier = tier === 'All' || row.dataset.rcTier === tier;
        const okParty = !party || row.dataset.rcParty === party;
        const okName = !q || (row.dataset.rcName || '').includes(q);
        const show = okTier && okParty && okName;
        row.hidden = !show;
        if (show) visible++;
    });
    section.querySelectorAll('.rc-group').forEach(group => {
        group.hidden = ![...group.querySelectorAll('.rc-row')].some(r => !r.hidden);
    });
    const empty = section.querySelector('.rc-empty');
    if (empty) empty.hidden = visible !== 0;
}

/* ── Map sync ────────────────────────────────────────────────────────────── */

function highlightDistrictMesh(dm) {
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
}

function syncMapToCandidate(stateAbbr, district) {
    if (!stateAbbr) return;
    const curAbbr = STATE_ABBR_MAP[activeState] || null;
    const distNum = district ? String(district).split('-').pop() : null;

    // Same state + a district → select it directly (no full state reload).
    if (curAbbr === stateAbbr && distNum) {
        const target = distNum === 'AL' ? 'AL' : String(distNum).padStart(2, '0');
        const dm = districtMeshes.find(m => {
            const n = String(m.userData.districtNum);
            return (n === 'AL' ? 'AL' : n.padStart(2, '0')) === target;
        });
        if (dm) {
            highlightDistrictMesh(dm);
            flyToDistrictTopDown(dm);
            openDistrictPanel(dm.userData.districtNum, dm.userData.districtLabel, dm.userData.stateName, dm.userData.regionHex, dm.userData.party);
            return;
        }
    }

    // Different state, or a district we don't have meshes for → full navigation.
    if (curAbbr !== stateAbbr || distNum) {
        window.__mapGoTo?.(stateAbbr, distNum || null);
    }
}

/* ── One-time delegated wiring ───────────────────────────────────────────── */

export function initRunningCandidatesFilters() {
    const host = document.getElementById('panel-running-candidates');
    if (!host) return;

    host.addEventListener('click', e => {
        // View toggle: "This state" ↔ "In the news"
        const viewTab = e.target.closest('.rc-viewtab');
        if (viewTab) {
            const next = viewTab.dataset.rcView === 'news' ? 'news' : 'state';
            if (next === viewMode) return;
            viewMode = next;
            try { localStorage.setItem(VIEW_PREF_KEY, viewMode); } catch {}
            host.innerHTML = renderRunningCandidatesSection(lastCtx.data, lastCtx.color);
            return;
        }

        // Tier chip
        const chip = e.target.closest('.rc-chip');
        if (chip) {
            const section = chip.closest('#rc-section');
            section.querySelectorAll('.rc-chip').forEach(c => c.classList.remove('rc-chip-active'));
            chip.classList.add('rc-chip-active');
            applyFilters(section);
            return;
        }

        // Candidate card → move the map to match (drawer opens via the
        // separate #info-panel delegation in panel-state.js).
        const card = e.target.closest('.rc-row .candidate-card');
        if (card) {
            const row = card.closest('.rc-row');
            syncMapToCandidate(row?.dataset.rcState || '', row?.dataset.rcDistrict || '');
        }
    });

    const onFilter = e => {
        if (!e.target.matches('.rc-search, .rc-party')) return;
        applyFilters(e.target.closest('#rc-section'));
    };
    host.addEventListener('input', onFilter);
    host.addEventListener('change', onFilter);
}
