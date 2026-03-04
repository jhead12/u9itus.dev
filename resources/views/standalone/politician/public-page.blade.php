@extends('standalone.layouts.dashboard')

@section('title', 'Public Profile Page')
@section('page-title', 'Public Profile Page')

@push('styles')
<style>
    .color-swatch { width: 2rem; height: 2rem; border-radius: 0.375rem; cursor: pointer; transition: transform .15s; }
    .color-swatch:hover { transform: scale(1.1); }
    .preset-card { transition: all .15s; cursor: pointer; }
    .preset-card.active { ring: 2px; --tw-ring-color: rgb(16 185 129 / 1); }
    .initiative-row { transition: background .15s; }
    .initiative-row:hover { background: rgb(51 65 85 / 0.4); }
</style>
{{-- Filerobot Image Editor --}}
<link rel="stylesheet" href="https://cdn.scaleflex.it/plugins/filerobot-image-editor/4/latest/filerobot-image-editor.min.css">
@endpush

@push('scripts')
<script src="https://cdn.scaleflex.it/plugins/filerobot-image-editor/4/latest/filerobot-image-editor.min.js"></script>
@endpush

@section('content')
<div class="max-w-4xl space-y-8">

    {{-- Status Banner --}}
    @if(session('success'))
    <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 rounded-xl px-5 py-3 text-sm font-medium flex items-center gap-2">
        ✓ {{ session('success') }}
    </div>
    @endif

    {{-- Directory Visibility Info Banner --}}
    @if(!$politician->page_published)
    <div class="bg-blue-900/30 border border-blue-600/50 rounded-xl p-5">
        <div class="flex items-start gap-4">
            <div class="w-10 h-10 rounded-lg bg-blue-500/20 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div class="flex-1">
                <h3 class="text-white font-semibold text-sm mb-1.5">Your Profile is Hidden from the Public Directory</h3>
                <p class="text-slate-300 text-sm leading-relaxed mb-3">
                    To appear in the <strong>Browse Politicians & Officials</strong> directory where voters can discover your profile, you must:
                </p>
                <ul class="text-slate-300 text-sm space-y-1.5 ml-4 list-disc">
                    <li>Enable the <strong>"Page Visibility"</strong> toggle below</li>
                    <li>Ensure your account status is <strong>active</strong></li>
                    <li>Save your settings</li>
                </ul>
                <p class="text-slate-400 text-xs mt-3 italic">
                    💡 Even verified politicians must explicitly publish their public page to appear in the directory. This gives you control over when your profile goes live.
                </p>
            </div>
        </div>
    </div>
    @endif

    {{-- Page Preview Link --}}
    @if($politician->slug)
    <div class="bg-blue-500/10 border border-blue-500/20 rounded-xl px-5 py-3 flex items-center justify-between">
        <div>
            @if($politician->page_published)
            <p class="text-sm font-medium text-blue-300">Your public page is <span class="text-emerald-400 font-bold">live</span></p>
            @else
            <p class="text-sm font-medium text-blue-300">Page Preview <span class="text-amber-400 font-bold">(draft mode)</span></p>
            @endif
            <p class="text-xs text-slate-400 mt-0.5">{{ url('/p/' . $politician->slug) }}</p>
        </div>
        <a href="{{ route('politician.public.show', $politician->slug) }}" target="_blank"
           class="text-xs font-semibold px-4 py-2 rounded-lg bg-blue-500/20 text-blue-300 hover:bg-blue-500/30 transition">
            Preview Page ↗
        </a>
    </div>
    @endif

    {{-- ════════════════════════════════════════ --}}
    {{-- Page Settings Form                       --}}
    {{-- ════════════════════════════════════════ --}}
    <form method="POST" action="{{ route('politician.public-page.update') }}" enctype="multipart/form-data">
        @csrf @method('PUT')

        <div class="space-y-6">

            {{-- ── Publish Toggle ── --}}
            <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-200">Page Visibility</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Make your profile page visible to the public at <code class="text-emerald-400">/p/{{ $politician->slug ?? 'your-slug' }}</code></p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="page_published" value="1" class="sr-only peer"
                            {{ old('page_published', $politician->page_published) ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none rounded-full peer
                                    peer-checked:after:translate-x-full peer-checked:after:border-white
                                    after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                                    after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all
                                    peer-checked:bg-emerald-500"></div>
                    </label>
                </div>
            </div>

            {{-- ── Layout Preset ── --}}
            <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6 space-y-4">
                <h2 class="text-sm font-semibold text-slate-200">Layout Preset</h2>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    @foreach(\App\Models\PoliticianPage::LAYOUTS as $preset)
                    <label class="preset-card block cursor-pointer">
                        <input type="radio" name="layout_preset" value="{{ $preset }}" class="sr-only"
                            {{ old('layout_preset', $page->layout_preset ?? 'classic') === $preset ? 'checked' : '' }}>
                        <div class="rounded-xl border-2 p-3 text-center transition
                                    {{ old('layout_preset', $page->layout_preset ?? 'classic') === $preset
                                        ? 'border-emerald-500 bg-emerald-500/10'
                                        : 'border-slate-700 hover:border-slate-500' }}">
                            <div class="text-xs font-semibold text-white capitalize">{{ $preset }}</div>
                            <div class="text-xs text-slate-400 mt-0.5">
                                @switch($preset)
                                    @case('classic')  Traditional rounded @break
                                    @case('modern')   Sharp edges @break
                                    @case('bold')     Accent side-bar @break
                                    @case('minimal')  Clean transparent @break
                                @endswitch
                            </div>
                        </div>
                    </label>
                    @endforeach
                </div>
            </div>

            {{-- ── Background Style ── --}}
            <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6 space-y-4">
                <h2 class="text-sm font-semibold text-slate-200">Background Style</h2>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    @foreach(\App\Models\PoliticianPage::BACKGROUNDS as $bg)
                    <label class="block cursor-pointer">
                        <input type="radio" name="background_style" value="{{ $bg }}" class="sr-only"
                            {{ old('background_style', $page->background_style ?? 'dark') === $bg ? 'checked' : '' }}>
                        <div class="rounded-xl border-2 p-3 text-center transition
                                    {{ old('background_style', $page->background_style ?? 'dark') === $bg
                                        ? 'border-emerald-500 bg-emerald-500/10'
                                        : 'border-slate-700 hover:border-slate-500' }}">
                            <div class="text-xs font-semibold text-white capitalize">{{ $bg }}</div>
                        </div>
                    </label>
                    @endforeach
                </div>
            </div>

            {{-- ── Theme Colors ── --}}
            <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6 space-y-4">
                <h2 class="text-sm font-semibold text-slate-200">Theme Colors</h2>
                <p class="text-xs text-slate-400">Colors are applied as CSS variables — not raw CSS. Choose values that work well on a dark background.</p>
                <div class="grid sm:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-2">Primary Color</label>
                        <div class="flex items-center gap-3">
                            <input type="color" name="primary_color" id="primaryColor"
                                   value="{{ old('primary_color', $page->primary_color ?? '#1e40af') }}"
                                   class="w-12 h-10 rounded-lg border border-slate-600 bg-transparent cursor-pointer" />
                            <input type="text" id="primaryColorHex"
                                   value="{{ old('primary_color', $page->primary_color ?? '#1e40af') }}"
                                   class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm font-mono
                                          focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500"
                                   placeholder="#1e40af"
                                   oninput="document.getElementById('primaryColor').value=this.value" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-2">Accent Color</label>
                        <div class="flex items-center gap-3">
                            <input type="color" name="accent_color" id="accentColor"
                                   value="{{ old('accent_color', $page->accent_color ?? '#f59e0b') }}"
                                   class="w-12 h-10 rounded-lg border border-slate-600 bg-transparent cursor-pointer" />
                            <input type="text" id="accentColorHex"
                                   value="{{ old('accent_color', $page->accent_color ?? '#f59e0b') }}"
                                   class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm font-mono
                                          focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500"
                                   placeholder="#f59e0b"
                                   oninput="document.getElementById('accentColor').value=this.value" />
                        </div>
                    </div>
                </div>

                {{-- Sync color picker → text field --}}
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        document.getElementById('primaryColor').addEventListener('input', function () {
                            document.getElementById('primaryColorHex').value = this.value;
                            document.querySelector('[name=primary_color]').value = this.value;
                        });
                        document.getElementById('accentColor').addEventListener('input', function () {
                            document.getElementById('accentColorHex').value = this.value;
                            document.querySelector('[name=accent_color]').value = this.value;
                        });
                    });
                </script>
            </div>

            {{-- ── Hero Banner ── --}}
            <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-200">Hero Banner Image</h2>
                        <p class="text-xs text-slate-400 mt-1">Optional image shown behind the hero section when background style is "image".</p>
                        <p class="text-xs text-emerald-400 font-medium mt-1.5">
                            📐 Recommended: <span class="font-semibold">1920×600px</span> (16:5 ratio) • Max 5MB
                        </p>
                    </div>
                </div>

                {{-- Current Banner Preview --}}
                <div id="heroBannerPreview" class="{{ $page->hero_banner_url ? '' : 'hidden' }} relative rounded-lg overflow-hidden border border-slate-700">
                    <img id="heroBannerPreviewImg" src="{{ $page->hero_banner_url ?? '' }}" alt="Hero banner preview" class="w-full h-40 object-cover">
                    <div class="absolute top-2 right-2">
                        <button type="button" onclick="removeHeroBanner()"
                                class="bg-red-500/90 hover:bg-red-600 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition">
                            Remove
                        </button>
                    </div>
                </div>

                {{-- Upload Options --}}
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-2">Upload Image</label>
                        <div class="relative">
                            <input type="file" id="heroBannerFile" name="hero_banner_file" accept="image/jpeg,image/png,image/jpg,image/webp"
                                   class="hidden" onchange="handleHeroBannerUpload(this)">
                            <button type="button" onclick="document.getElementById('heroBannerFile').click()"
                                    class="w-full bg-emerald-500/10 hover:bg-emerald-500/20 border border-emerald-500/30 text-emerald-400 px-4 py-2.5 rounded-lg text-sm font-medium transition flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                Choose Image
                            </button>
                        </div>
                        <p class="text-xs text-slate-500 mt-1.5">JPEG, PNG, WebP • Wide format works best</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-2">Or Enter Image URL</label>
                        <input type="url" id="heroBannerUrlInput" name="hero_banner_url"
                               value="{{ old('hero_banner_url', $page->hero_banner_url) }}"
                               placeholder="https://example.com/banner.jpg"
                               class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm
                                      focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                               onchange="updateHeroBannerPreview(this.value)" />
                        <p class="text-xs text-slate-500 mt-1.5">Direct link to your hosted image</p>
                    </div>
                </div>

                {{-- Image Editor Button --}}
                <div class="pt-2">
                    <button type="button" id="openImageEditor"
                            class="w-full bg-blue-500/10 hover:bg-blue-500/20 border border-blue-500/30 text-blue-400 px-4 py-2.5 rounded-lg text-sm font-medium transition flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Create or Edit Banner with Advanced Editor
                    </button>
                    <p class="text-xs text-slate-500 mt-1.5 text-center">Design from scratch or edit uploaded images • Add text, filters, adjustments & more</p>
                </div>

                {{-- Hidden input to store edited image --}}
                <input type="hidden" id="heroBannerEditedData" name="hero_banner_edited">
            </div>

            {{-- ── Section Visibility ── --}}
            <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6 space-y-3">
                <h2 class="text-sm font-semibold text-slate-200">Section Visibility</h2>
                <p class="text-xs text-slate-400">Choose which sections appear on your public page.</p>
                <div class="grid sm:grid-cols-2 gap-3">
                    @foreach(['show_bio' => 'About / Bio', 'show_initiatives' => 'Platform & Policies', 'show_campaigns' => 'Active Campaign Videos', 'show_contact' => 'Connect / Contact'] as $field => $label)
                    <label class="flex items-center gap-3 rounded-lg border border-slate-700 px-4 py-3 cursor-pointer hover:border-slate-500 transition">
                        <input type="checkbox" name="{{ $field }}" value="1" class="w-4 h-4 accent-emerald-500"
                            {{ old($field, $page->{$field} ?? true) ? 'checked' : '' }}>
                        <span class="text-sm text-slate-300">{{ $label }}</span>
                    </label>
                    @endforeach
                </div>
            </div>

            {{-- ── Custom CTA ── --}}
            <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6 space-y-4">
                <h2 class="text-sm font-semibold text-slate-200">Custom Call-to-Action</h2>
                <p class="text-xs text-slate-400">Override the default "Watch & Earn" button with your own CTA (optional).</p>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5">Button Text (max 80 chars)</label>
                        <input type="text" name="custom_cta_text" maxlength="80"
                               value="{{ old('custom_cta_text', $page->custom_cta_text) }}"
                               placeholder="Learn More About My Campaign"
                               class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm
                                      focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5">Button URL</label>
                        <input type="url" name="custom_cta_url"
                               value="{{ old('custom_cta_url', $page->custom_cta_url) }}"
                               placeholder="https://yourwebsite.com"
                               class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm
                                      focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                    </div>
                </div>
            </div>

            {{-- Save Button --}}
            <div class="flex items-center justify-between pt-2">
                @if($politician->slug)
                <a href="{{ route('politician.public.show', $politician->slug) }}" target="_blank"
                   class="text-sm text-slate-400 hover:text-white transition">
                    Preview page ↗
                </a>
                @else
                <span></span>
                @endif
                <button type="submit"
                        class="bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-semibold text-sm px-6 py-2.5 rounded-lg transition">
                    Save Page Settings
                </button>
            </div>

        </div>
    </form>

    {{-- ════════════════════════════════════════ --}}
    {{-- Platform Initiatives                      --}}
    {{-- ════════════════════════════════════════ --}}
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6 space-y-5">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-sm font-semibold text-slate-200">Platform &amp; Policy Initiatives</h2>
                <p class="text-xs text-slate-400 mt-0.5">These appear on your public page under "Platform &amp; Policy Positions".</p>
            </div>
            <button onclick="document.getElementById('addInitiativeForm').classList.toggle('hidden')"
                    class="bg-emerald-500/10 hover:bg-emerald-500/20 border border-emerald-500/30 text-emerald-400 text-xs font-semibold px-4 py-2 rounded-lg transition">
                + Add Initiative
            </button>
        </div>

        {{-- Add Form (hidden by default) --}}
        <form id="addInitiativeForm" method="POST" action="{{ route('politician.initiatives.store') }}" class="hidden bg-slate-900/60 border border-slate-700 rounded-xl p-5 space-y-4">
            @csrf
            <h3 class="text-sm font-semibold text-slate-300">New Initiative</h3>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1.5">Title <span class="text-red-400">*</span></label>
                    <input type="text" name="title" required maxlength="120"
                           placeholder="e.g. Affordable Housing"
                           class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm
                                  focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1.5">Icon (emoji or name)</label>
                    <input type="text" name="icon" maxlength="64"
                           placeholder="🏠 or heart"
                           class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm
                                  focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition" />
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-400 mb-1.5">Description</label>
                <textarea name="description" rows="3" maxlength="800"
                          placeholder="Describe this policy position or initiative..."
                          class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm
                                 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition resize-y"></textarea>
            </div>
            <div class="flex items-center gap-6">
                <div class="flex items-center gap-2">
                    <label class="text-xs text-slate-400">Sort Order</label>
                    <input type="number" name="sort_order" value="0" min="0" max="9999"
                           class="w-20 bg-slate-800 border border-slate-600 rounded-lg px-3 py-1.5 text-white text-sm
                                  focus:outline-none focus:ring-2 focus:ring-emerald-500/50" />
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-300 cursor-pointer">
                    <input type="checkbox" name="is_published" value="1" checked class="w-4 h-4 accent-emerald-500">
                    Published
                </label>
                <div class="flex-1"></div>
                <button type="submit"
                        class="bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-semibold text-xs px-5 py-2 rounded-lg transition">
                    Add Initiative
                </button>
            </div>
        </form>

        {{-- Existing Initiatives --}}
        @if($initiatives->isEmpty())
            <p class="text-sm text-slate-500 text-center py-4">No initiatives added yet.</p>
        @else
        <div class="space-y-3">
            @foreach($initiatives as $initiative)
            <div class="initiative-row rounded-xl border border-slate-700 p-4">
                <div class="flex items-start gap-4">
                    @if($initiative->icon)
                        <div class="text-2xl flex-shrink-0 mt-0.5">{{ $initiative->icon }}</div>
                    @endif
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-0.5">
                            <span class="font-semibold text-sm text-white">{{ $initiative->title }}</span>
                            @if(!$initiative->is_published)
                                <span class="text-xs text-slate-500 bg-slate-700 px-2 py-0.5 rounded-full">Draft</span>
                            @endif
                        </div>
                        @if($initiative->description)
                            <p class="text-xs text-slate-400 line-clamp-2">{{ $initiative->description }}</p>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        {{-- Inline Edit Toggle --}}
                        <button onclick="document.getElementById('editInit{{ $initiative->id }}').classList.toggle('hidden')"
                                class="text-xs text-slate-400 hover:text-white transition px-3 py-1.5 rounded-lg bg-slate-700/50 hover:bg-slate-700">
                            Edit
                        </button>
                        {{-- Delete --}}
                        <form method="POST" action="{{ route('politician.initiatives.destroy', $initiative) }}"
                              onsubmit="return confirm('Remove this initiative?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-red-400 hover:text-red-300 transition px-3 py-1.5 rounded-lg bg-red-900/20 hover:bg-red-900/30">
                                Remove
                            </button>
                        </form>
                    </div>
                </div>

                {{-- Inline Edit Form --}}
                <form id="editInit{{ $initiative->id }}" method="POST"
                      action="{{ route('politician.initiatives.update', $initiative) }}"
                      class="hidden mt-4 pt-4 border-t border-slate-700 space-y-3">
                    @csrf @method('PUT')
                    <div class="grid sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Title</label>
                            <input type="text" name="title" value="{{ $initiative->title }}" required maxlength="120"
                                   class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm
                                          focus:outline-none focus:ring-2 focus:ring-emerald-500/50" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Icon</label>
                            <input type="text" name="icon" value="{{ $initiative->icon }}" maxlength="64"
                                   class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm
                                          focus:outline-none focus:ring-2 focus:ring-emerald-500/50" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Description</label>
                        <textarea name="description" rows="2" maxlength="800"
                                  class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm
                                         focus:outline-none focus:ring-2 focus:ring-emerald-500/50 resize-y">{{ $initiative->description }}</textarea>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2">
                            <label class="text-xs text-slate-400">Sort</label>
                            <input type="number" name="sort_order" value="{{ $initiative->sort_order }}" min="0" max="9999"
                                   class="w-20 bg-slate-800 border border-slate-600 rounded-lg px-3 py-1.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50" />
                        </div>
                        <label class="flex items-center gap-2 text-sm text-slate-300 cursor-pointer">
                            <input type="checkbox" name="is_published" value="1" class="w-4 h-4 accent-emerald-500"
                                {{ $initiative->is_published ? 'checked' : '' }}>
                            Published
                        </label>
                        <div class="flex-1"></div>
                        <button type="submit"
                                class="bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-semibold text-xs px-4 py-1.5 rounded-lg transition">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
            @endforeach
        </div>
        @endif
    </div>

