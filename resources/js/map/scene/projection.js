/**
 * Geo-projection helpers for converting lon/lat to Three.js coordinates.
 * Uses Albers USA projection via D3 for accurate state/district shapes.
 */
import * as THREE from 'three';
import * as d3 from 'd3';
import * as topojson from 'topojson-client';

export { d3, topojson };

/* Projection constants tuned so the lower-48 + AK/HI inset fills ~70% of the
 * viewport width. Uniform NORM preserves aspect ratio (non-uniform scaling
 * here would condense/distort the map). */
const GEO_SCALE = 1070, GEO_TRANSLATE = [480, 300], NORM = 82;
const projection = d3.geoAlbersUsa().scale(GEO_SCALE).translate(GEO_TRANSLATE);

export function project([lon, lat]) {
    const p = projection([lon, lat]);
    if (!p) return null;
    return [(p[0] - GEO_TRANSLATE[0]) / NORM, -(p[1] - GEO_TRANSLATE[1]) / NORM];
}

/**
 * Inverse of project(): world xy back to [longitude, latitude].
 * Returns null if the point is outside the Albers USA clip (e.g. AK/HI insets
 * or far outside the continental view).
 */
export function unproject([x, y]) {
    const p = projection.invert([
        x * NORM + GEO_TRANSLATE[0],
        -y * NORM + GEO_TRANSLATE[1],
    ]);
    if (!p) return null;
    return [p[0], p[1]];
}

export function buildShapeFromRings(poly) {
    const p0 = project(poly[0][0]);
    if (!p0) return null;
    const shape = new THREE.Shape();
    shape.moveTo(p0[0], p0[1]);
    for (let i = 1; i < poly[0].length; i++) {
        const p = project(poly[0][i]);
        if (p) shape.lineTo(p[0], p[1]);
    }
    shape.closePath();

    // Interior rings become holes
    for (let h = 1; h < poly.length; h++) {
        const hp0 = project(poly[h][0]);
        if (!hp0) continue;
        const hole = new THREE.Path();
        hole.moveTo(hp0[0], hp0[1]);
        for (let i = 1; i < poly[h].length; i++) {
            const p = project(poly[h][i]);
            if (p) hole.lineTo(p[0], p[1]);
        }
        hole.closePath();
        shape.holes.push(hole);
    }
    return shape;
}