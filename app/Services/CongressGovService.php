<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CongressGovService
{
    protected string $baseUrl;
    protected ?string $apiKey;
    protected int $cacheDuration = 86400; // 24 hours

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.congress.base_url', 'https://api.congress.gov/v3'), '/');
        $this->apiKey = config('services.congress.api_key');
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Fetch current U.S. House members for a state district.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCurrentHouseMembersByDistrict(string $state, string $districtNumber): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $state = strtoupper(trim($state));
        $districtNumber = trim($districtNumber);

        if ($state === '' || $districtNumber === '') {
            return [];
        }

        if (strtoupper($districtNumber) === 'AL') {
            $districtNumber = '0';
        }

        $districtNumber = (string) ((int) $districtNumber);
        $cacheKey = 'congress_gov.member.' . strtolower($state . '.' . $districtNumber);

        return Cache::remember(
            $cacheKey,
            $this->cacheDuration,
            fn () => $this->fetchHouseMembersByDistrict($state, $districtNumber)
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchHouseMembersByDistrict(string $state, string $districtNumber): array
    {
        try {
            $response = Http::timeout(12)->get("{$this->baseUrl}/member/{$state}/{$districtNumber}", [
                'currentMember' => 'true',
                'format' => 'json',
                'api_key' => $this->apiKey,
            ]);

            if (! $response->successful()) {
                Log::warning('CongressGovService: request failed', [
                    'state' => $state,
                    'district' => $districtNumber,
                    'status' => $response->status(),
                    'body_excerpt' => mb_substr($response->body(), 0, 500),
                ]);

                return [];
            }

            $members = data_get($response->json(), 'members', []);

            if (! is_array($members)) {
                return [];
            }

            $parsed = [];

            foreach ($members as $member) {
                if (! is_array($member)) {
                    continue;
                }

                $entry = $this->parseMember($member, $state, $districtNumber);

                if ($entry !== null) {
                    $parsed[] = $entry;
                }
            }

            return $parsed;
        } catch (\Throwable $e) {
            Log::error('CongressGovService: exception while fetching members', [
                'state' => $state,
                'district' => $districtNumber,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Parse a single Congress.gov member entry into our internal shape, or null
     * to omit it.
     *
     * Despite the district segment in the request URL, this endpoint returns
     * every current member loosely tied to the state (e.g. neighboring House
     * districts and the state's senators), not just the queried district. Each
     * member's own "district" field and current term's chamber are what's
     * actually authoritative — trusting the queried district number previously
     * mislabeled members (e.g. CA-43's Maxine Waters showing up as "CA-29").
     *
     * @param  array<string, mixed>  $member
     * @return array<string, mixed>|null
     */
    protected function parseMember(array $member, string $state, string $districtNumber): ?array
    {
        $isSenator = $this->memberIsSenator($member);
        $memberDistrictRaw = data_get($member, 'district');

        if ($isSenator) {
            $politicalOffice = 'United States Senator';
            $districtCode = '';
        } else {
            $politicalOffice = 'United States Representative';

            // House members not carrying district data, or whose actual district
            // doesn't match the one queried, aren't relevant to this address —
            // skip them rather than mislabeling them.
            if ($memberDistrictRaw === null || (int) $memberDistrictRaw !== (int) $districtNumber) {
                return null;
            }

            $memberDistrictNumber = (int) $memberDistrictRaw;
            $districtCode = $memberDistrictNumber === 0
                ? $state . '-AL'
                : $state . '-' . str_pad((string) $memberDistrictNumber, 2, '0', STR_PAD_LEFT);
        }

        $name = (string) ($member['name'] ?? '');

        if ($name === '') {
            return null;
        }

        if (str_contains($name, ',')) {
            $parts = array_map('trim', explode(',', $name, 2));
            if (count($parts) === 2) {
                $name = trim($parts[1] . ' ' . $parts[0]);
            }
        }

        $memberUrl = (string) data_get($member, 'url', '');
        $bioguideId = '';
        if ($memberUrl !== '' && preg_match('~/member/([A-Z0-9]+)~i', $memberUrl, $matches) === 1) {
            $bioguideId = strtoupper((string) ($matches[1] ?? ''));
        }

        return [
            'full_name' => $name,
            'political_office' => $politicalOffice,
            'party_affiliation' => (string) ($member['partyName'] ?? $member['party'] ?? ''),
            // Congress.gov's member.state field is the full state name (e.g.
            // "California"), not a USPS abbreviation — use the already-verified
            // 2-letter $state this method was called with instead, which is what
            // every other lookup/persist path in the app compares against.
            'state' => $state,
            'district_code' => $districtCode,
            'photo_url' => $bioguideId !== '' ? 'https://unitedstates.github.io/images/congress/225x275/' . $bioguideId . '.jpg' : null,
            'website' => null,
            'source' => 'congress_gov',
            'external_id' => $bioguideId !== '' ? 'congress_gov_' . $bioguideId : '',
        ];
    }

    /**
     * @param  array<string, mixed>  $member
     */
    protected function memberIsSenator(array $member): bool
    {
        $terms = data_get($member, 'terms.item', []);

        if (! is_array($terms) || $terms === []) {
            return false;
        }

        $currentTerm = null;
        foreach ($terms as $term) {
            if (is_array($term) && ! array_key_exists('endYear', $term)) {
                $currentTerm = $term;
                break;
            }
        }

        $currentTerm ??= end($terms);
        $chamber = (string) data_get($currentTerm, 'chamber', '');

        return stripos($chamber, 'Senate') !== false;
    }
}
