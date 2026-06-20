{{--
    Feedback Widget
    Renders a persistent floating tab that opens an embedded Google Form modal.
    Only visible when admin has locked registrations (registration_open = false).
--}}
@php
    $registrationOpen = filter_var(
        \App\Services\PlatformSettingsService::get('registration_open', null, true),
        FILTER_VALIDATE_BOOLEAN
    );
@endphp

@if(!$registrationOpen)
<div id="feedback-widget" x-data="{ open: false }" class="fixed bottom-6 right-0 z-50 flex flex-col items-end">

    {{-- Floating tab trigger --}}
    <button
        @click="open = true"
        class="flex items-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold rounded-l-xl shadow-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-400/60"
        aria-label="Leave site feedback"
        style="writing-mode: horizontal-tb;"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h6m-6 4h4M5 4h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2z"/>
        </svg>
        Share Feedback
    </button>

    {{-- Modal backdrop + dialog --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @keydown.escape.window="open = false"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        style="display: none;"
    >
        {{-- Backdrop --}}
        <div
            class="absolute inset-0 bg-black/60 backdrop-blur-sm"
            @click="open = false"
        ></div>

        {{-- Dialog --}}
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="relative z-10 w-full max-w-lg bg-slate-900 border border-slate-700 rounded-2xl shadow-2xl overflow-hidden flex flex-col"
            style="max-height: 90vh;"
        >
            {{-- Header --}}
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-700 bg-slate-800/60">
                <div>
                    <h2 class="text-base font-semibold text-white">Share Your Feedback</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Help us improve — your thoughts matter.</p>
                </div>
                <button
                    @click="open = false"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-700 transition-colors focus:outline-none"
                    aria-label="Close feedback form"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Embedded Google Form --}}
            <div class="flex-1 overflow-hidden">
                <iframe
                    src="https://docs.google.com/forms/d/1eUabk9YnV2nNPSaTzpdWxXgJxNJmrxxhnpqVat7Q_jY/viewform?embedded=true"
                    width="100%"
                    height="560"
                    frameborder="0"
                    marginheight="0"
                    marginwidth="0"
                    title="Site Feedback Form"
                    class="w-full block bg-white"
                    loading="lazy"
                    sandbox="allow-scripts allow-same-origin allow-forms allow-popups"
                >
                    Loading…
                </iframe>
            </div>

            {{-- Footer fallback link --}}
            <div class="px-5 py-3 border-t border-slate-700 bg-slate-800/40 text-center">
                <a
                    href="https://docs.google.com/forms/d/1eUabk9YnV2nNPSaTzpdWxXgJxNJmrxxhnpqVat7Q_jY/viewform"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-xs text-emerald-400 hover:text-emerald-300 transition-colors"
                >
                    Open form in a new tab ↗
                </a>
            </div>
        </div>
    </div>
</div>
@endif
