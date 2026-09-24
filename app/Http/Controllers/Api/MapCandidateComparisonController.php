<?php

namespace App\Http\Controllers\Api;

use App\Models\CongressCommitteeAssignment;
use App\Models\CongressFloorSpeech;
use App\Models\CongressMemberLegislation;
use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use App\Models\PoliticianDonorSnapshot;
use App\Models\StateElectionDate;
use App\Support\MapCandidateHygiene;
use App\Support\PoliticianDataRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

/** Same-seat comparison using the map's existing public, filtered candidate pool. */
class MapCandidateComparisonController
{
    public function __invoke(Request $request, MapStateCandidatesController $map): JsonResponse
    {
        $input = $request->validate([
            'state' => ['required', Rule::in(PoliticianDataRules::ALLOWED_STATES)],
            'full_name' => 'required_without:district|nullable|string|max:160',
            'id' => 'nullable|integer|min:1',
            'slug' => 'nullable|string|max:255',
            'office' => 'nullable|string|max:180',
            'district' => 'nullable|string|max:40',
            'city' => 'nullable|string|max:100',
        ]);
        $state = $input['state'];
        $anchor = Politician::query()->where('is_active', true)
            ->whereRaw('UPPER(state) = ?', [$state])
            ->when(! empty($input['id']), fn ($q) => $q->whereKey($input['id']), function ($q) use ($input) {
                if (! empty($input['slug'])) {
                    $q->where('slug', $input['slug']);
                } else {
                    $q->whereRaw('LOWER(full_name) = ?', [mb_strtolower($input['full_name'] ?? '')]);
                }
            })->get();
        // Never guess between two profiles for different seats.
        $anchor = $anchor->count() === 1 ? $anchor->first() : null;
        $office = trim(explode('—', $input['office'] ?? $anchor?->political_office ?? (isset($input['district']) ? 'U.S. Representative' : ''))[0]);
        $district = $this->districtKey($input['district'] ?? $anchor?->district, $state);
        $city = trim($input['city'] ?? $anchor?->city ?? '');
        $data = $map(Request::create('/api/v1/map/state-candidates', 'GET', ['state' => $state]))->getData(true);
        $pool = [];
        $label = null;
        $isHouse = false;
        $isSenate = false;

        if (preg_match('/^(?:U\.?S\.?|United States) (?:Representative|House)(?:\b|$)/i', $office)) {
            $isHouse = true;
            $city = '';
            if ($district !== null) {
                foreach ($data['house_candidates'] ?? [] as $key => $rows) {
                    if ($this->districtKey($key, $state) === $district) {
                        $pool = array_merge($pool, $rows);
                    }
                }
                $label = "U.S. House · {$district}";
            }
        } elseif (preg_match('/^(?:U\.?S\.?|United States) Senat(?:e|ors?)\b/i', $office)) {
            $isSenate = true;
            $city = '';
            foreach ($data['offices'] ?? [] as $group) {
                if (($group['office'] ?? '') === 'U.S. Senators') {
                    $pool = $group['candidates'] ?? [];
                }
            }
            $label = "U.S. Senate · {$state}";
        } elseif ($canonical = $this->statewideOffice($office)) {
            $city = '';
            foreach ($data['offices'] ?? [] as $group) {
                if (($group['office'] ?? '') === $canonical) {
                    $pool = $group['candidates'] ?? [];
                }
            }
            $label = "{$canonical} · {$state}";
        } elseif ($city !== '' && (strcasecmp($office, 'Mayor') === 0 || preg_match('/\b(?:seat|ward|district)\s*#?\s*\d+\b/i', $office))) {
            // A generic council/judicial title can cover several seats. Require
            // an explicit seat number, except for the single mayoral office.
            foreach ($data['city_officials'] ?? [] as $cityName => $groups) {
                if (strcasecmp($cityName, $city) !== 0) continue;
                foreach ($groups as $group) {
                    if (strcasecmp($group['office'] ?? '', $office) === 0) {
                        $pool = $group['candidates'] ?? [];
                    }
                }
            }
            $label = "{$office} · {$city}, {$state}";
        }

        if ($label === null) {
            return response()->json([
                'available' => false, 'seat' => null, 'candidates' => [], 'selected_key' => null,
                'message' => 'We cannot confirm this exact seat yet. Comparisons need a district or seat identifier; people in different seats are not grouped together.',
            ]);
        }

        // Dated candidate records for this exact seat fill in a candidacy or party the
        // profile is missing (e.g. an incumbent governor whose re-election run was
        // never flagged on the profile).
        $ballot = $this->ballotRecords($state, $pool, $isHouse, $isSenate, $office, $district, $city);
        $pool = array_map(function (array $c) use ($ballot) {
            $record = $ballot->get(mb_strtolower(trim($c['full_name'] ?? '')));
            if ($record) {
                $c['is_running'] = true;
                $c['party'] = ($c['party'] ?? null) ?: $record->party_affiliation;
            }

            return $c;
        }, $pool);
        if ($isSenate) {
            // A state has two Senate seats, normally one on the ballot. Only people running
            // this cycle are in the race; a sitting senator whose seat is not up is not.
            $pool = array_values(array_filter($pool, fn ($c) => $c['is_running'] ?? false));
        }

        // A state-wide calendar alone does not establish a local or statewide
        // office's election. House districts share regular even-year elections;
        // other offices require a dated candidate record for this exact seat.
        $election = $this->currentElection($state, $isHouse, $pool, $office, $district, $city, $isSenate);
        if ($election === null) {
            return response()->json(['available' => false, 'seat' => ['label' => $label],
                'candidates' => [], 'selected_key' => null,
                'message' => 'No current election is confirmed for this seat.']);
        }

        $pool = array_values(array_filter($pool, fn ($c) => ! in_array($c['status'] ?? '', ['lost', 'eliminated', 'retired', 'former'], true)));
        [$pool] = MapCandidateHygiene::dedupe($pool);
        $selected = collect($pool)->first(fn ($c) => MapCandidateHygiene::identityKey($c['full_name'] ?? '') === MapCandidateHygiene::identityKey(($input['full_name'] ?? '')));
        if (empty($input['full_name'])) $selected = $pool[0] ?? null;
        if (! $selected) {
            return response()->json(['available' => false, 'seat' => ['label' => $label], 'candidates' => [], 'selected_key' => null,
                'message' => 'This profile could not be matched to the current public records for this seat.']);
        }

        $profiles = Politician::query()->where('is_active', true)
            ->whereIn('id', array_filter(array_column($pool, 'id')))
            ->with(['page', 'initiatives' => fn ($q) => $q->published(), 'publicBadges.topic'])->get()->keyBy('id');
        $records = $this->congressRecords($profiles);
        $finance = PoliticianDonorSnapshot::whereIn('politician_id', $profiles->keys())->whereNotNull('enriched_at')
            ->get()->keyBy('politician_id');
        $candidates = collect($pool)->map(function ($candidate) use ($profiles, $records, $finance) {
            $profile = $profiles->get($candidate['id'] ?? null);
            // Read incumbency independently of candidacy; 'active' is not proof
            // that an elected winner has begun their term.
            $status = $profile?->term_status ?? $candidate['status'] ?? null;
            $incumbency = $status === 'seated' ? 'Current officeholder'
                : ($status === 'running' ? 'Challenger' : 'Not recorded');
            return [
                'key' => $this->candidateKey($candidate),
                'full_name' => $candidate['full_name'],
                'party' => $candidate['party'] ?: 'Not recorded',
                'incumbency' => $incumbency,
                'candidacy' => ($candidate['is_running'] ?? false) ? 'Running' : 'Candidacy not confirmed',
                'profile_url' => $profile?->page_published ? $candidate['profile_url'] ?? null : null,
                'source_label' => $candidate['source_label'] ?? 'Public records',
                'updated_at' => $candidate['updated_at'] ?? null,
                'stances' => [...$this->stances($profile), ...($records['speeches'][$profile?->bioguide_id] ?? [])],
                'issue_focus' => $profile ? $profile->publicBadges->map(fn ($b) => $b->topic?->name)->filter()->unique()->sort()->values()->all() : [],
                'finance' => $this->finance($finance->get($profile?->id)),
                'legislation' => $records['legislation'][$profile?->bioguide_id] ?? null,
            ];
        })->sortBy('full_name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();

        return response()->json([
            'available' => true,
            'election' => $election,
            'seat' => ['label' => $label],
            'selected_key' => $this->candidateKey($selected),
            'candidates' => $candidates,
            'message' => count($candidates) < 2 ? 'No other candidates for this seat are recorded yet. This does not mean the race is uncontested.' : null,
        ]);
    }

    private function currentElection(string $state, bool $isHouse, array $pool, string $office, ?string $district, string $city, bool $isSenate = false): ?array
    {
        $running = collect($pool)->filter(fn ($c) => ($c['is_running'] ?? false)
            && ! in_array($c['status'] ?? '', ['lost', 'eliminated', 'retired', 'former'], true));
        if ($running->isEmpty()) return null;
        $today = now()->toDateString();
        $end = now()->addDays(90)->toDateString();
        if ($isHouse || $isSenate) {
            $stage = StateElectionDate::query()->where('state', $state)
                ->whereRaw('LOWER(stage_name) IN (?, ?)', ['primary', 'general'])
                ->whereDate('election_date', '>=', $today)->whereDate('election_date', '<=', $end)
                ->orderBy('election_date')->get()->first(fn ($date) => $date->election_date->year % 2 === 0);
            if ($stage) return ['date' => $stage->election_date->toDateString(), 'stage' => $stage->stage_name];
        }
        // Match both the person and the exact office/location. Names by
        // themselves cannot establish which seat an election date belongs to.
        $record = ElectionCandidateRecord::query()->where('state', $state)
            ->whereIn('full_name', $running->pluck('full_name')->all())
            ->when(! $isSenate, fn ($q) => $q->where('political_office', $office))
            ->when($city !== '', fn ($q) => $q->where('city', $city))
            ->whereDate('election_date', '>=', $today)->whereDate('election_date', '<=', $end)
            ->orderBy('election_date')->get()->first(fn ($r) => $isSenate ? $this->isSenateOffice((string) $r->political_office)
                : (! $isHouse || $this->districtKey($r->district, $state) === $district));
        return $record ? ['date' => $record->election_date->toDateString(), 'stage' => 'Election'] : null;
    }

    /**
     * Upcoming dated candidate records for people in this pool, keyed by lowercase name,
     * kept only when the record's office is this same seat.
     */
    private function ballotRecords(string $state, array $pool, bool $isHouse, bool $isSenate, string $office, ?string $district, string $city): \Illuminate\Support\Collection
    {
        $names = array_values(array_filter(array_column($pool, 'full_name')));
        if ($names === []) return collect();
        $canonical = $this->statewideOffice($office);

        return ElectionCandidateRecord::query()->where('state', $state)->whereIn('full_name', $names)
            ->whereDate('election_date', '>=', now()->toDateString())
            ->orderBy('election_date')->get()
            ->filter(fn ($r) => match (true) {
                $isSenate => $this->isSenateOffice((string) $r->political_office),
                $isHouse => $this->districtKey($r->district, $state) === $district,
                $canonical !== null => $this->statewideOffice((string) $r->political_office) === $canonical,
                default => strcasecmp((string) $r->political_office, $office) === 0 && strcasecmp((string) $r->city, $city) === 0,
            })
            ->unique(fn ($r) => mb_strtolower(trim($r->full_name)))
            ->keyBy(fn ($r) => mb_strtolower(trim($r->full_name)));
    }

    private function isSenateOffice(string $office): bool
    {
        return (bool) preg_match('/^(?:U\.?S\.?|United States) Senat(?:e|ors?)\b/i', trim($office));
    }

    /** @return array{cycle: mixed, receipts: ?string, disbursements: ?string, cash_on_hand: ?string, coverage_end_date: ?string, source_url: string}|null */
    private function finance(?PoliticianDonorSnapshot $snapshot): ?array
    {
        $summary = $snapshot?->fec_summary;
        if (empty($summary['receipts']) && empty($summary['disbursements']) && empty($summary['cash_on_hand'])) return null;

        return [
            'cycle' => $summary['cycle'] ?? $snapshot->election_cycle,
            'receipts' => $summary['receipts'] ?? null,
            'disbursements' => $summary['disbursements'] ?? null,
            'cash_on_hand' => $summary['cash_on_hand'] ?? null,
            'coverage_end_date' => $summary['coverage_end_date'] ?? null,
            'source_url' => $snapshot->fec_source_url ?: 'https://www.fec.gov/data/',
        ];
    }

    /**
     * Bill and committee record plus floor-speech positions for sitting members of
     * Congress, keyed by Bioguide ID.
     *
     * @return array{legislation: array<string, array>, speeches: array<string, list<array>>}
     */
    private function congressRecords(\Illuminate\Support\Collection $profiles): array
    {
        $bioguides = $profiles->pluck('bioguide_id')->filter()->unique()->values();
        if ($bioguides->isEmpty()) return ['legislation' => [], 'speeches' => []];

        $committees = CongressCommitteeAssignment::whereIn('bioguide_id', $bioguides)->whereNull('parent_code')
            ->orderByRaw('title IS NULL')->orderBy('name')->get()->groupBy('bioguide_id');
        $legislation = [];
        foreach (CongressMemberLegislation::whereIn('bioguide_id', $bioguides)->get() as $row) {
            $legislation[$row->bioguide_id] = [
                'sponsored' => $row->sponsored_total, 'cosponsored' => $row->cosponsored_total,
                'since_congress' => $row->since_congress,
            ];
        }
        foreach ($committees as $bioguide => $seats) {
            $legislation[$bioguide] = ($legislation[$bioguide] ?? []) + ['sponsored' => null, 'cosponsored' => null, 'since_congress' => null];
            $legislation[$bioguide]['committees'] = $seats->map(fn ($s) => $s->title ? "{$s->name} ({$s->title})" : $s->name)->all();
        }

        // Latest stated position per issue: Claude-read speeches with a summary only,
        // so a keyword-tagged title never stands in for a position.
        $speeches = [];
        CongressFloorSpeech::whereIn('bioguide_id', $bioguides)->whereNotNull('topic_key')->whereNotNull('position_summary')
            ->with('topic')->orderByDesc('spoken_on')->limit(200)->get()
            ->groupBy('bioguide_id')
            ->each(function ($rows, $bioguide) use (&$speeches) {
                $speeches[$bioguide] = $rows->unique('topic_key')->filter(fn ($s) => $s->topic)->take(6)->map(fn ($s) => [
                    'topic' => $s->topic->name,
                    'text' => $s->position_summary,
                    'quote' => $s->quote,
                    'source_label' => $s->kind === 'written' ? 'Congressional Record statement' : 'Congressional Record floor speech',
                    'source_url' => $s->source_url,
                    'updated_at' => $s->spoken_on?->toDateString(),
                ])->values()->all();
            });

        return ['legislation' => $legislation, 'speeches' => $speeches];
    }

    private function candidateKey(array $candidate): string
    {
        return ! empty($candidate['id']) ? 'profile:'.$candidate['id']
            : 'record:'.hash('sha256', ($candidate['scrape_source'] ?? '').'|'.($candidate['external_candidate_id'] ?? '').'|'.$candidate['full_name']);
    }

    private function statewideOffice(string $office): ?string
    {
        // Exact aliases only: "City Treasurer" is not the state treasurer.
        return match (mb_strtolower($office)) {
            'governor' => 'Governor',
            'lieutenant governor', 'lt. governor', 'lt governor' => 'Lieutenant Governor',
            'attorney general' => 'Attorney General',
            'state treasurer', 'treasurer' => 'State Treasurer',
            'state controller', 'controller', 'comptroller' => 'State Controller',
            'secretary of state' => 'Secretary of State',
            default => null,
        };
    }

    private function districtKey(?string $district, string $state): ?string
    {
        $district = strtoupper(trim((string) $district));
        if (preg_match('/^(?:([A-Z]{2})-)?(\d{1,2}|AL)$/', $district, $match)) {
            if ($match[1] !== '' && $match[1] !== $state) return null;
            $number = $match[2] === 'AL' ? 0 : (int) $match[2];
            return $state.'-'.($number === 0 ? 'AL' : str_pad((string) $number, 2, '0', STR_PAD_LEFT));
        }
        return null;
    }

    private function stances(?Politician $profile): array
    {
        if (! $profile) return [];
        $stances = [];
        if ($profile->page_published && ($profile->page?->show_initiatives ?? true) && $profile->slug) {
            foreach ($profile->initiatives as $initiative) {
                $text = trim(strip_tags((string) $initiative->description));
                if ($text === '') continue;
                $stances[] = ['topic' => trim($initiative->title), 'text' => $text,
                    'source_label' => 'Published profile position', 'source_url' => url('/p/'.$profile->slug),
                    'updated_at' => $initiative->updated_at?->toIso8601String()];
            }
        }
        // Reuse available provider data without triggering slow network calls
        // or writing provider IDs on this read-only comparison endpoint.
        if ($profile->show_votesmart_data && $profile->votesmart_id) {
            $cached = Cache::get('votesmart.politician.'.$profile->id, []);
            foreach (($cached['issue_positions'] ?? []) as $position) {
                if (! is_string($position['issue'] ?? null) || ! is_string($position['position'] ?? null)) continue;
                $text = trim(strip_tags($position['position']));
                if ($text === '') continue;
                $stances[] = ['topic' => trim($position['issue']), 'text' => $text,
                    'source_label' => 'Vote Smart',
                    'source_url' => 'https://justfacts.votesmart.org/candidate/political-courage-test/'.rawurlencode($profile->votesmart_id),
                    'updated_at' => null];
            }
        }
        return $stances;
    }
}
