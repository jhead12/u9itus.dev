{{--
    Guest Signup Nudge
    Dismissible modal shown when a guest clicks a gated action (watch full video,
    follow a candidate, etc). Dismissal is remembered for 7 days via cookie so it
    doesn't nag returning guests who already said "not now".
--}}
<div
    id="guest-signup-nudge"
    x-data="{
        show: false,
        dismiss() {
            document.cookie = 'u9_guest_nudge_dismissed=1; max-age=604800; path=/; SameSite=Lax';
            this.show = false;
        }
    }"
    x-on:open-guest-nudge.window="show = true"
    x-show="show"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    x-on:keydown.escape.window="dismiss()"
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    style="display: none;"
>
    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="dismiss()"></div>

    {{-- Dialog --}}
    <div
        x-show="show"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="relative z-10 w-full max-w-md bg-slate-900 border border-slate-700 rounded-2xl shadow-2xl overflow-hidden"
    >
        <div class="flex items-start justify-between gap-3 px-5 py-4 border-b border-slate-700 bg-slate-800/60">
            <div>
                <h2 class="text-base font-semibold text-white">Create a free account to continue</h2>
                <p class="text-xs text-slate-400 mt-0.5">It takes less than a minute.</p>
            </div>
            <button
                @click="dismiss()"
                class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-700 transition-colors focus:outline-none flex-shrink-0"
                aria-label="Close"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="px-5 py-4">
            <ul class="space-y-2.5 text-sm text-slate-300">
                <li class="flex items-start gap-2.5">
                    <span class="text-emerald-400 mt-0.5">&#10003;</span>
                    <span>Follow candidates and get notified about new activity</span>
                </li>
                <li class="flex items-start gap-2.5">
                    <span class="text-emerald-400 mt-0.5">&#10003;</span>
                    <span>Watch full campaign and citizen videos, not just previews</span>
                </li>
                <li class="flex items-start gap-2.5">
                    <span class="text-emerald-400 mt-0.5">&#10003;</span>
                    <span>Ask questions and take part in the community</span>
                </li>
            </ul>
        </div>

        <div class="px-5 pb-5 flex items-center gap-3">
            <a
                href="{{ route('register.voter') }}"
                class="flex-1 text-center bg-emerald-500 hover:bg-emerald-400 text-white text-sm font-semibold px-4 py-2.5 rounded-lg transition shadow-lg"
            >
                Create Free Account
            </a>
            <button
                @click="dismiss()"
                class="text-xs text-slate-400 hover:text-slate-200 transition-colors px-2 py-2.5"
            >
                Maybe later
            </button>
        </div>
    </div>
</div>

@once
<script>
    window.u9GuestNudge = {
        shouldShow() {
            return !document.cookie.split('; ').some((row) => row.startsWith('u9_guest_nudge_dismissed='));
        },
        trigger(event) {
            if (!this.shouldShow()) {
                return;
            }

            event.preventDefault();
            window.dispatchEvent(new CustomEvent('open-guest-nudge'));
        },
    };
</script>
@endonce
