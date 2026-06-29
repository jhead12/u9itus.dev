<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * CaptureEarlyBankReferral
 *
 * Stores the Early-bank member UUID in a 30-day httpOnly cookie whenever
 * a visitor arrives via an Early-bank referral link (`?ref=<member_uuid>`).
 *
 * Last-click wins: each new `?ref=` visit overwrites the cookie, so the
 * most recent referrer gets credit — standard for referral programs.
 *
 * The cookie is read back at voter registration time by StoreVoterRequest,
 * so attribution is preserved even if the visitor browses, leaves, and
 * returns later without the query parameter.
 *
 * Cookie properties:
 *   - httpOnly: not accessible from JavaScript
 *   - SameSite=Lax: sent on top-level navigation from earlybank.com
 *   - Secure: only on HTTPS (falls back to false in local dev)
 *   - 30-day TTL: configurable via EARLYBANK_REFERRAL_COOKIE_DAYS env var
 */
class CaptureEarlyBankReferral
{
    public const COOKIE_NAME = 'earlybank_ref';
    private const DEFAULT_DAYS = 30;

    public function handle(Request $request, Closure $next): Response
    {
        $ref = $request->query('ref');

        if (is_string($ref) && Str::isUuid($ref)) {
            $days = (int) env('EARLYBANK_REFERRAL_COOKIE_DAYS', self::DEFAULT_DAYS);

            Cookie::queue(Cookie::make(
                name:     self::COOKIE_NAME,
                value:    $ref,
                minutes:  60 * 24 * $days,
                path:     '/',
                domain:   null,
                secure:   $request->isSecure(),
                httpOnly: true,
                raw:      false,
                sameSite: 'lax',
            ));
        }

        return $next($request);
    }
}
