<?php

namespace App\Http\Controllers\Api;

use App\Models\Organization;
use App\Models\PoliticianDonorSnapshot;
use App\Services\PoliticianResolver;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MapCandidateEconomyController
{
    /** Max top contributors surfaced in the drawer's narrow layout. */
    private const MAX_CONTRIBUTORS = 5;

    public function __invoke(Request $request, PoliticianResolver $resolver): JsonResponse
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

        $cacheKey = 'map_candidate_economy:' . sha1(implode('|', [
            $slug, strtolower($fullName), $state, strtolower($office),
        ]));

        // Donor snapshots only change on the ~48h nightly enrichment cron, so
        // a longer TTL than the 15-min news endpoint is safe and cuts DB load.
        $data = Cache::remember($cacheKey, 3600, function () use (
            $slug, $fullName, $state, $office, $resolver
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
                ];
            }

            $snapshot = PoliticianDonorSnapshot::where('politician_id', $politician->id)->first();

            if (! $snapshot || ! $snapshot->enriched_at || (! $snapshot->hasAnyData() && ! $snapshot->hasPacAffiliations())) {
                return [
                    'candidate' => $candidate,
                    'has_data' => false,
                    'reason' => 'not_yet_enriched',
                ];
            }

            return [
                'candidate' => $candidate,
                'has_data' => true,
                'reason' => null,
                'election_cycle' => $snapshot->election_cycle,
                'enriched_at' => optional($snapshot->enriched_at)->toIso8601String(),
                'top_industries' => $this->industriesWithPct($snapshot->top_industries ?? []),
                'top_contributors' => $this->formatContributors($snapshot->top_contributors ?? []),
                'fec_summary' => $snapshot->fec_summary,
                'pac_affiliations' => Organization::badgesForPacAffiliations($snapshot->pac_affiliations ?? []),
                'sources' => [
                    'opensecrets_url' => $snapshot->opensecrets_source_url,
                    'fec_url' => $snapshot->fec_source_url,
                ],
            ];
        });

        return response()->json($data);
    }

    /**
     * @param  array<int, array{name?: string, total?: mixed}>  $industries
     * @return array<int, array{name: string, total_display: string, total_numeric: float, pct: int}>
     */
    private function industriesWithPct(array $industries): array
    {
        if (empty($industries)) {
            return [];
        }

        $parsed = [];
        $max = 0.0;
        foreach ($industries as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $totalDisplay = (string) ($row['total'] ?? '');
            $totalNumeric = Money::parseLoose($totalDisplay);
            $max = max($max, $totalNumeric);
            $parsed[] = ['name' => $name, 'total_display' => $totalDisplay, 'total_numeric' => $totalNumeric];
        }

        return array_map(function ($row) use ($max) {
            $row['pct'] = $max > 0 ? (int) round($row['total_numeric'] / $max * 100) : 0;

            return $row;
        }, $parsed);
    }

    /**
     * @param  array<int, array{name?: string, total?: mixed}>  $contributors
     * @return array<int, array{name: string, total_display: string}>
     */
    private function formatContributors(array $contributors): array
    {
        $rows = [];
        foreach (array_slice($contributors, 0, self::MAX_CONTRIBUTORS) as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $rows[] = ['name' => $name, 'total_display' => (string) ($row['total'] ?? '')];
        }

        return $rows;
    }
}
