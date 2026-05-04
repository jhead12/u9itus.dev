@extends('standalone.layouts.dashboard')

@section('title', 'Data Imports')
@section('page-title', 'Data Imports')

@section('content')
<div class="min-h-screen bg-gray-950 text-gray-100 py-12 px-6 sm:px-8">
    <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-white mb-2">State Data Imports</h1>
            <p class="text-gray-400">Monitor scheduled politician profile syncs and import health</p>
        </div>

        <div class="mb-8 bg-gray-900 border border-gray-800 rounded-lg p-6">
            <h2 class="text-xl font-semibold text-white mb-2">One-Off Unverified Profile Import</h2>
            <p class="text-sm text-gray-400 mb-4">
                Use this form to create or update a single unclaimed, unverified politician profile from an official website.
            </p>

            @if ($errors->has('unverified_profile'))
                <div class="mb-4 rounded-lg border border-red-700/60 bg-red-900/20 px-4 py-3 text-sm text-red-200">
                    {{ $errors->first('unverified_profile') }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.imports.unverified-profile.seed') }}" class="space-y-4">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="website" class="block text-sm font-medium text-gray-300 mb-1">Website URL</label>
                        <input id="website" name="website" type="url" value="{{ old('website', 'https://jackson.asmdc.org/') }}" required
                               class="w-full rounded-md border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-gray-100 focus:border-cyan-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-300 mb-1">Full Name</label>
                        <input id="name" name="name" type="text" value="{{ old('name', 'Dr. Corey A. Jackson') }}" required
                               class="w-full rounded-md border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-gray-100 focus:border-cyan-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="office" class="block text-sm font-medium text-gray-300 mb-1">Office</label>
                        <input id="office" name="office" type="text" value="{{ old('office', 'Assemblymember') }}" required
                               class="w-full rounded-md border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-gray-100 focus:border-cyan-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="level" class="block text-sm font-medium text-gray-300 mb-1">Governance Level</label>
                        <input id="level" name="level" type="text" value="{{ old('level', 'State') }}"
                               class="w-full rounded-md border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-gray-100 focus:border-cyan-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="state" class="block text-sm font-medium text-gray-300 mb-1">State</label>
                        <input id="state" name="state" type="text" maxlength="2" value="{{ old('state', 'CA') }}" required
                               class="w-full rounded-md border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-gray-100 focus:border-cyan-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="district" class="block text-sm font-medium text-gray-300 mb-1">District</label>
                        <input id="district" name="district" type="text" value="{{ old('district', 'AD-60') }}"
                               class="w-full rounded-md border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-gray-100 focus:border-cyan-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="party" class="block text-sm font-medium text-gray-300 mb-1">Party</label>
                        <input id="party" name="party" type="text" value="{{ old('party', 'Democratic') }}"
                               class="w-full rounded-md border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-gray-100 focus:border-cyan-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="city" class="block text-sm font-medium text-gray-300 mb-1">City</label>
                        <input id="city" name="city" type="text" value="{{ old('city', 'Moreno Valley') }}"
                               class="w-full rounded-md border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-gray-100 focus:border-cyan-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="source" class="block text-sm font-medium text-gray-300 mb-1">Source Key</label>
                        <input id="source" name="source" type="text" value="{{ old('source', 'official_state_website') }}"
                               class="w-full rounded-md border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-gray-100 focus:border-cyan-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="photo_url" class="block text-sm font-medium text-gray-300 mb-1">Profile Photo URL</label>
                        <input id="photo_url" name="photo_url" type="url" value="{{ old('photo_url', 'https://i.ytimg.com/vi/-eFgDaiyDGM/hqdefault.jpg') }}"
                               class="w-full rounded-md border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-gray-100 focus:border-cyan-500 focus:outline-none">
                    </div>
                </div>

                <div>
                    <label for="bio" class="block text-sm font-medium text-gray-300 mb-1">Bio</label>
                    <textarea id="bio" name="bio" rows="4"
                              class="w-full rounded-md border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-gray-100 focus:border-cyan-500 focus:outline-none">{{ old('bio', 'Official unclaimed profile imported from the Assemblymember website for California Assembly District 60. Capitol Office: (916) 319-2060. District Office: (951) 653-0960. Profile is available for verified claim by campaign staff.') }}</textarea>
                </div>

                <div class="flex items-center gap-3">
                    <input id="publish_hidden" name="publish" type="hidden" value="0">
                    <input id="publish" name="publish" type="checkbox" value="1" class="h-4 w-4 rounded border-gray-700 bg-gray-950 text-cyan-500"
                           {{ old('publish', '1') === '1' ? 'checked' : '' }}>
                    <label for="publish" class="text-sm text-gray-300">Publish profile in public directory</label>
                </div>

                <div>
                    <button type="submit"
                            class="inline-flex items-center rounded-md bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-400/60">
                        Run One-Off Import
                    </button>
                </div>
            </form>
        </div>

        <div class="mb-8 bg-gray-900 border border-gray-800 rounded-lg p-6">
            <h2 class="text-xl font-semibold text-white mb-2">OCR Bulk Candidate Upload</h2>
            <p class="text-sm text-gray-400 mb-4">
                Upload scanned local government voting packages (PDF or images) and convert detected candidate rows into bulk import records.
            </p>

            @if ($errors->has('ocr_import'))
                <div class="mb-4 rounded-lg border border-red-700/60 bg-red-900/20 px-4 py-3 text-sm text-red-200">
                    {{ $errors->first('ocr_import') }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.imports.ocr-candidates') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="ocr-source" class="block text-sm font-medium text-gray-300 mb-1">Source Key</label>
                        <input id="ocr-source" name="source" type="text" value="{{ old('source', 'local_gov_ocr') }}" required
                               class="w-full rounded-md border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-gray-100 focus:border-cyan-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="scan_upload" class="block text-sm font-medium text-gray-300 mb-1">Scan Upload (PDF/Image/TXT/JSON)</label>
                        <input id="scan_upload" name="scan_upload" type="file" required
                               accept=".pdf,.png,.jpg,.jpeg,.tif,.tiff,.bmp,.webp,.txt,.json"
                               class="w-full rounded-md border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-gray-100 file:mr-3 file:rounded file:border-0 file:bg-gray-800 file:px-2 file:py-1 file:text-xs file:text-gray-100 focus:border-cyan-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="ocr-state" class="block text-sm font-medium text-gray-300 mb-1">State (optional default)</label>
                        <input id="ocr-state" name="state" type="text" maxlength="2" value="{{ old('state', 'CA') }}"
                               class="w-full rounded-md border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-gray-100 focus:border-cyan-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="ocr-office" class="block text-sm font-medium text-gray-300 mb-1">Office (optional default)</label>
                        <input id="ocr-office" name="political_office" type="text" value="{{ old('political_office') }}"
                               placeholder="e.g. City Council Member"
                               class="w-full rounded-md border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-gray-100 focus:border-cyan-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="ocr-level" class="block text-sm font-medium text-gray-300 mb-1">Governance Level</label>
                        <input id="ocr-level" name="governance_level" type="text" value="{{ old('governance_level', 'Local') }}"
                               class="w-full rounded-md border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-gray-100 focus:border-cyan-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="ocr-district" class="block text-sm font-medium text-gray-300 mb-1">District (optional default)</label>
                        <input id="ocr-district" name="district" type="text" value="{{ old('district') }}"
                               class="w-full rounded-md border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-gray-100 focus:border-cyan-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="ocr-county" class="block text-sm font-medium text-gray-300 mb-1">County (optional default)</label>
                        <input id="ocr-county" name="county" type="text" value="{{ old('county') }}"
                               class="w-full rounded-md border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-gray-100 focus:border-cyan-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="ocr-city" class="block text-sm font-medium text-gray-300 mb-1">City (optional default)</label>
                        <input id="ocr-city" name="city" type="text" value="{{ old('city') }}"
                               class="w-full rounded-md border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-gray-100 focus:border-cyan-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="ocr-party" class="block text-sm font-medium text-gray-300 mb-1">Party (optional default)</label>
                        <input id="ocr-party" name="party_affiliation" type="text" value="{{ old('party_affiliation') }}"
                               class="w-full rounded-md border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-gray-100 focus:border-cyan-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="ocr-election-date" class="block text-sm font-medium text-gray-300 mb-1">Election Date (optional default)</label>
                        <input id="ocr-election-date" name="election_date" type="date" value="{{ old('election_date') }}"
                               class="w-full rounded-md border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-gray-100 focus:border-cyan-500 focus:outline-none">
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-4">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-300">
                        <input type="checkbox" name="dry_run" value="1" {{ old('dry_run') ? 'checked' : '' }}
                               class="h-4 w-4 rounded border-gray-700 bg-gray-950 text-cyan-500">
                        Dry Run (validate without saving)
                    </label>

                    <button type="submit"
                            class="inline-flex items-center rounded-md bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-400/60">
                        Run OCR Import
                    </button>
                </div>

                <p class="text-xs text-gray-500">
                    Best results: clear scans, one contest per page, candidate lines formatted like "First Last - Party". Server OCR tools used when available: <span class="font-mono">pdftotext</span> and <span class="font-mono">tesseract</span>.
                </p>
            </form>

            @if (session('ocr_import_count'))
                <div class="mt-4 rounded-lg border border-green-800/60 bg-green-900/20 px-4 py-3 text-sm text-green-200">
                    OCR parsed {{ (int) session('ocr_import_count') }} candidate row(s).
                </div>
            @endif

            @if(session('import_output'))
                <div class="mt-4 rounded-lg border border-gray-700 bg-gray-950/60 p-3">
                    <p class="text-xs text-gray-400 mb-2">Import Output</p>
                    <pre class="text-xs text-gray-200 whitespace-pre-wrap">{{ session('import_output') }}</pre>
                </div>
            @endif
        </div>

        <!-- Status Summary -->
        @if ($latestRun)
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-gray-900 border border-gray-800 rounded-lg p-4">
                    <div class="text-sm text-gray-400 mb-1">Latest Status</div>
                    <div class="text-2xl font-bold">
                        @if ($latestRun->status === 'success')
                            <span class="text-green-400">✓ Success</span>
                        @elseif ($latestRun->status === 'failed')
                            <span class="text-red-400">✗ Failed</span>
                        @else
                            <span class="text-yellow-400">⊙ {{ ucfirst($latestRun->status) }}</span>
                        @endif
                    </div>
                </div>

                <div class="bg-gray-900 border border-gray-800 rounded-lg p-4">
                    <div class="text-sm text-gray-400 mb-1">Created</div>
                    <div class="text-2xl font-bold text-blue-400">{{ $latestRun->created_count ?? 0 }}</div>
                </div>

                <div class="bg-gray-900 border border-gray-800 rounded-lg p-4">
                    <div class="text-sm text-gray-400 mb-1">Updated</div>
                    <div class="text-2xl font-bold text-purple-400">{{ $latestRun->updated_count ?? 0 }}</div>
                </div>

                <div class="bg-gray-900 border border-gray-800 rounded-lg p-4">
                    <div class="text-sm text-gray-400 mb-1">Last Run</div>
                    <div class="text-sm font-mono text-gray-400">
                        @if ($latestRun->started_at)
                            {{ $latestRun->started_at->setTimezone('America/Los_Angeles')->format('M d, H:i PT') }}
                        @else
                            Never
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <!-- Import Logs Table -->
        <div class="bg-gray-900 border border-gray-800 rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-800 bg-gray-800">
                            <th class="px-6 py-4 text-left text-gray-300 font-semibold">Date</th>
                            <th class="px-6 py-4 text-left text-gray-300 font-semibold">State</th>
                            <th class="px-6 py-4 text-left text-gray-300 font-semibold">Status</th>
                            <th class="px-6 py-4 text-center text-gray-300 font-semibold">Created</th>
                            <th class="px-6 py-4 text-center text-gray-300 font-semibold">Updated</th>
                            <th class="px-6 py-4 text-center text-gray-300 font-semibold">Skipped</th>
                            <th class="px-6 py-4 text-center text-gray-300 font-semibold">Campaigns</th>
                            <th class="px-6 py-4 text-left text-gray-300 font-semibold">Exit Code</th>
                            <th class="px-6 py-4 text-left text-gray-300 font-semibold">Error</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        @forelse ($imports as $import)
                            @php
                                $stateCode = null;
                                if (preg_match('/\[state=([A-Z]{2})\]/', (string) $import->output, $stateMatch) === 1) {
                                    $stateCode = $stateMatch[1];
                                }
                            @endphp
                            <tr class="hover:bg-gray-800/50 transition-colors">
                                <td class="px-6 py-4 font-mono text-gray-400 whitespace-nowrap">
                                    {{ $import->started_at?->setTimezone('America/Los_Angeles')->format('M d, Y H:i PT') ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 font-mono text-gray-300 whitespace-nowrap">
                                    {{ $stateCode ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4">
                                    @if ($import->status === 'success')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-900/30 text-green-400 border border-green-800">
                                            ✓ Success
                                        </span>
                                    @elseif ($import->status === 'failed')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-red-900/30 text-red-400 border border-red-800">
                                            ✗ Failed
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-700 text-gray-300 border border-gray-600">
                                            {{ ucfirst($import->status) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-center font-mono text-blue-400">
                                    {{ $import->created_count ?? 0 }}
                                </td>
                                <td class="px-6 py-4 text-center font-mono text-purple-400">
                                    {{ $import->updated_count ?? 0 }}
                                </td>
                                <td class="px-6 py-4 text-center font-mono text-yellow-400">
                                    {{ $import->skipped_count ?? 0 }}
                                </td>
                                <td class="px-6 py-4 text-center font-mono text-pink-400">
                                    {{ $import->campaigns_created_count ?? 0 }}
                                </td>
                                <td class="px-6 py-4 font-mono text-gray-400">
                                    {{ $import->exit_code ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 max-w-xs">
                                    @if ($import->error_message)
                                        <span class="text-red-400 text-xs" title="{{ $import->error_message }}">
                                            {{ Str::limit($import->error_message, 50) }}
                                        </span>
                                    @else
                                        <span class="text-gray-500 text-xs">No errors</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-6 py-8 text-center text-gray-500">
                                    No import runs found. Imports will appear here after the first scheduled run at 02:00 PT.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if ($imports->hasPages())
                <div class="border-t border-gray-800 px-6 py-4 bg-gray-800/30">
                    {{ $imports->links() }}
                </div>
            @endif
        </div>

        <!-- Info Box -->
        <div class="mt-8 bg-blue-900/20 border border-blue-800 rounded-lg p-4">
            <div class="flex items-start gap-3">
                <div class="text-blue-400 text-lg">i</div>
                <div>
                    <h3 class="font-semibold text-blue-300 mb-1">About State Rotation Imports</h3>
                    <p class="text-sm text-gray-400">
                        This dashboard displays daily state-rotation politician profile imports. Imports are scheduled daily at <strong>02:00 PT</strong>
                        and sync one U.S. state per day from the congress-legislators API.
                        The health check runs hourly to ensure freshness. For operational details, see the
                        <a href="{{ route('admin.dashboard') }}" class="text-blue-400 hover:text-blue-300 underline">operations documentation</a>.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
