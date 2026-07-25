{{-- Shared clipboard-copy helper for referral link inputs.
     copyLink(inputId, confirmId?)
       - copies the readonly input's value to the clipboard (with an
         execCommand fallback for non-secure contexts);
       - if confirmId is given, reveals that "✓ Copied!" element for 3s
         (used on the voter page, which has dedicated confirm elements);
       - otherwise swaps the adjacent Copy button's label to "Copied!" for
         ~1.8s (used on the politician page). --}}
<script>
window.copyLink = function (inputId, confirmId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    navigator.clipboard?.writeText(input.value).catch(() => {
        input.select();
        document.execCommand('copy');
    });
    if (confirmId) {
        const el = document.getElementById(confirmId);
        if (el) {
            el.classList.remove('hidden');
            setTimeout(() => el.classList.add('hidden'), 3000);
        }
    } else {
        const btn = input.nextElementSibling;
        if (btn) {
            const orig = btn.textContent.trim();
            btn.textContent = 'Copied!';
            setTimeout(() => { btn.textContent = orig; }, 1800);
        }
    }
};
</script>