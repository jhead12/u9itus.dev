<?php

namespace App\Http\Controllers\Api;

use App\Models\ElectionCandidateRecord;
use App\Models\PoliticalCampaign;
use App\Models\Politician;
use App\Services\CandidateNewsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MapCandidateOverviewController
{
    public function __invoke(Request $request, CandidateNewsService $newsService): JsonResponse
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

        $politician = $this->resolvePolitician($slug, $fullName, $state, $office);
        $scraped = $this->resolveScrapedRecord($fullName, $state, $office, $scrapeSource, $externalCandidateId);

        $news = $politician
            ? $newsService->getForPolitician($politician, 24)
            : $newsService->getForCandidateName($fullName, 24, $state !== '' ? $state : null);

        $verified = $news->where('verification_status', 'verified');
        $newsPool = $verified->isNotEmpty() ? $verified : $news;
        $newsItems = $newsPool
            ->sortByDesc('published_at')
            ->take(5)
            ->values()
            ->map(function ($item) {
                return [
                    'headline' => $item->headline,
                    'source_name' => $item->source_name,
                    'source_url' => $item->source_url,
                    'snippet' => $item->snippet,
                    'published_at' => optional($item->published_at)?->toIso8601String(),
                    'provider' => $item->provider,
                    'verification_status' => $item->verification_status,
                ];
            });

        $activeVideo = $this->resolveActiveVideo($politician, $scraped, $newsItems->all());

        return response()->json([
            'candidate' => [
                'full_name' => $fullName !== '' ? $fullName : ($politician?->full_name ?? null),
                'state' => $state !== '' ? $state : ($politician?->state ?? null),
                'office' => $office !== '' ? $office : ($politician?->political_office ?? null),
                'slug' => $slug !== '' ? $slug : ($politician?->slug ?? null),
                'is_platform' => (bool) $politician,
                'has_scraped_record' => (bool) $scraped,
            ],
            'news' => $newsItems,
            'active_video' => $activeVideo,
        ]);
    }

    private function resolvePolitician(string $slug, string $fullName, string $state, string $office): ?Politician
    {
        if ($slug !== '') {
            return Politician::query()
                ->where('slug', $slug)
                ->where('is_active', true)
                ->first();
        }

        if ($fullName === '') {
            return null;
        }

        return Politician::query()
            ->where('is_active', true)
            ->whereRaw('LOWER(full_name) = ?', [strtolower($fullName)])
            ->when($state !== '', fn ($q) => $q->whereRaw("UPPER(COALESCE(state, '')) = ?", [$state]))
            ->when($office !== '', fn ($q) => $q->whereRaw("LOWER(COALESCE(political_office, '')) LIKE ?", ['%' . strtolower($office) . '%']))
            ->first();
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
