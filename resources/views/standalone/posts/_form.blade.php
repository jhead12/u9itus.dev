@props(['post' => null, 'topics' => collect(), 'selectedTopicIds' => []])

@php($isEdit = $post !== null)
@php($routePrefix = auth()->user()->hasRole('politician') ? 'politician.posts' : 'citizen.posts')

{{-- The visible fields below live in the sidebar (Featured Image, Topics) and the
     main column, split across two grid cells that can't both nest inside one
     literal <form> tag. Each field instead points at this hidden form via the
     HTML5 form="post-form" attribute, so they all submit together regardless
     of where they sit in the DOM. --}}
<form id="post-form" method="POST"
      action="{{ $isEdit ? route($routePrefix . '.update', $post) : route($routePrefix . '.store') }}"
      enctype="multipart/form-data"
      @if($isEdit) data-autosave="1" @endif>
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif
</form>

@if($errors->any())
<div class="rounded-xl border border-red-500/30 bg-red-500/10 p-4 mb-6">
    <p class="text-sm font-medium text-red-300">Please fix the following before submitting:</p>
    <ul class="mt-2 list-disc pl-5 text-xs text-red-200 space-y-1">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
    <!-- Main column -->
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Title <span class="text-red-400">*</span></label>
                <input form="post-form" type="text" name="title" value="{{ old('title', $post?->title) }}" required maxlength="120"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-lg font-medium placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                    placeholder="e.g. Why we need safer crosswalks on Main Street" />
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Subtitle</label>
                <input form="post-form" type="text" name="subtitle" value="{{ old('subtitle', $post?->subtitle) }}" maxlength="255"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                    placeholder="Optional one-line summary" />
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Body</label>
                <div id="post-body-editor"
                     data-post-body-editor
                     data-input="post-body-input"
                     data-upload-url="{{ route($routePrefix . '.images') }}"
                     class="border border-slate-700 rounded-b-lg text-sm [&_.ql-editor]:min-h-[420px]"></div>
                <textarea form="post-form" id="post-body-input" name="body" maxlength="50000" class="hidden">{{ old('body', $post?->body) }}</textarea>
                <p class="text-xs text-slate-500 mt-1">Allowed formatting: headings, bold/italic/underline/strike, alignment, lists, quotes, links, and images.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Excerpt</label>
                <textarea form="post-form" name="excerpt" rows="2" maxlength="500"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition resize-none"
                    placeholder="Short preview text for social shares and listings...">{{ old('excerpt', $post?->excerpt) }}</textarea>
            </div>
        </div>

        <!-- SEO -->
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6 space-y-4">
            <h2 class="text-sm font-semibold text-slate-200">SEO & Sharing</h2>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Meta Title</label>
                <input form="post-form" type="text" name="meta_title" value="{{ old('meta_title', $post?->meta_title) }}" maxlength="70"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                    placeholder="Title shown in search results and social previews" />
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Meta Description</label>
                <textarea form="post-form" name="meta_description" rows="2" maxlength="160"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition resize-none"
                    placeholder="Short description for search engines and social shares...">{{ old('meta_description', $post?->meta_description) }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Canonical URL</label>
                <input form="post-form" type="url" name="canonical_url" value="{{ old('canonical_url', $post?->canonical_url) }}" maxlength="2048"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                    placeholder="https://... (leave blank to use the default URL)" />
            </div>
        </div>

        <!-- Location (for map pins) -->
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6 space-y-4" x-data="{ hasGeo: {{ old('latitude', $post?->latitude) ? 'true' : 'false' }} }">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-200">Map Location</h2>
                <label class="inline-flex items-center gap-2 text-sm text-slate-300 cursor-pointer">
                    <input type="checkbox" x-model="hasGeo" class="accent-amber-500" />
                    Show on map
                </label>
            </div>

            <div x-show="hasGeo" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Location Name</label>
                    <input form="post-form" type="text" name="location_name" value="{{ old('location_name', $post?->location_name) }}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                        placeholder="e.g. Lincoln High School, Springfield" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Latitude</label>
                    <input form="post-form" type="number" step="any" name="latitude" value="{{ old('latitude', $post?->latitude) }}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                        placeholder="39.7817" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Longitude</label>
                    <input form="post-form" type="number" step="any" name="longitude" value="{{ old('longitude', $post?->longitude) }}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                        placeholder="-89.6501" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">City</label>
                    <input form="post-form" type="text" name="city" value="{{ old('city', $post?->city) }}"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                        placeholder="Springfield" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">State</label>
                    <input form="post-form" type="text" name="state" value="{{ old('state', $post?->state) }}" maxlength="2"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                        placeholder="IL" />
                </div>
                <div class="sm:col-span-2">
                    <p class="text-xs text-slate-500">Set latitude and longitude to make this post appear as a pin on the U9itus map.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="space-y-6">
        <!-- Publish -->
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-5 space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-200">Publish</h2>
                @if($isEdit)
                <span class="text-xs font-medium px-2 py-0.5 rounded-full border border-slate-600 text-slate-300">
                    {{ $post->status->label() }}
                </span>
                @endif
            </div>

            @if($isEdit)
            <p id="autosave-status" class="text-xs text-slate-500 h-4"></p>
            @endif

            <div class="flex flex-col gap-2 pt-1">
                <button form="post-form" type="submit"
                        class="w-full bg-amber-500 hover:bg-amber-400 text-slate-900 font-semibold rounded-lg px-4 py-2.5 text-sm transition-colors">
                    {{ $isEdit ? 'Update Post' : 'Save Draft' }}
                </button>

                @if($isEdit && $post->status->value !== 'published')
                <form id="publish-form" method="POST" action="{{ route($routePrefix . '.publish', $post) }}">
                    @csrf
                    <button type="submit"
                            class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-semibold rounded-lg px-4 py-2.5 text-sm transition">
                        Publish Now
                    </button>
                </form>
                @endif

                @if($isEdit && $post->status->value === 'published')
                <form id="archive-form" method="POST" action="{{ route($routePrefix . '.archive', $post) }}">
                    @csrf
                    <button type="submit"
                            class="w-full bg-slate-700 hover:bg-slate-600 text-white font-semibold rounded-lg px-4 py-2.5 text-sm transition">
                        Archive
                    </button>
                </form>
                @endif

                @if($isEdit)
                <a id="view-public-link" href="{{ route('blog.show', $post) }}" target="_blank"
                   class="text-center text-amber-400 hover:text-amber-300 text-sm font-medium py-1">
                    View public page →
                </a>
                @endif

                <a href="{{ route($routePrefix . '.index') }}"
                   class="text-center text-slate-400 hover:text-white text-sm transition py-1">Cancel</a>
            </div>
        </div>

        <!-- Featured Image -->
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-5 space-y-3">
            <h2 class="text-sm font-semibold text-slate-200">Featured Image</h2>
            @if($post?->featured_image_url)
            <img src="{{ $post->featured_image_url }}" alt="" class="w-full max-h-40 object-cover rounded-lg border border-slate-700" />
            @endif
            <input form="post-form" type="file" name="featured_image_file" accept="image/jpeg,image/png,image/webp"
                class="w-full text-xs text-slate-300 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-slate-700 file:text-white file:text-xs hover:file:bg-slate-600" />
            <p class="text-xs text-slate-500">Uploading a file replaces the URL below. JPEG, PNG, or WebP, up to 5&nbsp;MB.</p>
            <input form="post-form" type="url" name="featured_image_url" value="{{ old('featured_image_url', $post?->featured_image_url) }}" maxlength="2048"
                class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-white text-xs placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                placeholder="Or paste an image URL" />
        </div>

        <!-- Topics -->
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-5 space-y-3">
            <h2 class="text-sm font-semibold text-slate-200">Topics</h2>
            <div class="flex flex-wrap gap-2">
                @foreach($topics as $topic)
                <label class="inline-flex items-center gap-2 rounded-lg border border-slate-700 bg-slate-900/30 px-3 py-1.5 text-xs text-slate-300 cursor-pointer hover:border-slate-600">
                    <input form="post-form" type="checkbox" name="topic_ids[]" value="{{ $topic->id }}"
                        {{ in_array($topic->id, old('topic_ids', $selectedTopicIds)) ? 'checked' : '' }}
                        class="accent-amber-500" />
                    {{ $topic->name }}
                </label>
                @endforeach
            </div>
            @if($topics->isEmpty())
            <p class="text-sm text-slate-500">No topics available yet. Ask an admin to add topics in the platform settings.</p>
            @endif
        </div>

        @if($isEdit)
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-5">
            <h2 class="text-sm font-semibold text-slate-200">Promote This Post</h2>
            <p class="text-sm text-slate-400 mt-1">
                Spend wallet credits to feature this post on topic pages, the voter ad room, and the map. (Coming in Phase 3.)
            </p>
        </div>
        @endif
    </div>
</div>

@push('scripts')
    @vite(['resources/js/blog/editor.js'])
@endpush
