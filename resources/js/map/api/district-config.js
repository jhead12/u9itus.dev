/**
 * District config — fetches and caches Congress number, TIGERweb layer, CD field, and party map.
 * Falls back to hardcoded 119th Congress defaults.
 */
import { DISTRICT_CONFIG } from '../state/map-state.js';
import { DISTRICT_PARTY_MAP } from '../config/constants.js';

export function getTigerwebUrl() {
    const congress = DISTRICT_CONFIG.congress_number || 119;
    return `https://tigerweb.geo.census.gov/arcgis/rest/services/TIGERweb/tigerWMS_Congress${congress}/MapServer/28/query`;
}

export async function initDistrictConfig() {
    try {
        const res = await fetch('/api/v1/map/district-config');
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        if (data.congress_number) DISTRICT_CONFIG.congress_number = data.congress_number;
        if (data.congress_label)  DISTRICT_CONFIG.congress_label  = data.congress_label;
        if (data.cd_field)       DISTRICT_CONFIG.cd_field        = data.cd_field;
        if (data.party_map_url) {
            DISTRICT_CONFIG.party_map_url = data.party_map_url;
            try {
                const pm = await fetch(data.party_map_url);
                if (pm.ok) {
                    const pmData = await pm.json();
                    for (const [k, v] of Object.entries(pmData)) DISTRICT_PARTY_MAP[k] = v;
                }
            } catch { /* party map fetch is optional */ }
        }
    } catch (err) {
        console.warn('District config fetch failed, using defaults:', err.message);
    }
}