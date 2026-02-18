<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirect requests that originate from the Wix platform
 * to the standalone application, preventing cross-platform confusion.
 */
class RedirectIfWix
{
    /**
     * Wix platform indicators (request headers or query params).
     */
    private const WIX_HEADERS = [
        'x-wix-instance',
        'x-wix-site-id',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // If the app is in Wix-only mode, let through
        if (! config('platform.standalone.enabled', false)) {
            return $next($request);
        }

        // Detect Wix requests by header
        foreach (self::WIX_HEADERS as $header) {
            if ($request->hasHeader($header)) {
                // Wix iframe requests should use dedicated Wix API routes
                if ($request->expectsJson()) {
                    return response()->json(['error' => 'Use Wix API endpoints for Wix platform requests.'], 400);
                }
                return redirect('/');
            }
        }

        return $next($request);
    }
}