</div>

<script>
// ─── Hero Banner Management ───────────────────────────────────────────────
let currentHeroBannerUrl = '{{ old('hero_banner_url', $page->hero_banner_url ?? '') }}';
let filerobotImageEditor = null;

/**
 * Handle file selection for hero banner
 */
function handleHeroBannerUpload(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Validate file size (5MB max)
        if (file.size > 5 * 1024 * 1024) {
            alert('Image file size must be less than 5MB');
            input.value = '';
            return;
        }

        // Validate file type
        if (!['image/jpeg', 'image/jpg', 'image/png', 'image/webp'].includes(file.type)) {
            alert('Please upload a valid image file (JPEG, PNG, or WebP)');
            input.value = '';
            return;
        }

        // Create a preview URL
        const reader = new FileReader();
        reader.onload = function(e) {
            currentHeroBannerUrl = e.target.result;
            updateHeroBannerPreview(currentHeroBannerUrl);
            // Clear the URL input since we're using an uploaded file
            document.getElementById('heroBannerUrlInput').value = '';
        };
        reader.readAsDataURL(file);
    }
}

/**
 * Update hero banner preview
 */
function updateHeroBannerPreview(url) {
    const preview = document.getElementById('heroBannerPreview');
    const previewImg = document.getElementById('heroBannerPreviewImg');
    
    if (url && url.trim() !== '') {
        currentHeroBannerUrl = url;
        if (previewImg) {
            previewImg.src = url;
        }
        if (preview) {
            preview.classList.remove('hidden');
        }
    }
}

