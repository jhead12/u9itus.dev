<?php

namespace App\Http\Middleware;

use App\Models\Voter;
use App\Services\GuestBoundaryMergeService;
use App\Support\GuestBoundaryCookie;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * On every authenticated request, folds any guest-cookie map favorites (and
 * pending digest-opt-in state) into the logged-in voter's real rows, then
 * clears the cookies — same "cheap no-op unless cookie present" shape as
 * CaptureReferralContext, and registered right after it in the `web` group
 * so every login path (password login, registration, EarlyBank SSO, Guest
 * Trial Mode's own auto-login) is covered without needing to duplicate this
 * call at each entry point.
 */
class MergeGuestFavoriteBoundaries
{
    public function __construct(
        private readonly GuestBoundaryMergeService $mergeService,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $voter = Auth::user()->voter ?? null;

        if (! $voter) {
            return $next($request);
        }

        $cookieItems = GuestBoundaryCookie::readItems($request);
        $pendingUuid = GuestBoundaryCookie::readVoterUuid($request);

        if ($cookieItems === [] && $pendingUuid === null) {
            return $next($request);
        }

        if ($cookieItems !== []) {
            $this->mergeService->mergeCookieItemsIntoVoter($cookieItems, $voter);
        }

        if ($pendingUuid !== null && $pendingUuid !== $voter->uuid) {
            $pending = Voter::whereNull('user_id')->where('uuid', $pendingUuid)->first();

            if ($pending) {
                $this->mergeService->reparentPendingVoter($pending, $voter);
            }
        }

        Cookie::queue(GuestBoundaryCookie::forgetBoundariesCookie());
        Cookie::queue(GuestBoundaryCookie::forgetVoterCookie());

        return $next($request);
    }
}
