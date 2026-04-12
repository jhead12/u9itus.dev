<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Services\IdmeOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class IdmeController extends Controller
{
    public function __construct(private readonly IdmeOAuthService $idmeOAuthService)
    {
    }

    public function redirectToProvider(Request $request): RedirectResponse
    {
        if (! $this->idmeOAuthService->isConfigured()) {
            return $this->fallbackRedirect('Id.me is not configured yet. Please contact support.');
        }

        $state = Str::random(64);

        $request->session()->put('idme_oauth_state', $state);
        $request->session()->put('idme_oauth_user_id', (int) Auth::id());

        return redirect()->away($this->idmeOAuthService->authorizationUrl($state));
    }

    public function handleCallback(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        $sessionState = (string) $request->session()->pull('idme_oauth_state', '');
        $sessionUserId = (int) $request->session()->pull('idme_oauth_user_id', 0);

        if ($sessionState === '' || ! hash_equals($sessionState, (string) $request->query('state')) || $sessionUserId !== (int) Auth::id()) {
            return $this->fallbackRedirect('Id.me verification failed due to an invalid session. Please try again.');
        }

        try {
            $tokenPayload = $this->idmeOAuthService->exchangeCodeForToken((string) $request->query('code'));
            $attributes = $this->idmeOAuthService->fetchAttributes((string) $tokenPayload['access_token']);

            $idmeUuid = $this->extractIdmeUuid($attributes);
            if ($idmeUuid === null) {
                throw new \RuntimeException('Id.me attributes did not include a stable identity identifier.');
            }

            $user = $request->user();
            $user->update([
                'idme_uuid' => $idmeUuid,
                'idme_verified_at' => now(),
                'kyc_status' => 'approved',
                'kyc_reviewed_at' => now(),
                'kyc_reviewer_id' => null,
                'kyc_rejection_reason' => null,
            ]);

            return $this->fallbackRedirect('Id.me verification complete. Your account is now verified.', 'success');
        } catch (Throwable $e) {
            Log::warning('Id.me verification callback failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return $this->fallbackRedirect('We could not complete your Id.me verification right now. Please try again.');
        }
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'configured' => $this->idmeOAuthService->isConfigured(),
            'verified' => $user->idme_verified_at !== null,
            'idme_uuid' => $user->idme_uuid,
            'idme_verified_at' => optional($user->idme_verified_at)?->toIso8601String(),
            'kyc_status' => $user->kyc_status,
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function extractIdmeUuid(array $attributes): ?string
    {
        $candidates = [
            $attributes['uuid'] ?? null,
            $attributes['id'] ?? null,
            $attributes['sub'] ?? null,
            is_array($attributes['user'] ?? null) ? ($attributes['user']['uuid'] ?? null) : null,
            is_array($attributes['user'] ?? null) ? ($attributes['user']['id'] ?? null) : null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    private function fallbackRedirect(string $message, string $flashKey = 'error'): RedirectResponse
    {
        $user = Auth::user();

        $route = 'dashboard';
        if ($user?->hasRole('voter')) {
            $route = 'voter.profile';
        } elseif ($user?->hasRole('politician')) {
            $route = 'politician.profile';
        } elseif ($user?->hasRole('admin')) {
            $route = 'admin.dashboard';
        }

        return redirect()->route($route)->with($flashKey, $message);
    }
}
