/**
 * Geo-projection helpers for converting lon/lat to Three.js coordinates.
 * Uses Albers USA projection via D3 for accurate state/district shapes.
 */
import * as d3 from 'd3';
import * as topojson from 'topojson-client';

export { d3, topojson };

export const GEO_SCALE = 1800;
export const GEO_TRANSLATE = [960, 600];
export const NORM = { x: GEO_TRANSLATE[0] * 2, y: GEO_TRANSLATE[1] * 2 };

const projection = d3.geoAlbersUsa().scale(GEO_SCALE).translate(GEO_TRANSLATE);

export function project(coord) {
    const p = projection(coord);
    if (!p) return null;
    return [(p[0] - NORM.x / 2) / NORM.x * 20, (NORM.y / 2 - p[1]) / NORM.y * 20];
}

export function buildShapeFromRings(poly) {
    const exterior = poly[0]; // first ring is always the outer boundary
    if (!exterior || exterior.length < 3) return null;

    const shape = new THREE.Shape();
    const p0 = project(exterior[0]);
    if (!p0) return null;
    shape.moveTo(p0[0], p0[1]);

    for (let i = 1; i < exterior.length; i++) {
        const p = project(exterior[i]);
        if (p) shape.lineTo(p[0], p[1]);
    }
    shape.closePath();

    // Interior rings become holes
    for (let h = 1; h < poly.length; h++) {
        const hole = poly[h];
        if (!hole || hole.length < 3) continue;
        const path = new THREE.Path();
        const ph0 = project(hole[0]);
        if (!ph0) continue;
        path.moveTo(ph0[0], ph0[1]);
        for (let i = 1; i < hole.length; i++) {
            const ph = project(hole[i]);
            if (ph) path.lineTo(ph[0], ph[1]);
        }
        path.closePath();
        shape.holes.push(path);
    }

    return shape;
}