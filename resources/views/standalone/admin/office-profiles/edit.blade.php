@extends('standalone.layouts.dashboard')

@section('title', ($profile->exists ? 'Edit' : 'Add') . ' Office Profile — ' . $politician->full_name)
@section('page-title', 'Office Profile')

@section('content')
<div class="px-6 py-8 max-w-4xl mx-auto">

    {{-- Breadcrumb --}}
    <nav class="mb-6 flex items-center gap-2 text-sm text-slate-400">
        <a href="{{ route('admin.office-profiles.index') }}" class="hover:text-white transition">Office Profiles</a>
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="text-white">{{ $politician->full_name }}</span>
    </nav>

    {{-- Header --}}
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-white">
            {{ $profile->exists ? 'Edit' : 'Add' }} Office Profile
        </h1>
        <p class="text-slate-400 mt-1">
            <span class="text-emerald-400 font-medium">{{ $politician->full_name }}</span>
            @if($politician->political_office)
                &middot; {{ $politician->political_office }}
            @endif
            @if($politician->state)
                &middot; {{ strtoupper($politician->state) }}
            @endif
        </p>
        <p class="text-slate-500 text-sm mt-2">
            This information will be shown to voters in the "About This Office" popup while watching campaign videos.
            Write for a general audience — assume voters have no political background.
        </p>
    </div>

    {{-- Flash errors --}}
    @if($errors->any())
        <div class="bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3 mb-6">
            <p class="text-red-400 text-sm font-medium mb-1">Please fix the following errors:</p>
            @foreach($errors->all() as $error)
                <p class="text-red-300 text-sm">• {{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('admin.office-profiles.update', $politician) }}"
        class="space-y-8"
    >
        @csrf

        {{-- ── Section 1: Office Identity ─────────────────────────────── --}}
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6 space-y-5">
            <h2 class="text-white font-semibold text-base border-b border-slate-700 pb-3">Office Identity</h2>

            {{-- Office Title --}}
            <div>
                <label for="office_title" class="block text-sm font-medium text-slate-300 mb-1.5">
                    Official Title <span class="text-red-400">*</span>
                </label>
                <input
                    type="text"
                    id="office_title"
                    name="office_title"
                    value="{{ old('office_title', $profile->office_title) }}"
                    placeholder="e.g. U.S. Senator, City Council Member, School Board Trustee"
                    class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 @error('office_title') border-red-500 @enderror"
                    required
                >
                @error('office_title')<p class="text-red-400 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                {{-- Governance Level --}}
                <div>
                    <label for="governance_level" class="block text-sm font-medium text-slate-300 mb-1.5">Governance Level</label>
                    <select id="governance_level" name="governance_level"
                        class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
                        <option value="">— Select —</option>
                        @foreach(['federal','state','county','city','school_board','special_district'] as $level)
                            <option value="{{ $level }}" @selected(old('governance_level', $profile->governance_level) === $level)>
                                {{ ucwords(str_replace('_', ' ', $level)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- How Selected --}}
                <div>
                    <label for="how_elected_or_appointed" class="block text-sm font-medium text-slate-300 mb-1.5">How Selected</label>
                    <select id="how_elected_or_appointed" name="how_elected_or_appointed"
                        class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
                        <option value="">— Select —</option>
                        <option value="elected" @selected(old('how_elected_or_appointed', $profile->how_elected_or_appointed) === 'elected')>Elected by voters</option>
                        <option value="appointed" @selected(old('how_elected_or_appointed', $profile->how_elected_or_appointed) === 'appointed')>Appointed</option>
                        <option value="retained" @selected(old('how_elected_or_appointed', $profile->how_elected_or_appointed) === 'retained')>Retention election</option>
                    </select>
                </div>

                {{-- Jurisdiction --}}
                <div>
                    <label for="jurisdiction" class="block text-sm font-medium text-slate-300 mb-1.5">Jurisdiction</label>
                    <input type="text" id="jurisdiction" name="jurisdiction"
                        value="{{ old('jurisdiction', $profile->jurisdiction) }}"
                        placeholder="e.g. State of California, City of Houston"
                        class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
                </div>

                {{-- Term Length --}}
                <div>
                    <label for="term_length_years" class="block text-sm font-medium text-slate-300 mb-1.5">Term Length (years)</label>
                    <input type="number" id="term_length_years" name="term_length_years" min="1" max="99"
                        value="{{ old('term_length_years', $profile->term_length_years) }}"
                        placeholder="e.g. 4"
                        class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
                </div>

                {{-- Seats in body --}}
                <div>
                    <label for="seats_in_body" class="block text-sm font-medium text-slate-300 mb-1.5">
                        Total Seats in Governing Body
                    </label>
                    <input type="number" id="seats_in_body" name="seats_in_body" min="1"
                        value="{{ old('seats_in_body', $profile->seats_in_body) }}"
                        placeholder="e.g. 100 for US Senate"
                        class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
                </div>
            </div>
        </div>

        {{-- ── Section 2: Compensation ──────────────────────────────────── --}}
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6 space-y-5">
            <h2 class="text-white font-semibold text-base border-b border-slate-700 pb-3">Compensation</h2>
            <p class="text-slate-500 text-xs">Enter amounts in whole dollars (e.g. 174000). The system stores them internally as cents.</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="annual_salary_min" class="block text-sm font-medium text-slate-300 mb-1.5">Annual Salary — Low ($)</label>
                    <input type="number" id="annual_salary_min" name="annual_salary_min" min="0"
                        value="{{ old('annual_salary_min', $profile->annual_salary_min ? intval($profile->annual_salary_min / 100) : '') }}"
                        placeholder="e.g. 174000"
                        class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
                </div>
                <div>
                    <label for="annual_salary_max" class="block text-sm font-medium text-slate-300 mb-1.5">Annual Salary — High ($)</label>
                    <input type="number" id="annual_salary_max" name="annual_salary_max" min="0"
                        value="{{ old('annual_salary_max', $profile->annual_salary_max ? intval($profile->annual_salary_max / 100) : '') }}"
                        placeholder="Leave blank if same as low"
                        class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
                </div>
            </div>

            <div>
                <label for="salary_source_note" class="block text-sm font-medium text-slate-300 mb-1.5">Salary Source / Citation</label>
                <input type="text" id="salary_source_note" name="salary_source_note"
                    value="{{ old('salary_source_note', $profile->salary_source_note) }}"
                    placeholder="e.g. Per Congressional Research Service, 2024"
                    class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
            </div>
        </div>

        {{-- ── Section 3: Civic Descriptions ───────────────────────────── --}}
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6 space-y-5">
            <h2 class="text-white font-semibold text-base border-b border-slate-700 pb-3">Civic Description <span class="text-slate-500 text-xs font-normal">(shown to voters)</span></h2>

            <div>
                <label for="role_summary" class="block text-sm font-medium text-slate-300 mb-1.5">What Does This Official Do? <span class="text-slate-500 text-xs">(1–3 sentences)</span></label>
                <textarea id="role_summary" name="role_summary" rows="3"
                    placeholder="Plain-language explanation of the day-to-day work of this position…"
                    class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 resize-y">{{ old('role_summary', $profile->role_summary) }}</textarea>
            </div>

            <div>
                <label for="community_impact" class="block text-sm font-medium text-slate-300 mb-1.5">How Does It Affect Residents' Daily Lives?</label>
                <textarea id="community_impact" name="community_impact" rows="3"
                    placeholder="Explain concrete ways this office impacts housing, schools, roads, public safety, healthcare, etc."
                    class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 resize-y">{{ old('community_impact', $profile->community_impact) }}</textarea>
            </div>

            <div>
                <label for="key_duties" class="block text-sm font-medium text-slate-300 mb-1.5">
                    Key Responsibilities
                    <span class="text-slate-500 text-xs font-normal">(one per line — shown as bullet points)</span>
                </label>
                <textarea id="key_duties" name="key_duties" rows="5"
                    placeholder="Votes on federal legislation&#10;Confirms presidential cabinet nominees&#10;Ratifies international treaties&#10;Serves on committees"
                    class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 resize-y font-mono">{{ old('key_duties', is_array($profile->key_duties) ? implode("\n", $profile->key_duties) : '') }}</textarea>
            </div>

            <div>
                <label for="powers_and_limits" class="block text-sm font-medium text-slate-300 mb-1.5">
                    Powers &amp; Limits
                    <span class="text-slate-500 text-xs font-normal">(one per line — e.g. "CAN veto legislation" or "CANNOT override Supreme Court rulings")</span>
                </label>
                <textarea id="powers_and_limits" name="powers_and_limits" rows="5"
                    placeholder="CAN introduce and vote on federal bills&#10;CAN block nominees via filibuster&#10;CANNOT declare war unilaterally"
                    class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 resize-y font-mono">{{ old('powers_and_limits', is_array($profile->powers_and_limits) ? implode("\n", $profile->powers_and_limits) : '') }}</textarea>
            </div>
        </div>

        {{-- ── Section 4: Source & Verification ───────────────────────── --}}
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6 space-y-5">
            <h2 class="text-white font-semibold text-base border-b border-slate-700 pb-3">Source &amp; Verification</h2>

            <div>
                <label for="source_url" class="block text-sm font-medium text-slate-300 mb-1.5">Official Source URL</label>
                <input type="url" id="source_url" name="source_url"
                    value="{{ old('source_url', $profile->source_url) }}"
                    placeholder="https://www.congress.gov/members/… or official government page"
                    class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 @error('source_url') border-red-500 @enderror">
                @error('source_url')<p class="text-red-400 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <label class="flex items-start gap-3 cursor-pointer group">
                <input
                    type="checkbox"
                    name="is_verified"
                    value="1"
                    @checked(old('is_verified', $profile->is_verified))
                    class="mt-0.5 w-4 h-4 rounded border-slate-600 bg-slate-900 text-emerald-500 focus:ring-emerald-500/50 cursor-pointer"
                >
                <div>
                    <span class="text-sm font-medium text-slate-300 group-hover:text-white transition">Mark as verified</span>
                    <p class="text-slate-500 text-xs mt-0.5">Check this once you have confirmed the data is accurate against official sources. The voter popup will show a "Verified by U9itus" badge.</p>
                    @if($profile->data_verified_at)
                        <p class="text-blue-400 text-xs mt-0.5">Last verified: {{ $profile->data_verified_at->format('M j, Y') }}</p>
                    @endif
                </div>
            </label>
        </div>

        {{-- Submit --}}
        <div class="flex items-center justify-between gap-4 pt-2">
            <a href="{{ route('admin.office-profiles.index') }}"
               class="px-5 py-2.5 rounded-lg bg-slate-700 hover:bg-slate-600 text-white text-sm font-medium transition">
                ← Back to list
            </a>

            <div class="flex items-center gap-3">
                @if($profile->exists)
                    <form method="POST" action="{{ route('admin.office-profiles.destroy', $politician) }}"
                          onsubmit="return confirm('Delete this office profile? This cannot be undone.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="px-5 py-2.5 rounded-lg bg-red-900/40 hover:bg-red-900/70 text-red-300 hover:text-white text-sm font-medium transition">
                            Delete Profile
                        </button>
                    </form>
                @endif

                <button type="submit"
                    class="px-6 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold transition shadow-md shadow-emerald-900/30">
                    {{ $profile->exists ? 'Save Changes' : 'Create Profile' }}
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
