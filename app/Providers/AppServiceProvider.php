<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (array_keys(\App\Support\AdminAccess::catalog()) as $permission) {
            \Illuminate\Support\Facades\Gate::define($permission, fn ($user) => \App\Support\AdminAccess::allowed($user, $permission));
        }
        \Illuminate\Support\Facades\Gate::define('staff.roles.manage', fn ($user) => \App\Support\AdminAccess::owner($user));
        // Privileged identities must first be demoted through the serialized
        // access service. This covers generic profile/self-delete paths too.
        \App\Models\User::deleting(function ($user) {
            if ($user->hasRole('admin', 'web')) {
                throw \Illuminate\Validation\ValidationException::withMessages(['user' => 'Staff accounts cannot be deleted. Revoke their access instead.']);
            }
        });
        \App\Models\User::updating(function ($user) {
            if ($user->hasRole(\App\Support\AdminAccess::OWNER, 'web')
                && (($user->isDirty('suspended_at') && $user->suspended_at)
                    || ($user->isDirty('user_type') && $user->user_type !== 'admin')
                    || ($user->isDirty('email_verified_at') && ! $user->email_verified_at))) {
                throw \Illuminate\Validation\ValidationException::withMessages(['user' => 'Demote this Super Admin through Staff access before disabling the account.']);
            }
        });
        // Financial configuration health checks (non-local/non-test environments only).
        if (! app()->environment('local', 'testing')) {
            if (empty(config('services.stripe.webhook_secret'))) {
                Log::critical(
                    'STRIPE_WEBHOOK_SECRET is not set. ' .
                    'Webhook signature verification is disabled — the platform cannot accept Stripe webhooks securely.'
                );
            }
        }

        // SEC-7: per-(email|IP) rate limit for login attempts. Keying on email+IP
        // means an attacker on one IP cannot lock out a victim by spamming that
        // victim's email (the victim's own attempts come from a different IP and
        // use a separate bucket), while still throttling brute-force from any IP.
        // Keep paid/location lookups separate from ordinary map page requests.
        RateLimiter::for('map-geocode', fn ($request) => Limit::perMinute(30)->by('map-geocode:'.$request->ip()));

        RateLimiter::for('login', function ($request): Limit {
            $key = ($request->input('email') ? strtolower((string) $request->input('email')) : '')
                . '|' . $request->ip();

            return Limit::perMinute(5)->by($key);
        });

        // SMS 2FA recovery: caps paid Twilio sends independently of the
        // generic 6/min throttle used for code-guessing endpoints.
        RateLimiter::for('2fa-recovery-sms', function ($request): Limit {
            $userId = $request->user()?->id ?? 'guest';

            return Limit::perHour(3)->by('2fa-recovery-sms|' . $userId . '|' . $request->ip());
        });

        // Registration: caps signup attempts per IP so a bot can't hammer
        // /register/* faster than RegistrationSecurityService's daily count
        // even bothers to reject it. Content/IP checks still run underneath.
        RateLimiter::for('register', function ($request): Limit {
            return Limit::perMinutes(15, 5)->by($request->ip());
        });

        // Request latency logging for slow web endpoints.
        if (app()->runningInConsole()) {
            return;
        }

        $slowRequestMs = (int) env('SLOW_REQUEST_LOG_THRESHOLD_MS', 1200);
        $slowQueryMs = (int) env('SLOW_QUERY_LOG_THRESHOLD_MS', 300);
        $requestStartedAt = microtime(true);

        DB::listen(function ($query) use ($slowQueryMs): void {
            if ($query->time >= $slowQueryMs) {
                Log::warning('Slow query detected', [
                    'sql' => $query->sql,
                    'bindings_count' => is_array($query->bindings) ? count($query->bindings) : 0,
                    'time_ms' => $query->time,
                ]);
            }
        });

        app()->terminating(function () use ($requestStartedAt, $slowRequestMs): void {
            $durationMs = (int) round((microtime(true) - $requestStartedAt) * 1000);

            if ($durationMs >= $slowRequestMs) {
                $request = request();

                Log::warning('Slow request detected', [
                    'method' => $request->method(),
                    'path' => '/' . ltrim($request->path(), '/'),
                    'duration_ms' => $durationMs,
                    'status' => http_response_code(),
                    'user_id' => optional($request->user())->id,
                ]);
            }
        });
    }
}
