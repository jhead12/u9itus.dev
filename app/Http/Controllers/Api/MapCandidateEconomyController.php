<?php

namespace App\Http\Controllers\Api;

use App\Models\Organization;
use App\Models\Politician;
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

    /** Max independent-spending rows surfaced in the drawer's narrow layout. */
    private const MAX_OUTSIDE_SPENDING = 8;

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
                    'endorsements' => [],
                ];
            }

            // Detected from news coverage, independent of donor-snapshot data — load
            // it before the snapshot gate below so it's still present even when the
            // candidate has no donor snapshot yet.
            $endorsements = $this->formatEndorsements($politician);

            $snapshot = PoliticianDonorSnapshot::where('politician_id', $politician->id)->first();

            if (! $snapshot || ! $snapshot->enriched_at || (! $snapshot->hasAnyData() && ! $snapshot->hasPacAffiliations())) {
                return [
                    'candidate' => $candidate,
                    'has_data' => false,
                    'reason' => 'not_yet_enriched',
                    'endorsements' => $endorsements,
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
                'outside_spending' => $this->formatOutsideSpending($snapshot->outside_spending ?? []),
                'pac_affiliations' => Organization::badgesForPacAffiliations($snapshot->pac_affiliations ?? []),
                'endorsements' => $endorsements,
                'sources' => [
                    'opensecrets_url' => $snapshot->opensecrets_source_url,
                    'fec_url' => $snapshot->fec_source_url,
                ],
            ];
        });

        return response()->json($data);
    }

    /**
     * News-detected endorsements (e.g. "Governor Endorsed") — distinct from
     * the donor-inferred pac_affiliations badges above.
     *
     * @return array<int, array{group: string, label: string, badge_text: string, endorser_name: ?string, source_url: ?string}>
     */
    private function formatEndorsements(Politician $politician): array
    {
        return $politician->endorsements()->active()->get()->map(fn ($e) => [
            'group' => $e->group_key,
            'label' => $e->label,
            'badge_text' => $e->label . ' Endorsed',
            'endorser_name' => $e->endorser_name,
            'source_url' => $e->source_url,
        ])->all();
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

    /**
     * @param  array<int, array{committee_id?: string, committee_name?: string, total?: mixed, support_oppose?: string}>  $items
     * @return array{items: array<int, array{committee_id: ?string, committee_name: ?string, total: float, support_oppose: string}>, hidden_count: int}
     */
    private function formatOutsideSpending(array $items): array
    {
        if (empty($items)) {
            return ['items' => [], 'hidden_count' => 0];
        }

        $shown = array_slice($items, 0, self::MAX_OUTSIDE_SPENDING);
        $hidden = max(0, count($items) - count($shown));

        $rows = [];
        foreach ($shown as $row) {
            $committeeId = $row['committee_id'] ?? null;
            $committeeName = $row['committee_name'] ?? null;

            // Snapshots written before committee_id became its own field only
            // stored committee_name, which itself held the raw FEC ID whenever
            // resolveCommitteeNames() couldn't find a real name. Recover the ID
            // from committee_name in that case so old snapshots still link out.
            if (empty($committeeId) && $committeeName && preg_match('/^[A-Z]\d{8}$/', $committeeName)) {
                $committeeId = $committeeName;
                $committeeName = null;
            }

            $rows[] = [
                'committee_id' => $committeeId ?: null,
                'committee_name' => $committeeName ?: null,
                'total' => (float) ($row['total'] ?? 0),
                'support_oppose' => ($row['support_oppose'] ?? 'S') === 'O' ? 'O' : 'S',
            ];
        }

        return ['items' => $rows, 'hidden_count' => $hidden];
    }
}
