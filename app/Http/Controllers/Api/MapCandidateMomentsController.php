<?php

namespace App\Http\Controllers\Api;

use App\Services\PoliticianResolver;
use App\Services\ViralMomentEnricherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MapCandidateMomentsController
{
    /** Clips surfaced in the drawer's Videos tab. */
    private const LIMIT = 5;

    public function __invoke(Request $request, PoliticianResolver $resolver, ViralMomentEnricherService $moments): JsonResponse
    {
        $slug = trim((string) $request->query('slug', ''));
        $fullName = trim((string) $request->query('full_name', ''));
        $state = strtoupper(trim((string) $request->query('state', '')));
        $office = trim((string) $request->query('office', ''));

        if ($slug === '' && $fullName === '') {
            return response()->json([
                'error' => 'Provide slug or full_name.',
            ], 422);
        }

        $cacheKey = 'map_candidate_moments:' . sha1(implode('|', [
            $slug, strtolower($fullName), $state, strtolower($office),
        ]));

        // Moments only refresh on the 6h enrichment schedule, so a longer TTL
        // than the 15-min news endpoint is safe and cuts DB load.
        $data = Cache::remember($cacheKey, 1800, function () use (
            $slug, $fullName, $state, $office, $resolver, $moments
        ) {
            $politician = $resolver->resolve($slug, $fullName, $state, $office);

            $candidate = [
                'full_name' => $fullName !== '' ? $fullName : ($politician?->full_name ?? null),
                'slug' => $slug !== '' ? $slug : ($politician?->slug ?? null),
            ];

            if (! $politician) {
                return [
                    'candidate' => $candidate,
                    'has_data' => false,
                    'reason' => 'not_on_platform',
                    'moments' => [],
                ];
            }

            $display = $moments->getDisplayData($politician, self::LIMIT);

            if (! $display) {
                return [
                    'candidate' => $candidate,
                    'has_data' => false,
                    'reason' => 'not_yet_enriched',
                    'moments' => [],
                ];
            }

            return [
                'candidate' => $candidate,
                'has_data' => true,
                'reason' => null,
                'featured' => $display['featured'],
                'moments' => $display['moments'],
                'source' => $display['source'],
                'enriched_at' => $display['enriched_at'],
            ];
        });

        return response()->json($data);
    }
}
