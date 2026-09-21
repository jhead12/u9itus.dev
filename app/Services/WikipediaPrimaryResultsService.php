<?php

namespace App\Services;

use App\Models\StateElectionDate;
use App\Support\MapCandidateHygiene;
use App\Support\OfficeCanonicalizer;
use App\Support\PoliticianDataRules;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reads a race's primary outcome from its Wikipedia election article
 * (e.g. "2026 California gubernatorial election") instead of scraping the
 * candidate's own page.
 *
 * Wikipedia's election articles list each primary's field under standard
 * headings — "Nominee", "Advanced to general election", "Eliminated in
 * primary" — with one bullet per candidate. That structure is far more
 * reliable than keyword-matching a biography, and the MediaWiki API is not
 * behind the WAF that blocks Ballotpedia from GitHub Actions runners.
 *
 * Only an explicit heading decides a result. A "Withdrawn" heading means the
 * candidate left the race, which is reported even before the primary is held.
 * Declined and disqualified entries are ignored, and a name under both an
 * advanced and an eliminated/withdrawn heading is treated as unresolved.
 */
class WikipediaPrimaryResultsService
{
    private const API = 'https://en.wikipedia.org/w/api.php';

    private const USER_AGENT = 'U9itus-civic-enrichment/1.0 (+https://u9itus.dev/about)';

    private const ADVANCED_HEADING = '/^(nominees?|presumptive nominees?|advanced to (the )?general( election)?|qualified for (the )?general( election)?|general election candidates?|winners?)$/i';

    private const DECLARED_HEADING = '/^(declared( candidates)?|candidates)$/i';

    private const ELIMINATED_HEADING = '/^(eliminated|lost|defeated|failed to advance|did not advance)\b/i';

    private const WITHDRAWN_HEADING = '/^(withdrawn|withdrew|withdrawn candidates|withdrawn before the primary)$/i';

    /** @var array<string, array{advanced: string[], eliminated: string[], withdrawn: string[], roster: array<string, string[]>}|null> */
    private array $raceCache = [];

    /** @var array<string, string|null> */
    private array $wikitextCache = [];

