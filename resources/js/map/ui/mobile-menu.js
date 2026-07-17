/**
 * Mobile menu — hamburger button and dropdown.
 */
export function initMobileMenu() {
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const mobileMenu = document.getElementById('mobile-menu');
    const mobBtnDistricts = document.getElementById('mob-btn-districts');
    const mobBtnReset = document.getElementById('mob-btn-reset');

    function closeMobileMenu() {
        mobileMenu.classList.remove('open');
        mobileMenuBtn.setAttribute('aria-expanded', 'false');
    }

    mobileMenuBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = mobileMenu.classList.toggle('open');
        mobileMenuBtn.setAttribute('aria-expanded', String(isOpen));
    });

    document.addEventListener('click', (e) => {
        if (!mobileMenu.contains(e.target) && e.target !== mobileMenuBtn) {
            closeMobileMenu();
        }
    });

    mobBtnDistricts.addEventListener('click', () => {
        document.getElementById('btn-districts').click();
        closeMobileMenu();
    });

    mobBtnReset.addEventListener('click', () => {
        document.getElementById('btn-reset').click();
        closeMobileMenu();
    });
}