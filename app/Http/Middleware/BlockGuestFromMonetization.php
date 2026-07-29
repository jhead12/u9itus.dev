<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks guest-trial voters (see ProvisionGuestVoterSession) from
 * money-related routes. Applied per-route rather than as a blanket
 * exclusion, since missing one here has real financial exposure.
 */
class BlockGuestFromMonetization
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->user()?->is_guest, 403, 'Create a free account to access this.');

        return $next($request);
    }
}
