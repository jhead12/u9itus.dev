/**
 * State panel — renders statewide office holders, candidates, city officials.
 */
import { STATE_ABBR_MAP, PARTY_HEX, PARTY_LABEL, OFFICE_ROLES, CITY_OFFICE_ROLES, DISTRICT_COUNTS } from '../config/constants.js';
import { stateData, statePanelRequestId, mapMode, activeRegion, activeState, colorMode, DISTRICT_CONFIG } from '../state/map-state.js';
import { openDistrictPanel } from './panel-district.js';
import { openPolDrawer } from './politician-drawer.js';
import { fmtPop } from '../config/city-data.js';
import { trackEvent } from '../api/interaction.js';
import { renderCityCard, wireCityCardClicks, fetchCitiesForState } from './city-demographics-card.js';

// Every office section now starts collapsed when a panel first opens — no
// office defaults to expanded. Kept as a Set (rather than deleting the
// isOpen mechanism) so a future default-open office is a one-line add.
export const OFFICE_DEFAULT_OPEN = new Set([]);
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

/**
 * Render issue/discourse topic chips from a candidate's `badges` array
 * (name/icon/color, capped at 4 by the Map API). Used on candidate cards and
 * the politician drawer hero. Badges may be self-declared or inferred from
 * news/viral-moment/Vote Smart signals (badge_type='inferred_discourse').
 */
export function topicChipsHtml(badges) {
    if (!badges || !badges.length) return '';
    return badges.map(b => {
        const color = b.color || '#6366f1';
        const icon = b.icon ? `<span style="margin-right:2px;">${escapeHtml(b.icon)}</span>` : '';
        return `<span class="topic-chip" style="background:${escapeHtml(color)}1a;border:1px solid ${escapeHtml(color)}40;color:${escapeHtml(color)};" title="${escapeHtml(b.name || '')}">${icon}${escapeHtml(b.name || '')}</span>`;
    }).join('');
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
        ? `<span style="color:#a7b4c7;font-size:9px;margin-left:4px;">📅 ${elDateStr}</span>`
        : '';
    const st = c.status === 'seated' ? `<span class="status-seated">● Seated</span>` : c.is_running ? `<span class="status-running">● Running 2026</span>${elBadge}` : '';
    const vf = c.verified ? `<span class="verified-badge">✓ Verified</span>` : '';
    const chips = topicChipsHtml(c.badges);
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
            ${chips ? `<div class="candidate-chips">${chips}</div>` : ''}
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
                  ${c.term_note ? `<span style="color:#a7b4c7;"> &nbsp;·&nbsp; ${c.term_note}</span>` : ''}
                </span>
               </div>`
            : '';
        return termNotice + renderCandidate({ ...c, office: g.office }, color);
    }).join('');

    const candidatesHtml = running.length
        ? `<p style="color:#94a3b8;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;margin:10px 0 6px;">${runningLabel}</p>
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
              <span class="name-summary" style="font-weight:400;font-size:9px;margin-left:4px;text-transform:none;letter-spacing:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:120px;display:${isOpen ? 'none' : 'inline-block'};vertical-align:middle;">${allNames}</span>
            </span>
            <span class="chevron">▾</span>
        </div>
        <div class="office-body">
            ${role ? `<p class="office-role-tip">${role}</p>` : ''}
            ${seated.length ? `<p style="color:#94a3b8;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;margin:0 0 6px;">Current Officeholder</p>` : ''}
            ${seatedHtml}
            ${candidatesHtml}
        </div>
    </div>`;
}

/**
 * Compact pill row of real election dates for a state — primary/general
 * election dates and candidate filing deadlines, synced from Vote Smart
 * (see StateElectionDate::upcomingForState()). Shown at the very top of a
 * panel since "when" is the most time-sensitive thing a voter needs.
 */
export function renderElectionDatesBanner(electionDates, color) {
    if (!electionDates?.length) return '';

    const pills = [];
    for (const stage of electionDates) {
        if (stage.election_date_formatted) {
            pills.push(`<span style="font-size:11px;padding:3px 10px;border-radius:999px;background:${color}18;border:1px solid ${color}44;color:${color};font-weight:600;white-space:nowrap;">🗳️ ${escapeHtml(stage.stage_name)}: ${escapeHtml(stage.election_date_formatted)}</span>`);
        }
        if (stage.filing_deadline_formatted) {
            pills.push(`<span style="font-size:11px;padding:3px 10px;border-radius:999px;background:rgba(148,163,184,0.1);border:1px solid rgba(148,163,184,0.3);color:#94a3b8;white-space:nowrap;">📋 ${escapeHtml(stage.stage_name)} filing deadline: ${escapeHtml(stage.filing_deadline_formatted)}</span>`);
        }
    }
    if (!pills.length) return '';

    return `<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px;">${pills.join('')}</div>`;
}

