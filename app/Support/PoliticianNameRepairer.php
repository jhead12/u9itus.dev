<?php

namespace App\Support;

/**
 * Repairs a Politician full_name mangled by a leading qualifier/title/
 * geography word — "Independent Michael Shellenberger", "Former California
 * Xavier Becerra", "Lt. Gov. Eleni Kounalakis" — instead of only rejecting
 * it. Strips one leading match of {@see PoliticianDataRules::LEADING_QUALIFIER_PATTERN}
 * at a time (so multi-qualifier fragments like "Lt. Gov. Eleni" need two
 * passes: strip "Lt." → strip "Gov." → done) and stops as soon as the
 * remainder passes {@see PoliticianDataRules::nameViolation()} — the same
 * loose check the Politician model's saving hook already applies to every
 * write, so a repaired name is held to no stricter a bar than a hand-typed
 * one would be.
 *
 * Used both by Politician::boot()'s saving hook (so a repair happens
 * automatically on every write path) and by the politicians:repair-names
 * backfill command (to fix rows already sitting in the table).
 */
class PoliticianNameRepairer
{
    /**
     * @return array{name: string, changed: bool, unrepairable: bool}
     *   `changed` is true only when a qualifier was stripped AND the
     *   remainder is a valid name. `unrepairable` is true when a qualifier
     *   was stripped but nothing valid was left (e.g. "Former California")
     *   — a signal worth flagging for manual review, distinct from a name
     *   that never matched a leading qualifier at all.
     */
    public static function repair(?string $name): array
    {
        $original = trim((string) $name);
        $current = $original;
        $strippedAnything = false;

        while ($current !== '') {
            $next = trim((string) preg_replace(PoliticianDataRules::LEADING_QUALIFIER_PATTERN, '', $current));

            if ($next === $current) {
                break;
            }

            $current = $next;
            $strippedAnything = true;
        }

        if (! $strippedAnything) {
            return ['name' => $original, 'changed' => false, 'unrepairable' => false];
        }

        if ($current === '' || PoliticianDataRules::nameViolation($current) !== null) {
            return ['name' => $original, 'changed' => false, 'unrepairable' => true];
        }

        return ['name' => $current, 'changed' => true, 'unrepairable' => false];
    }
}
