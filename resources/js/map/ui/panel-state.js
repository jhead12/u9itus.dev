/**
 * State panel — renders statewide office holders, candidates, city officials.
 */
import { STATE_ABBR_MAP, PARTY_HEX, PARTY_LABEL, OFFICE_ROLES, CITY_OFFICE_ROLES, DISTRICT_COUNTS } from '../config/constants.js';
import { stateData, statePanelRequestId, mapMode, activeRegion, activeState, colorMode, DISTRICT_CONFIG } from '../state/map-state.js';
import { openDistrictPanel } from './panel-district.js';
import { openPolDrawer } from './politician-drawer.js';
import { fmtPop } from '../config/city-data.js';
import { trackEvent } from '../api/interaction.js';

export const OFFICE_DEFAULT_OPEN = new Set([
    'Governor', 'Lieutenant Governor', 'Attorney General',
    'State Treasurer', 'State Controller', 'Secretary of State',
    'Other Statewide', 'Mayor',
]);
let _officeIdx = 0;

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

export function partyClass(p) {
    if (!p) return 'party-I';
    const l = p.toLowerCase();
    if (l.includes('democrat')) return 'party-D';
    if (l.includes('republican')) return 'party-R';
    if (l.includes('libertarian')) return 'party-L';
    if (l.includes('green')) return 'party-G';
    return 'party-I';
}

export function detectElectionPhase(candidates) {
    const today = new Date();
    let anyPrimaryResult = false, generalPassed = false;
    for (const c of (candidates || [])) {
        if (c.primary_result) anyPrimaryResult = true;
        if (c.status === 'lost') anyPrimaryResult = true;
        if (c.general_date && new Date(c.general_date) < today) { generalPassed = true; break; }
    }
    if (generalPassed) return 'post_general';
    if (anyPrimaryResult) return 'post_primary';
    return 'pre_primary';
}

export function renderCandidate(c, color) {
    color = color || '#6366f1';
    const safeName = escapeHtml(c.full_name || 'Unknown Candidate');
    const avatarSvg = window.avatarInitials(c.full_name, color, 36);
    const fallbackDataUri = `data:image/svg+xml;utf8,${encodeURIComponent(avatarSvg)}`;
    const av = c.photo
        ? `<img class="candidate-avatar" src="${escapeHtml(c.photo)}" loading="lazy" alt="${safeName}" onerror="this.onerror=null;this.src='${fallbackDataUri}';">`
        : `<span class="candidate-avatar-placeholder">${avatarSvg}</span>`;
    const py = c.party ? `<span class="party-pill ${partyClass(c.party)}">${c.party}</span>` : '';
    const elDate = c.general_date || c.election_date || null;
    const elDateStr = elDate ? (() => {
        try { return new Date(elDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }); }
        catch { return null; }
    })() : null;
    const elBadge = (c.is_running && elDateStr)
        ? `<span style="color:#64748b;font-size:9px;margin-left:4px;">📅 ${elDateStr}</span>`
        : '';
    const st = c.status === 'seated' ? `<span class="status-seated">● Seated</span>` : c.is_running ? `<span class="status-running">● Running 2026</span>${elBadge}` : '';
    const vf = c.verified ? `<span class="verified-badge">✓ Verified</span>` : '';
    const popupData = encodeURIComponent(JSON.stringify({ ...c, color }));
    const _slugAttr = c.profile_url
        ? (() => { try { return new URL(c.profile_url, location.origin).pathname.split('/').filter(Boolean).pop() || ''; } catch { return ''; } })()
        : '';
    return `<div class="candidate-card"
        style="border-left-color:${color};"
        data-candidate="${popupData}"
        ${_slugAttr ? `data-slug="${_slugAttr}"` : ''}
        title="Click to learn more about ${safeName}"
        role="button" tabindex="0"
        onkeydown="if(event.key==='Enter'||event.key===' ')this.click()">
        ${av}
        <div style="flex:1;min-width:0;">
            <div class="candidate-name">${safeName}</div>
            <div class="candidate-meta">${py}${st}${vf}</div>
        </div></div>`;
}

