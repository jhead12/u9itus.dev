/**
 * Auth-aware session context.
 * Reads meta tags injected server-side when voter features are enabled.
 */

const _meta = (name) => document.querySelector(`meta[name="${name}"]`)?.content || '';

window.U9 = window.U9 || {};
window.U9.session = Object.freeze({
    role:          _meta('u9-user-role') || 'guest',
    userUuid:      _meta('u9-user-uuid') || null,
    referralCode:  _meta('u9-ref-code')  || null,
    isAuthenticated() { return this.role !== 'guest' && this.role !== ''; },
    isVoter()         { return this.role === 'voter'; },
    isPolitician()    { return this.role === 'politician'; },
});

/* Auth-aware CTA telemetry — fires one impression per pageview and a click event. */
(function () {
    const cta     = document.getElementById('btn-signin-cta');
    const popover = document.getElementById('earn-popover');
    if (!cta || !popover) return;

    setTimeout(() => window.__mapTrack?.('map_signin_cta_shown', { role: window.U9.session.role }), 0);

    cta.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = popover.classList.toggle('open');
        cta.classList.toggle('active', isOpen);
        cta.setAttribute('aria-expanded', String(isOpen));
    });
})();