@extends('standalone.layouts.dashboard')

@section('title', 'Edit Email Template — ' . $template->name)
@section('page-title', 'Edit Email Template')

@section('content')
<div class="space-y-6 max-w-3xl mx-auto">

    {{-- Back link --}}
    <a href="{{ route('admin.email-templates.index') }}"
       class="inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-white transition">
        ← Back to Email Templates
    </a>

    @if(session('success'))
    <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm rounded-lg px-4 py-3">
        {{ session('success') }}
    </div>
    @endif

    {{-- Template meta card --}}
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl px-5 py-4">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-white font-semibold text-base">{{ $template->name }}</h2>
                <p class="text-sm text-slate-400 mt-1">{{ $template->description }}</p>
                <div class="flex items-center gap-3 mt-2">
                    <span class="text-xs px-2 py-0.5 rounded-full bg-slate-700 text-slate-400">{{ $template->categoryLabel() }}</span>
                    <code class="text-xs text-slate-500 font-mono">{{ $template->key }}</code>
                </div>
            </div>
            <a href="{{ route('admin.email-templates.preview', $template) }}"
               target="_blank"
               class="shrink-0 text-xs px-3 py-1.5 rounded-lg bg-slate-700 text-slate-300 hover:bg-slate-600 transition">
                Preview →
            </a>
        </div>
    </div>

    {{-- Edit form --}}
    <form method="POST" action="{{ route('admin.email-templates.update', $template) }}" class="space-y-5">
        @csrf
        @method('PUT')

        {{-- Subject override --}}
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl px-5 py-5 space-y-4">
            <h3 class="text-sm font-semibold text-white">
                {{ $template->category === 'referral' ? 'Share Title' : 'Subject Line' }}
            </h3>

            <div>
                <label for="subject_override" class="block text-xs font-medium text-slate-400 mb-1.5">
                    @if($template->category === 'referral')
                        Custom Share Title <span class="text-slate-600">(used as the email subject and native-share title — leave blank for the built-in default)</span>
                    @else
                        Custom Subject <span class="text-slate-600">(leave blank to use the default built into the code)</span>
                    @endif
                </label>
                <input type="text"
                       id="subject_override"
                       name="subject_override"
                       value="{{ old('subject_override', $template->subject_override) }}"
                       placeholder="e.g. ✅ Your identity has been verified!"
                       class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2.5 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-emerald-500/60 transition" />
                @error('subject_override')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="preview_text" class="block text-xs font-medium text-slate-400 mb-1.5">
                    @if($template->category === 'referral')
                        Admin Notes <span class="text-slate-600">(internal note for your reference — not shown to users)</span>
                    @else
                        Preview Text <span class="text-slate-600">(the short snippet shown below the subject in email clients — optional)</span>
                    @endif
                </label>
                <input type="text"
                       id="preview_text"
                       name="preview_text"
                       value="{{ old('preview_text', $template->preview_text) }}"
                       placeholder="e.g. Your account now has full access to the platform."
                       class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2.5 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-emerald-500/60 transition" />
            </div>
        </div>

        {{-- Body override --}}
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl px-5 py-5 space-y-4">
            <div class="flex items-start justify-between">
                <div>
                    @if($template->category === 'referral')
                        <h3 class="text-sm font-semibold text-white">Share Message</h3>
                        <p class="text-xs text-slate-500 mt-1">
                            Plain-text message shown in share toolbars, email drafts, and social share links.
                            Leave blank to restore the platform default. Supports the merge variables listed below.
                        </p>
                    @else
                        <h3 class="text-sm font-semibold text-white">HTML Body Override</h3>
                        <p class="text-xs text-slate-500 mt-1">
                            Paste a complete HTML email body to replace the default Blade template.
                            Leave blank to use the built-in responsive template.
                        </p>
                    @endif
                </div>
                @if($template->hasBodyOverride())
                <span class="shrink-0 text-xs px-2 py-0.5 rounded-full
                    {{ $template->category === 'referral'
                        ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20'
                        : 'bg-blue-500/10 text-blue-400 border border-blue-500/20' }}">
                    {{ $template->category === 'referral' ? 'Custom text active' : 'Custom HTML active' }}
                </span>
                @endif
            </div>

            {{-- Available variables --}}
            @if($template->available_variables)
            <div>
                <p class="text-xs text-slate-500 mb-2">Available merge variables:</p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($template->available_variables as $var)
                    <code class="text-xs bg-slate-700/70 text-emerald-400 px-2 py-0.5 rounded">{{ $var }}</code>
                    @endforeach
                </div>
                <p class="text-xs text-slate-600 mt-2">
                    Note: variable substitution in body overrides requires custom rendering logic.
                    For basic use, paste static HTML and update it as needed.
                </p>
            </div>
            @endif

            <textarea id="body_override"
                      name="body_override"
                      rows="18"
                      placeholder="{{ $template->category === 'referral' ? 'Enter the share message text...' : 'Paste full HTML here, or leave blank to use the default Blade template...' }}"
                      class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2.5 {{ $template->category === 'referral' ? 'text-sm' : 'text-xs font-mono' }} text-slate-300 placeholder-slate-600 focus:outline-none focus:border-emerald-500/60 transition resize-y">{{ old('body_override', $template->body_override) }}</textarea>
        </div>

        {{-- Active toggle --}}
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl px-5 py-4">
            <label class="flex items-center justify-between cursor-pointer">
                <div>
                    @if($template->category === 'referral')
                        <p class="text-sm font-medium text-white">Use custom share message</p>
                        <p class="text-xs text-slate-500 mt-0.5">When disabled, share toolbars and referral pages will fall back to the built-in default text.</p>
                    @else
                        <p class="text-sm font-medium text-white">Send this notification</p>
                        <p class="text-xs text-slate-500 mt-0.5">When disabled, this email will be silently skipped even when the triggering event occurs.</p>
                    @endif
                </div>
                <div class="relative ml-4">
                    <input type="hidden" name="is_active" value="0" />
                    <input type="checkbox"
                           name="is_active"
                           value="1"
                           id="is_active"
                           {{ old('is_active', $template->is_active) ? 'checked' : '' }}
                           class="sr-only peer" />
                    <label for="is_active"
                           class="relative inline-flex h-6 w-11 items-center rounded-full cursor-pointer
                                  bg-slate-700 peer-checked:bg-emerald-600 transition-colors">
                        <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow
                                     translate-x-1 peer-checked:translate-x-6 transition-transform"></span>
                    </label>
                </div>
            </label>
        </div>

        {{-- Submit --}}
        <div class="flex items-center gap-3">
            <button type="submit"
                    class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold rounded-lg transition">
                Save Template
            </button>
            <a href="{{ route('admin.email-templates.index') }}"
               class="px-5 py-2.5 bg-slate-700 hover:bg-slate-600 text-slate-300 text-sm font-medium rounded-lg transition">
                Cancel
            </a>
        </div>
    </form>

</div>
@endsection
