<?php

namespace App\Support;

/**
 * Lightweight name-display helper.
 */
class Name
{
    /**
     * Format a full name as "First L." (first name + last-name initial) for
     * display contexts where the full name would over-expose another user's
     * identity — e.g. a referrer's "Referred Voters" list.
     */
    public static function firstNameLastInitial(?string $fullName): string
    {
        $parts = preg_split('/\s+/', trim((string) $fullName), -1, PREG_SPLIT_NO_EMPTY);

        if (empty($parts)) {
            return 'Anonymous';
        }

        if (count($parts) === 1) {
            return $parts[0];
        }

        return $parts[0] . ' ' . strtoupper(substr(end($parts), 0, 1)) . '.';
    }
}
