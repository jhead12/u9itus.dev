<?php

namespace App\Support;

use App\Models\Politician;

/**
 * Keeps bulk importers from overwriting data an existing profile already has.
 *
 * Seat-holder feeds (Congress legislators, statewide officeholder enrichment)
 * know who holds an office, not whether they're running again. They used to
 * write is_running_candidate = false / term_status = seated on every nightly
 * run, erasing re-election runs recorded by the primary-result sync — on
 * 2026-09-24 not one of 439 House members showed as running.
 */
final class ProfileOverwriteGuard
{
    /** Owned by the primary-result, candidate-import and status pipelines. */
    public const LIFECYCLE_FIELDS = ['is_running_candidate', 'term_status', 'status_updated_at'];

    /** Curated fields a feed may fill but never replace. */
    public const DEFAULT_FILL_ONLY = ['bio', 'profile_photo_url', 'website_url', 'video_links', 'city'];

    /**
     * Strip from $payload what would overwrite existing data: lifecycle status
     * once the profile has one, and any $fillOnly field that already has a value.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $fillOnly
     * @return array<string, mixed>
     */
    public static function filter(Politician $existing, array $payload, array $fillOnly = self::DEFAULT_FILL_ONLY): array
    {
        // 'unknown' is the importers' placeholder, not a status anyone set.
        if (! self::blank($existing->term_status) && $existing->term_status !== 'unknown') {
            $payload = array_diff_key($payload, array_flip(self::LIFECYCLE_FIELDS));
        }

        foreach ($fillOnly as $field) {
            if (! self::blank($existing->getAttribute($field))) {
                unset($payload[$field]);
            }
        }

        return $payload;
    }

    private static function blank(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}
