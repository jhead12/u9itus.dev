<?php

namespace App\Support;

/**
 * Text to send to an outside name search (FEC, OpenSecrets). Those indexes store
 * "JEFFRIES, HAKEEM" and don't match a query carrying a middle initial —
 * "Hakeem S. Jeffries" finds nothing — so the initial is dropped.
 */
class NameSearch
{
    /**
     * "Hakeem S. Jeffries" → "Hakeem Jeffries". Only single-letter tokens BETWEEN a real
     * first and last name go: "J. D. Vance" keeps both initials (they are his first name),
     * and a two-word name is left alone.
     */
    public static function withoutMiddleInitials(string $name): string
    {
        $tokens = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $isInitial = fn (string $t) => (bool) preg_match('/^\p{L}\.?$/u', $t);

        if (count(array_filter($tokens, fn ($t) => ! $isInitial($t))) < 2) {
            return trim($name);
        }

        $first = array_search(false, array_map($isInitial, $tokens), true);
        $last = count($tokens) - 1;
        while ($last > 0 && $isInitial($tokens[$last])) {
            $last--;
        }

        $kept = [];
        foreach ($tokens as $i => $token) {
            if ($i > $first && $i < $last && $isInitial($token)) {
                continue;
            }
            $kept[] = $token;
        }

        return implode(' ', $kept);
    }
}
