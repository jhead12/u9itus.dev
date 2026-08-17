<?php

namespace App\Http\Controllers\Api;

use App\Models\ElectionCandidateRecord;
use App\Models\PoliticalCampaign;
use App\Models\Politician;
use App\Services\CandidateNewsService;
use App\Services\PoliticianResolver;
use App\Services\VoteSmartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MapCandidateOverviewController
{
    public function __invoke(Request $request, CandidateNewsService $newsService, PoliticianResolver $resolver, VoteSmartService $voteSmart): JsonResponse
    {
        $slug = trim((string) $request->query('slug', ''));
        $fullName = trim((string) $request->query('full_name', ''));
        $state = strtoupper(trim((string) $request->query('state', '')));
        $office = trim((string) $request->query('office', ''));
        $scrapeSource = trim((string) $request->query('scrape_source', ''));
        $externalCandidateId = trim((string) $request->query('external_candidate_id', ''));

        if ($slug === '' && $fullName === '') {
            return response()->json([
                'error' => 'Provide slug or full_name.',
            ], 422);
        }

        // Build a deterministic cache key from the lookup params.
        $cacheKey = 'map_candidate_overview:' . sha1(implode('|', [
            $slug, strtolower($fullName), $state, strtolower($office),
            $scrapeSource, $externalCandidateId,
        ]));

        // Cache for 15 minutes — short enough to pick up newly published news,
        // long enough to absorb a burst of drawer-opens on the same candidate.
        $data = Cache::remember($cacheKey, 900, function () use (
            $slug, $fullName, $state, $office, $scrapeSource, $externalCandidateId, $newsService, $resolver, $voteSmart
        ) {
            $politician = $resolver->resolve($slug, $fullName, $state, $office);
            $scraped = $this->resolveScrapedRecord($fullName, $state, $office, $scrapeSource, $externalCandidateId);

            // 40, not 24 — the pool now also covers the candidate's official-site
            // fetch (press releases + events), split back out by content_type below.
            $items = $politician
                ? $newsService->getForPolitician($politician, 40)
                : $newsService->getForCandidateName($fullName, 40, $state !== '' ? $state : null);

            $verified = $items->where('verification_status', 'verified');
            $pool = $verified->isNotEmpty() ? $verified : $items;

            $mapItem = fn ($item) => [
                'headline' => $item->headline,
                'source_name' => $item->source_name,
                'source_url' => $item->source_url,
                'snippet' => $item->snippet,
                'published_at' => optional($item->published_at)?->toIso8601String(),
                'provider' => $item->provider,
                'verification_status' => $item->verification_status,
            ];

            $byType = fn (string $type, int $limit) => $pool
                ->where('content_type', $type)
                ->sortByDesc('published_at')
                ->take($limit)
                ->values()
                ->map($mapItem);

            $newsItems = $byType('news', 5);
            $pressReleaseItems = $byType('press_release', 3);
            $eventItems = $byType('event', 3);

            $activeVideo = $this->resolveActiveVideo($politician, $scraped, $pool->sortByDesc('published_at')->map($mapItem)->all());

            $voteSmartData = $politician
                ? $this->resolveVoteSmartData($politician, $voteSmart)
                : ['bio' => null, 'key_votes' => []];
            $bio = $voteSmartData['bio'];

            return [
                'candidate' => [
                    'full_name' => $fullName !== '' ? $fullName : ($politician?->full_name ?? null),
                    'state' => $state !== '' ? $state : ($politician?->state ?? null),
                    'office' => $office !== '' ? $office : ($politician?->political_office ?? null),
                    'slug' => $slug !== '' ? $slug : ($politician?->slug ?? null),
                    'is_platform' => (bool) $politician,
                    'has_scraped_record' => (bool) $scraped,
                    'birth_date' => $bio['birth_date'] ?? null,
                    'education' => $bio['education'] ?? null,
                    'profession' => $bio['profession'] ?? null,
                ],
                'news' => $newsItems->all(),
                'press_releases' => $pressReleaseItems->all(),
                'events' => $eventItems->all(),
                'active_video' => $activeVideo,
                'key_votes' => $voteSmartData['key_votes'],
            ];
        });

        return response()->json($data);
    }

    private function resolveScrapedRecord(string $fullName, string $state, string $office, string $scrapeSource, string $externalCandidateId): ?ElectionCandidateRecord
    {
        if ($scrapeSource !== '' && $externalCandidateId !== '') {
            $exact = ElectionCandidateRecord::query()
                ->where('source', $scrapeSource)
                ->where('external_candidate_id', $externalCandidateId)
                ->first();
            if ($exact) {
                return $exact;
            }
        }

        if ($fullName === '') {
            return null;
        }

        return ElectionCandidateRecord::query()
            ->whereRaw('LOWER(full_name) = ?', [strtolower($fullName)])
            ->when($state !== '', fn ($q) => $q->whereRaw("UPPER(COALESCE(state, '')) = ?", [$state]))
            ->when($office !== '', fn ($q) => $q->whereRaw("LOWER(COALESCE(political_office, '')) LIKE ?", ['%' . strtolower($office) . '%']))
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Bio fields (birth date / education / profession) and key votes, both
     * from Vote Smart. fetchPoliticianRatings() already pulls this on every
     * call (bundled with ratings/positions/votes and cached together) but
     * historically only the ratings/positions made it into any UI on this
     * endpoint; the bio fields and key votes were fetched and discarded.
     * Reuses that method as-is (same caching, same show_votesmart_data
     * consent gate) rather than duplicating the candidate-ID lookup.
     *
     * @return array{bio: array<string, mixed>|null, key_votes: array<int, array<string, mixed>>}
     */
    private function resolveVoteSmartData(Politician $politician, VoteSmartService $voteSmart): array
    {
        try {
            $data = $voteSmart->fetchPoliticianRatings($politician);
        } catch (\Throwable $e) {
            Log::warning('MapCandidateOverviewController: Vote Smart fetch failed', [
                'politician_id' => $politician->id,
                'error' => $e->getMessage(),
            ]);

            return ['bio' => null, 'key_votes' => []];
        }

        $candidate = $data['candidate'] ?? null;
        $votes = is_array($data['key_votes'] ?? null) ? $data['key_votes'] : [];

        usort($votes, fn ($a, $b) => strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? '')));

        return [
            'bio' => is_array($candidate) ? $candidate : null,
            'key_votes' => array_slice(array_values($votes), 0, 5),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $newsItems
     * @return array<string, mixed>|null
     */
    private function resolveActiveVideo(?Politician $politician, ?ElectionCandidateRecord $scraped, array $newsItems): ?array
    {
        if ($politician) {
            $campaign = PoliticalCampaign::query()
                ->where('politician_id', $politician->id)
                ->whereIn('status', ['active'])
                ->where(function ($q) {
                    $q->whereNotNull('media_url')->orWhereNotNull('live_feed_url');
                })
                ->orderByDesc('started_at')
                ->orderByDesc('id')
                ->first(['title', 'media_url', 'media_type', 'live_feed_url', 'campaign_type', 'started_at']);

            if ($campaign) {
                return [
                    'title' => $campaign->title,
                    'url' => $campaign->media_url ?: $campaign->live_feed_url,
                    'media_type' => $campaign->media_type,
                    'campaign_type' => $campaign->campaign_type,
                    'source' => 'campaign',
                    'started_at' => optional($campaign->started_at)?->toIso8601String(),
                ];
            }
        }

        $payload = is_array($scraped?->payload) ? $scraped->payload : [];

        foreach (['media_url', 'video_url', 'campaign_video_url', 'youtube_url', 'video_link', 'live_feed_url'] as $k) {
            $url = trim((string) ($payload[$k] ?? ''));
            if ($url !== '') {
                return [
                    'title' => $payload['campaign_title'] ?? $payload['headline'] ?? 'Campaign Video',
                    'url' => $url,
                    'media_type' => $payload['media_type'] ?? null,
                    'campaign_type' => $payload['campaign_type'] ?? null,
                    'source' => 'scraped_payload',
                    'started_at' => null,
                ];
            }
        }

        $videoLinks = $payload['video_links'] ?? null;
        if (is_array($videoLinks)) {
            foreach ($videoLinks as $video) {
                if (!is_array($video)) {
                    continue;
                }
                $url = trim((string) ($video['url'] ?? ''));
                if ($url === '') {
                    continue;
                }
                return [
                    'title' => $video['title'] ?? 'Campaign Video',
                    'url' => $url,
                    'media_type' => null,
                    'campaign_type' => null,
                    'source' => 'scraped_payload',
                    'started_at' => null,
                ];
            }
        }

        foreach ($newsItems as $article) {
            $url = trim((string) ($article['source_url'] ?? ''));
            if ($url === '') {
                continue;
            }

            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            if (
                str_contains($host, 'youtube.com')
                || str_contains($host, 'youtu.be')
                || str_contains($host, 'vimeo.com')
                || str_contains($host, 'c-span.org')
            ) {
                return [
                    'title' => $article['headline'] ?? 'Recent Video Coverage',
                    'url' => $url,
                    'media_type' => null,
                    'campaign_type' => null,
                    'source' => 'news_feed',
                    'started_at' => null,
                ];
            }
        }

        return null;
    }
}
