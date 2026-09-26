/**
 * District panel — renders U.S. House candidates for a clicked district.
 */
import { STATE_ABBR_MAP, PARTY_HEX, PARTY_LABEL, OFFICE_ROLES } from '../config/constants.js';
import { stateData, statePanelRequestId, mapMode, activeRegion, activeState } from '../state/map-state.js';
import { districtMeshes, selectDistrict, meshesForDistrict } from '../scene/district-overlay.js';
import { openInfoPanel } from './info-panel.js';
import { renderCandidate, renderOfficeGroup, partyClass, detectElectionPhase, noDataNotice, renderCityOfficialsSection, renderElectionDatesBanner, renderPollingLocationsLink, renderBallotMeasuresSection } from './panel-state.js';
import { openPolDrawer, renderItemListSection } from './politician-drawer.js';
import { createFavoriteButton } from './boundary-favorite.js';
import { renderCityCard, fetchCitiesForState, wireCityCardClicks } from './city-demographics-card.js';
import { fetchStatePlaces } from '../api/tigerweb-places.js';
import { pointInPolygons } from '../utils/point-in-polygon.js';
import { formatCalendarDate } from '../utils/dates.js';
import { districtCode, houseCandidatesFor, seatedMember } from '../utils/district-keys.js';
import { renderRunningCandidatesSection } from './panel-running-candidates.js';
import { markActiveDistrictRow } from './panel-districts.js';
import { escapeHtml } from '../utils/html.js';
import { createShareButton, districtShareUrl, syncDistrictUrl } from './share-link.js';

/** Cap on the plain Census-boundary city list, largest-by-land-area first. */
const MAX_BOUNDARY_CITIES = 15;

/**
 * Finds which incorporated places (from live Census TIGERweb boundaries)
 * have their representative point inside this district's polygon(s).
 * Independent of city_demographics — works even when that table is empty,
 * since it's a geometry test against real Census boundaries, not a lookup
 * against our own synced economic data.
 */
async function findBoundaryCitiesInDistrict(stateName, districtNum) {
    const polys = districtMeshes
        .filter(m => m.userData.districtNum === districtNum && m.userData.stateName === stateName && m.userData.rings)
        .map(m => m.userData.rings);
    if (!polys.length) return [];

    const places = await fetchStatePlaces(stateName);
    if (!places.length) return [];

    return places
        .filter(p => pointInPolygons(p.lon, p.lat, polys))
        .sort((a, b) => b.areaLand - a.areaLand)
        .slice(0, MAX_BOUNDARY_CITIES);
}

function renderBoundaryCitiesList(cities, color) {
    if (!cities.length) return '';
    const items = cities.map(c => `<span style="font-size:11px;padding:3px 10px;border-radius:999px;background:${color}18;border:1px solid ${color}44;color:${color};white-space:nowrap;">${c.name}</span>`).join('');
    return `<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:4px;">${items}</div>
        <p style="font-size:10px;color:#94a3b8;margin:6px 0 0;">Source: U.S. Census Bureau TIGERweb boundaries.</p>`;
}

/**
 * Renders the "Cities in this District" section. Prefers the richer
 * economy-card view (Census ACS data, once city_demographics is synced);
 * falls back to a plain name list from live TIGERweb boundaries so the
 * section still shows something real even before that sync has run.
 */
async function fetchDistrictCitiesSection(regionName, stateAbbr, stateName, districtNum, houseKey, color, cityOfficials) {
    const cities = await fetchCitiesForState(regionName, stateAbbr);
    const districtCities = cities.filter(c => c.district_code === houseKey);

    let bodyHtml;
    let officialsHtml = '';
    if (districtCities.length) {
        const cityNames = new Set(districtCities.map(c => c.city));
        bodyHtml = districtCities.map(c => renderCityCard(c, stateAbbr, color)).join('');
        officialsHtml = renderCityOfficialsSection(cityOfficials, color, cityNames, '👤 Local Representatives');
    } else {
        const boundaryCities = await findBoundaryCitiesInDistrict(stateName, districtNum);
        bodyHtml = renderBoundaryCitiesList(boundaryCities, color);
    }

    if (!bodyHtml) return '';

    return `<div id="dist-cities-econ">
        <div style="border-top:1px solid ${color}20;margin:16px 0 14px;display:flex;align-items:center;gap:8px;">
            <span style="color:${color};font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;white-space:nowrap;">🏙 Cities in this District</span>
            <div style="flex:1;border-top:1px solid ${color}20;"></div>
        </div>
        ${bodyHtml}
        ${officialsHtml}
    </div>`;
}