/**
 * Remove hero banner
 */
function removeHeroBanner() {
    if (confirm('Are you sure you want to remove the hero banner?')) {
        currentHeroBannerUrl = '';
        document.getElementById('heroBannerUrlInput').value = '';
        document.getElementById('heroBannerFile').value = '';
        document.getElementById('heroBannerEditedData').value = '';
        document.getElementById('heroBannerPreview').classList.add('hidden');
    }
}

/**
 * Initialize Filerobot Image Editor
 */
function initializeImageEditor() {
    const openEditorBtn = document.getElementById('openImageEditor');
    
    if (!openEditorBtn) {
        console.error('Image editor button not found');
        return;
    }

    if (!window.FilerobotImageEditor) {
        console.error('Filerobot Image Editor library not loaded');
        return;
    }
    
    openEditorBtn.addEventListener('click', function() {
        // Initialize Filerobot Image Editor
        const { TABS, TOOLS } = window.FilerobotImageEditor;
            
            // If no image exists, create a blank canvas with recommended dimensions
            const imageSource = currentHeroBannerUrl && currentHeroBannerUrl.trim() !== ''
                ? currentHeroBannerUrl
                : undefined; // Undefined will create blank canvas

            const config = {
                source: imageSource,
                defaultSavedImageName: 'hero-banner',
                defaultSavedImageType: 'png',
                useBackendTranslations: false,
                onSave: (editedImageObject, designState) => {
                    // Update preview with edited image
                    currentHeroBannerUrl = editedImageObject.imageBase64;
                    updateHeroBannerPreview(currentHeroBannerUrl);

                    // Store edited image data in hidden input
                    document.getElementById('heroBannerEditedData').value = editedImageObject.imageBase64;

                    // Clear file input and URL input since we have edited data
                    document.getElementById('heroBannerFile').value = '';
                    document.getElementById('heroBannerUrlInput').value = '';

                    filerobotImageEditor.terminate();
                },
                onClose: (closingReason) => {
                    filerobotImageEditor.terminate();
                },
                annotationsCommon: {
                    fill: '#10b981'
                },
                Text: {
                    text: 'Your Text Here',
                    fontSize: 48,
                    fontFamily: 'Arial',
                    fill: '#ffffff'
                },
                Rotate: { angle: 90, componentType: 'slider' },
                Crop: {
                    presetsItems: [
                        {
                            titleKey: 'classicTv',
                            descriptionKey: '4:3',
                            ratio: 4 / 3,
                        },
                        {
                            titleKey: 'cinemascope',
                            descriptionKey: '21:9',
                            ratio: 21 / 9,
                        },
                        {
                            titleKey: 'widescreen',
                            descriptionKey: '16:9',
                            ratio: 16 / 9,
                        },
                        {
                            titleKey: 'banner',
                            descriptionKey: '1920:600',
                            ratio: 1920 / 600,
                        },
                    ],
                    presetsFolders: [
                        {
                            titleKey: 'socialMedia',
                            groups: [
                                {
                                    titleKey: 'facebook',
                                    items: [
                                        {
                                            titleKey: 'profile',
                                            width: 180,
                                            height: 180,
                                            descriptionKey: 'fbProfileSize',
                                        },
                                        {
                                            titleKey: 'cover',
                                            width: 820,
                                            height: 312,
                                            descriptionKey: 'fbCoverSize',
                                        },
                                    ],
                                },
                            ],
                        },
                    ],
                },
                tabsIds: [TABS.ADJUST, TABS.ANNOTATE, TABS.FILTERS, TABS.FINETUNE, TABS.RESIZE],
                // Start with annotation tools for blank canvas, or adjust tools for existing images
                defaultTabId: imageSource ? TABS.ADJUST : TABS.ANNOTATE,
                defaultToolId: imageSource ? TOOLS.CROP : TOOLS.TEXT,
                theme: {
                    palette: {
                        'bg-primary': '#0f172a',
                        'bg-secondary': '#1e293b',
                        'accent-primary': '#10b981',
                    },
                },
            };

            // Create or reuse container element
            let container = document.getElementById('filerobot-image-editor-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'filerobot-image-editor-container';
                document.body.appendChild(container);
            }

            filerobotImageEditor = new window.FilerobotImageEditor.default(
                container,
                config
            );

            filerobotImageEditor.render();
        });
}

