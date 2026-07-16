<?php

namespace App\Http\Middleware;

use App\Models\Voter;
use App\Models\ViewSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SEC-4: enforce that the bearer-token voter owns the route's resource.
 *
 * Run after AuthenticateVoterToken. The $binding argument selects which route
 * param to compare against the token voter:
 *
 *  - "voter"   (default): {voter:uuid} resolves a Voter — the token voter must
 *    be the same voter (prevents using one voter's token against another's UUID).
 *  - "session": {session:uuid} resolves a ViewSession — the token voter must
 *    own the session (session.voter_id === token voter id). This binds view
 *    progress / completion to the authenticated voter (COR-5).
 */
class EnsureVoterTokenMatches
{
    public function handle(Request $request, Closure $next, string $binding = 'voter'): Response
    {
        $tokenVoter = $request->attributes->get(AuthenticateVoterToken::VOTER_ATTR);

        if (!$tokenVoter instanceof Voter) {
            return response()->json(['message' => 'Authentication required.'], 401);
        }

        if ($binding === 'session') {
            $session = $request->route('session');

            if ($session instanceof ViewSession && (int) $session->voter_id === (int) $tokenVoter->id) {
                return $next($request);
            }

            return response()->json(['message' => 'Token does not own this view session.'], 403);
        }

        $routeVoter = $request->route('voter');

        if ($routeVoter instanceof Voter && $routeVoter->is($tokenVoter)) {
            return $next($request);
        }

        return response()->json(['message' => 'Token does not own this voter resource.'], 403);
    }
}