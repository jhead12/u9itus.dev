/**
 * District panel — renders U.S. House candidates for a clicked district.
 */
import { STATE_ABBR_MAP, PARTY_HEX, PARTY_LABEL, OFFICE_ROLES } from '../config/constants.js';
import { stateData, statePanelRequestId, mapMode, activeRegion, activeState } from '../state/map-state.js';
import { districtMeshes } from '../scene/district-overlay.js';
import { openInfoPanel } from './info-panel.js';
import { renderCandidate, renderOfficeGroup, partyClass, detectElectionPhase, noDataNotice } from './panel-state.js';
import { openPolDrawer } from './politician-drawer.js';

export async function openDistrictPanel(districtNum, districtLabel, stateName, regionHex, party = 'U') {
    const color = PARTY_HEX[party] || regionHex || '#6366f1';
    const partyLabel = PARTY_LABEL[party] || 'Unknown';
    document.getElementById('panel-state').textContent = `${stateName} — ${districtLabel}`;
    const badge = document.getElementById('panel-badge');
    badge.textContent = `${partyLabel} · 119th Congress`;
    badge.style.cssText = `display:inline-block;padding:3px 12px;border-radius:999px;font-size:11px;font-weight:600;background:${color}22;color:${color};border:1px solid ${color}55;`;
    document.getElementById('panel-states').innerHTML = '';
    openInfoPanel();

    const candEl = document.getElementById('panel-candidates');
    candEl.innerHTML = `<div class="panel-spinner"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="animation:spin 1s linear infinite;color:${color};"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4" stroke-dashoffset="10" stroke-linecap="round"/></svg>&nbsp;Loading…</div>`;

    await new Promise(r => setTimeout(r, 320));

    const _stateAbbr = STATE_ABBR_MAP[stateName] || '';
    const _distNumInt = (districtNum === 'AL') ? null : parseInt(districtNum, 10);
    const houseKey = (_distNumInt !== null) ? `${_stateAbbr}-${String(_distNumInt).padStart(2, '0')}` : `${_stateAbbr}-AL`;

    const liveDist = stateData?.house_candidates?.[houseKey];
    let seated, challenger, third;
    if (liveDist?.length) {
        [seated, challenger, third] = [0, 1, 2].map(i => {
            const c = liveDist[i];
            if (!c) return null;
            return {
                full_name: c.full_name, party: c.party, is_running: c.is_running,
                status: c.status || 'running', verified: c.verified || false,
                photo: c.photo || null, slug: c.slug || null,
                profile_url: c.profile_url || null,
                ballotpedia_url: c.ballotpedia_url || null, website: c.website || null,
                bio: c.bio_excerpt || null, raised: null, stance_topic: null, stance_text: null,
                primary_result: c.primary_result || null,
                general_date: c.general_date || null,
                office: `U.S. Representative — ${districtLabel}`,
            };
        });
    } else {
        seated = challenger = third = null;
    }

    const stateOffices = stateData?.offices ?? [];
    const distPop = stateData?.district_populations?.[houseKey];
    const statePop = stateData?.population;
    const popBadge = distPop
        ? `<span style="color:#94a3b8;font-size:11px;margin-left:8px;">👥 ${distPop.formatted} residents <span style="opacity:.6">(${distPop.census_year} Census)</span></span>`
        : (statePop ? `<span style="color:#94a3b8;font-size:11px;margin-left:8px;">👥 State pop: ${statePop.formatted}</span>` : '');

    const _distApiStatus = stateData?._apiStatus || 'unreachable';
    const _distLive = !!(stateData?.house_candidates?.[houseKey]?.length);
    const distCands = [seated, challenger, third].filter(Boolean);
    const distPhase = detectElectionPhase(distCands);
    const advancedChalls = [challenger, third].filter(c => c && (!c.primary_result || c.primary_result === 'advanced_to_general'));

    let challSection = '';
    if (distPhase === 'post_general') {
        challSection = '';
    } else if (distPhase === 'post_primary') {
        if (advancedChalls.length) {
            const genDate = advancedChalls.find(c => c.general_date)?.general_date;
            const challLabel = 'General Election Candidates'
                + (genDate ? ` · ${new Date(genDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}` : '');
            challSection = `<p style="color:#475569;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;margin:12px 0 6px;">${challLabel}</p>
                ${advancedChalls.map(c => renderCandidate(c, color)).join('')}`;
        }
    } else {
        challSection = `<p style="color:#475569;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;margin:12px 0 6px;">2026 Primary Challengers</p>
            ${challenger ? renderCandidate(challenger, color) : ''}
            ${third ? renderCandidate(third, color) : ''}`;
    }

    const houseHtml = seated
        ? `<p style="color:#475569;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;margin:8px 0 6px;">Current Officeholder</p>
        ${renderCandidate(seated, color)}
        ${challSection}`
        : noDataNotice('No records for this district yet. Data is synced weekly from congress-legislators and Ballotpedia.');

    const statewideHtml = stateOffices.length
        ? stateOffices.map(g => renderOfficeGroup(g, OFFICE_ROLES, color)).join('')
        : noDataNotice('Statewide candidate records for this state are not yet available. Check back after the next weekly sync.');

    const _distBanner = (_distApiStatus === 'unreachable')
        ? `<div style="display:flex;align-items:center;gap:8px;background:#1e1a2e;border:1px solid #7c3aed55;border-radius:8px;padding:8px 12px;margin-bottom:12px;">
             <span style="font-size:14px;">⚠️</span>
             <div><span style="color:#a78bfa;font-size:11px;font-weight:600;">DATA UNREACHABLE</span><span style="color:#64748b;font-size:11px;"> · Live records unavailable right now.</span></div>
           </div>`
        : '';

    candEl.innerHTML = `${_distBanner}

    <div style="background:${color}0a;border:1px solid ${color}22;border-radius:8px;padding:8px 10px;margin-bottom:12px;font-size:11px;color:#475569;">
        <span style="color:${color};font-weight:600;">119th Congress</span> &nbsp;·&nbsp; 2025–2027
        &nbsp;·&nbsp; <a href="https://www.house.gov" target="_blank" rel="noopener" style="color:${color};text-decoration:none;">house.gov →</a>
        ${popBadge}
    </div>
    <div class="office-section">
        <div class="office-title" style="background:${color}18;border-left:3px solid ${color};color:${color};padding:6px 10px;border-radius:6px;margin-bottom:6px;">
            🏛&nbsp;U.S. Representative — ${districtLabel}
        </div>
        <p class="office-role-tip">Elected every 2 years. Represents ~750,000 constituents in the U.S. House of Representatives.</p>
        ${houseHtml}
    </div>

    <div style="border-top:1px solid ${color}20;margin:16px 0 14px;display:flex;align-items:center;gap:8px;">
        <span style="color:${color};font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;white-space:nowrap;">Statewide Races — ${stateName}</span>
        <div style="flex:1;border-top:1px solid ${color}20;"></div>
    </div>
    ${statewideHtml}`;
}