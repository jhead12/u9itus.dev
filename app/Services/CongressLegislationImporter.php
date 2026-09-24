<?php

namespace App\Services;

use App\Models\CongressMemberLegislation;
use App\Models\PoliticianTopic;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Rolls up the bills a member of Congress sponsored and cosponsored, by Congress.gov
 * policy area and by politician_topics slug. A bill counts toward a topic when its
 * policy area is mapped to that topic or its title matches the topic's keywords, so
 * a firearms bill filed under "Crime and Law Enforcement" still reaches gun-control.
 * Only bills and joint resolutions count; simple resolutions are mostly commemorative.
 */
class CongressLegislationImporter
{
    private const COUNTED_TYPES = ['HR', 'S', 'HJRES', 'SJRES'];

    private const PAGE_SIZE = 250;

    // Cosponsorship lists run to thousands for long-serving members; this bounds the
    // API calls per member while still reaching back two Congresses for nearly everyone.
    private const MAX_PAGES = 12;

    protected string $baseUrl;

    protected ?string $apiKey;

    /** @var array<string, list<string>>|null policy area => topic slugs */
    protected ?array $policyAreaTopics = null;

    public function __construct(protected IssueClassifierService $classifier)
    {
        $this->baseUrl = rtrim((string) config('services.congress.base_url', 'https://api.congress.gov/v3'), '/');
        $this->apiKey = config('services.congress.api_key');
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    public static function currentCongress(): int
    {
        return intdiv(now()->year - 1789, 2) + 1;
    }

    public function import(string $bioguide, int $sinceCongress): CongressMemberLegislation
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('CONGRESS_API_KEY is not set.');
        }

        $policyAreas = [];
        $topics = [];
        $totals = ['sponsored' => 0, 'cosponsored' => 0];

        foreach (array_keys($totals) as $role) {
            foreach ($this->bills($bioguide, $role, $sinceCongress) as $bill) {
                $totals[$role]++;

                $area = trim((string) ($bill['policyArea']['name'] ?? ''));
                if ($area !== '') {
                    $policyAreas[$area][$role] = ($policyAreas[$area][$role] ?? 0) + 1;
                }

                foreach ($this->topicsFor($area, (string) ($bill['title'] ?? '')) as $slug) {
                    $topics[$slug][$role] = ($topics[$slug][$role] ?? 0) + 1;
                }
            }
        }

        return CongressMemberLegislation::updateOrCreate(['bioguide_id' => $bioguide], [
            'since_congress' => $sinceCongress,
            'sponsored_total' => $totals['sponsored'],
            'cosponsored_total' => $totals['cosponsored'],
            'policy_areas' => $this->normalize($policyAreas),
            'topics' => $this->normalize($topics),
        ]);
    }

    /** @return list<string> */
    public function topicsFor(string $policyArea, string $title): array
    {
        $slugs = $this->policyAreaTopics()[$policyArea] ?? [];
        if ($match = $this->classifier->confidentKeywordMatch($title)) {
            $slugs[] = $match['topic_slug'];
        }

        return array_values(array_unique($slugs));
    }

    /**
     * Newest-first pages of the member's legislation, stopping once a whole page predates
     * $sinceCongress.
     *
     * @return \Generator<int, array>
     */
    protected function bills(string $bioguide, string $role, int $sinceCongress): \Generator
    {
        $key = $role === 'sponsored' ? 'sponsoredLegislation' : 'cosponsoredLegislation';

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $response = Http::timeout(30)->retry(2, 1000, throw: false)->get("{$this->baseUrl}/member/{$bioguide}/{$role}-legislation", [
                'format' => 'json',
                'limit' => self::PAGE_SIZE,
                'offset' => $page * self::PAGE_SIZE,
                'api_key' => $this->apiKey,
            ]);
            if (! $response->successful()) {
                throw new RuntimeException("Congress.gov {$role} legislation for {$bioguide} failed (HTTP {$response->status()}).");
            }

            $items = (array) ($response->json($key) ?? []);
            $inRange = false;
            foreach ($items as $bill) {
                if ((int) ($bill['congress'] ?? 0) < $sinceCongress) {
                    continue;
                }
                $inRange = true;
                if (in_array(strtoupper((string) ($bill['type'] ?? '')), self::COUNTED_TYPES, true)) {
                    yield $bill;
                }
            }

            if (! $inRange || count($items) < self::PAGE_SIZE || ! $response->json('pagination.next')) {
                return;
            }
        }
    }

    /** @return array<string, list<string>> */
    protected function policyAreaTopics(): array
    {
        if ($this->policyAreaTopics === null) {
            $this->policyAreaTopics = [];
            foreach (PoliticianTopic::where('is_active', true)->get(['slug', 'policy_areas']) as $topic) {
                foreach ((array) ($topic->policy_areas ?? []) as $area) {
                    $this->policyAreaTopics[(string) $area][] = (string) $topic->slug;
                }
            }
        }

        return $this->policyAreaTopics;
    }

    /** Fills missing roles with 0 and orders by sponsored, then cosponsored, count. */
    protected function normalize(array $counts): array
    {
        $counts = array_map(fn (array $c) => ['sponsored' => $c['sponsored'] ?? 0, 'cosponsored' => $c['cosponsored'] ?? 0], $counts);
        uasort($counts, fn (array $a, array $b) => [$b['sponsored'], $b['cosponsored']] <=> [$a['sponsored'], $a['cosponsored']]);

        return $counts;
    }
}
