<?php

namespace App\Services\CandidateDiscovery;

use App\Models\CandidateRoster;
use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use App\Support\MapCandidateHygiene;
use App\Support\OfficeCanonicalizer;

/**
 * Is this news-discovered candidate a real person on a real ballot, or a headline?
 *
 * News discovery extracts capitalised runs from headlines, so it produces "Hochul
 * Agenda" and "Greg Abbott" in North Carolina as readily as real challengers. A name
 * is corroborated only when independent data agrees, in the same state:
 *
 *  1. the FEC roster lists the person for that chamber, or
 *  2. a record from a non-news source (Ballotpedia, state feed, FEC record) has them, or
 *  3. a sitting or verified official of that state has the name (the person is real;
 *     they may be running for a different office).
 *
 * Names are compared through MapCandidateHygiene::identityKey(), so "Steve" and "Steven"
 * are one person. When nothing matches, the reason says what the name is close to, so an
 * admin can tell "misspelt real person" from "not a person".
 *
 * A district that disagrees with the FEC's does NOT fail a corroborated name (maps are
 * redrawn mid-decade, and a real person with a stale district is still real). The result
 * carries the authoritative `district` so the caller can correct it from real data.
 */
class CandidateCorroboration
{
    /** @var array<string, array{roster: array<string, array<int, array<string, mixed>>>, records: array<string, array<int, array<string, mixed>>>, officials: array<string, array<int, array<string, mixed>>>}> */
    private array $byState = [];

    /** @return array{corroborated: bool, source: ?string, reason: ?string, district: ?string} */
    public function check(ElectionCandidateRecord $record): array
    {
        return $this->checkIdentity($record->full_name, $record->state, $record->political_office, $record->district);
    }

    /** @return array{corroborated: bool, source: ?string, reason: ?string, district: ?string} */
    public function checkIdentity(?string $name, ?string $state, ?string $office, ?string $district = null): array
    {
        $key = MapCandidateHygiene::identityKey($name);
        $state = strtoupper(trim((string) $state));
        if ($key === '' || $state === '') {
            return $this->fail('missing a usable name or state');
        }

        $kind = self::officeKind($office);
        $index = $this->index($state);

        foreach (['roster', 'records'] as $tier) {
            foreach ($index[$tier][$key] ?? [] as $row) {
                if ($row['kind'] === $kind) {
                    $real = $kind === 'house' && $row['district'] && $this->districtsConflict($district, $row['district']) ? $row['district'] : null;

                    return ['corroborated' => true, 'source' => $row['source'], 'reason' => null, 'district' => $real];
                }
            }
        }

        if (isset($index['officials'][$key])) {
            return ['corroborated' => true, 'source' => 'official', 'reason' => null, 'district' => null];
        }

        $similar = $this->similarTo($key, $index);

        return $this->fail($similar !== null
            ? "no exact match in {$state}; close to \"{$similar['name']}\" ({$similar['source']})"
            : "no FEC filing, non-news record or sitting official in {$state} matches this name");
    }

    /** house | senate | legislature | president | a canonical statewide office | other */
    public static function officeKind(?string $office): string
    {
        $lower = strtolower(trim((string) $office));
        if ($lower === '') {
            return 'other';
        }
        if (preg_match('/state (senat|repres|house)|assembl|legislat|county|city|council|mayor/', $lower)) {
            return 'legislature';
        }
        if (preg_match('/president/', $lower) && ! str_contains($lower, 'vice')) {
            return 'president';
        }

        return match (true) {
            (bool) preg_match('/representative|house|congress/', $lower) => 'house',
            (bool) preg_match('/senator|senate/', $lower) => 'senate',
            default => OfficeCanonicalizer::canonicaliseStatewide($office) ?? 'other',
        };
    }

    /** @return array{corroborated: false, source: null, reason: string, district: null} */
    private function fail(string $reason): array
    {
        return ['corroborated' => false, 'source' => null, 'reason' => $reason, 'district' => null];
    }

    private function districtsConflict(?string $ours, ?string $theirs): bool
    {
        $a = $this->districtNumber($ours);
        $b = $this->districtNumber($theirs);

        return $a !== null && $b !== null && $a !== $b;
    }

