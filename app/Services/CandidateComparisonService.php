<?php

namespace App\Services;

use App\Http\Controllers\Api\MapStateCandidatesController;

use App\Models\CongressCommitteeAssignment;
use App\Models\CongressFloorSpeech;
use App\Models\CongressMemberLegislation;
use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use App\Models\PoliticianDonorSnapshot;
use App\Models\StateElectionDate;
use App\Support\MapCandidateHygiene;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/** Public same-seat comparison assembly. */
class CandidateComparisonService
{
    public function compare(array $input, MapStateCandidatesController $map): array
    {
        $state = $input['state'];
        $research = ($input['context'] ?? null) === 'research';
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
        if ($anchor->count() > 1) {
            return ['available' => false, 'seat' => null, 'candidates' => [], 'selected_key' => null, 'message' => 'This name matches multiple profiles. Select a specific candidate.'];
        }
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
            // The current map pool is state-wide, without Senate class/seat identifiers.
            // Keep its existing election behavior, but never broaden this into year-round seat research.
            if ($research) {
                return ['available' => false, 'seat' => null, 'candidates' => [], 'selected_key' => null,
                    'message' => 'This state has two Senate seats. We cannot confirm the exact Senate seat for this research comparison yet.'];
            }
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
            return [
                'available' => false, 'seat' => null, 'candidates' => [], 'selected_key' => null,
                'message' => 'We cannot confirm this exact seat yet. Comparisons need a district or seat identifier; people in different seats are not grouped together.',
            ];
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
        $role = \App\Support\OfficeGlossary::forPlace($office, $state, $city);
        $seat = ['label' => $label, 'state' => $state, 'office' => $office, 'district' => $district, 'city' => $city,
            'role' => $role ? $role + ['glossary_url' => url('/compare/glossary').'#'.$role['slug']] : null];
        $election = $this->currentElection($state, $isHouse, $pool, $office, $district, $city, $isSenate, $research);
        if ($election === null && ! $research) {
            return ['available' => false, 'seat' => $seat,
                'candidates' => [], 'selected_key' => null,
                'message' => 'No current election is confirmed for this seat.'];
        }

        $pool = array_values(array_filter($pool, fn ($c) => ! in_array($c['status'] ?? '', ['lost', 'eliminated', 'retired', 'former'], true)));
        [$pool] = MapCandidateHygiene::dedupe($pool);
        $selected = collect($pool)->first(fn ($c) => MapCandidateHygiene::identityKey($c['full_name'] ?? '') === MapCandidateHygiene::identityKey(($input['full_name'] ?? '')));
        if (empty($input['full_name']) && empty($input['id'])) $selected = $pool[0] ?? null;
        if (! $selected && ! $research) {
            return ['available' => false, 'seat' => $seat, 'candidates' => [], 'selected_key' => null,
                'message' => 'This profile could not be matched to the current public records for this seat.'];
        }

        $profiles = Politician::query()->where('is_active', true)
            ->whereIn('id', array_filter(array_column($pool, 'id')))
            ->with(['page', 'initiatives' => fn ($q) => $q->published(), 'publicBadges.topic'])->get()->keyBy('id');
        $records = $this->congressRecords($profiles);
        $news = $this->recentNews($profiles->keys()->all());
        $finance = PoliticianDonorSnapshot::whereIn('politician_id', $profiles->keys())->whereNotNull('enriched_at')
            ->get()->keyBy('politician_id');
        $candidates = collect($pool)->map(function ($candidate) use ($profiles, $records, $finance, $news) {
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
                'news' => $news[$profile?->id] ?? ['coverage' => [], 'press_releases' => []],
            ];
        })->sortBy('full_name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();

        return [
            'available' => true,
            'election' => $election,
            'seat' => $seat,
            'selected_key' => $selected ? $this->candidateKey($selected) : null,
            'candidates' => $candidates,
            'message' => count($candidates) < 2 ? 'No other candidates for this seat are recorded yet. This does not mean the race is uncontested.' : null,
        ];
    }

