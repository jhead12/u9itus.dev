<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\BackfillStateElectionData;
use App\Models\BallotMeasure;
use App\Models\CandidateLead;
use App\Models\CandidateNewsArticle;
use App\Models\Committee;
use App\Models\ElectionDataBackfill;
use App\Models\Politician;
use App\Models\StateElectionDate;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * WebMCP tool backend — https://github.com/webmachinelearning/webmcp
 *
 * The public JSON endpoints here are what the browser-side WebMCP tools
 * (resources/js/webmcp/index.js) call from `execute()`. They are deliberately
 * read-mostly, unauthenticated, and rate-limited: an AI agent browsing
 * u9itus.dev uses them to answer civic questions ("who is running in my
 * district", "compare these two candidates") and to feed the candidate-lead
 * pipeline. All heavy lifting reuses existing models.
 *
 * Routes: see routes/api.php → prefix `v1/mcp`.
 */
class WebMcpController extends Controller
{
    /** Hard cap on any list endpoint. */
    private const MAX_LIMIT = 20;

    /**
     * GET /api/v1/mcp/candidates
     *
     * Typeahead-style search over published candidate/official profiles.
     * Query params: q, state, governance_level, party, running (bool),
     * funded_by (donor/PAC/industry text), limit, offset.
     */
    public function candidates(Request $request): JsonResponse
    {
        // Query-string booleans arrive as "true"/"false"/"1"/"0" — Laravel's
        // `boolean` rule rejects the strings "true"/"false", so normalise first.
        if ($request->has('running')) {
            $request->merge([
                'running' => filter_var($request->input('running'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }

        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'size:2'],
            'governance_level' => ['nullable', 'string', 'max:32'],
            'party' => ['nullable', 'string', 'max:64'],
            'running' => ['nullable', 'boolean'],
            'funded_by' => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);

        $limit = (int) ($data['limit'] ?? 10);
        $offset = (int) ($data['offset'] ?? 0);
        $q = trim($data['q'] ?? '');
        $fundingNeedles = $this->fundingNeedles(trim($data['funded_by'] ?? ''));

        $base = Politician::query()
            ->where('page_published', true)
            ->where('is_active', true)
            ->whereNotNull('slug')
            ->when($q !== '', fn ($query) => $query->where('full_name', 'like', '%'.$q.'%'))
            ->when(! empty($data['state']), fn ($query) => $query->where('state', strtoupper($data['state'])))
            ->when(! empty($data['governance_level']), fn ($query) => $query->where('governance_level', $data['governance_level']))
            ->when(! empty($data['party']), fn ($query) => $query->where('party_affiliation', 'like', '%'.$data['party'].'%'))
            ->when(array_key_exists('running', $data) && $data['running'] !== null,
                fn ($query) => $query->where('is_running_candidate', (bool) $data['running']))
            ->when($fundingNeedles !== [], fn ($query) => $query
                ->whereHas('donorSnapshot', fn ($ds) => $ds->where(function ($w) use ($fundingNeedles) {
                    foreach ($fundingNeedles as $needle) {
                        $like = '%'.$needle.'%';
                        $w->orWhereRaw('lower(top_contributors) like ?', [$like])
                            ->orWhereRaw('lower(top_industries) like ?', [$like])
                            ->orWhereRaw('lower(outside_spending) like ?', [$like])
                            ->orWhereRaw('lower(pac_affiliations) like ?', [$like]);
                    }
                })));

        $total = (clone $base)->count();

        $results = $base
            ->when($fundingNeedles !== [], fn ($query) => $query->with('donorSnapshot'))
            ->when($q !== '', fn ($query) => $query
                ->orderByRaw('CASE WHEN LOWER(full_name) LIKE ? THEN 0 ELSE 1 END', [mb_strtolower($q).'%']))
            ->orderByDesc('verified_official')
            ->orderBy('full_name')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(function (Politician $p) use ($fundingNeedles) {
                $summary = $this->candidateSummary($p);
                if ($fundingNeedles !== []) {
                    $summary['funding_match'] = $this->fundingMatch($p, $fundingNeedles);
                }

                return $summary;
            })
            ->values();

        $returned = $results->count();
        $nextOffset = ($offset + $returned) < $total ? $offset + $returned : null;

        return response()->json([
            'query' => $data,
            'count' => $returned,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => $nextOffset !== null,
            'next_offset' => $nextOffset,
            'results' => $results,
        ]);
    }

    /**
     * GET /api/v1/mcp/candidates/{politician:uuid}
     *
     * Full civic dossier for one candidate: identity, office, transparency
     * IDs, links, recent verified news, donor snapshot, and upcoming
     * election dates for their state.
     */
    public function candidate(Politician $politician): JsonResponse
    {
        abort_unless($politician->page_published && $politician->is_active, 404);

        return response()->json($this->candidateDossier($politician));
    }

    /**
     * GET /api/v1/mcp/candidates/compare?uuids=a,b,c
     *
     * Side-by-side dossiers for 2–4 candidates so an agent can narrate a
     * structured comparison without N round-trips.
     */
    public function compare(Request $request): JsonResponse
    {
        $uuids = collect(explode(',', (string) $request->query('uuids', '')))
            ->map(fn ($u) => trim($u))
            ->filter()
            ->unique()
            ->take(4)
            ->values();

        abort_if($uuids->count() < 2, 422, 'Provide 2–4 candidate uuids in ?uuids=a,b,c');

        $candidates = Politician::query()
            ->whereIn('uuid', $uuids)
            ->where('page_published', true)
            ->where('is_active', true)
            ->get()
            ->map(fn (Politician $p) => $this->candidateDossier($p))
            ->values();

        return response()->json([
            'requested' => $uuids,
            'count' => $candidates->count(),
            'candidates' => $candidates,
        ]);
    }

    /**
     * GET /api/v1/mcp/ballot-measures
     *
     * Ballot measures (state + county scoped) — params: state, q, status, limit.
     */
    public function ballotMeasures(Request $request): JsonResponse
    {
        $data = $request->validate([
            'state' => ['nullable', 'string', 'size:2'],
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'in:upcoming,passed,failed'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
        ]);

        $measures = BallotMeasure::query()
            ->when(! empty($data['state']), fn ($q) => $q->where('state', strtoupper($data['state'])))
            ->when(! empty($data['status']), fn ($q) => $q->where('status', $data['status']))
            ->when(! empty($data['q']), fn ($q) => $q->where(function ($q) use ($data) {
                $q->where('title', 'like', '%'.$data['q'].'%')
                    ->orWhere('measure_number', 'like', '%'.$data['q'].'%');
            }))
            ->orderBy('election_date')
            ->limit((int) ($data['limit'] ?? self::MAX_LIMIT))
            ->get()
            ->map(fn (BallotMeasure $m) => [
                'state' => $m->state,
                'county' => $m->county,
                'measure_number' => $m->measure_number,
                'title' => $m->title,
                'summary' => $m->summary,
                'yes_meaning' => $m->yes_meaning,
                'no_meaning' => $m->no_meaning,
                'election_date' => optional($m->election_date)?->toDateString(),
                'status' => $m->status,
                'source' => $m->source,
                'source_url' => $m->source_url,
                'read_more' => $this->ballotMeasureReadMore($m),
            ])
            ->values();

        $payload = [
            'query' => $data,
            'count' => $measures->count(),
            'results' => $measures,
        ];

        // Nothing on file for a specific state — kick the on-demand backfill and
        // tell the caller to check back.
        if ($measures->isEmpty() && ! empty($data['state'])) {
            $payload['backfill'] = $this->backfillStatusFor(strtoupper($data['state']));
        }

        return response()->json($payload);
    }

    /**
     * The canonical "go read the actual measure" link for a ballot-measure row.
     *
     * `summary` / `yes_meaning` / `no_meaning` are deliberately plain-language;
     * operational detail (eligibility thresholds, dollar amounts, funding
     * source, repayment terms) lives at the source. Hand the agent a labelled
     * link it can cite verbatim, or null when we have nothing on file.
     */
    private function ballotMeasureReadMore(BallotMeasure $m): ?array
    {
        if (! $m->source_url) {
            return null;
        }

        $labels = [
            'ballotpedia' => 'Full text & fiscal analysis on Ballotpedia',
            'wikipedia' => 'Background on Wikipedia',
            'manual' => 'Official measure text',
        ];

        return [
            'label' => $labels[strtolower((string) $m->source)] ?? 'Full measure text & analysis',
            'url' => $m->source_url,
        ];
    }

    /**
     * POST /api/v1/mcp/ballot-measures/watch
     *
     * Register an email to be notified once a state's ballot measures are
     * available. Also nudges the backfill if it isn't already running.
     */
    public function watchBallotMeasures(Request $request): JsonResponse
    {
        $data = $request->validate([
            'state' => ['required', 'string', 'size:2'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $state = strtoupper($data['state']);

        if (BallotMeasure::where('state', $state)->exists()) {
            return response()->json([
                'status' => 'already_available',
                'message' => "Ballot measures for {$state} are available now.",
            ]);
        }

        $row = ElectionDataBackfill::firstOrNew(['state' => $state]);
        $row->status ??= ElectionDataBackfill::STATUS_QUEUED;
        $row->addWatcher($data['email']);
        $row->save();

        if ($row->isReattemptable() && Cache::add("civic:backfill:{$state}", true, now()->addMinutes(30))) {
            BackfillStateElectionData::dispatch($state);
        }

        return response()->json([
            'status' => 'watching',
            'message' => "We'll email you once {$state} ballot measures are published.",
        ], 202);
    }

    /**
     * Debounced dispatch of the single-state backfill + a caller-facing status.
     *
     * @return array{status: string, message: string}
     */
    private function backfillStatusFor(string $state): array
    {
        $row = ElectionDataBackfill::firstWhere('state', $state);

        if ($row && $row->status === ElectionDataBackfill::STATUS_RUNNING) {
            return [
                'status' => 'in_progress',
                'message' => "We're gathering {$state} election data now — check back in a few minutes.",
            ];
        }

        if ($row && $row->status === ElectionDataBackfill::STATUS_UNAVAILABLE && ! $row->isReattemptable()) {
            return [
                'status' => 'unavailable',
                'message' => "Ballot measures for {$state} aren't published by our sources yet — they usually appear about a month before the election. Ask to be notified when they're available.",
            ];
        }

        if (Cache::add("civic:backfill:{$state}", true, now()->addMinutes(30))) {
            ElectionDataBackfill::updateOrCreate(
                ['state' => $state],
                ['status' => ElectionDataBackfill::STATUS_QUEUED],
            );
            BackfillStateElectionData::dispatch($state);

            return [
                'status' => 'queued',
                'message' => "No measures on file for {$state} yet — we're checking official sources now. Check back in a few minutes.",
            ];
        }

        return [
            'status' => 'in_progress',
            'message' => "Already checking {$state} — check back shortly.",
        ];
    }

    /**
     * GET /api/v1/mcp/elections?state=CA
     *
     * Upcoming election stages (primary / general / runoff) with filing
     * deadlines for a state.
     */
    public function elections(Request $request): JsonResponse
    {
        $state = strtoupper(trim((string) $request->query('state', '')));

        abort_if(strlen($state) !== 2, 422, 'Provide a 2-letter ?state= code.');

        return response()->json([
            'state' => $state,
            'elections' => StateElectionDate::upcomingForState($state),
        ]);
    }

    /**
     * POST /api/v1/mcp/candidate-leads
     *
     * Submit a candidate an agent has spotted (e.g. in a news article the
     * user is reading) into the human-reviewed lead pipeline. This never
     * publishes anything — leads land as `pending` for the existing
     * verify-leads workflow to confirm.
     */
    public function submitLead(Request $request): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:255'],
            'state' => ['nullable', 'string', 'size:2'],
            'office_hint' => ['nullable', 'string', 'max:128'],
            'source_url' => ['required', 'url', 'max:2048'],
            'context' => ['nullable', 'string', 'max:2000'],
        ]);

        $sourceHash = hash('sha256', strtolower(trim($data['source_url'])));

        $existing = CandidateLead::query()
            ->where('source_key', 'webmcp')
            ->where('source_hash', $sourceHash)
            ->first();

        if ($existing) {
            return response()->json([
                'status' => 'duplicate',
                'id' => $existing->id,
                'lead_status' => $existing->status,
                'message' => 'This source was already submitted.',
            ], 200);
        }

        try {
            $lead = CandidateLead::create([
                'source_key' => 'webmcp',
                'full_name' => trim($data['full_name']),
                'state' => ! empty($data['state']) ? strtoupper($data['state']) : null,
                'office_hint' => $data['office_hint'] ?? null,
                'source_url' => $data['source_url'],
                'discovery_context' => $data['context'] ?? null,
                'discovered_at' => now(),
                'status' => CandidateLead::STATUS_PENDING,
            ]);
        } catch (QueryException $e) {
            // Unique (source_key, source_hash) race — treat as duplicate.
            return response()->json([
                'status' => 'duplicate',
                'message' => 'This source was already submitted.',
            ], 200);
        }

        return response()->json([
            'status' => 'received',
            'id' => $lead->id,
            'lead_status' => $lead->status,
            'message' => 'Lead queued for human verification. Nothing is published automatically.',
        ], 201);
    }

    /* ----------------------------------------------------------------------
     | Shape helpers
     * -------------------------------------------------------------------- */

    private function candidateSummary(Politician $p): array
    {
        return [
            'uuid' => $p->uuid,
            'full_name' => $p->full_name,
            'office' => $p->political_office,
            'party' => $p->party_affiliation,
            'state' => $p->state,
            'city' => $p->city,
            'district' => $p->district,
            'governance_level' => $p->governance_level,
            'is_running' => (bool) $p->is_running_candidate,
            'term_status' => $p->term_status,
            'verified_official' => (bool) $p->verified_official,
            'photo' => $this->absoluteUrl($p->profile_photo_url),
            'profile_url' => $p->slug ? url('/p/'.$p->slug) : null,
            'bio_excerpt' => $p->bio ? Str::limit($p->bio, 200) : null,
        ];
    }

    /**
     * Expand a free-text `funded_by` term into the lower-case substrings to
     * match against a donor snapshot's JSON columns. A term that names a known
     * advocacy group in config/pac_affiliations.php also pulls in that group's
     * contributor-name patterns (e.g. "AIPAC" → norpac, pro-israel america …)
     * and the group key itself, so the filter catches money routed through any
     * aligned PAC, not just an exact name.
     *
     * @return list<string>
     */
    private function fundingNeedles(string $term): array
    {
        $term = mb_strtolower(trim($term));
        if ($term === '') {
            return [];
        }

        $needles = [$term];

        foreach ((array) config('pac_affiliations.groups', []) as $key => $group) {
            $key = mb_strtolower((string) $key);
            $label = mb_strtolower((string) ($group['label'] ?? ''));

            if (str_contains($key, $term) || str_contains($label, $term) || str_contains($term, $key)) {
                $needles[] = $key;
                foreach ((array) ($group['patterns'] ?? []) as $pattern) {
                    $needles[] = mb_strtolower((string) $pattern);
                }
            }
        }

        return array_values(array_unique(array_filter($needles, fn ($n) => $n !== '')));
    }

    /**
     * The concrete donor-snapshot rows a `funded_by` filter matched on, so an
     * agent can cite named contributors / PACs and dollar amounts rather than
     * just asserting the link. Relies on `donorSnapshot` being eager-loaded.
     *
     * @param  list<string>  $needles
     * @return array<int, array<string, mixed>>
     */
    private function fundingMatch(Politician $p, array $needles): array
    {
        $snap = $p->donorSnapshot;
        if (! $snap) {
            return [];
        }

        $hit = function (mixed $value) use ($needles): bool {
            if (! is_string($value) || $value === '') {
                return false;
            }
            $haystack = mb_strtolower($value);
            foreach ($needles as $needle) {
                if (str_contains($haystack, $needle)) {
                    return true;
                }
            }

            return false;
        };

        $amount = fn ($row) => isset($row['total']) && is_numeric($row['total']) ? (float) $row['total'] : null;
        $out = [];

        foreach ((array) $snap->pac_affiliations as $row) {
            if ($hit($row['label'] ?? null) || $hit($row['matched_name'] ?? null) || $hit($row['group'] ?? null)) {
                $out[] = [
                    'kind' => 'pac_affiliation',
                    'name' => $row['matched_name'] ?? ($row['label'] ?? 'PAC'),
                    'group' => $row['label'] ?? ($row['group'] ?? null),
                    'amount' => $amount($row),
                ];
            }
        }

        foreach ((array) $snap->outside_spending as $row) {
            if ($hit($row['committee_name'] ?? null)) {
                $out[] = [
                    'kind' => 'outside_spending',
                    'name' => $row['committee_name'] ?? 'Committee',
                    'support_oppose' => $row['support_oppose'] ?? null,
                    'amount' => $amount($row),
                ];
            }
        }

        foreach ((array) $snap->top_contributors as $row) {
            if ($hit($row['name'] ?? null)) {
                $out[] = [
                    'kind' => 'contributor',
                    'name' => $row['name'] ?? 'Contributor',
                    'amount' => $amount($row),
                ];
            }
        }

        return $out;
    }

    /**
     * Swap raw FEC committee IDs for human names in an outside-spending list
     * using the local `committees` registry (App\Services\FECService seeds it
     * as a side effect of nightly enrichment, so a name resolved once for any
     * candidate is reused everywhere). Read-time only — no external calls. Each
     * row keeps a `committee` sub-object with the id, whether the name is a
     * real resolution, and any hand-curated organisation link.
     *
     * @param  array<int, array<string, mixed>>  $spending
     * @return array<int, array<string, mixed>>
     */
    private function hydrateCommitteeNames(array $spending): array
    {
        $ids = collect($spending)
            ->pluck('committee_id')
            ->filter(fn ($id) => is_string($id) && $id !== '')
            ->unique()
            ->values();

        $registry = $ids->isEmpty()
            ? collect()
            : Committee::query()
                ->whereIn('fec_committee_id', $ids)
                ->with('organization:id,name')
                ->get()
                ->keyBy('fec_committee_id');

        return collect($spending)->map(function ($row) use ($registry) {
            $id = is_string($row['committee_id'] ?? null) ? $row['committee_id'] : null;
            $stored = $row['committee_name'] ?? null;
            $storedIsRawId = ! is_string($stored)
                || $stored === ''
                || $stored === $id
                || preg_match('/^C\d{8}$/', $stored) === 1;

            $committee = $id ? $registry->get($id) : null;
            $resolvedName = $storedIsRawId ? $committee?->name : $stored;

            $row['committee_name'] = $resolvedName ?? $stored ?? $id ?? 'Unknown spender';
            $row['committee'] = [
                'fec_committee_id' => $id,
                'name_resolved' => $resolvedName !== null,
                'organization' => $committee?->organization?->name,
            ];

            return $row;
        })->all();
    }

    private function candidateDossier(Politician $p): array
    {
        $news = CandidateNewsArticle::query()
            ->forCandidate($p->id, $p->full_name)
            ->where('verification_status', 'verified')
            ->orderByDesc('published_at')
            ->limit(5)
            ->get()
            ->map(fn (CandidateNewsArticle $a) => [
                'headline' => $a->headline,
                'source_name' => $a->source_name,
                'source_url' => $a->source_url,
                'snippet' => $a->snippet,
                'published_at' => optional($a->published_at)?->toIso8601String(),
                'topic' => $a->topic_key,
            ])
            ->values();

        $donor = $p->donorSnapshot?->toArray();
        if (is_array($donor)) {
            $donor = collect($donor)->except(['id', 'politician_id', 'created_at', 'updated_at'])->all();

            if (! empty($donor['outside_spending']) && is_array($donor['outside_spending'])) {
                $donor['outside_spending'] = $this->hydrateCommitteeNames($donor['outside_spending']);
            }
        }

        return array_merge($this->candidateSummary($p), [
            'bio' => $p->bio,
            'website_url' => $p->website_url,
            'wikipedia_url' => $p->wikipedia_url,
            'ballotpedia_url' => $p->ballotpedia_id ? 'https://ballotpedia.org/'.$p->ballotpedia_id : null,
            'transparency_ids' => array_filter([
                'fec_candidate_id' => $p->fec_candidate_id,
                'opensecrets_id' => $p->opensecrets_id,
                'votesmart_id' => $p->votesmart_id,
                'ballotpedia_id' => $p->ballotpedia_id,
            ]),
            'social_links' => $p->social_links,
            'video_links' => $p->video_links,
            'office_profile' => $p->officeProfile?->toVoterPayload(),
            'upcoming_elections' => $p->state ? StateElectionDate::upcomingForState($p->state) : [],
            'recent_news' => $news,
            'donor_snapshot' => $donor,
        ]);
    }

    private function absoluteUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        return str_starts_with($path, 'http') ? $path : url($path);
    }
}
