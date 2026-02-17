<?php

namespace App\Http\Middleware;

use App\Services\WixOAuthService;
use App\Services\WixSsoService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that verifies requests originating from Wix.
 *
 * Wix sends an `instance` query parameter (a signed JWT-like token)
 * with every request made from the Wix Dashboard or widget iframe.
 * This middleware decodes and verifies it, then bridges the Wix
 * member identity into a Laravel auth session (SSO).
 */
class VerifyWixInstance
{
    public function __construct(
        protected WixOAuthService $wixOAuth,
        protected WixSsoService   $wixSso,
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

        // SSO: resolve/create Laravel user from Wix member identity and log them in
        try {
            $user = $this->wixSso->loginFromInstance($decoded);
            if ($user) {
                $request->merge(['wix_user' => $user]);
            }
        } catch (\Throwable $e) {
            // SSO failure should not block the Wix request — log and continue
            Log::warning('Wix SSO failed', [
                'error'       => $e->getMessage(),
                'instance_id' => $decoded['instanceId'] ?? null,
            ]);
        }

        return $next($request);
    }
}
