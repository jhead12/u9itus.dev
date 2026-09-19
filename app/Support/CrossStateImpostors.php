<?php

namespace App\Support;

use App\Models\Politician;

/**
 * Spots "candidates" that are really a sitting statewide official from
 * another state.
 *
 * News discovery searches per state ("North Carolina Governor candidates"), so
 * national headlines that merely mention Greg Abbott get filed as NC and NY
 * Governor leads. Nobody holds the same statewide executive office in two
 * states, so a lead whose name matches a *seated* holder of that office
 * elsewhere is a mention, not a candidacy.
 *
 * Deliberately narrow: statewide executive offices only, and callers apply it
 * to unverified rows only (never to a row a person or an official feed vouches
 * for), because two different people can share a name.
 */
final class CrossStateImpostors
{
    /**
     * Sitting statewide executives, keyed by "identityKey|Canonical Office". "Sitting" is
     * a seated term OR a verified official: an incumbent running for re-election is often
     * stored as "running" (that is how Greg Abbott shows as a "2026 Candidate"), and
     * without the verified branch he would not count as the holder of his own office.
     *
     * @return array<string, array<int, array{id: int, state: string, name: string}>>
     */
    public static function seatedHolders(): array
    {
        $holders = [];

        Politician::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('term_status', 'seated')->orWhere('verified_official', true))
            ->whereNotNull('state')->where('state', '!=', '')
            ->whereNotNull('political_office')
            ->get(['id', 'full_name', 'political_office', 'state'])
            ->each(function (Politician $p) use (&$holders): void {
                $office = OfficeCanonicalizer::canonicaliseStatewide($p->political_office);
                $key = MapCandidateHygiene::identityKey($p->full_name);
                if ($office === null || $key === '') {
                    return;
                }
                $holders[$key.'|'.$office][] = [
                    'id' => $p->id,
                    'state' => strtoupper((string) $p->state),
                    'name' => (string) $p->full_name,
                ];
            });

        return $holders;
    }

    /**
     * Sitting holders indexed by state then lowercase surname, for surnameStub().
     *
     * @param  array<string, array<int, array{id: int, state: string, name: string}>>  $holders  from seatedHolders()
     * @return array<string, array<string, array{id: int, state: string, name: string}>>
     */
    public static function surnameIndex(array $holders): array
    {
        $index = [];
        foreach ($holders as $group) {
            foreach ($group as $holder) {
                $words = preg_split('/\s+/', trim($holder['name'])) ?: [];
                $surname = mb_strtolower(rtrim((string) end($words), '.,'));
                if ($surname !== '' && count($words) >= 2) {
                    $index[$holder['state']][$surname] = $holder;
                }
            }
        }

        return $index;
    }

    /**
     * The sitting official of this state a two-word name is probably a headline about: the
     * first word is that official's surname and the whole isn't their name — "Hochul Agenda",
     * "Hochul Budget". Catches headline words nobody has listed. Only a hint: callers queue
     * it for a human rather than acting on it.
     *
     * @param  array<string, array<string, array{id: int, state: string, name: string}>>  $index  from surnameIndex()
     * @return array{id: int, state: string, name: string}|null
     */
    public static function surnameStub(?string $name, ?string $state, array $index): ?array
    {
        $words = preg_split('/\s+/', trim((string) $name)) ?: [];
        if (count($words) !== 2 || ! $state) {
            return null;
        }

        $holder = $index[strtoupper($state)][mb_strtolower($words[0])] ?? null;

        return $holder !== null && MapCandidateHygiene::identityKey($name) !== MapCandidateHygiene::identityKey($holder['name'])
            ? $holder
            : null;
    }

    /**
     * The sitting holder of this office IN THIS state whose name this is (or a headline-mangled
     * variant of it, "Greg Abbott's") — i.e. the real official this row duplicates.
     *
     * @param  array<string, array<int, array{id: int, state: string, name: string}>>  $holders  from seatedHolders()
     * @return array{id: int, state: string, name: string}|null
     */
    public static function holderInState(?string $name, ?string $office, ?string $state, array $holders): ?array
    {
        $canonical = OfficeCanonicalizer::canonicaliseStatewide($office);
        $key = MapCandidateHygiene::identityKey($name);
        if ($canonical === null || $key === '' || ! $state) {
            return null;
        }

        foreach ($holders[$key.'|'.$canonical] ?? [] as $holder) {
            if ($holder['state'] === strtoupper($state)) {
                return $holder;
            }
        }

        return null;
    }

    /**
     * The seated holder (in another state) this name/office would be an
     * impostor of, or null.
     *
     * @param  array<string, array<int, array{id: int, state: string, name: string}>>  $holders  from seatedHolders()
     * @return array{id: int, state: string, name: string}|null
     */
    public static function holderElsewhere(?string $name, ?string $office, ?string $state, array $holders): ?array
    {
        $canonical = OfficeCanonicalizer::canonicaliseStatewide($office);
        $key = MapCandidateHygiene::identityKey($name);
        if ($canonical === null || $key === '' || ! $state) {
            return null;
        }

        $state = strtoupper($state);
        $matches = $holders[$key.'|'.$canonical] ?? [];

        // If anyone with this name holds the office in *this* state, it's them.
        foreach ($matches as $holder) {
            if ($holder['state'] === $state) {
                return null;
            }
        }

        return $matches[0] ?? null;
    }
}
