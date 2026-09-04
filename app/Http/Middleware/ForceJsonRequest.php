<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force the request to be treated as an API/JSON request.
 *
 * The WebMCP endpoints are machine endpoints. An agent that hand-rolls a
 * `fetch()` (instead of going through the registered tool, which sets the
 * header) often omits `Accept: application/json`. Without it Laravel renders a
 * validation error as a 302 redirect to an HTML page, and the caller's
 * `response.json()` then blows up on `<!DOCTYPE …`. Pinning the header here
 * makes every failure path — validation, throttle, model-binding 404 — return
 * JSON with a readable message.
 */
class ForceJsonRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