    /**
     * @return 'advanced_to_general'|'eliminated'|'withdrawn'|null
     */
    public function resultFor(string $name, string $state, string $office, ?string $district, int $year): ?string
    {
        $pending = $this->primaryStillPending($state, $year);

        try {
            foreach ($this->candidateTitles($state, $office, $year) as $title) {
                $race = $this->race($title, $office, $district);
                if ($race === null) {
                    continue;
                }

                $advanced = $this->contains($race['advanced'], $name);
                $eliminated = $this->contains($race['eliminated'], $name);
                $withdrawn = $this->contains($race['withdrawn'], $name);

                // Ballot-outcome headings cannot describe a primary that has not been held.
                if ($pending) {
                    $advanced = $eliminated = false;
                }

                if ($withdrawn && ! $advanced && ! $eliminated) {
                    return 'withdrawn';
                }
                if ($advanced !== $eliminated && ! $withdrawn) {
                    return $advanced ? 'advanced_to_general' : 'eliminated';
                }
            }
        } catch (\Throwable $e) {
            Log::warning('WikipediaPrimaryResultsService: lookup failed', ['name' => $name, 'state' => $state, 'error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * The whole field on the race's article, for auditing what we list against it.
     *
     * @return array{title: string, pending: bool, advanced: string[], eliminated: string[], withdrawn: string[], declared: string[]}|null
     */
    public function roster(string $state, string $office, int $year, ?string $district = null): ?array
    {
        try {
            foreach ($this->candidateTitles($state, $office, $year) as $title) {
                $race = $this->race($title, $office, $district);
                if ($race !== null) {
                    return ['title' => $title, 'pending' => $this->primaryStillPending($state, $year)] + $race['roster'];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('WikipediaPrimaryResultsService: roster lookup failed', ['state' => $state, 'error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Whether a name is anywhere on a race's article (advanced, eliminated, withdrawn or declared).
     * A name carrying stray headline words ("Rob Sand Record-Breaking") still counts when a run of
     * two or three of its words is a listed person; that person is returned as `decorated`.
     *
     * @param  array{advanced: string[], eliminated: string[], withdrawn: string[], declared: string[]}  $roster
     * @return array{listed: bool, decorated: ?string}
     */
    public function findInRoster(string $name, array $roster): array
    {
        $all = array_merge($roster['advanced'], $roster['eliminated'], $roster['withdrawn'], $roster['declared']);

        if ($this->contains($all, $name)) {
            return ['listed' => true, 'decorated' => null];
        }

        $words = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ([3, 2] as $length) {
            for ($i = 0; $i + $length <= count($words); $i++) {
                $run = implode(' ', array_slice($words, $i, $length));
                foreach ($all as $listed) {
                    if ($this->contains([$listed], $run)) {
                        return ['listed' => false, 'decorated' => $listed];
                    }
                }
            }
        }

        return ['listed' => false, 'decorated' => null];
    }

    /**
     * A future primary date means an "Eliminated"/"Nominee" heading on the
     * article cannot describe this cycle's primary yet.
     */
    private function primaryStillPending(string $state, int $year): bool
    {
        $dates = StateElectionDate::query()
            ->where('state', strtoupper($state))
            ->where('election_year', $year)
            ->whereRaw('LOWER(stage_name) LIKE ?', ['%primary%'])
            ->whereNotNull('election_date')
            ->pluck('election_date');

        return $dates->isNotEmpty() && $dates->every(fn ($d) => $d->isFuture());
    }

    /**
     * @return string[]
     */
    private function candidateTitles(string $state, string $office, int $year): array
    {
        $stateName = $this->stateName($state);
        if ($stateName === null) {
            return [];
        }

        $lower = strtolower($office);
        $statewide = OfficeCanonicalizer::canonicaliseStatewide($office);

        if ($statewide === 'Governor') {
            return ["{$year} {$stateName} gubernatorial election"];
        }
        if ($statewide === 'Lieutenant Governor') {
            return ["{$year} {$stateName} lieutenant gubernatorial election"];
        }
        if ($statewide !== null) {
            $label = match ($statewide) {
                'State Treasurer' => ['Treasurer', 'State Treasurer'],
                'State Controller' => ['Controller', 'Comptroller', 'State Controller'],
                default => [$statewide],
            };

            return array_map(fn ($l) => "{$year} {$stateName} {$l} election", $label);
        }
        if (str_contains($lower, 'senator') || str_contains($lower, 'senate')) {
            return ["{$year} United States Senate election in {$stateName}", "{$year} United States Senate special election in {$stateName}"];
        }
        if (str_contains($lower, 'representative') || str_contains($lower, 'congress')) {
            return ["{$year} United States House of Representatives election in {$stateName}", "{$year} United States House of Representatives elections in {$stateName}"];
        }

        return [];
    }

    private function stateName(string $state): ?string
    {
        $code = strtoupper(trim($state));
        $name = array_search($code, PoliticianDataRules::stateNameToCode(), true);
        if ($name === false) {
            return null;
        }

        return str_replace(' Of ', ' of ', ucwords(strtolower($name)));
    }

    /**
     * @return array{advanced: string[], eliminated: string[], withdrawn: string[], roster: array<string, string[]>}|null
     */
    private function race(string $title, string $office, ?string $district): ?array
    {
        $key = $title.'|'.($district ?? '');
        if (array_key_exists($key, $this->raceCache)) {
            return $this->raceCache[$key];
        }

        $wikitext = $this->fetchWikitext($title);
        if ($wikitext !== null && $this->isHouseOffice($office)) {
            $wikitext = $this->houseSection($wikitext, $district);
        }

        return $this->raceCache[$key] = $wikitext === null ? null : $this->parse($wikitext);
    }

    private function isHouseOffice(string $office): bool
    {
        $lower = strtolower($office);

        return str_contains($lower, 'representative') || str_contains($lower, 'congress');
    }

    private function fetchWikitext(string $title): ?string
    {
        if (! array_key_exists($title, $this->wikitextCache)) {
            $this->wikitextCache[$title] = $this->requestWikitext($title);
        }

        return $this->wikitextCache[$title];
    }

    private function requestWikitext(string $title): ?string
    {
        $response = Http::timeout(15)
            ->withHeaders(['User-Agent' => self::USER_AGENT])
            ->get(self::API, [
                'action' => 'parse',
                'page' => $title,
                'prop' => 'wikitext',
                'redirects' => 1,
                'format' => 'json',
                'formatversion' => '2',
            ]);

        if (! $response->ok()) {
            return null;
        }

        $text = $response->json('parse.wikitext');

        return is_string($text) && $text !== '' ? $text : null;
    }

    /**
     * The part of a state's House article that describes one district. Big states keep the
     * districts in sub-articles ("… elections in California (districts 1–26)") that the main
     * article only links with {{main|…}}, so those are followed when the main one has no district
     * section of its own.
     */
    private function houseSection(string $wikitext, ?string $district): ?string
    {
        $section = $this->districtSection($wikitext, $district);
        if ($section !== null) {
            return $section;
        }

        preg_match_all('/\{\{\s*main\s*\|\s*([^|}]*districts?[^|}]*?)\s*(?:\|[^}]*)?\}\}/i', $wikitext, $matches);
        $parts = [];
        foreach (array_unique($matches[1]) as $subTitle) {
            $sub = $this->fetchWikitext(trim($subTitle));
            if ($sub === null) {
                continue;
            }
            $found = $this->districtSection($sub, $district);
            if ($found === null) {
                continue;
            }
            $parts[] = $found;
            if ($district !== null) {
                break;
            }
        }

        return $parts === [] ? null : implode("\n", $parts);
    }

    /**
     * Narrow a state's House article to one district's section. An at-large
     * seat (no district, or 0) matches "At-large". A record with no district in a
     * state that has several returns every district's section together, so the
     * candidate is found wherever they ran (a name is only ever in one race).
     */
    private function districtSection(string $wikitext, ?string $district): ?string
    {
        $number = $district !== null ? (int) preg_replace('/\D/', '', $district) : 0;
        $pattern = $number > 0
            ? '/^district\s+0*'.$number.'\b/i'
            : '/^(at[- ]large|district\s+0*1\b)/i';

        $sections = $this->sections($wikitext);
        if ($number === 0 && ! $this->hasAtLargeSection($sections)) {
            $districtCount = count(array_filter($sections, fn ($s) => preg_match('/^district\s+\d+/i', $s['title'])));
            if ($districtCount > 1) {
                return $wikitext;
            }
        }
        foreach ($sections as $i => $section) {
            if (! preg_match($pattern, $section['title'])) {
                continue;
            }

            $out = [$section['body']];
            for ($j = $i + 1; $j < count($sections) && $sections[$j]['level'] > $section['level']; $j++) {
                $out[] = str_repeat('=', $sections[$j]['level']).$sections[$j]['title'].str_repeat('=', $sections[$j]['level'])."\n".$sections[$j]['body'];
            }

            // Re-wrap the district heading itself so parse() sees the same shape.
            return str_repeat('=', $section['level']).$section['title'].str_repeat('=', $section['level'])."\n".implode("\n", $out);
        }

        return null;
    }

    /** @param array<int, array{title: string, level: int, body: string}> $sections */
    private function hasAtLargeSection(array $sections): bool
    {
        foreach ($sections as $section) {
            if (preg_match('/^at[- ]large/i', $section['title'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{advanced: string[], eliminated: string[], withdrawn: string[], roster: array<string, string[]>}|null
     */
    private function parse(string $wikitext): ?array
    {
        $advanced = [];
        $eliminated = [];
        $withdrawn = [];
        $roster = ['advanced' => [], 'eliminated' => [], 'withdrawn' => [], 'declared' => []];

        foreach ($this->sections($wikitext) as $section) {
            if (preg_match(self::DECLARED_HEADING, $section['title'])) {
                foreach ($this->bulletNames($section['body']) as $entry) {
                    $roster['declared'][] = $entry['display'];
                }

                continue;
            }

            $isAdvanced = (bool) preg_match(self::ADVANCED_HEADING, $section['title']);
            $isWithdrawn = ! $isAdvanced && preg_match(self::WITHDRAWN_HEADING, $section['title']);
            $isEliminated = ! $isAdvanced && ! $isWithdrawn && preg_match(self::ELIMINATED_HEADING, $section['title']);
            if (! $isAdvanced && ! $isEliminated && ! $isWithdrawn) {
                continue;
            }

            // Only the section's own bullets: child headings such as
            // "Endorsements" list people who are not candidates.
            foreach ($this->bulletNames($section['body']) as $entry) {
                if ($isAdvanced) {
                    $advanced = array_merge($advanced, $entry['variants']);
                    $roster['advanced'][] = $entry['display'];
                } elseif ($isWithdrawn) {
                    $withdrawn = array_merge($withdrawn, $entry['variants']);
                    $roster['withdrawn'][] = $entry['display'];
                } else {
                    $eliminated = array_merge($eliminated, $entry['variants']);
                    $roster['eliminated'][] = $entry['display'];
                }
            }
        }

        if ($advanced === [] && $eliminated === [] && $withdrawn === [] && $roster['declared'] === []) {
            return null;
        }

        return ['advanced' => $advanced, 'eliminated' => $eliminated, 'withdrawn' => $withdrawn, 'roster' => $roster];
    }

    /**
     * Split wikitext into sections: each heading with the text up to the next heading of any level.
     *
     * @return array<int, array{level: int, title: string, body: string}>
     */
    private function sections(string $wikitext): array
    {
        $sections = [];
        $current = null;

        foreach (preg_split('/\R/', $wikitext) ?: [] as $line) {
            if (preg_match('/^(={2,6})\s*(.+?)\s*\1\s*$/', $line, $m)) {
                if ($current !== null) {
                    $sections[] = $current;
                }
                $title = trim(preg_replace('/\[\[(?:[^|\]]*\|)?([^\]]*)\]\]|<[^>]+>|\'{2,}/', '$1', $m[2]) ?? $m[2]);
                $current = ['level' => strlen($m[1]), 'title' => $title, 'body' => ''];

                continue;
            }
            if ($current !== null) {
                $current['body'] .= $line."\n";
            }
        }
        if ($current !== null) {
            $sections[] = $current;
        }

        return $sections;
    }

    /**
     * The candidate is the text before the first comma on each bullet — what follows
     * ("state senator", "former [[Assembly Majority Leader]]") describes them. Most are
     * wikilinked; the unlinked ones ("* Tom Woodard, retired CEO") are read as plain text.
     *
     * @return array<int, array{display: string, variants: string[]}> `variants` are what a name is matched against
     */
    private function bulletNames(string $body): array
    {
        $entries = [];
        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            if (! preg_match('/^\*+\s*(.*)$/', $line, $m)) {
                continue;
            }

            $head = preg_split('/,|<ref|\{\{|\s\(|\s[–—-]\s/u', $m[1], 2)[0] ?? '';

            if (preg_match('/\[\[([^\]|#]+)(?:\|([^\]]*))?\]\]/', $head, $link) && ! preg_match('/^(file|image|category|wikipedia):/i', $link[1])) {
                $label = trim($link[2] ?? '');
                $entries[] = [
                    'display' => $label !== '' ? $label : trim(preg_replace('/\s*\(.*?\)\s*$/', '', $link[1]) ?? $link[1]),
                    'variants' => $label !== '' ? [$link[1], $label] : [$link[1]],
                ];

                continue;
            }

            $plain = trim(preg_replace('/<[^>]+>|\'{2,}|\[\[|\]\]/', '', $head) ?? '');
            // A running mate is listed under their ticket, not as a candidate of their own.
            if ($plain !== '' && ! preg_match('/^running mate\\b/i', $plain)) {
                $entries[] = ['display' => $plain, 'variants' => [$plain]];
            }
        }

        return $entries;
    }

    /**
     * @param string[] $names
     */
    public function contains(array $names, string $candidate): bool
    {
        $wanted = $this->tokens($candidate);
        if (count($wanted) < 2) {
            return false;
        }

        foreach ($names as $name) {
            $tokens = $this->tokens($name);
            if (count($tokens) >= 2 && end($tokens) === end($wanted) && $this->sameGivenName(reset($tokens), reset($wanted))) {
                return true;
            }
        }

        return false;
    }

    /** "Barb" and "Barbara", "Jen" and "Jennifer", "Steve" and "Steven": one is the start of the other, or a known nickname. */
    private function sameGivenName(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        [$short, $long] = strlen($a) <= strlen($b) ? [$a, $b] : [$b, $a];
        if (strlen($short) >= 3 && str_starts_with($long, $short)) {
            return true;
        }

        return MapCandidateHygiene::identityKey("{$a} Same") === MapCandidateHygiene::identityKey("{$b} Same");
    }

    /**
     * @return string[]
     */
    private function tokens(string $name): array
    {
        $name = preg_replace('/\(.*?\)|"[^"]*"|“[^”]*”/u', ' ', $name) ?? $name;
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $name = strtolower($ascii !== false ? $ascii : $name);
        $name = preg_replace('/[^a-z\s-]/', ' ', $name) ?? $name;
        $tokens = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter($tokens, fn ($t) => ! in_array($t, ['jr', 'sr', 'ii', 'iii', 'iv'], true) && strlen($t) > 1));
    }
}
