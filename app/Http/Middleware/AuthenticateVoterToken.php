<?php

namespace App\Http\Middleware;

use App\Models\VoterApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SEC-4: authenticate stateless voter API consumers via a bearer token.
 *
 * Resolves the VoterApiToken by hashing the Authorization: Bearer value,
 * validates it (exists, voter is active, not expired), stamps last-used, and
 * stores the resolved Voter + token record on the request attributes so the
 * ownership middleware and controllers can compare them to route bindings.
 *
 * This deliberately avoids the Sanctum/Guard machinery (Sanctum is not
 * installed and API voters are not tied to User accounts). The resolved Voter
 * is exposed via request attributes, not Auth::user().
 */
class AuthenticateVoterToken
{
    /** Request-attribute key holding the resolved Voter. */
    public const VOTER_ATTR = 'voter_token_voter';

    /** Request-attribute key holding the resolved VoterApiToken record. */
    public const TOKEN_ATTR = 'voter_token_record';

    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if (!is_string($bearer) || $bearer === '') {
            return response()->json(['message' => 'Voter API token required.'], 401);
        }

        $record = VoterApiToken::findToken($bearer);

        if ($record === null || $record->voter === null) {
            return response()->json(['message' => 'Invalid voter API token.'], 401);
        }

        if (!$record->voter->is_active) {
            return response()->json(['message' => 'Voter account is not active.'], 403);
        }

        if (!$record->isValid()) {
            return response()->json(['message' => 'Voter API token has expired.'], 401);
        }

        $record->touchUsage();

        $request->attributes->set(self::VOTER_ATTR, $record->voter);
        $request->attributes->set(self::TOKEN_ATTR, $record);

        return $next($request);
    }
}