    /** "CA-03" / "CA-3" → "3"; "AK-AL" / "AK-00" → "AL"; null when there's no district. */
    private function districtNumber(?string $district): ?string
    {
        $district = strtoupper(trim((string) $district));
        if ($district === '' || $district === 'STATEWIDE') {
            return null;
        }

        $part = str_contains($district, '-') ? substr($district, strrpos($district, '-') + 1) : $district;
        $part = ltrim($part, '0');

        return $part === '' ? 'AL' : $part;
    }

    /**
     * @param  array{roster: array<string, array<int, array<string, mixed>>>, records: array<string, array<int, array<string, mixed>>>, officials: array<string, array<int, array<string, mixed>>>}  $index
     * @return array{name: string, source: string}|null
     */
    private function similarTo(string $key, array $index): ?array
    {
        [$first, $last] = array_pad(explode('|', $key, 2), 2, '');
        if ($last === '') {
            return null;
        }

        foreach (['roster', 'records', 'officials'] as $tier) {
            foreach ($index[$tier] as $otherKey => $rows) {
                [$otherFirst, $otherLast] = array_pad(explode('|', (string) $otherKey, 2), 2, '');
                if ($otherLast === $last && $this->similarFirstNames($first, $otherFirst)) {
                    return ['name' => $rows[0]['name'], 'source' => $rows[0]['source']];
                }
            }
        }

        return null;
    }

    private function similarFirstNames(string $a, string $b): bool
    {
        if ($a === '' || $b === '') {
            return false;
        }

        return levenshtein($a, $b) <= 2 || (min(strlen($a), strlen($b)) >= 3 && (str_starts_with($a, $b) || str_starts_with($b, $a)));
    }

    /**
     * @return array{roster: array<string, array<int, array<string, mixed>>>, records: array<string, array<int, array<string, mixed>>>, officials: array<string, array<int, array<string, mixed>>>}
     */
    private function index(string $state): array
    {
        return $this->byState[$state] ??= [
            'roster' => $this->rosterIndex($state),
            'records' => $this->recordIndex($state),
            'officials' => $this->officialIndex($state),
        ];
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function rosterIndex(string $state): array
    {
        $out = [];
        CandidateRoster::query()->where('state', $state)->get(['full_name', 'identity_key', 'office', 'district', 'source'])
            ->each(function (CandidateRoster $r) use (&$out): void {
                $kind = ['H' => 'house', 'S' => 'senate', 'P' => 'president'][$r->office] ?? 'other';
                $row = ['name' => $r->full_name, 'kind' => $kind, 'district' => $r->district, 'source' => strtoupper($r->source)];
                $out[$r->identity_key][] = $row;

                // Also reachable by any other given name ("Ken" ↔ "Warren Kenneth Paxton Jr.").
                foreach (array_slice(MapCandidateHygiene::identityKeys($r->full_name), 1) as $alt) {
                    if ($alt !== $r->identity_key) {
                        $out[$alt][] = $row;
                    }
                }
            });

        return $out;
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function recordIndex(string $state): array
    {
        $out = [];
        ElectionCandidateRecord::query()
            ->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [$state])
            ->where('source', '!=', ElectionCandidateRecord::DISCOVERY_SOURCE)
            ->get(['full_name', 'political_office', 'district', 'source'])
            ->each(function (ElectionCandidateRecord $r) use (&$out): void {
                $key = MapCandidateHygiene::identityKey($r->full_name);
                if ($key !== '') {
                    $out[$key][] = ['name' => $r->full_name, 'kind' => self::officeKind($r->political_office), 'district' => $r->district, 'source' => $r->source];
                }
            });

        return $out;
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function officialIndex(string $state): array
    {
        $out = [];
        Politician::query()
            ->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [$state])
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('term_status', 'seated')->orWhere('verified_official', true))
            ->get(['full_name', 'political_office'])
            ->each(function (Politician $p) use (&$out): void {
                $key = MapCandidateHygiene::identityKey($p->full_name);
                if ($key !== '') {
                    $out[$key][] = ['name' => $p->full_name, 'kind' => self::officeKind($p->political_office), 'district' => null, 'source' => 'official'];
                }
            });

        return $out;
    }
}
