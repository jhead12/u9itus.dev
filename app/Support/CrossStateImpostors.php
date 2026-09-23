<?php

namespace App\Support;

use App\Models\Politician;
use App\Services\CandidateDiscovery\CandidateCorroboration;

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

    /**
     * States where a sitting or verified official of ANY office has each name, keyed by identityKey().
     * "Marsha Blackburn" (a Tennessee senator) turns up as a Michigan Governor candidate from a
     * national headline; {@see holderElsewhere()} misses her because her office is not a statewide one.
     *
     * @return array<string, array<int, string>>
     */
    public static function seatedStatesByName(): array
    {
        $byName = [];

        Politician::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('term_status', 'seated')->orWhere('verified_official', true))
            ->whereNotNull('state')->where('state', '!=', '')
            ->get(['full_name', 'state'])
            ->each(function (Politician $p) use (&$byName): void {
                $key = MapCandidateHygiene::identityKey($p->full_name);
                if ($key !== '') {
                    $byName[$key][strtoupper((string) $p->state)] = strtoupper((string) $p->state);
                }
            });

        return array_map('array_values', $byName);
    }

    /**
     * The state a same-named sitting official is in, when they sit somewhere other than
     * $state and nobody of that name sits in it. Use only for statewide executive offices: a
     * U.S. Senate race can legitimately share a name with an official elsewhere (two Mike Rogers).
     *
     * @param  array<string, array<int, string>>  $byName  from seatedStatesByName()
     */
    public static function sittingOnlyElsewhere(?string $name, ?string $state, array $byName): ?string
    {
        $states = $byName[MapCandidateHygiene::identityKey($name)] ?? [];
        $state = strtoupper(trim((string) $state));

        return $states !== [] && $state !== '' && ! in_array($state, $states, true) ? $states[0] : null;
    }

    /**
     * Narrower, federal-only counterpart to sittingOnlyElsewhere(): gated to Senate/House/
     * President races. Two different people CAN legitimately share a name across federal
     * races (two Mike Rogers), so a name collision alone is not proof — callers MUST
     * additionally require CandidateCorroboration::checkIdentity() to fail before treating
     * this as a finding. See FlagSuspectProfiles::classify().
     *
     * @param  array<string, array<int, string>>  $byName  from seatedStatesByName()
     */
    public static function federalNameCollisionElsewhere(?string $name, ?string $state, ?string $office, array $byName): ?string
    {
        if (! in_array(CandidateCorroboration::officeKind($office), ['senate', 'house', 'president'], true)) {
            return null;
        }

        return self::sittingOnlyElsewhere($name, $state, $byName);
    }
}
