<?php

namespace App\Http\Controllers\Api;

use App\Models\CandidateNewsArticle;
use App\Models\Politician;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Running candidates who have made the news in the last 24 hours, nationwide.
 *
 * Powers the "In the news" toggle on the map's Running Candidates panel: a
 * bounded, time-relevant slice of the thousands of declared candidates, so a
 * national list stays useful without pagination. Each card carries its most
 * recent qualifying headline.
 *
 * GET /api/v1/map/candidates-in-news
 */
class MapCandidatesInNewsController
{
    private const WINDOW_HOURS = 24;

    public function __invoke(Request $request): JsonResponse
    {
        $data = Cache::remember('map_candidates_in_news_v1', 300, function () {
            $since = now()->subHours(self::WINDOW_HOURS);

            // Newest qualifying article per politician within the window.
            $articles = CandidateNewsArticle::query()
                ->whereNotNull('politician_id')
                ->where('published_at', '>=', $since)
                ->orderByDesc('published_at')
                ->get(['politician_id', 'headline', 'source_name', 'source_url', 'published_at']);

            $latestByPol = [];
            foreach ($articles as $a) {
                $latestByPol[$a->politician_id] ??= $a;
            }

            if (! $latestByPol) {
                return ['window_hours' => self::WINDOW_HOURS, 'total' => 0, 'candidates' => []];
            }

            $pols = Politician::query()
                ->whereIn('id', array_keys($latestByPol))
                ->where('is_active', true)
                ->where('is_running_candidate', true)
                ->where(fn ($q) => $q->where('term_status', '!=', 'lost')->orWhereNull('term_status'))
                ->get(['id', 'uuid', 'full_name', 'party_affiliation', 'profile_photo_url',
                       'slug', 'political_office', 'governance_level', 'state', 'district',
                       'verified_official', 'ballotpedia_id', 'website_url', 'bio']);

            $cards = $pols->map(function (Politician $p) use ($latestByPol) {
                $a = $latestByPol[$p->id];
                $gl = strtolower((string) $p->governance_level);
                $office = strtolower((string) $p->political_office);

                $tier = (str_contains($office, 'senat') || str_contains($office, 'representative') || $gl === 'federal')
                    ? 'Federal'
                    : ($gl === 'state' ? 'Statewide' : 'Local');

                return [
                    'source'          => 'platform',
                    'uuid'            => $p->uuid,
                    'full_name'       => $p->full_name,
                    'party'           => $p->party_affiliation,
                    'photo'           => $p->profile_photo_url
                        ? (str_starts_with($p->profile_photo_url, 'http') ? $p->profile_photo_url : url($p->profile_photo_url))
                        : null,
                    'slug'            => $p->slug,
                    'status'          => 'running',
                    'is_running'      => true,
                    'verified'        => (bool) $p->verified_official,
                    'ballotpedia_url' => $p->ballotpedia_id ? 'https://ballotpedia.org/' . $p->ballotpedia_id : null,
                    'website'         => $p->website_url,
                    'profile_url'     => $p->slug ? url('/p/' . $p->slug) : null,
                    'bio_excerpt'     => $p->bio ? Str::limit($p->bio, 180) : null,
                    'badges'          => [],
                    'office'          => $p->political_office,
                    'state'           => $p->state ? strtoupper($p->state) : null,
                    'district'        => $p->district,
                    '_tier'           => $tier,
                    'news'            => [
                        'headline'     => $a->headline,
                        'source_name'  => $a->source_name,
                        'source_url'   => $a->source_url,
                        'published_at' => optional($a->published_at)->toIso8601String(),
                    ],
                ];
            })
            ->sortBy([['state', 'asc'], ['full_name', 'asc']])
            ->values()
            ->all();

            return [
                'window_hours' => self::WINDOW_HOURS,
                'total'        => count($cards),
                'candidates'   => $cards,
            ];
        });

        return response()->json($data);
    }
}
