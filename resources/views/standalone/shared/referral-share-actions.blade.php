@php
    $shareSubject = $shareSubject ?? 'Join me on U9itus';
    $shareMessage = $shareMessage ?? 'Use my referral link to join U9itus.';
    $shareBody = $shareBody ?? ($shareMessage . "\n\n" . $shareLink);
    $emailShareUrl = 'mailto:?subject=' . rawurlencode($shareSubject) . '&body=' . rawurlencode($shareBody);
    $xShareUrl = 'https://twitter.com/intent/tweet?text=' . rawurlencode($shareMessage) . '&url=' . rawurlencode($shareLink);
    $facebookShareUrl = 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($shareLink) . '&quote=' . rawurlencode($shareMessage);
    $whatsAppShareUrl = 'https://api.whatsapp.com/send?text=' . rawurlencode($shareMessage . ' ' . $shareLink);
    $telegramShareUrl = 'https://t.me/share/url?url=' . rawurlencode($shareLink) . '&text=' . rawurlencode($shareMessage);
@endphp

<div class="mt-3 rounded-xl border border-slate-700/60 bg-slate-900/50 p-4 space-y-3">
    <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Preloaded Share Message</p>
        <p class="mt-1 text-sm text-slate-200 leading-relaxed">{{ $shareMessage }}</p>
    </div>

    <div class="flex flex-wrap gap-2">
        <button type="button"
                onclick="shareReferralLink(this)"
                data-link="{{ $shareLink }}"
                data-title="{{ $shareSubject }}"
                data-text="{{ $shareMessage }}"
                class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-600 text-slate-200 hover:text-white hover:border-slate-500 transition text-sm font-medium">
            Share
        </button>
        <a href="{{ $emailShareUrl }}"
           class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-sky-500/30 bg-sky-500/10 text-sky-200 hover:text-sky-100 transition text-sm font-medium">
            Email Draft
        </a>
        <a href="{{ $xShareUrl }}" target="_blank" rel="noopener noreferrer"
           class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-600 text-slate-200 hover:text-white hover:border-slate-500 transition text-sm font-medium">
            X
        </a>
        <a href="{{ $facebookShareUrl }}" target="_blank" rel="noopener noreferrer"
           class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-blue-500/30 bg-blue-500/10 text-blue-200 hover:text-blue-100 transition text-sm font-medium">
            Facebook
        </a>
        <a href="{{ $whatsAppShareUrl }}" target="_blank" rel="noopener noreferrer"
           class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-emerald-500/30 bg-emerald-500/10 text-emerald-200 hover:text-emerald-100 transition text-sm font-medium">
            WhatsApp
        </a>
        <a href="{{ $telegramShareUrl }}" target="_blank" rel="noopener noreferrer"
           class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-cyan-500/30 bg-cyan-500/10 text-cyan-200 hover:text-cyan-100 transition text-sm font-medium">
            Telegram
        </a>
    </div>
</div>

@once
    <script>
        async function shareReferralLink(button) {
            var link = button.getAttribute('data-link');
            var title = button.getAttribute('data-title') || 'Join me on U9itus';
            var text = button.getAttribute('data-text') || 'Use my referral link to join U9itus.';

            if (!link) {
                return;
            }

            if (navigator.share) {
                try {
                    await navigator.share({ title: title, text: text, url: link });
                    return;
                } catch (error) {
                    if (!error || error.name === 'AbortError') {
                        return;
                    }
                }
            }

            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(link);
                }
            } catch (error) {
                console.error('Failed to share referral link:', error);
            }
        }
    </script>
@endonce
