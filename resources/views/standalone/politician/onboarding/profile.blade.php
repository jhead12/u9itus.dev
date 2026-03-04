<x-onboarding-layout
    :progress="$progress"
    :phases="$phases"
    :total-phases="$totalPhases"
    current-phase="political_profile"
    title="Complete Your Political Profile"
    description="Let voters know who you are and what you stand for"
>
    <form method="POST" action="{{ route('politician.onboarding.complete-profile') }}" class="space-y-6">
        @csrf

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="governance_level" class="block text-sm font-medium text-gray-300 mb-2">Governance Level</label>
                <select name="governance_level" id="governance_level" required
                        class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white">
                    <option value="">Select...</option>
                    <option value="Federal" {{ old('governance_level', $politician?->governance_level) == 'Federal' ? 'selected' : '' }}>Federal</option>
                    <option value="State" {{ old('governance_level', $politician?->governance_level) == 'State' ? 'selected' : '' }}>State</option>
                    <option value="County" {{ old('governance_level', $politician?->governance_level) == 'County' ? 'selected' : '' }}>County</option>
                    <option value="City" {{ old('governance_level', $politician?->governance_level) == 'City' ? 'selected' : '' }}>City</option>
                </select>
            </div>

            <div>
                <label for="office_title" class="block text-sm font-medium text-gray-300 mb-2">Office Title</label>
                <input type="text" name="office_title" id="office_title" required
                       value="{{ old('office_title', $politician?->political_office) }}"
                       class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="party_affiliation" class="block text-sm font-medium text-gray-300 mb-2">Party</label>
                <select name="party_affiliation" id="party_affiliation" required
                        class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white">
                    <option value="">Select...</option>
                    <option value="Democratic" {{ old('party_affiliation', $politician?->party_affiliation) == 'Democratic' ? 'selected' : '' }}>Democratic</option>
                    <option value="Republican" {{ old('party_affiliation', $politician?->party_affiliation) == 'Republican' ? 'selected' : '' }}>Republican</option>
                    <option value="Independent" {{ old('party_affiliation', $politician?->party_affiliation) == 'Independent' ? 'selected' : '' }}>Independent</option>
                    <option value="Other" {{ old('party_affiliation', $politician?->party_affiliation) == 'Other' ? 'selected' : '' }}>Other</option>
                </select>
            </div>

            <div>
                <label for="state" class="block text-sm font-medium text-gray-300 mb-2">State</label>
                <select name="state" id="state" required
                        class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white">
                    <option value="">Select state...</option>
                    @foreach(config('u9itus.us_states', []) as $abbr => $stateName)
                        <option value="{{ $abbr }}" {{ old('state', $politician?->state) == $abbr ? 'selected' : '' }}>{{ $stateName }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label for="bio" class="block text-sm font-medium text-gray-300 mb-2">Bio (Optional)</label>
            <textarea name="bio" id="bio" rows="4"
                      class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white">{{ old('bio', $politician?->bio) }}</textarea>
        </div>

        <button type="submit"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg">
            Continue →
        </button>
    </form>
</x-onboarding-layout>
