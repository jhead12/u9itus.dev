<?php

namespace App\Http\Middleware;

use App\Services\UserAccessTrackingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackUserAccessContext
{
    public function __construct(private readonly UserAccessTrackingService $trackingService)
    {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            $this->trackingService->track($request, $request->user(), 'request');
        }

        return $next($request);
    }
}
