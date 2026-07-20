/**
 * Guided tour step definitions.
 * Each step has a target (CSS selector or null for center), position, title, body, and optional media.
 */

function _mediaFor(slug, transcript) {
    const base = `/media/tour/${slug}`;
    return {
        video: [
            { src: `${base}.webm`, type: 'video/webm' },
            { src: `${base}.mp4`,  type: 'video/mp4'  },
        ],
        audio: [
            { src: `${base}.mp3`,  type: 'audio/mpeg' },
        ],
        captions: [
            { src: `${base}.en.vtt`, srclang: 'en', label: 'English', default: true },
        ],
        poster:     `${base}-poster.jpg`,
        transcript: transcript,
    };
}

export const TOUR_STEPS = [
    {
        target: null, pos: 'center',
        title: '🗺 Welcome to U9itus',
        body: `Explore U.S. political representation — governors, attorneys general, representatives, and more — all on an interactive 3D map.<br><br>This quick tour covers the key controls. You can skip at any time.`,
        media: _mediaFor('welcome',
            `The U9itus U.S. map is totally interactive. It allows you immediate access to political representatives in all states by simply pointing your mouse on the desired state to review, by clicking on this state. The state will separate away from all other states and will show area divisions populating into districts, and names of political representatives serving in these districts. Find the representatives serving any area in the United States. Click on their names and find out all about them. You can check what they stand for from various reliable resources. You can also find out who is bankrolling their campaigns — right here at U9itus. In upcoming seasons, this information will be vital in your making an intelligent choice when you go to the polls.`
        ),
    },
    {
        target: '#map-canvas-region', pos: 'right',
        title: '↑ ↓  Tilt',
        body: `Press <kbd>↑</kbd> <kbd>↓</kbd> to tilt the map toward overhead or a low-angle view. Hold a key for continuous movement.`,
        media: _mediaFor('tilt-rotate',
            `You can change your viewing angle at any time using the up and down arrow keys. Press the up arrow to tilt the map toward a top-down overhead view, or the down arrow for a low, dramatic angle. Hold either key down for continuous, smooth movement until you release it. The map stays locked to the United States, so you can not accidentally rotate away from the country.`
        ),
    },
    {
        target: null, pos: 'center',
        title: '🔍 Zoom',
        body: `<strong style="color:#e2e8f0">Scroll</strong> your mouse wheel to zoom in and out.<br><br>Or use <kbd>+</kbd> to zoom in and <kbd>−</kbd> to zoom out from the keyboard.`,
        media: _mediaFor('zoom',
            `Zoom into any part of the country by scrolling your mouse wheel forward to move closer, or backward to pull away. If you prefer the keyboard, press the plus key to zoom in and the minus key to zoom out. You can still tilt the map while zoomed in, so you can frame the exact view you want.`
        ),
    },
    {
        target: '#btn-search', pos: 'bottom',
        title: '/ Search',
        body: `Press <kbd>/</kbd> to open the search bar and jump to any state or congressional district by name.`,
        media: _mediaFor('search',
            `Looking for a specific place? Press the forward-slash key, or click the Search button in the top right, to open the search bar. Start typing any state name or congressional district, and U9itus will fly the camera directly to your selection. Press Escape to dismiss the search at any time.`
        ),
    },
    {
        target: '#map-canvas-region', pos: 'right',
        title: '🖱 Click a State',
        body: `Click any state to open its political profile — statewide officeholders, 2026 candidates, and congressional district boundaries.`,
        media: _mediaFor('click-state',
            `Click directly on any state to open its full political profile. You will see the current statewide officeholders, the candidates running in upcoming elections, and an overlay of congressional district boundaries. From there you can click an individual district to drill down into the representatives serving that area.`
        ),
    },
    {
        target: '#offices-toggle', pos: 'bottom',
        title: '<kbd>O</kbd>  Offices Toggle',
        body: `Press <kbd>O</kbd> or click the <strong style="color:#e2e8f0">Statewide Executive Offices</strong> header to collapse or expand that section. Your preference is remembered.`,
        media: _mediaFor('offices-toggle',
            `When a state is open, you can collapse or expand the Statewide Executive Offices section to focus on just the information you care about. Press the letter O on your keyboard, or click the section header. Your preference is remembered the next time you visit, so the panel always opens the way you like it.`
        ),
    },
    {
        target: null, pos: 'center',
        title: '<kbd>R</kbd>  Reset View',
        body: `Press <kbd>R</kbd> at any time to snap the camera back to the default isometric overview position, no matter how far you've tilted or panned.`,
        media: _mediaFor('reset',
            `If you ever get lost or want to start over, just press the letter R on your keyboard. The camera will smoothly fly back to the default overview of the entire country, no matter how far you have zoomed, tilted, or panned. It is the fastest way to reset your view and pick a new state to explore.`
        ),
    },
    {
        target: '#kb-hint-badge', pos: 'top',
        title: '<kbd>?</kbd>  Keyboard Help',
        body: `Press <kbd>?</kbd> or click this badge to see the full shortcut reference. You can relaunch this tour from there too.`,
        isLast: true,
        media: _mediaFor('keyboard-help',
            `Every action on the map has a keyboard shortcut. Press the question mark key, or click the Keyboard shortcuts badge in the bottom left, to open the full reference. From that same dialog you can replay this guided tour any time. That is everything — welcome to U9itus, and happy exploring.`
        ),
    },
];

export const TOUR_KEY = 'u9_map_tour_v1';