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

        return Cache::remember($cacheKey, $this->cacheDuration, function () use ($state, $districtNumber) {
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

                $data = $response->json();
                $members = data_get($data, 'members', []);

                if (! is_array($members)) {
                    return [];
                }

                $parsed = [];

                foreach ($members as $member) {
                    if (! is_array($member)) {
                        continue;
                    }

                    $name = (string) ($member['name'] ?? '');
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

                    $parsed[] = [
                        'full_name' => $name,
                        'political_office' => 'United States Representative',
                        'party_affiliation' => (string) ($member['partyName'] ?? $member['party'] ?? ''),
                        'state' => strtoupper((string) ($member['state'] ?? $state)),
                        'district_code' => strtoupper((string) ($member['state'] ?? $state)) . '-' . str_pad((string) ((int) $districtNumber), 2, '0', STR_PAD_LEFT),
                        'website' => null,
                        'source' => 'congress_gov',
                        'external_id' => $bioguideId !== '' ? 'congress_gov_' . $bioguideId : '',
                    ];
                }

                return array_values(array_filter($parsed, function (array $entry): bool {
                    return trim((string) ($entry['full_name'] ?? '')) !== '';
                }));
            } catch (\Throwable $e) {
                Log::error('CongressGovService: exception while fetching members', [
                    'state' => $state,
                    'district' => $districtNumber,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }
}
