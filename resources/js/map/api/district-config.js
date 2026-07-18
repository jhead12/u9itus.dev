/**
 * District config — fetches and caches Congress number, TIGERweb layer, CD field, and party map.
 * Falls back to hardcoded 119th Congress defaults. The /api/v1/map/district-config
 * endpoint is refreshed daily by the `geo:sync-district-config` workflow.
 */
import { DISTRICT_CONFIG } from '../state/map-state.js';
import { DISTRICT_PARTY_MAP } from '../config/constants.js';

/**
 * Build the TIGERweb query URL dynamically so we always target the right layer.
 * Congressional districts live under the `Legislative` service; the layer index
 * comes from DISTRICT_CONFIG.tigerweb_layer (default 0 for the 119th Congress).
 */
export function getTigerwebUrl() {
    const layer = DISTRICT_CONFIG.tigerweb_layer ?? 0;
    return `https://tigerweb.geo.census.gov/arcgis/rest/services/TIGERweb/Legislative/MapServer/${layer}/query`;
}

export async function initDistrictConfig() {
    try {
        const res = await fetch('/api/v1/map/district-config', { cache: 'no-store' });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const cfg = await res.json();
        if (cfg && cfg.cd_field) {
            DISTRICT_CONFIG.congress_number = cfg.congress_number ?? 119;
            DISTRICT_CONFIG.tigerweb_layer  = cfg.tigerweb_layer  ?? 0;
            DISTRICT_CONFIG.cd_field        = cfg.cd_field        ?? 'CD119';
            DISTRICT_CONFIG.congress_label  = cfg.congress_label  ?? '119th Congress (2025–2027)';
            // Overlay the DB party map on top of the static fallback.
            // DB data wins when present; static fill covers any missing districts.
            if (cfg.party_map && typeof cfg.party_map === 'object' && Object.keys(cfg.party_map).length > 0) {
                DISTRICT_CONFIG.party_map = cfg.party_map;
                Object.assign(DISTRICT_PARTY_MAP, cfg.party_map);
            }
        }
    } catch (err) {
        console.warn('[district-config] fetch failed, using static fallback:', err.message);
    }
}