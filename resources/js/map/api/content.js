/**
 * Fetch geo-tagged civic content for the current map viewport.
 */
import * as THREE from 'three';
import { unproject } from '../scene/projection.js';

/**
 * @typedef {Object} MapContentOptions
 * @property {number} south
 * @property {number} west
 * @property {number} north
 * @property {number} east
 * @property {string} [topic]
 * @property {string} [category] - Citizen business_category filter (e.g. "food")
 * @property {number} [limit=50]
 */

/**
 * @typedef {Object} MapBusiness
 * @property {string} id
 * @property {'business'} type
 * @property {string} name
 * @property {string|null} category
 * @property {string} address
 * @property {number} lat
 * @property {number} lng
 * @property {boolean} verified
 */

/**
 * @typedef {Object} MapPost
 * @property {string} id
 * @property {'post'} type
 * @property {string} title
 * @property {string|null} excerpt
 * @property {string} author_name
 * @property {string} published_at
 * @property {string} url
 * @property {number} lat
 * @property {number} lng
 * @property {string|null} location_name
 * @property {boolean} is_promoted
 */

/**
 * Fetch published geo-tagged blog posts, events, and opted-in businesses
 * within a bounding box.
 * @param {MapContentOptions} bounds
 * @returns {Promise<{posts: MapPost[], businesses: MapBusiness[], total: number}>}
 */
export async function fetchMapContent(bounds) {
    const params = new URLSearchParams({
        south: String(bounds.south),
        west: String(bounds.west),
        north: String(bounds.north),
        east: String(bounds.east),
    });
    if (bounds.topic) params.set('topic', bounds.topic);
    if (bounds.category) params.set('category', bounds.category);
    if (bounds.limit) params.set('limit', String(bounds.limit));

    const res = await fetch(`/api/v1/map/content?${params}`, {
        headers: { 'Accept': 'application/json' },
    });
    if (!res.ok) {
        throw new Error(`Map content fetch failed: ${res.status}`);
    }
    return res.json();
}

/**
 * Derive a bounding box from camera + map group state.
 * This is a coarse approximation: project the visible corners of the
 * normalized device coordinates into map world space and un-project.
 *
 * @param {import('three').Camera} camera
 * @param {import('three').Group} mapGroup
 * @returns {MapContentOptions|null}
 */
export function getViewportBounds(camera, mapGroup) {
    // Need the renderer size. The module doesn't import renderer to keep it
    // lightweight; callers can pass dimensions or we read the canvas size.
    const canvas = document.querySelector('#map-container canvas');
    if (!canvas) return null;

    const W = canvas.clientWidth;
    const H = canvas.clientHeight;

    const inverseMatrix = new THREE.Matrix4();
    // world -> camera -> clip -> screen is the forward path.
    // To go screen -> world we invert camera.matrixWorld * projectionMatrixInverse.
    inverseMatrix.copy(camera.projectionMatrixInverse).multiply(camera.matrixWorldInverse);

    const ndcToWorld = (ndcX, ndcY) => {
        const v = new THREE.Vector3(ndcX, ndcY, 0.5);
        v.applyMatrix4(inverseMatrix);
        // We want world-space on the map plane (z ≈ 0). Apply inverse mapGroup matrix.
        const world = v.applyMatrix4(mapGroup.matrixWorld.clone().invert());
        return [world.x, world.y];
    };

    const corners = [
        ndcToWorld(-1, -1),
        ndcToWorld(1, -1),
        ndcToWorld(1, 1),
        ndcToWorld(-1, 1),
    ];

    const lats = [];
    const lngs = [];
    for (const [x, y] of corners) {
        const ll = unproject([x, y]);
        if (ll) {
            lats.push(ll[1]);
            lngs.push(ll[0]);
        }
    }
    if (lats.length < 4 || lngs.length < 4) return null;

    return {
        south: Math.min(...lats),
        north: Math.max(...lats),
        west: Math.min(...lngs),
        east: Math.max(...lngs),
    };
}
