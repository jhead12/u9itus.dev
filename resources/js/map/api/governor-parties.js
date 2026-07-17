/**
 * Governor parties — fetches and caches governor party data for state coloring.
 * Results are cached for 24h in localStorage.
 */
import { STATE_ABBR_MAP, PARTY_INT, PARTY_HEX } from '../config/constants.js';
import { govPartyByAbbr, colorMode } from '../state/map-state.js';
import { stateMeshes } from '../scene/state-meshes.js';

const GOV_PARTY_KEY = 'u9_map_gov_party_cache';
const GOV_PARTY_TTL = 24 * 60 * 60 * 1000; // 24h

let _govPartyPromise = null;

function _readGovPartyCache() {
    try {
        const raw = localStorage.getItem(GOV_PARTY_KEY);
        if (!raw) return null;
        const { ts, data } = JSON.parse(raw);
        if (Date.now() - ts > GOV_PARTY_TTL) { localStorage.removeItem(GOV_PARTY_KEY); return null; }
        return data;
    } catch { return null; }
}

function _writeGovPartyCache(data) {
    try { localStorage.setItem(GOV_PARTY_KEY, JSON.stringify({ ts: Date.now(), data })); } catch {}
}

export function getStatePartyColor(abbr) {
    const party = govPartyByAbbr[abbr];
    return party ? PARTY_HEX[party] || PARTY_HEX.U : PARTY_HEX.U;
}

export async function ensureGovernorParties() {
    if (Object.keys(govPartyByAbbr).length) return govPartyByAbbr;

    const cached = _readGovPartyCache();
    if (cached) {
        Object.assign(govPartyByAbbr, cached);
        return govPartyByAbbr;
    }

    if (_govPartyPromise) return _govPartyPromise;

    _govPartyPromise = (async () => {
        try {
            const res = await fetch('/api/v1/map/state-candidates?governors=1');
            if (!res.ok) return govPartyByAbbr;
            const data = await res.json();
            const parties = data.governor_parties || {};
            Object.assign(govPartyByAbbr, parties);
            _writeGovPartyCache(parties);
        } catch { /* degrade gracefully */ }
        return govPartyByAbbr;
    })();

    return _govPartyPromise;
}

export function applyColorMode() {
    for (const m of stateMeshes) {
        if (colorMode === 'party') {
            const abbr = STATE_ABBR_MAP[m.userData.name];
            const party = govPartyByAbbr[abbr] || 'U';
            m.material.color.setHex(PARTY_INT[party]);
            m.userData.originalColor = PARTY_INT[party];
        } else {
            m.material.color.setHex(m.userData.originalColor);
        }
    }
}