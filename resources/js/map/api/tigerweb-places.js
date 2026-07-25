/**
 * Fetch incorporated place names + representative points from the Census
 * TIGERweb API. Independent of our own `city_demographics` table, so
 * "which cities are in this district" works even when that table hasn't
 * been synced yet — this is boundary/name data straight from the Census
 * Bureau, not our scraped economic enrichment.
 */
import { STATE_FIPS } from '../config/constants.js';
import { idbGet, idbSet } from '../utils/idb-cache.js';

const PLACES_URL =
    'https://tigerweb.geo.census.gov/arcgis/rest/services/TIGERweb/Places_CouSub_ConCity_SubMCD/MapServer/4/query';

// Place names/locations are stable — same TTL as the district boundary cache.
const PLACES_IDB_TTL = 30 * 24 * 60 * 60 * 1000; // 30 days

const placesCache = {};

/**
 * @param {string} stateName full state name, e.g. "Florida"
 * @returns {Promise<Array<{name: string, lat: number, lon: number, areaLand: number}>>}
 */
export async function fetchStatePlaces(stateName) {
    const fips = STATE_FIPS[stateName];
    if (!fips) return [];

    if (placesCache[fips]) return placesCache[fips];

    const idbKey = `u9_tigerweb_places_${fips}`;
    const cached = await idbGet(idbKey);
    if (cached?.length) {
        placesCache[fips] = cached;
        return cached;
    }

    const params = new URLSearchParams({
        where: `STATE='${fips}'`,
        outFields: 'NAME,INTPTLAT,INTPTLON,AREALAND',
        returnGeometry: 'false',
        f: 'json',
    });

    let places = [];
    try {
        const res = await fetch(`${PLACES_URL}?${params}`, { cache: 'no-store' });
        const data = await res.json();
        places = (data.features || [])
            .map(f => ({
                name: f.attributes.NAME,
                lat: parseFloat(f.attributes.INTPTLAT),
                lon: parseFloat(f.attributes.INTPTLON),
                areaLand: parseFloat(f.attributes.AREALAND) || 0,
            }))
            .filter(p => p.name && Number.isFinite(p.lat) && Number.isFinite(p.lon));
    } catch (e) {
        console.warn('[tigerweb-places] fetch failed:', e.message);
        return [];
    }

    placesCache[fips] = places;
    idbSet(idbKey, places, PLACES_IDB_TTL).catch(() => {});
    return places;
}