    /**
     * Seats in a state with at least one confirmed running candidate, so voters
     * can pick a race instead of guessing names or districts. Uses the same
     * public pool, exact-seat rules, and exclusions as compare(); Senate seats
     * are left out until the data identifies which of the two seats is up.
     *
     * @return list<array{label: string, office: string, district: ?string, city: ?string, election: ?array, running: list<array>, params: array<string, string>}>
     */
    public function races(string $state, MapStateCandidatesController $map): array
    {
        $data = $map(Request::create('/api/v1/map/state-candidates', 'GET', ['state' => $state]))->getData(true);
        $upcoming = ElectionCandidateRecord::query()->where('state', $state)
            ->whereDate('election_date', '>=', now()->toDateString())->orderBy('election_date')->get();
        $houseDate = StateElectionDate::query()->where('state', $state)
            ->whereRaw('LOWER(stage_name) IN (?, ?)', ['primary', 'general'])
            ->whereDate('election_date', '>=', now()->toDateString())
            ->orderBy('election_date')->get()->first(fn ($date) => $date->election_date->year % 2 === 0);

        $seats = [];
        // The map can key one district several ways (CA-03, CA-3); merge them as compare() does.
        foreach ($data['house_candidates'] ?? [] as $key => $rows) {
            if ($district = $this->districtKey($key, $state)) {
                $seats[$district] ??= ['label' => "U.S. House · {$district}", 'office' => 'U.S. Representative', 'district' => $district, 'city' => '', 'house' => true, 'pool' => []];
                $seats[$district]['pool'] = array_merge($seats[$district]['pool'], $rows);
            }
        }
        $seats = array_values($seats);
        foreach ($data['offices'] ?? [] as $group) {
            if ($canonical = $this->statewideOffice((string) ($group['office'] ?? ''))) {
                $seats[] = ['label' => "{$canonical} · {$state}", 'office' => $canonical, 'district' => null, 'city' => '', 'house' => false, 'pool' => $group['candidates'] ?? []];
            }
        }
        foreach ($data['city_officials'] ?? [] as $cityName => $groups) {
            foreach ($groups as $group) {
                $office = (string) ($group['office'] ?? '');
                if (strcasecmp($office, 'Mayor') === 0 || preg_match('/\b(?:seat|ward|district)\s*#?\s*\d+\b/i', $office)) {
                    $seats[] = ['label' => "{$office} · {$cityName}, {$state}", 'office' => $office, 'district' => null, 'city' => $cityName, 'house' => false, 'pool' => $group['candidates'] ?? []];
                }
            }
        }

        $races = [];
        foreach ($seats as $seat) {
            $ballot = $this->ballotRecords($state, $seat['pool'], $seat['house'], false, $seat['office'], $seat['district'], $seat['city'], $upcoming);
            $pool = array_map(function (array $c) use ($ballot) {
                if ($record = $ballot->get(mb_strtolower(trim($c['full_name'] ?? '')))) {
                    $c['is_running'] = true;
                    $c['party'] = ($c['party'] ?? null) ?: $record->party_affiliation;
                }
                return $c;
            }, $seat['pool']);
            $pool = array_values(array_filter($pool, fn ($c) => ! in_array($c['status'] ?? '', ['lost', 'eliminated', 'retired', 'former'], true)));
            [$pool] = MapCandidateHygiene::dedupe($pool);
            $running = array_values(array_filter($pool, fn ($c) => $c['is_running'] ?? false));
            if ($running === []) continue;
            usort($running, fn ($a, $b) => strnatcasecmp($a['full_name'] ?? '', $b['full_name'] ?? ''));

            $recordDate = $ballot->min(fn ($r) => $r->election_date?->toDateString());
            $election = $recordDate ? ['date' => $recordDate, 'stage' => 'Election']
                : ($seat['house'] && $houseDate ? ['date' => $houseDate->election_date->toDateString(), 'stage' => $houseDate->stage_name] : null);
            $params = array_filter(['state' => $state, 'office' => $seat['office'], 'district' => $seat['district'], 'city' => $seat['city']]);
            // Statewide and local seats are resolved from a named person on the seat.
            if (! $seat['district']) $params['full_name'] = $running[0]['full_name'];
            $params['selected'] = implode(',', array_map(fn ($c) => $this->candidateKey($c), array_slice($running, 0, 3)));

            $races[] = [
                'label' => $seat['label'], 'office' => $seat['office'], 'district' => $seat['district'], 'city' => $seat['city'] ?: null,
                'election' => $election,
                'running' => array_map(fn ($c) => ['key' => $this->candidateKey($c), 'full_name' => $c['full_name'], 'party' => ($c['party'] ?? null) ?: null], $running),
                'params' => $params,
            ];
        }
        usort($races, fn ($a, $b) => [($a['election']['date'] ?? '9999'), $a['label']] <=> [($b['election']['date'] ?? '9999'), $b['label']]);

        return $races;
    }

