<?php

namespace App\Services;

use App\Models\Politician;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Sets politicians.bioguide_id for sitting U.S. senators and representatives by matching
 * the congress-legislators "current" dataset on name + state (+ chamber/district), which
 * is what ties a profile to its roll-call votes.
 */
class CongressMemberLinker
{
    private const USER_AGENT = 'U9itus-civic-enrichment/1.0 (+https://u9itus.dev/about)';

    /**
     * @return array{linked: int, already: int, unmatched: array<int, string>}
     */
    public function link(bool $dryRun = false): array
    {
        $members = $this->currentMembers();
        $stats = ['linked' => 0, 'already' => 0, 'unmatched' => []];

        $politicians = Politician::query()
            ->where(fn ($q) => $q->where('governance_level', 'Federal')->orWhereIn('political_office', self::OFFICES))
            ->get(['id', 'full_name', 'state', 'political_office', 'district', 'bioguide_id']);

        foreach ($members as $member) {
            $matches = $politicians->filter(fn (Politician $p) => $this->matches($p, $member));

            if ($matches->isEmpty()) {
                $stats['unmatched'][] = "{$member['name']} ({$member['state']}".($member['district'] !== null ? '-'.$member['district'] : '').')';

                continue;
            }

            foreach ($matches as $politician) {
                if ($politician->bioguide_id === $member['bioguide']) {
                    $stats['already']++;

                    continue;
                }

                if (! $dryRun) {
                    Politician::query()->whereKey($politician->id)->update(['bioguide_id' => $member['bioguide']]);
                }
                $stats['linked']++;
            }
        }

        return $stats;
    }

    private const OFFICES = [
        'U.S. Representative', 'U.S. Senator', 'United States Representative', 'United States Senator',
    ];

    /** @param array{name: string, first: string, last: string, state: string, chamber: string, district: ?int, bioguide: string} $member */
    protected function matches(Politician $politician, array $member): bool
    {
        if (strtoupper((string) $politician->state) !== $member['state']) {
            return false;
        }

        $office = strtolower((string) $politician->political_office);
        $isSenator = str_contains($office, 'senator');
        $isRep = str_contains($office, 'representative');
        if (($member['chamber'] === 'sen' && $isRep) || ($member['chamber'] === 'rep' && $isSenator)) {
            return false;
        }

        $theirs = $this->words($politician->full_name);
        $full = $this->words($member['name']);
        $short = $this->words($member['first'].' '.$member['last']);

        if ($theirs === $full || $theirs === $short) {
            return true;
        }

        // Nicknames ("Bernie" / "Bernard"): the same surname in the same seat still pins the person down.
        $surname = fn (array $words) => $words === [] ? '' : $words[count($words) - 1];
        if ($surname($theirs) === '' || $surname($theirs) !== $surname($this->words($member['last']))) {
            return false;
        }

        if ($member['chamber'] === 'rep') {
            return $member['district'] !== null
                && preg_match('/(\d+|AL)$/i', (string) $politician->district, $m) === 1
                && (int) $m[1] === $member['district'];
        }

        return $isSenator;
    }

    /** @return array<int, string> Lower-case ASCII name words without punctuation or suffixes. */
    protected function words(string $name): array
    {
        $clean = strtolower(Str::ascii($name));
        $clean = preg_replace('/[^a-z\s]/', ' ', $clean) ?? '';

        return array_values(array_filter(
            preg_split('/\s+/', $clean) ?: [],
            fn (string $w) => $w !== '' && ! in_array($w, ['jr', 'sr', 'ii', 'iii', 'iv'], true),
        ));
    }

    /** @return array<int, array{name: string, first: string, last: string, state: string, chamber: string, district: ?int, bioguide: string}> */
    protected function currentMembers(): array
    {
        $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
            ->timeout(30)->retry(2, 500, throw: false)->get(CongressVoteImporter::LEGISLATORS_URL);

        if (! $response->successful()) {
            return [];
        }

        $members = [];
        foreach ((array) $response->json() as $row) {
            $bioguide = $row['id']['bioguide'] ?? null;
            $term = end($row['terms']) ?: [];
            $chamber = $term['type'] ?? null;
            if (! $bioguide || ! in_array($chamber, ['sen', 'rep'], true)) {
                continue;
            }

            $first = (string) ($row['name']['first'] ?? '');
            $last = (string) ($row['name']['last'] ?? '');

            $members[] = [
                'name' => (string) ($row['name']['official_full'] ?? trim($first.' '.$last)),
                'first' => $first,
                'last' => $last,
                'state' => strtoupper((string) ($term['state'] ?? '')),
                'chamber' => $chamber,
                'district' => isset($term['district']) ? (int) $term['district'] : null,
                'bioguide' => $bioguide,
            ];
        }

        return $members;
    }
}