export function renderOfficeGroup(g, roles, color) {
    color = color || '#6366f1';
    const role = roles?.[g.office] ?? '';
    const isOpen = OFFICE_DEFAULT_OPEN.has(g.office);
    const sectionId = `off-body-${_officeIdx++}`;
    const phase = g.election_phase || detectElectionPhase(g.candidates);
    const isSeated = c => c.status === 'seated' || (c.status === 'active' && !c.is_running);
    const seated = g.candidates.filter(isSeated);
    let running = g.candidates.filter(c => !isSeated(c) && c.status !== 'lost');
    const today = new Date();
    const nextElDate = g.candidates
        .map(c => c.general_date || c.election_date || null)
        .filter(Boolean)
        .map(d => new Date(d))
        .filter(d => !isNaN(d))
        .sort((a, b) => a - b)
        .find(d => d >= today) || null;
    const nextElStr = nextElDate
        ? nextElDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
        : null;

    let runningLabel = '';
    if (phase === 'post_general') {
        running = [];
    } else if (phase === 'post_primary') {
        running = running.filter(c => c.primary_result === 'advanced_to_general');
        const genDate = running.find(c => c.general_date)?.general_date;
        runningLabel = 'General Election Candidates'
            + (genDate ? ` · ${new Date(genDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}` : '');
    } else {
        runningLabel = '2026 Primary Candidates';
    }

    const seatedHtml = seated.map(c => {
        const termNotice = (c.term_end || c.term_note)
            ? `<div style="display:flex;align-items:center;gap:6px;background:#0f172a;border:1px solid #334155;border-radius:6px;padding:6px 10px;margin-bottom:8px;font-size:10px;">
                <span style="color:#f59e0b;">⏳</span>
                <span style="color:#94a3b8;">
                  ${c.term_end ? `<strong style="color:#e2e8f0;">Term ends ${c.term_end}</strong>` : ''}
                  ${c.term_note ? `<span style="color:#64748b;"> &nbsp;·&nbsp; ${c.term_note}</span>` : ''}
                </span>
               </div>`
            : '';
        return termNotice + renderCandidate({ ...c, office: g.office }, color);
    }).join('');

    const candidatesHtml = running.length
        ? `<p style="color:#475569;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;margin:10px 0 6px;">${runningLabel}</p>
           ${running.map(c => renderCandidate({ ...c, office: g.office }, color)).join('')}`
        : '';

    const allNames = g.candidates.map(c => c.full_name).join(', ');
    const nameSummary = !isOpen
        ? `<span style="font-weight:400;opacity:.55;font-size:9px;margin-left:8px;text-transform:none;letter-spacing:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:160px;display:inline-block;vertical-align:middle;">${allNames}</span>`
        : '';

    return `<div class="office-section${isOpen ? '' : ' collapsed'}" id="off-${sectionId}">
        <div class="office-title"
             style="background:${color}18;border-left:3px solid ${color};color:${color};"
             onclick="(function(el){
               const sec=el.closest('.office-section');
               const open=sec.classList.toggle('collapsed');
               el.querySelector('.name-summary').style.display = sec.classList.contains('collapsed') ? 'inline-block' : 'none';
             })(this)"
             role="button" aria-expanded="${isOpen}" tabindex="0"
             onkeydown="if(event.key==='Enter'||event.key===' ')this.click()">
            <span style="display:flex;align-items:center;gap:6px;flex:1;min-width:0;">
              <span>🏛&nbsp;${g.office}</span>
              ${nextElStr ? `<span style="background:#f59e0b18;border:1px solid #f59e0b44;border-radius:4px;padding:1px 6px;font-size:9px;font-weight:600;color:#f59e0b;white-space:nowrap;">📅 ${nextElStr}</span>` : ''}
              <span class="name-summary" style="font-weight:400;opacity:.55;font-size:9px;margin-left:4px;text-transform:none;letter-spacing:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:120px;display:${isOpen ? 'none' : 'inline-block'};vertical-align:middle;">${allNames}</span>
            </span>
            <span class="chevron">▾</span>
        </div>
        <div class="office-body">
            ${role ? `<p class="office-role-tip">${role}</p>` : ''}
            ${seated.length ? `<p style="color:#475569;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;margin:0 0 6px;">Current Officeholder</p>` : ''}
            ${seatedHtml}
            ${candidatesHtml}
        </div>
    </div>`;
}

export function noDataNotice(msg) {
    return `<div style="display:flex;align-items:center;gap:8px;background:#1e293b;border:1px solid #334155;border-radius:8px;padding:10px 12px;margin-bottom:12px;">
        <span style="font-size:16px;">📭</span>
        <span style="color:#94a3b8;font-size:11px;">${msg}</span>
    </div>`;
}

