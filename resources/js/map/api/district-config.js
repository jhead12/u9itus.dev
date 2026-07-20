/**
 * District config — fetches and caches Congress number, TIGERweb layer, CD field, and party map.
 * Falls back to hardcoded 119th Congress defaults. The /api/v1/map/district-config
 * endpoint is refreshed daily by the `geo:sync-district-config` workflow.
 *
 * Results are cached in localStorage for 6 hours so low-connectivity devices
 * skip the network round-trip on repeat visits.
 */
import { DISTRICT_CONFIG } from '../state/map-state.js';
import { DISTRICT_PARTY_MAP } from '../config/constants.js';

const DIST_CFG_LS_KEY = 'u9_map_district_config';
const DIST_CFG_TTL    = 6 * 60 * 60 * 1000; // 6 hours

function _readDistrictConfigCache() {
    try {
        const raw = localStorage.getItem(DIST_CFG_LS_KEY);
        if (!raw) return null;
        const { ts, data } = JSON.parse(raw);
        if (Date.now() - ts > DIST_CFG_TTL) { localStorage.removeItem(DIST_CFG_LS_KEY); return null; }
        return data;
    } catch { return null; }
}

function _writeDistrictConfigCache(data) {
    try { localStorage.setItem(DIST_CFG_LS_KEY, JSON.stringify({ ts: Date.now(), data })); } catch {}
}

/**
 * Build the TIGERweb query URL dynamically so we always target the right layer.
 * Congressional districts live under the `Legislative` service; the layer index
 * comes from DISTRICT_CONFIG.tigerweb_layer (default 0 for the 119th Congress).
 */
export function getTigerwebUrl() {
    const layer = DISTRICT_CONFIG.tigerweb_layer ?? 0;
    return `https://tigerweb.geo.census.gov/arcgis/rest/services/TIGERweb/Legislative/MapServer/${layer}/query`;
}

function _applyConfig(cfg) {
    if (!cfg || !cfg.cd_field) return;
    DISTRICT_CONFIG.congress_number = cfg.congress_number ?? 119;
    DISTRICT_CONFIG.tigerweb_layer  = cfg.tigerweb_layer  ?? 0;
    DISTRICT_CONFIG.cd_field        = cfg.cd_field        ?? 'CD119';
    DISTRICT_CONFIG.congress_label  = cfg.congress_label  ?? '119th Congress (2025–2027)';
    if (cfg.party_map && typeof cfg.party_map === 'object' && Object.keys(cfg.party_map).length > 0) {
        DISTRICT_CONFIG.party_map = cfg.party_map;
        Object.assign(DISTRICT_PARTY_MAP, cfg.party_map);
    }
}

export async function initDistrictConfig() {
    // 1. Apply cached config immediately — zero-latency on repeat visits.
    const cached = _readDistrictConfigCache();
    if (cached) {
        _applyConfig(cached);
        // Refresh in the background so the next visit gets up-to-date data.
        fetch('/api/v1/map/district-config')
            .then(r => r.ok ? r.json() : null)
            .then(cfg => { if (cfg?.cd_field) { _applyConfig(cfg); _writeDistrictConfigCache(cfg); } })
            .catch(() => {});
        return;
    }

    // 2. First visit — fetch synchronously so TIGERweb URLs are correct before
    //    any district layer is rendered.
    try {
        const res = await fetch('/api/v1/map/district-config');
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const cfg = await res.json();
        _applyConfig(cfg);
        _writeDistrictConfigCache(cfg);
    } catch (err) {
        console.warn('[district-config] fetch failed, using static fallback:', err.message);
    }
}