// Initialize the editor when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeImageEditor);
} else {
    // DOM is already loaded
    initializeImageEditor();
}

// ─── Layout & Style Button Visual Feedback ────────────────────────────
/**
 * Add instant visual feedback to radio button selections
 */
const addRadioFeedback = (radioName) => {
    const radios = document.querySelectorAll(`input[name="${radioName}"]`);
    
    radios.forEach(radio => {
        radio.addEventListener('change', function() {
            // Get all radio buttons with the same name
            const allRadios = document.querySelectorAll(`input[name="${radioName}"]`);
            
            // Update all buttons in this group
            allRadios.forEach(r => {
                const label = r.closest('label');
                if (!label) return;
                
                const styledDiv = label.querySelector('.rounded-xl.border-2');
                if (!styledDiv) return;
                
                if (r.checked) {
                    // Active state
                    styledDiv.classList.remove('border-slate-700', 'hover:border-slate-500');
                    styledDiv.classList.add('border-emerald-500', 'bg-emerald-500/10');
                } else {
                    // Inactive state
                    styledDiv.classList.remove('border-emerald-500', 'bg-emerald-500/10');
                    styledDiv.classList.add('border-slate-700', 'hover:border-slate-500');
                }
            });
        });
    });
};

// Apply instant feedback to layout preset and background style selections
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        addRadioFeedback('layout_preset');
        addRadioFeedback('background_style');
    });
} else {
    addRadioFeedback('layout_preset');
    addRadioFeedback('background_style');
}
</script>

@endsection
