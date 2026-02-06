<?php

namespace App\Http\Middleware;

use App\Services\WixOAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that verifies requests originating from Wix.
 *
 * Wix sends an `instance` query parameter (a signed JWT-like token)
 * with every request made from the Wix Dashboard or widget iframe.
 * This middleware decodes and verifies it.
 */
class VerifyWixInstance
{
    public function __construct(
        protected WixOAuthService $wixOAuth,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $instance = $request->query('instance') ?? $request->header('X-Wix-Instance');

        if (!$instance) {
            return response()->json(['error' => 'Missing Wix instance'], 401);
        }

        $decoded = $this->wixOAuth->decodeInstance($instance);

        if (!$decoded) {
            return response()->json(['error' => 'Invalid Wix instance'], 403);
        }

        // Attach decoded instance data to the request for downstream use
        $request->merge([
            'wix_instance' => $decoded,
            'wix_instance_id' => $decoded['instanceId'] ?? null,
        ]);

        return $next($request);
    }
}
