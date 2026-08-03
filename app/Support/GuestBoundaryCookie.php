<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

/**
 * Cookie contract for guest (unauthenticated) map favorites.
 *
 * Mirrors CaptureReferralContext's cookie handling (encrypted, httpOnly,
 * lax, first-party/functional — not used for tracking):
 *
 *  - `u9_guest_boundaries` — versioned JSON array of favorited boundary
 *    payloads for a browser that has never opted into the email digest.
 *    Capped at MAX_ITEMS so an abusive client can't grow the cookie
 *    unboundedly.
 *  - `u9_guest_voter_id`   — once a guest opts into the weekly digest, a
 *    pending Voter row (user_id null) is created and this cookie holds its
 *    uuid. From then on, favorites are written directly to
 *    voter_favorite_boundaries under that pending row instead of the JSON
 *    blob, and get reparented onto the real Voter on login/registration.
 */
class GuestBoundaryCookie
{
    public const BOUNDARIES_COOKIE = 'u9_guest_boundaries';
    public const VOTER_COOKIE = 'u9_guest_voter_id';
    public const TTL_MINUTES = 259200; // 180 days
    public const MAX_ITEMS = 25;
    private const PAYLOAD_VERSION = 1;

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function readItems(Request $request): array
    {
        $raw = $request->cookie(self::BOUNDARIES_COOKIE);

        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded) || ! isset($decoded['items']) || ! is_array($decoded['items'])) {
            return [];
        }

        return array_slice(array_values($decoded['items']), 0, self::MAX_ITEMS);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public static function makeCookie(array $items, Request $request): SymfonyCookie
    {
        $payload = json_encode([
            'v' => self::PAYLOAD_VERSION,
            'items' => array_slice($items, 0, self::MAX_ITEMS),
        ]);

        return cookie(
            self::BOUNDARIES_COOKIE,
            $payload,
            self::TTL_MINUTES,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax'
        );
    }

    public static function forgetBoundariesCookie(): SymfonyCookie
    {
        return Cookie::forget(self::BOUNDARIES_COOKIE);
    }

    public static function makeVoterCookie(string $voterUuid, Request $request): SymfonyCookie
    {
        return cookie(
            self::VOTER_COOKIE,
            $voterUuid,
            self::TTL_MINUTES,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax'
        );
    }

    public static function forgetVoterCookie(): SymfonyCookie
    {
        return Cookie::forget(self::VOTER_COOKIE);
    }

    public static function readVoterUuid(Request $request): ?string
    {
        $uuid = $request->cookie(self::VOTER_COOKIE);

        return is_string($uuid) && trim($uuid) !== '' ? trim($uuid) : null;
    }

    /**
     * Stable composite key matching the client's favoriteKey() in
     * resources/js/map/state/map-state.js, so a cookie-mode entry's `id`
     * (this key) round-trips through save/remove the same way a DB id does.
     */
    public static function keyFor(array $item): string
    {
        return implode('|', [
            (string) ($item['type'] ?? ''),
            strtoupper((string) ($item['state_abbr'] ?? '')),
            (string) ($item['district_number'] ?? ''),
            (string) ($item['city_name'] ?? ''),
        ]);
    }
}
