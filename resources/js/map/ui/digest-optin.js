/**
 * Inline "email me weekly updates about my saved places" prompt.
 *
 * Shown once (ever — same localStorage-gated precedent as tour.js's
 * TOUR_KEY) after the first successful boundary save (`u9:favorites-changed`).
 * Voters just confirm (no email needed, notification_preferences already
 * has their address); guests type an email and get a double opt-in
 * confirmation link (see GuestDigestOptInController) — no digest content
 * is ever sent until that link is clicked.
 */
import { optInToDigest } from '../api/favorites.js';
import { trackEvent } from '../api/interaction.js';

const SHOWN_KEY = 'u9map_digest_prompt_shown';

function alreadyShown() {
    try {
        return !!localStorage.getItem(SHOWN_KEY);
    } catch {
        return false;
    }
}

function markShown() {
    try {
        localStorage.setItem(SHOWN_KEY, '1');
    } catch {
        // ignore (private browsing / storage disabled)
    }
}

function buildToast(isVoter, onDismiss, onSubmit) {
    const toast = document.createElement('div');
    toast.className = 'digest-optin-toast';
    toast.setAttribute('role', 'dialog');
    toast.setAttribute('aria-label', 'Weekly saved places updates');

    const title = document.createElement('p');
    title.className = 'digest-optin-title';
    title.textContent = '📬 Get weekly updates about your saved places?';

    const body = document.createElement('p');
    body.className = 'digest-optin-body';
    body.textContent = 'New candidate news, endorsements, and more — once a week, no spam.';

    const form = document.createElement('form');
    form.className = 'digest-optin-form';

    let emailInput = null;
    if (!isVoter) {
        emailInput = document.createElement('input');
        emailInput.type = 'email';
        emailInput.required = true;
        emailInput.placeholder = 'you@example.com';
        emailInput.className = 'digest-optin-input';
        emailInput.setAttribute('aria-label', 'Email address');
        form.appendChild(emailInput);
    }

    const actions = document.createElement('div');
    actions.className = 'digest-optin-actions';

    const submitBtn = document.createElement('button');
    submitBtn.type = 'submit';
    submitBtn.className = 'digest-optin-btn-primary';
    submitBtn.textContent = 'Yes, email me';

    const dismissBtn = document.createElement('button');
    dismissBtn.type = 'button';
    dismissBtn.className = 'digest-optin-btn-dismiss';
    dismissBtn.textContent = 'No thanks';
    dismissBtn.addEventListener('click', onDismiss);

    actions.appendChild(submitBtn);
    actions.appendChild(dismissBtn);
    form.appendChild(actions);
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        onSubmit(emailInput ? emailInput.value.trim() : null, form);
    });

    toast.appendChild(title);
    toast.appendChild(body);
    toast.appendChild(form);
    return toast;
}

function showStatus(toast, form, message) {
    const status = document.createElement('p');
    status.className = 'digest-optin-status';
    status.textContent = message;
    form.replaceWith(status);
    setTimeout(() => toast.remove(), 4000);
}

export function maybeShowDigestOptIn() {
    if (alreadyShown()) return;
    markShown();

    const isVoter = !!window.U9?.session?.isVoter?.();

    const toast = buildToast(
        isVoter,
        () => {
            toast.remove();
            trackEvent('boundary_digest_optin_dismissed', {});
        },
        async (email, form) => {
            const result = await optInToDigest(email || undefined);
            if (result?.ok) {
                trackEvent('boundary_digest_optin', { meta: { status: result.status } });
                showStatus(toast, form, result.status === 'confirmation_sent'
                    ? 'Check your inbox to confirm!'
                    : "You're all set — updates start this week.");
            } else {
                showStatus(toast, form, 'Something went wrong — please try again later.');
            }
        }
    );

    document.body.appendChild(toast);
}

/** Wire the one-time prompt to fire after the first favorite save. */
export function initDigestOptInPrompt() {
    window.addEventListener('u9:favorites-changed', maybeShowDigestOptIn, { once: true });
}
