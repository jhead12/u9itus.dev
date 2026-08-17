/**
 * Shared screen-space rectangle-overlap test used by every HTML overlay's
 * greedy collision suppression (city/gov markers, district labels,
 * candidate dots — see render-loop.js, labels-overlay.js,
 * candidate-markers.js). Rectangle overlap, not point-distance, because
 * city/gov markers are anchored at their LEFT edge (so the dot sits at the
 * true geo point) while district labels and candidate dots are anchored at
 * their CENTER — a single symmetric distance threshold can't represent both
 * shapes correctly, and using one caused candidate dots to render on top of
 * a city pill's far side while still passing an anchor-to-anchor distance
 * check.
 */
export function rectsOverlap(a, b) {
    return a.left < b.right && b.left < a.right && a.top < b.bottom && b.top < a.bottom;
}
