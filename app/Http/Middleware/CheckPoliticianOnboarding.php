<?php

namespace App\Http\Middleware;

use App\Services\OnboardingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPoliticianOnboarding
{
    public function __construct(
        protected OnboardingService $onboardingService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Skip check if not authenticated or not a politician
        if (!$user || !$user->hasRole('politician')) {
            return $next($request);
        }

        // Skip check if already on onboarding routes
        if ($request->routeIs('politician.onboarding.*')) {
            return $next($request);
        }

        // Check if onboarding is required
        if ($this->onboardingService->isOnboardingRequired($user, 'politician')) {
            $progress = $this->onboardingService->getOrCreate($user, 'politician');
            $nextPhase = $this->onboardingService->getNextRequiredPhase($progress);

            if ($nextPhase) {
                $phases = $this->onboardingService->getPhasesForType('politician');
                $route = $phases[$nextPhase]['route'] ?? 'politician.onboarding.welcome';
                return redirect()->route($route);
            }
        }

        return $next($request);
    }
}
