<?php

namespace App\Http\Middleware;

use App\Models\Politician;
use App\Models\Voter;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class CaptureReferralContext
{
    private const COOKIE_NAME = 'u9_referral_code';
    private const SESSION_KEY = 'referral.code';
    private const COOKIE_TTL_MINUTES = 43200; // 30 days

    public function handle(Request $request, Closure $next): Response
    {
        $incomingCode = $request->query('ref') ?: $request->query('referral_code');

        if (is_string($incomingCode) && trim($incomingCode) !== '') {
            $normalizedCode = strtoupper(trim($incomingCode));

            if ($this->hasValidFormat($normalizedCode) && $this->referralExists($normalizedCode)) {
                $request->session()->put(self::SESSION_KEY, $normalizedCode);
                Cookie::queue($this->makeCookie($normalizedCode, $request));
            } else {
                // Invalid or unknown code should never block browsing.
                $request->session()->forget(self::SESSION_KEY);
                Cookie::queue(Cookie::forget(self::COOKIE_NAME));
            }
        } else {
            $cookieCode = $request->cookie(self::COOKIE_NAME);

            if (
                is_string($cookieCode)
                && trim($cookieCode) !== ''
                && !$request->session()->has(self::SESSION_KEY)
            ) {
                $request->session()->put(self::SESSION_KEY, strtoupper(trim($cookieCode)));
            }
        }

        return $next($request);
    }

    private function hasValidFormat(string $code): bool
    {
        return preg_match('/^[A-Z0-9_-]{4,20}$/', $code) === 1;
    }

    private function referralExists(string $code): bool
    {
        return Voter::where('referral_code', $code)->exists()
            || Politician::where('referral_code', $code)->exists();
    }

    private function makeCookie(string $code, Request $request)
    {
        return cookie(
            self::COOKIE_NAME,
            $code,
            self::COOKIE_TTL_MINUTES,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax'
        );
    }
}