/**
 * Renders the "Local Election & Civic News" section: polling-place changes,
 * ballot measures, redistricting, and similar election-administration
 * stories scoped to the cities inside this district. Reuses the same card
 * markup as the politician drawer's news list. Returns '' (no section) when
 * the backend has nothing verified yet, same as fetchDistrictCitiesSection.
 */
async function fetchDistrictNewsSection(houseKey, districtLabel, stateName, color) {
    const params = new URLSearchParams({ district_code: houseKey, district_label: districtLabel, state_name: stateName });

    let news = [];
    try {
        const res = await fetch(`/api/v1/map/district-news?${params.toString()}`);
        if (res.ok) {
            const data = await res.json();
            news = Array.isArray(data.news) ? data.news : [];
        }
    } catch (err) {
        console.warn('[map] district-news fetch failed:', err);
    }

    if (!news.length) return '';

    return `<div id="dist-news">
        ${renderItemListSection('📰 Local Election & Civic News', news)}
    </div>`;
}

/** The district currently shown in the panel, so it can be re-rendered when late data arrives. */
let openDistrict = null;

export function getOpenDistrict() { return openDistrict; }

export function clearOpenDistrict() {
    openDistrict = null;
    document.getElementById('panel-fav-btn')?.remove();
    document.getElementById('panel-share-btn')?.remove();
    syncDistrictUrl();
}

/** Re-render the open district (e.g. once the state payload or boundaries arrive). */
export function refreshOpenDistrict() {
    if (!openDistrict) return;
    const { num, label, stateName, regionHex, party } = openDistrict;
    openDistrictPanel(num, label, stateName, regionHex, party);
}

/** Candidate-card shape for renderCandidate(), from a house_candidates row. */
function toCard(c, districtLabel, houseKey) {
    return {
        id: c.id || null, source: c.source || null,
        source_label: c.source_label || null, updated_at: c.updated_at || null,
        full_name: c.full_name, party: c.party, is_running: c.is_running,
        status: c.status || 'running', verified: c.verified || false,
        photo: c.photo || null, slug: c.slug || null,
        profile_url: c.profile_url || null,
        ballotpedia_url: c.ballotpedia_url || null, website: c.website || null,
        bio: c.bio_excerpt || null, raised: null, stance_topic: null, stance_text: null,
        primary_result: c.primary_result || null,
        general_date: c.general_date || null,
        office: `U.S. Representative — ${districtLabel}`,
        // Machine-readable district key ("FL-13") alongside the human label
        // above — office text alone can't be regex-parsed back into a district
        // code (districtLabel there is e.g. "District 13").
        district: houseKey,
    };
}

function dpSection(title, sub, bodyHtml, extraClass = '') {
    return `<section class="dp-section ${extraClass}">
        <h3 class="dp-title">${title}</h3>
        ${sub ? `<p class="dp-sub">${sub}</p>` : ''}
        ${bodyHtml}
    </section>`;
}

/**
 * The civic snapshot is an exclusive accordion: one section open at a time,
 * each with a one-line preview so a closed section still answers "what's in
 * here?". The open section survives re-renders (late data arriving) for the
 * same district, and resets to the representative when the district changes.
 */
const SNAP_GROUP = 'civic-snapshot';
let snapOpen = 'rep';

function snapSection(id, title, preview, bodyHtml, extraClass = '') {
    return `<details class="snap-section ${extraClass}" name="${SNAP_GROUP}" data-snap="${id}"${snapOpen === id ? ' open' : ''}>
        <summary>
            <span class="snap-heading">
                <span class="snap-title">${title}</span>
                ${preview ? `<span class="snap-preview">${preview}</span>` : ''}
            </span>
        </summary>
        <div class="snap-body">${bodyHtml}</div>
    </details>`;
}

/** Track the open section, and close the rest in browsers without exclusive <details name>. */
function wireSnapshotToggle(container) {
    if (container.dataset.snapWired) return;
    container.dataset.snapWired = '1';
    container.addEventListener('toggle', (e) => {
        const el = e.target;
        if (!el.matches?.('details.snap-section')) return;
        if (el.open) {
            snapOpen = el.dataset.snap;
            container.querySelectorAll('details.snap-section[open]').forEach(d => { if (d !== el) d.open = false; });
            // In the scrolling panel / bottom sheet, keep the section's title in view.
            requestAnimationFrame(() => el.querySelector('summary')?.scrollIntoView({ block: 'nearest' }));
        } else if (snapOpen === el.dataset.snap) {
            snapOpen = null;
        }
    }, true);
}

