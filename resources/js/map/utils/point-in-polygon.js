/**
 * Point-in-polygon test (ray casting) over GeoJSON-shaped ring data —
 * [lon, lat] coordinate pairs, first ring is the outer boundary, any
 * further rings are holes.
 */

function pointInRing(lon, lat, ring) {
    let inside = false;
    for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
        const [xi, yi] = ring[i];
        const [xj, yj] = ring[j];
        const intersect = ((yi > lat) !== (yj > lat))
            && (lon < (xj - xi) * (lat - yi) / (yj - yi) + xi);
        if (intersect) inside = !inside;
    }
    return inside;
}

/**
 * @param {number} lon
 * @param {number} lat
 * @param {Array<Array<Array<[number, number]>>>} polys array of polygons, each polygon is [outerRing, ...holeRings]
 */
export function pointInPolygons(lon, lat, polys) {
    for (const poly of polys) {
        const outer = poly?.[0];
        if (!outer || !pointInRing(lon, lat, outer)) continue;

        let inHole = false;
        for (let h = 1; h < poly.length; h++) {
            if (pointInRing(lon, lat, poly[h])) { inHole = true; break; }
        }
        if (!inHole) return true;
    }
    return false;
}
