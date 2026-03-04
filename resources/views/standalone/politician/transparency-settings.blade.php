@extends('standalone.layouts.app')

@section('content')
<div class="min-h-screen bg-gray-900 py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-white mb-2">Transparency Settings</h1>
            <p class="text-gray-400">
                Control what public data appears on your campaign profile page
            </p>
        </div>

        <!-- Status Messages -->
        @if (session('success'))
            <div class="mb-6 bg-green-900/50 border border-green-700 text-green-100 px-4 py-3 rounded-lg">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 bg-red-900/50 border border-red-700 text-red-100 px-4 py-3 rounded-lg">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Verification Status Card -->
        <div class="bg-gray-800 rounded-lg shadow-xl p-6 mb-8 border border-gray-700">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-3">
                        <h2 class="text-xl font-semibold text-white">Profile Verification</h2>
                        <span class="px-3 py-1 text-sm font-medium rounded-full
                            @if($verificationStatus['color'] === 'green') bg-green-900/50 text-green-300 border border-green-700
                            @elseif($verificationStatus['color'] === 'yellow') bg-yellow-900/50 text-yellow-300 border border-yellow-700
                            @else bg-gray-700 text-gray-300 border border-gray-600
                            @endif">
                            {{ $verificationStatus['label'] }}
                        </span>
                    </div>
                    <p class="text-gray-400 text-sm">
                        {{ $verificationStatus['description'] }}
                    </p>

                    @if($verificationStatus['color'] === 'green' && isset($verificationStatus['verified_at']))
                        <p class="text-gray-500 text-xs mt-2">
                            Verified on {{ $verificationStatus['verified_at'] }}
                        </p>
                    @endif
                </div>

                @if($verificationStatus['action'])
                    <button
                        type="button"
                        onclick="openVerificationModal()"
                        class="ml-4 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium text-sm transition"
                    >
                        {{ $verificationStatus['action'] }}
                    </button>
                @endif
            </div>
        </div>

        <!-- Verification Info -->
        @if($politician->verification_status === 'unverified')
            <div class="bg-blue-900/30 border border-blue-700 text-blue-100 px-6 py-4 rounded-lg mb-8">
                <div class="flex items-start">
                    <svg class="w-5 h-5 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <h3 class="font-semibold mb-1">Why verify your profile?</h3>
                        <p class="text-sm">
                            Verification with a government email address (.gov, .mil, or official state legislature domain) 
                            unlocks the ability to display public data from trusted sources like Ballotpedia, OpenSecrets, 
                            Vote Smart, and the FEC on your campaign page. This builds trust with voters through transparency.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Transparency Data Sources -->
        <div class="bg-gray-800 rounded-lg shadow-xl p-6 border border-gray-700">
            <h2 class="text-xl font-semibold text-white mb-4">Public Data Sources</h2>
            
            @if($politician->verification_status !== 'verified')
                <div class="bg-gray-700/50 border border-gray-600 rounded-lg p-8 text-center">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    <p class="text-gray-300 font-medium mb-2">Verification Required</p>
                    <p class="text-gray-400 text-sm">
                        Verify your profile to unlock transparency features
                    </p>
                </div>
            @else
                <form action="{{ route('politician.transparency-settings.update') }}" method="POST" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <p class="text-gray-400 text-sm mb-6">
                        Enable or disable specific data sources. All data is pulled from official public sources and cached for 24 hours.
                        You can disable any source at any time.
                    </p>

                    <!-- Ballotpedia -->
                    <div class="bg-gray-700/50 rounded-lg p-5 border border-gray-600">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <h3 class="text-lg font-semibold text-white">Ballotpedia</h3>
                                    <a href="https://ballotpedia.org" target="_blank" class="text-blue-400 hover:text-blue-300 text-xs">
                                        ↗ Visit site
                                    </a>
                                </div>
                                <p class="text-gray-400 text-sm mb-3">
                                    Display your voting record, committee assignments, and sponsored legislation
                                </p>
                                <div class="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        id="show_ballotpedia_data"
                                        name="show_ballotpedia_data"
                                        value="1"
                                        {{ $politician->show_ballotpedia_data ? 'checked' : '' }}
                                        class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-blue-600 focus:ring-blue-500"
                                    >
                                    <label for="show_ballotpedia_data" class="text-sm text-gray-300">
                                        Show Ballotpedia data on my profile
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- OpenSecrets -->
                    <div class="bg-gray-700/50 rounded-lg p-5 border border-gray-600">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <h3 class="text-lg font-semibold text-white">OpenSecrets</h3>
                                    <a href="https://www.opensecrets.org" target="_blank" class="text-blue-400 hover:text-blue-300 text-xs">
                                        ↗ Visit site
                                    </a>
                                </div>
                                <p class="text-gray-400 text-sm mb-3">
                                    Display campaign finance data including top contributors, industries, and sector breakdown
                                </p>
                                <div class="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        id="show_opensecrets_data"
                                        name="show_opensecrets_data"
                                        value="1"
                                        {{ $politician->show_opensecrets_data ? 'checked' : '' }}
                                        class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-blue-600 focus:ring-blue-500"
                                    >
                                    <label for="show_opensecrets_data" class="text-sm text-gray-300">
                                        Show OpenSecrets data on my profile
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Vote Smart -->
                    <div class="bg-gray-700/50 rounded-lg p-5 border border-gray-600">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <h3 class="text-lg font-semibold text-white">Vote Smart</h3>
                                    <a href="https://justfacts.votesmart.org" target="_blank" class="text-blue-400 hover:text-blue-300 text-xs">
                                        ↗ Visit site
                                    </a>
                                </div>
                                <p class="text-gray-400 text-sm mb-3">
                                    Display interest group ratings, issue positions, and key votes
                                </p>
                                <div class="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        id="show_votesmart_data"
                                        name="show_votesmart_data"
                                        value="1"
                                        {{ $politician->show_votesmart_data ? 'checked' : '' }}
                                        class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-blue-600 focus:ring-blue-500"
                                    >
                                    <label for="show_votesmart_data" class="text-sm text-gray-300">
                                        Show Vote Smart data on my profile
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- FEC (Federal candidates only) -->
                    <div class="bg-gray-700/50 rounded-lg p-5 border border-gray-600 {{ in_array($politician->office_position, ['US Representative', 'US Senator', 'President', 'Vice President']) ? '' : 'opacity-50' }}">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <h3 class="text-lg font-semibold text-white">Federal Election Commission (FEC)</h3>
                                    <a href="https://www.fec.gov" target="_blank" class="text-blue-400 hover:text-blue-300 text-xs">
                                        ↗ Visit site
                                    </a>
                                    @if(!in_array($politician->office_position, ['US Representative', 'US Senator', 'President', 'Vice President']))
                                        <span class="text-xs text-gray-500">(Federal candidates only)</span>
                                    @endif
                                </div>
                                <p class="text-gray-400 text-sm mb-3">
                                    Display official federal campaign finance filings and committee data
                                </p>
                                <div class="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        id="show_fec_data"
                                        name="show_fec_data"
                                        value="1"
                                        {{ $politician->show_fec_data ? 'checked' : '' }}
                                        {{ in_array($politician->office_position, ['US Representative', 'US Senator', 'President', 'Vice President']) ? '' : 'disabled' }}
                                        class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-blue-600 focus:ring-blue-500"
                                    >
                                    <label for="show_fec_data" class="text-sm text-gray-300">
                                        Show FEC data on my profile
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-4 border-t border-gray-700">
                        <button
                            type="submit"
                            class="w-full sm:w-auto px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition"
                        >
                            Save Settings
                        </button>
                    </div>
                </form>
            @endif
        </div>

        <!-- Data Attribution Info -->
        <div class="mt-6 bg-gray-800/50 border border-gray-700 rounded-lg p-4">
            <p class="text-gray-400 text-sm">
                <strong class="text-white">Data Attribution:</strong> All public data displayed on your profile includes 
                direct links to the source platforms (Ballotpedia, OpenSecrets, Vote Smart, FEC). Voters can verify 
                the information directly from these authoritative sources.
            </p>
        </div>
    </div>