/**
 * Ballot measure cards — collapsed by default (title/date/summary, same as
 * before), click-to-expand reveals yes/no meaning, status, and a source
 * link, all inline. Never navigates to the dedicated ballot-measure page —
 * the point is general info without leaving the map. Mirrors the
 * .office-section collapse pattern above (self-contained inline onclick,
 * no delegated listener needed).
 */
export function renderBallotMeasuresSection(ballotMeasures, color) {
    if (!ballotMeasures?.length) return '';
    let cardsHtml = '';
    ballotMeasures.forEach((m, i) => {
        const label = [m.measure_number, m.title].filter(Boolean).join(' — ');
        const dateLine = m.election_date
            ? `<p style="color:#a7b4c7;font-size:10px;margin:2px 0 0;">${escapeHtml(new Date(m.election_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }))}</p>`
            : '';
        const summaryLine = m.summary
            ? `<p style="color:#94a3b8;font-size:11px;line-height:1.5;margin:4px 0 0;">${escapeHtml(m.summary)}</p>`
            : '';

        const hasDetail = Boolean(m.yes_meaning || m.no_meaning || m.status || m.source_url || m.detail_url);
        const statusLine = m.status
            ? `<p style="color:#a7b4c7;font-size:10px;text-transform:uppercase;letter-spacing:.05em;margin:0 0 6px;">Status: ${escapeHtml(m.status)}</p>`
            : '';
        const yesNoHtml = (m.yes_meaning || m.no_meaning) ? `<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:6px;">
                ${m.yes_meaning ? `<div><p style="color:#34d399;font-size:9px;font-weight:700;text-transform:uppercase;margin:0 0 2px;">A "Yes" Vote</p><p style="color:#94a3b8;font-size:11px;line-height:1.5;margin:0;">${escapeHtml(m.yes_meaning)}</p></div>` : ''}
                ${m.no_meaning ? `<div><p style="color:#f87171;font-size:9px;font-weight:700;text-transform:uppercase;margin:0 0 2px;">A "No" Vote</p><p style="color:#94a3b8;font-size:11px;line-height:1.5;margin:0;">${escapeHtml(m.no_meaning)}</p></div>` : ''}
            </div>` : '';
        const sourceLine = m.source_url
            ? `<a href="${escapeHtml(m.source_url)}" target="_blank" rel="noopener" style="display:inline-block;margin-top:8px;margin-right:14px;color:${color};font-size:10px;font-weight:600;text-decoration:none;" onclick="event.stopPropagation()">Read full text ↗</a>`
            : '';
        const detailLink = m.detail_url
            ? `<a href="${escapeHtml(m.detail_url)}" target="_blank" rel="noopener" style="display:inline-block;margin-top:8px;color:#e2e8f0;font-size:10px;font-weight:600;text-decoration:none;" onclick="event.stopPropagation()">View full details ↗</a>`
            : '';

        cardsHtml += `<div class="bm-card${hasDetail ? '' : ' bm-no-detail'} collapsed" id="bm-${i}">
            <div class="bm-card-header"
                 ${hasDetail ? `onclick="this.closest('.bm-card').classList.toggle('collapsed')"
                 role="button" tabindex="0" aria-expanded="false"
                 onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();this.click();}"` : ''}>
                <div style="flex:1;min-width:0;">
                    <p style="color:#e2e8f0;font-size:12px;font-weight:600;margin:0;">${escapeHtml(label || 'Ballot Measure')}</p>
                    ${dateLine}
                    ${summaryLine}
                </div>
                ${hasDetail ? `<span class="bm-chevron">▾</span>` : ''}
            </div>
            ${hasDetail ? `<div class="bm-detail">
                ${statusLine}
                ${yesNoHtml}
                ${sourceLine}
                ${detailLink}
            </div>` : ''}
        </div>`;
    });

    // Wraps in the same .office-section/.office-title/.office-body markup
    // renderOfficeGroup() uses, so the whole "Ballot Measures" block gets
    // one click-to-collapse toggle (chevron + all) for free, and looks
    // consistent with every other collapsible section in this panel.
    return `<div class="office-section">
        <div class="office-title"
             style="background:${color}18;border-left:3px solid ${color};color:${color};"
             onclick="this.closest('.office-section').classList.toggle('collapsed')"
             role="button" aria-expanded="true" tabindex="0"
             onkeydown="if(event.key==='Enter'||event.key===' ')this.click()">
            <span>🗳️&nbsp;Ballot Measures</span>
            <span class="chevron">▾</span>
        </div>
        <div class="office-body">${cardsHtml}</div>
    </div>`;
}

/**
 * Compact "State Stats" summary — population and mapped-business counts,
 * both already returned by the state-candidates payload (no new data
 * sources fetched here). Always visible, not collapsible — it's two
 * numbers, unlike the office/topic/ballot-measure sections below it.
 */
export function renderStateStatsSection(data, color) {
    const pop = data?.population?.formatted ?? null;
    const businessCount = data?.business_count ?? null;
    if (!pop && !businessCount) return '';

    const stat = (icon, value, label) => `<div style="flex:1;min-width:0;text-align:center;">
        <div style="color:${color};font-size:15px;font-weight:700;">${icon} ${value}</div>
        <div style="color:#94a3b8;font-size:9px;text-transform:uppercase;letter-spacing:.05em;margin-top:2px;">${label}</div>
    </div>`;

    return `<div style="display:flex;gap:8px;background:${color}0f;border:1px solid ${color}33;border-radius:8px;padding:10px 8px;margin-bottom:12px;">
        ${pop ? stat('👥', pop, 'Population') : ''}
        ${businessCount !== null ? stat('🏪', businessCount.toLocaleString('en-US'), 'Local Businesses') : ''}
    </div>`;
}

/**
 * Groups candidates across every office (statewide + House) by the issue
 * topic chips already attached to each candidate (see topicChipsHtml) —
 * self-declared or inferred from news/viral-moment/Vote Smart signals. A
 * candidate tagged with multiple topics appears under each one. Renders
 * nothing when no candidate in the state carries any topic badge, so this
 * section doesn't show up as a permanently-empty box on sparse states.
 */
export function renderCandidatesByTopicSection(offices, color) {
    const byTopic = new Map();
    for (const g of (offices || [])) {
        for (const c of (g.candidates || [])) {
            for (const b of (c.badges || [])) {
                if (!b?.name) continue;
                if (!byTopic.has(b.name)) byTopic.set(b.name, { badge: b, candidates: [] });
                byTopic.get(b.name).candidates.push({ ...c, office: g.office });
            }
        }
    }
    if (!byTopic.size) return '';

    const topics = [...byTopic.values()].sort((a, b) => b.candidates.length - a.candidates.length);
    const body = topics.map(({ badge, candidates }) => {
        const topicColor = badge.color || color;
        const icon = badge.icon ? `${escapeHtml(badge.icon)}&nbsp;` : '';
        return `<div style="margin-bottom:12px;">
            <p style="color:${topicColor};font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;margin:0 0 6px;">${icon}${escapeHtml(badge.name)}</p>
            ${candidates.map(c => renderCandidate(c, color)).join('')}
        </div>`;
    }).join('');

    return `<div class="office-section collapsed">
        <div class="office-title"
             style="background:${color}18;border-left:3px solid ${color};color:${color};"
             onclick="this.closest('.office-section').classList.toggle('collapsed')"
             role="button" aria-expanded="false" tabindex="0"
             onkeydown="if(event.key==='Enter'||event.key===' ')this.click()">
            <span>🏷️&nbsp;Candidates by Topic</span>
            <span class="chevron">▾</span>
        </div>
        <div class="office-body">${body}</div>
    </div>`;
}

/** Nonpartisan polling-place lookup — linked out directly rather than looked
 * up in-panel, since Google Civic's own coverage is inconsistent outside an
 * active-election window and vote.org's tool is more reliable either way. */
const EXTERNAL_POLLING_LOCATOR_URL = 'https://www.vote.org/polling-place-locator/';

/**
 * Renders the "Find Your Polling Place" section: a single link out to
 * vote.org's locator.
 */
export function renderPollingLocationsLink(color) {
    return `<div style="border-top:1px solid ${color}20;margin:16px 0 14px;display:flex;align-items:center;gap:8px;">
        <span style="color:${color};font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;white-space:nowrap;">🗳️ Find Your Polling Place</span>
        <div style="flex:1;border-top:1px solid ${color}20;"></div>
    </div>
    <a href="${EXTERNAL_POLLING_LOCATOR_URL}" target="_blank" rel="noopener noreferrer"
       style="display:inline-flex;align-items:center;gap:6px;background:${color}18;border:1px solid ${color}44;border-radius:8px;padding:8px 12px;color:${color};font-size:12px;font-weight:600;text-decoration:none;">
        Look up your polling place on vote.org →
    </a>`;
}

/**
 * @param {Object} cityOfficials keyed by city name -> array of officials
 * @param {Set<string>|null} filterCities restrict to these city names, or null for all
 */
export function renderCityOfficialsSection(cityOfficials, color, filterCities = null, label = '🏙 City Officials') {
    const cityEntries = Object.entries(cityOfficials ?? {})
        .filter(([city]) => !filterCities || filterCities.has(city));
    if (!cityEntries.length) return '';

    let html = `<div style="border-top:1px solid ${color}20;margin:16px 0 14px;display:flex;align-items:center;gap:8px;">
        <span style="color:${color};font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;white-space:nowrap;">${label}</span>
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
    return html;
}

export function noDataNotice(msg) {
    return `<div style="display:flex;align-items:center;gap:8px;background:#1e293b;border:1px solid #334155;border-radius:8px;padding:10px 12px;margin-bottom:12px;">
        <span style="font-size:16px;">📭</span>
        <span style="color:#94a3b8;font-size:11px;">${msg}</span>
    </div>`;
}

/** Renders the "Cities & Economy" section for a state, reusing the region panel's per-city Census data. */
async function fetchStateCitiesEconomy(stateAbbr, regionName, color) {
    const cities = await fetchCitiesForState(regionName, stateAbbr);
    if (!cities.length) return '';

    return `<div id="state-cities-econ">
        <div style="border-top:1px solid ${color}20;margin:16px 0 14px;display:flex;align-items:center;gap:8px;">
            <span style="color:${color};font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;white-space:nowrap;">🏙 Cities &amp; Economy</span>
            <div style="flex:1;border-top:1px solid ${color}20;"></div>
        </div>
        ${cities.map(c => renderCityCard(c, stateAbbr, color)).join('')}
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
                          <span style="color:#a7b4c7;font-size:11px;"> · Showing preview data. Live records are unavailable right now.</span>
                        </div>
                      </div>`,
    };
    let html = renderElectionDatesBanner(data?.election_dates, color);
    html += DATA_BANNERS[apiStatus] ?? DATA_BANNERS.unreachable;

    if (districtCount > 0) {
        const expected = DISTRICT_COUNTS[stateName] || districtCount;
        const popLine = (data?.population)
            ? `<p style="color:#94a3b8;font-size:11px;margin:4px 0 0;">👥 State population: <strong style="color:#e2e8f0;">${data.population.formatted}</strong> <span style="opacity:.6">(${data.population.census_year} Census)</span></p>`
            : '';
        html += `<div style="background:${color}0f;border:1px solid ${color}33;border-radius:8px;padding:10px 12px;margin-bottom:14px;">
            <p style="color:${color};font-size:12px;font-weight:600;margin:0 0 4px;">🗺 ${districtCount} of ${expected} Congressional Districts loaded</p>
            <p style="color:#94a3b8;font-size:11px;margin:0 0 4px;">${DISTRICT_CONFIG.congress_label} district boundaries</p>
            <p style="color:#94a3b8;font-size:11px;margin:0;">Click any district on the map to view its U.S. House candidates</p>
            ${popLine}
        </div>`;
    }

    html += `<div id="state-cities-econ"></div>`;

    html += offices.length
        ? offices.map(g => renderOfficeGroup(g, OFFICE_ROLES, color)).join('')
        : noDataNotice('Statewide candidate records for this state are not yet available. Check back after the next weekly sync.');

    html += renderCityOfficialsSection(data?.city_officials, color);

    candEl.innerHTML = html;

    // Stats, topics, and ballot measures all live outside #panel-candidates
    // so they stay visible whether "Statewide Executive Offices" is
    // collapsed or expanded, and survive drilling into a district (see
    // panel-district.js, which only ever repaints #panel-candidates).
    const statsEl = document.getElementById('panel-stats');
    if (statsEl) statsEl.innerHTML = renderStateStatsSection(data, color);

    const topicsEl = document.getElementById('panel-topics');
    if (topicsEl) topicsEl.innerHTML = renderCandidatesByTopicSection(offices, color);

    const ballotEl = document.getElementById('panel-ballot-measures');
    if (ballotEl) ballotEl.innerHTML = renderBallotMeasuresSection(data?.ballot_measures ?? [], color);

    const stateAbbr = STATE_ABBR_MAP[stateName];
    if (stateAbbr && regionName) {
        const reqId = statePanelRequestId;
        fetchStateCitiesEconomy(stateAbbr, regionName, color).then(sectionHtml => {
            if (reqId !== statePanelRequestId || !sectionHtml) return;
            const placeholder = document.getElementById('state-cities-econ');
            if (!placeholder) return;
            placeholder.outerHTML = sectionHtml;
            wireCityCardClicks(candEl);
        });
    }
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
            // c.district ("FL-13") is set by panel-district.js's candidate build;
            // office text alone ("U.S. Representative — District 13") can't be
            // regex-parsed back into a district code, so that's the fallback only
            // for candidate shapes that don't carry the explicit field.
            const _dKey = c.district || ((c.office || '').match(/([A-Z]{2}-(?:\d+|AL))/)?.[1] ?? null);
            openPolDrawer(c, c.color, {
                population: _dKey ? (stateData?.district_populations?.[_dKey] ?? null) : null,
                districtNumber: _dKey ? _dKey.split('-')[1] : null
            });
        } catch (err) {
            // Surface unexpected errors so we can diagnose drawer-open failures.
            console.error('[map] candidate-card click failed:', err);
        }
    });
}