export async function openStatePanel(stateName, regionName, region, districtCount, panelData = null) {
    const data = panelData || stateData || {};
    const color = region?.hex || '#6366f1';
    const candEl = document.getElementById('panel-candidates');

    await new Promise(r => setTimeout(r, 380));

    _officeIdx = 0;
    const offices = data?.offices ?? [];
    const apiStatus = data?._apiStatus || 'unreachable';

    const DATA_BANNERS = {
        live: '',
        empty: `<div style="display:flex;align-items:center;gap:8px;background:#1e293b;border:1px solid #334155;border-radius:8px;padding:8px 12px;margin-bottom:12px;">
                        <span style="font-size:14px;">📭</span>
                        <span style="color:#94a3b8;font-size:11px;">No candidate records found for this state yet. Data is added weekly via the sync workflow.</span>
                      </div>`,
        unreachable: `<div style="display:flex;align-items:center;gap:8px;background:#1e1a2e;border:1px solid #7c3aed55;border-radius:8px;padding:8px 12px;margin-bottom:12px;">
                        <span style="font-size:14px;">⚠️</span>
                        <div>
                          <span style="color:#a78bfa;font-size:11px;font-weight:600;">DATA UNREACHABLE</span>
                          <span style="color:#64748b;font-size:11px;"> · Showing preview data. Live records are unavailable right now.</span>
                        </div>
                      </div>`,
    };
    let html = DATA_BANNERS[apiStatus] ?? DATA_BANNERS.unreachable;

    if (districtCount > 0) {
        const expected = DISTRICT_COUNTS[stateName] || districtCount;
        const popLine = (data?.population)
            ? `<p style="color:#475569;font-size:11px;margin:4px 0 0;">👥 State population: <strong style="color:#e2e8f0;">${data.population.formatted}</strong> <span style="opacity:.6">(${data.population.census_year} Census)</span></p>`
            : '';
        html += `<div style="background:${color}0f;border:1px solid ${color}33;border-radius:8px;padding:10px 12px;margin-bottom:14px;">
            <p style="color:${color};font-size:12px;font-weight:600;margin:0 0 4px;">🗺 ${districtCount} of ${expected} Congressional Districts loaded</p>
            <p style="color:#475569;font-size:11px;margin:0 0 4px;">${DISTRICT_CONFIG.congress_label} district boundaries</p>
            <p style="color:#475569;font-size:11px;margin:0;">Click any district on the map to view its U.S. House candidates</p>
            ${popLine}
        </div>`;
    }

    html += offices.length
        ? offices.map(g => renderOfficeGroup(g, OFFICE_ROLES, color)).join('')
        : noDataNotice('Statewide candidate records for this state are not yet available. Check back after the next weekly sync.');

    const cityOfficials = data?.city_officials ?? {};
    const cityEntries = Object.entries(cityOfficials);
    if (cityEntries.length > 0) {
        html += `<div style="border-top:1px solid ${color}20;margin:16px 0 14px;display:flex;align-items:center;gap:8px;">
            <span style="color:${color};font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;white-space:nowrap;">🏙 City Officials</span>
            <div style="flex:1;border-top:1px solid ${color}20;"></div>
        </div>`;
        for (const [city, officials] of cityEntries) {
            html += `<div style="margin-bottom:14px;">
                <p style="color:#94a3b8;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;margin:0 0 6px;"
                   title="City of ${city}">${city}</p>`;
            for (const o of officials) {
                const officeTitle = o.political_office || 'Mayor';
                const roleDesc = CITY_OFFICE_ROLES[officeTitle] || null;
                const elDateCity = o.election_date || null;
                if (roleDesc) {
                    html += `<p class="office-role-tip" style="margin-bottom:6px;">${roleDesc}</p>`;
                }
                html += renderCandidate({
                    full_name: o.full_name, party: o.party, status: o.status || 'seated',
                    is_running: false, verified: o.verified || false, photo: o.photo || null,
                    slug: o.slug || null, profile_url: o.profile_url || null,
                    ballotpedia_url: o.ballotpedia_url || null, website: o.website || null,
                    bio: o.bio_excerpt || null, office: officeTitle, general_date: elDateCity,
                }, color);
            }
            html += '</div>';
        }
    }

    candEl.innerHTML = html;
}

/* ── Offices toggle (collapsible section) ── */
const OFFICES_PREF_KEY = 'u9_map_offices_collapsed';

window.toggleOfficesSection = function () {
    const toggle = document.getElementById('offices-toggle');
    const container = document.getElementById('panel-candidates');
    const collapsed = toggle.classList.toggle('collapsed');
    container.classList.toggle('section-collapsed', collapsed);
    toggle.setAttribute('aria-expanded', String(!collapsed));
    try { localStorage.setItem(OFFICES_PREF_KEY, collapsed ? '1' : '0'); } catch {}
};

export function initOfficesToggle() {
    try {
        if (localStorage.getItem(OFFICES_PREF_KEY) === '1') {
            const toggle = document.getElementById('offices-toggle');
            const container = document.getElementById('panel-candidates');
            toggle.classList.add('collapsed');
            container.classList.add('section-collapsed');
            toggle.setAttribute('aria-expanded', 'false');
        }
    } catch {}
}

/* Delegate click on any .candidate-card to open the politician drawer */
export function initCandidateCardClick() {
    document.getElementById('info-panel').addEventListener('click', e => {
        const card = e.target.closest('.candidate-card[data-candidate]');
        if (!card) return;
        e.stopPropagation();
        try {
            const raw = card.dataset.candidate || '';
            const c = JSON.parse(decodeURIComponent(raw));
            const _dKey = (c.office || '').match(/([A-Z]{2}-(?:\d+|AL))/)?.[1] ?? null;
            openPolDrawer(c, c.color, {
                population: _dKey ? (stateData?.district_populations?.[_dKey] ?? null) : null
            });
        } catch (err) {
            // Surface unexpected errors so we can diagnose drawer-open failures.
            console.error('[map] candidate-card click failed:', err);
        }
    });
}