</div>

<!-- Verification Modal -->
<div id="verificationModal" class="hidden fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 p-4">
    <div class="bg-gray-800 rounded-lg max-w-md w-full p-6 border border-gray-700">
        <h3 class="text-xl font-semibold text-white mb-4">Verify Your Profile</h3>
        
        <form action="{{ route('politician.transparency-settings.verify') }}" method="POST">
            @csrf
            
            <div class="mb-4">
                <label for="government_email" class="block text-sm font-medium text-gray-300 mb-2">
                    Government Email Address
                </label>
                <input
                    type="email"
                    id="government_email"
                    name="government_email"
                    required
                    placeholder="your.name@example.gov"
                    class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
                <p class="mt-2 text-xs text-gray-400">
                    Must be a .gov, .mil, or official state legislature email address
                </p>
            </div>

            <div class="bg-blue-900/30 border border-blue-700 rounded-lg p-3 mb-4">
                <p class="text-xs text-blue-200">
                    We'll send a verification link to this email. Click the link to complete verification 
                    and unlock transparency features.
                </p>
            </div>

            <div class="flex gap-3">
                <button
                    type="button"
                    onclick="closeVerificationModal()"
                    class="flex-1 px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg font-medium transition"
                >
                   Cancel
                </button>
                <button
                    type="submit"
                    class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition"
                >
                    Send Verification Email
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openVerificationModal() {
    document.getElementById('verificationModal').classList.remove('hidden');
}

function closeVerificationModal() {
    document.getElementById('verificationModal').classList.add('hidden');
}

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeVerificationModal();
    }
});

// Close modal on background click
document.getElementById('verificationModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeVerificationModal();
    }
});
</script>
@endsection