function loadingRow(color, text) {
    return `<div class="panel-spinner"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="animation:spin 1s linear infinite;color:${color};" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4" stroke-dashoffset="10" stroke-linecap="round"/></svg>&nbsp;${text}</div>`;
}

/**
 * The district panel — a "civic snapshot" of one seat, in the order a voter
 * needs it (each an accordion section, one open at a time):
 *   1. Your representative      — the sitting member (a current officeholder)
 *   2. Candidates & races       — who is running for this seat, then statewide races
 *   3. Election dates & polling — the state's calendar and where to find a polling place
 *   4. Ballot measures & profiles — measures listed for the state, and direct profile links
 * Below the accordion: cities, local news, the district switcher and the
 * rest of the state's races. Everything renders from data already in
 * memory, so nothing waits on the district boundaries; only the map
 * highlight does.
 */
export async function openDistrictPanel(districtNum, districtLabel, stateName, regionHex, party = 'U') {
    const color = PARTY_HEX[party] || regionHex || '#6366f1';
    const partyLabel = PARTY_LABEL[party] || 'Unknown';
    const sameDistrict = openDistrict?.num === String(districtNum) && openDistrict?.stateName === stateName;
    if (!sameDistrict) snapOpen = 'rep';
    openDistrict = { num: String(districtNum), label: districtLabel, stateName, regionHex, party };

    const infoPanel = document.getElementById('info-panel');
    infoPanel.dataset.view = 'district';
    infoPanel.scrollTop = 0;
    document.querySelector('#panel-districts details.pd-section')?.removeAttribute('open');
    const switchLabel = document.querySelector('#panel-districts summary.pd-title');
    if (switchLabel) switchLabel.textContent = 'Switch district';

    const stateAbbr = STATE_ABBR_MAP[stateName] || '';
    const houseKey = districtCode(stateAbbr, districtNum);

    document.getElementById('panel-state').textContent = `${stateName} — ${districtLabel}`;
    const badge = document.getElementById('panel-badge');
    badge.textContent = `${houseKey} · ${partyLabel} · 119th Congress`;
    badge.style.cssText = `display:inline-block;padding:3px 12px;border-radius:999px;font-size:11px;font-weight:600;background:${color}22;color:${color};border:1px solid ${color}55;`;
    markActiveDistrictRow(districtNum);
    // No-op until the boundaries have loaded; mode-transitions re-applies it then.
    selectDistrict(meshesForDistrict(districtNum));
    openInfoPanel();

    const candEl = document.getElementById('panel-candidates');
    const payloadReady = !!stateData?.house_candidates;

    const all = houseCandidatesFor(stateData, stateAbbr, districtNum);
    const seatedRow = seatedMember(all);
    const seated = seatedRow ? toCard(seatedRow, districtLabel, houseKey) : null;
    const others = all.filter(c => c !== seatedRow && c.status !== 'lost').map(c => toCard(c, districtLabel, houseKey));

    const stateOffices = stateData?.offices ?? [];
    const distPop = stateData?.district_populations?.find?.(d => d.district === houseKey)
        ?? stateData?.district_populations?.[houseKey];
    const popBadge = distPop
        ? `👥 ${distPop.formatted} residents <span style="opacity:.7">(${distPop.census_year} Census)</span>`
        : '';

    const distPhase = detectElectionPhase([seated, ...others].filter(Boolean));
    const seatCandidates = distPhase === 'post_general'
        ? []
        : distPhase === 'post_primary'
            ? others.filter(c => !c.primary_result || c.primary_result === 'advanced_to_general')
            : others;

    // One date, from the state's election calendar — never a per-candidate value.
    const generalDate = stateData?.general_election_date
        ?? stateData?.election_dates?.find?.(d => /general/i.test(d.stage_name || ''))?.election_date
        ?? null;
    const seatSub = distPhase === 'post_primary'
        ? `General election${generalDate ? ` · ${formatCalendarDate(generalDate)}` : ''}`
        : distPhase === 'pre_primary'
            ? `2026 primary${generalDate ? ` · general election ${formatCalendarDate(generalDate)}` : ''}`
            : '';

    // A section is in one of three states: still loading, failed (the state
    // payload was unreachable), or ready — and only "ready" may say "none".
    const unreachable = stateData?._apiStatus === 'unreachable';
    const pending = (text) => unreachable
        ? noDataNotice('These records couldn’t be loaded right now. Try again in a moment.')
        : loadingRow(color, text);
    const loadPreview = unreachable ? 'Unavailable right now' : 'Loading…';

    // ── 1. Your representative — a current officeholder ─────────────────────
    const repHtml = seated
        ? renderCandidate(seated, color)
        : payloadReady
            ? noDataNotice('No sitting representative is on record for this district. Data is synced weekly from congress-legislators.')
            : pending('Loading representative…');
    const repMeta = `<p class="dp-meta">119th Congress · 2025–2027 · <a href="https://www.house.gov" target="_blank" rel="noopener" style="color:${color};">house.gov →</a>${popBadge ? ` · ${popBadge}` : ''}</p>`;
    const repPreview = seated
        ? `${escapeHtml(seated.full_name)}${seated.party ? ` · ${escapeHtml(seated.party)}` : ''}`
        : payloadReady ? 'None on record' : loadPreview;

    // ── 2. Candidates & races — people asking for votes, not officeholders ──
    const reelection = seated?.is_running
        ? `<p class="dp-empty">${escapeHtml(seated.full_name)}, the current representative, is also running for re-election.</p>`
        : '';
    const seatBody = seatCandidates.length
        ? seatCandidates.map(c => renderCandidate(c, color)).join('')
        : payloadReady
            ? `<p class="dp-empty">${distPhase === 'post_general'
                ? 'This seat’s election has concluded.'
                : 'No other candidates are listed for this seat in our records yet. That doesn’t mean no one is running — the state’s official candidate list is the authority.'}</p>`
            : pending('Loading candidates…');
    const statewideHtml = stateOffices.length
        ? stateOffices.map(g => renderOfficeGroup(g, OFFICE_ROLES, color)).join('')
        : payloadReady
            ? noDataNotice('Statewide candidate records for this state are not yet available. Check back after the next weekly sync.')
            : '';
    const candBody = `<p class="snap-kicker">Candidates — people running, not current officeholders</p>
        ${seatSub ? `<p class="dp-sub">${seatSub}</p>` : ''}
        ${reelection}${seatBody}
        ${statewideHtml ? `<h4 class="snap-subhead">Statewide races · ${escapeHtml(stateName)}</h4>${statewideHtml}` : ''}`;
    const candPreview = !payloadReady
        ? loadPreview
        : distPhase === 'post_general'
            ? 'Election concluded'
            : seatCandidates.length
                ? `${seatCandidates.length} running for this seat`
                : 'None listed for this seat';

    // ── 3. Election dates & polling place ───────────────────────────────────
    const electionDates = stateData?.election_dates ?? [];
    const datesBody = payloadReady
        ? `${renderElectionDatesBanner(electionDates, color) || noDataNotice(`Election dates for ${escapeHtml(stateName)} aren’t available yet.`)}
           <p class="dp-meta">State-wide calendar (primary and general elections, candidate filing deadlines), synced from Vote Smart. It covers ${escapeHtml(stateName)} as a whole — confirm the details for your address with your county election office.</p>
           <h4 class="snap-subhead">Find your polling place</h4>${renderPollingLocationsLink(color, { bare: true })}`
        : `${pending('Loading election dates…')}<h4 class="snap-subhead">Find your polling place</h4>${renderPollingLocationsLink(color, { bare: true })}`;
    const datesPreview = generalDate
        ? `General election · ${formatCalendarDate(generalDate)}`
        : payloadReady ? (electionDates.length ? 'See dates' : 'Dates not available') : loadPreview;

    // ── 4. Ballot measures & candidate profiles ─────────────────────────────
    const measures = stateData?.ballot_measures ?? [];
    const measuresBody = payloadReady
        ? (measures.length
            ? `<p class="dp-sub">Listed for ${escapeHtml(stateName)} — not filtered to this district. Local measures are tagged with where they apply.</p>${renderBallotMeasuresSection(measures, color, { bare: true })}`
            : noDataNotice(`No upcoming ballot measures are listed for ${escapeHtml(stateName)}. Our coverage is limited, so check your county elections office for local measures.`))
        : pending('Loading ballot measures…');
    const profiled = [seated, ...seatCandidates].filter(c => c?.profile_url);
    const profilesBody = profiled.length
        ? `<ul class="snap-links">${profiled.map(c => `<li><a href="${escapeHtml(c.profile_url)}" target="_blank" rel="noopener">${escapeHtml(c.full_name)}<span class="snap-link-role"> · ${c === seated ? 'current representative' : 'candidate'}</span> ↗</a></li>`).join('')}</ul>`
        : payloadReady ? '<p class="dp-empty">No public profile pages yet for this seat’s candidates.</p>' : '';
    const researchBody = `<h4 class="snap-subhead">Research a candidate</h4>
        <p class="dp-sub">Open any candidate card above to see their bio, funding, votes and videos.</p>${profilesBody}`;
    const measuresPreview = payloadReady
        ? (measures.length ? `${measures.length} measure${measures.length === 1 ? '' : 's'} listed` : 'None listed')
        : loadPreview;

    const banner = unreachable
        ? `<div style="display:flex;align-items:center;gap:8px;background:#1e1a2e;border:1px solid #7c3aed55;border-radius:8px;padding:8px 12px;margin-bottom:12px;" role="alert">
             <span style="font-size:14px;" aria-hidden="true">⚠️</span>
             <div><span style="color:#a78bfa;font-size:11px;font-weight:600;">DATA UNREACHABLE</span><span style="color:#a7b4c7;font-size:11px;"> · Live records unavailable right now.</span></div>
           </div>`
        : '';

    candEl.innerHTML = `${banner}
    <div class="snap" role="group" aria-label="Civic snapshot for ${escapeHtml(districtLabel)}">
        ${snapSection('rep', 'Your representative', repPreview, `<p class="snap-kicker">Current officeholder — serving now</p>${repHtml}${repMeta}`, 'dp-rep')}
        ${snapSection('candidates', 'Candidates &amp; races', candPreview, candBody)}
        ${snapSection('dates', 'Election dates &amp; polling', datesPreview, datesBody)}
        ${snapSection('measures', 'Ballot measures &amp; profiles', measuresPreview, `${measuresBody}${researchBody}`)}
    </div>
    <div id="dist-cities-econ"></div>
    <div id="dist-news"></div>`;
    wireSnapshotToggle(candEl);

    // "Other races": the state-wide rollup, demoted to a collapsed section
    // that leaves out the seat already shown above.
    const runningEl = document.getElementById('panel-running-candidates');
    if (runningEl && payloadReady) {
        runningEl.innerHTML = renderRunningCandidatesSection(stateData, color, {
            title: `Other races in ${stateName}`,
            collapsed: true,
            excludeDistrict: houseKey,
        });
    }

    // Star toggle: save this district as a boundary (voter) / sign-in nudge (guest).
    mountDistrictFav(stateName, stateAbbr, districtNum, districtLabel);

    // Local election/civic-administration news for this district's cities —
    // fetched async so it doesn't block the rest of the panel.
    if (stateAbbr) {
        const reqId = statePanelRequestId;
        fetchDistrictNewsSection(houseKey, districtLabel, stateName, color).then(sectionHtml => {
            if (reqId !== statePanelRequestId || !sectionHtml) return;
            const placeholder = document.getElementById('dist-news');
            if (!placeholder) return;
            placeholder.outerHTML = sectionHtml;
        });
    }

    // Cities within this district + their local officials — fetched async
    // (region-demographics) so it doesn't block the rest of the panel.
    if (activeRegion && stateAbbr) {
        const reqId = statePanelRequestId;
        fetchDistrictCitiesSection(activeRegion, stateAbbr, stateName, String(districtNum), houseKey, color, stateData?.city_officials).then(sectionHtml => {
            if (reqId !== statePanelRequestId || !sectionHtml) return;
            const placeholder = document.getElementById('dist-cities-econ');
            if (!placeholder) return;
            placeholder.outerHTML = sectionHtml;
            wireCityCardClicks(candEl);
        });
    }
}

/**
 * Mount (or refresh) the save-boundary star and share button in the district
 * panel header, and point the address bar at this district.
 * Replaces any prior instance so repeated panel opens don't stack buttons.
 */
function mountDistrictFav(stateName, stateAbbr, districtNum, districtLabel) {
    const host = document.querySelector('#panel-header > div');
    if (!host || !stateAbbr) return;
    document.getElementById('panel-fav-btn')?.remove();
    const btn = createFavoriteButton({
        type: 'district',
        state_abbr: stateAbbr,
        district_number: districtNum,
        label: `${stateName} ${districtLabel}`,
    });
    btn.id = 'panel-fav-btn';
    host.appendChild(btn);

    document.getElementById('panel-share-btn')?.remove();
    const shareBtn = createShareButton(districtShareUrl(stateAbbr, districtNum), districtCode(stateAbbr, districtNum));
    shareBtn.id = 'panel-share-btn';
    host.appendChild(shareBtn);
    syncDistrictUrl(stateAbbr, districtNum);
}