    private function currentElection(string $state, bool $isHouse, array $pool, string $office, ?string $district, string $city, bool $isSenate = false, bool $research = false): ?array
    {
        $running = collect($pool)->filter(fn ($c) => ($c['is_running'] ?? false)
            && ! in_array($c['status'] ?? '', ['lost', 'eliminated', 'retired', 'former'], true));
        if ($running->isEmpty()) return null;
        $today = now()->toDateString();
        $end = $research ? '9999-12-31' : now()->addDays(90)->toDateString();
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
    private function ballotRecords(string $state, array $pool, bool $isHouse, bool $isSenate, string $office, ?string $district, string $city, ?\Illuminate\Support\Collection $upcoming = null): \Illuminate\Support\Collection
    {
        $names = array_values(array_filter(array_column($pool, 'full_name')));
        if ($names === []) return collect();
        $canonical = $this->statewideOffice($office);
        $upcoming ??= ElectionCandidateRecord::query()->where('state', $state)->whereIn('full_name', $names)
            ->whereDate('election_date', '>=', now()->toDateString())
            ->orderBy('election_date')->get();

        return $upcoming->whereIn('full_name', $names)
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

    /**
     * Latest verified articles per profile, split into independent coverage and the
     * candidate's own press releases. Rejected articles (failed the relevance gate)
     * are never shown. Tone is deliberately not judged; people without a profile get
     * none, since matching articles by name alone could attach them to the wrong person.
     *
     * @param  list<int>  $politicianIds
     * @return array<int, array{coverage: list<array>, press_releases: list<array>}>
     */
    private function recentNews(array $politicianIds, int $perGroup = 3): array
    {
        if ($politicianIds === []) return [];
        $news = [];
        // One bounded query per person, so a heavily covered candidate cannot crowd out the others.
        collect($politicianIds)->mapWithKeys(fn ($id) => [$id => \App\Models\CandidateNewsArticle::query()->verified()
            ->where('politician_id', $id)->whereIn('content_type', ['news', 'press_release'])->whereNotNull('published_at')
            ->where('published_at', '>=', now()->subYear())->orderByDesc('published_at')->limit(40)
            ->get(['headline', 'source_name', 'source_url', 'published_at', 'content_type'])])
            ->filter->isNotEmpty()
            ->each(function ($articles, $id) use (&$news, $perGroup) {
                $groups = ['coverage' => [], 'press_releases' => []];
                $seen = [];
                foreach ($articles as $a) {
                    $source = trim((string) $a->source_name);
                    // Feeds often append " - Publisher" to the headline; the publisher is shown separately.
                    $headline = trim(html_entity_decode(strip_tags((string) $a->headline), ENT_QUOTES | ENT_HTML5));
                    if ($source !== '' && str_ends_with(mb_strtolower($headline), mb_strtolower(' - '.$source))) {
                        $headline = trim(mb_substr($headline, 0, -mb_strlen(' - '.$source)));
                    }
                    // Tag and archive listing pages ("Jane Doe Archives") are not articles.
                    if ($headline === '' || preg_match('/\b(?:archives?|tag|topics?)$/i', $headline)) continue;
                    // A reprinted press release is the candidate's own words, not independent coverage.
                    $group = $a->content_type === 'press_release' || preg_match('/^press release\s*[:\-–—]/iu', $headline) ? 'press_releases' : 'coverage';
                    // The same release is often posted at several URLs.
                    $dedupe = mb_strtolower(preg_replace('/^press release\s*[:\-–—]\s*/iu', '', $headline));
                    if (isset($seen[$dedupe]) || count($groups[$group]) >= $perGroup) continue;
                    $seen[$dedupe] = true;
                    $groups[$group][] = ['headline' => $headline, 'source_name' => $source ?: null, 'source_url' => $a->source_url,
                        'published_at' => $a->published_at->toDateString()];
                }
                $news[$id] = $groups;
            });

        return $news;
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
        if (preg_match('/^(?:((?!CD)[A-Z]{2})-)?(?:DISTRICT\s+|CD[- ]?)?(\d{1,2}|AL|AT[- ]LARGE)$/', $district, $match)) {
            if ($match[1] !== '' && $match[1] !== $state) return null;
            $number = is_numeric($match[2]) ? (int) $match[2] : 0;
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
