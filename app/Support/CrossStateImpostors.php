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
     * Seated statewide executives, keyed by "identityKey|Canonical Office".
     *
     * @return array<string, array<int, array{id: int, state: string, name: string}>>
     */
    public static function seatedHolders(): array
    {
        $holders = [];

        Politician::query()
            ->where('is_active', true)
            ->where('term_status', 'seated')
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
