/**
 * Mobile/tablet menu — hamburger button opens #map-menu-sheet, the same
 * Layers + Controls dropdown content used on desktop, restyled at ≤768px
 * (see map.css) into one stacked bottom sheet. No changes needed to
 * layers-panel.js / controls-menu.js: their own trigger buttons are hidden
 * on mobile and simply never clicked, so this owns visibility independently.
 */
export function initMobileMenu() {
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const sheet = document.getElementById('map-menu-sheet');

    function closeSheet() {
        sheet.classList.remove('open');
        sheet.setAttribute('aria-hidden', 'true');
        mobileMenuBtn.setAttribute('aria-expanded', 'false');
    }

    function openSheet() {
        sheet.classList.add('open');
        sheet.setAttribute('aria-hidden', 'false');
        mobileMenuBtn.setAttribute('aria-expanded', 'true');
    }

    mobileMenuBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        if (sheet.classList.contains('open')) {
            closeSheet();
        } else {
            openSheet();
        }
    });

    document.addEventListener('click', (e) => {
        if (sheet.classList.contains('open') && !sheet.contains(e.target) && e.target !== mobileMenuBtn) {
            closeSheet();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && sheet.classList.contains('open')) {
            closeSheet();
        }
    });
}
