<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * EarlyBankApiAuth
 *
 * Authenticates server-to-server API calls from the Early-bank.com service.
 * Early-bank is deployed as a sibling Railway service in the same private network,
 * so we use a shared bearer token (timing-safe compared) rather than full OAuth.
 *
 * Expected header:
 *   Authorization: Bearer <EARLYBANK_API_TOKEN>
 *
 * The token is read from config('services.earlybank.api_token'), which is sourced
 * from the EARLYBANK_API_TOKEN env var on the u9itus Railway service and must
 * match the U9ITUS_API_TOKEN env var on the earlybank Railway service.
 */
class EarlyBankApiAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.earlybank.api_token', '');

        if ($expected === '') {
            Log::critical('EarlyBankApiAuth: EARLYBANK_API_TOKEN is not configured.');
            return response()->json(['error' => 'service_not_configured'], 503);
        }

        $provided = $this->extractBearer($request);

        if ($provided === null || ! hash_equals($expected, $provided)) {
            Log::warning('EarlyBankApiAuth: invalid or missing bearer token', [
                'ip'   => $request->ip(),
                'path' => $request->path(),
            ]);
            return response()->json(['error' => 'unauthorized'], 401);
        }

        return $next($request);
    }

    private function extractBearer(Request $request): ?string
    {
        $header = $request->header('Authorization', '');
        if (! is_string($header) || $header === '') {
            return null;
        }

        if (stripos($header, 'Bearer ') !== 0) {
            return null;
        }

        return trim(substr($header, 7));
    }
}
