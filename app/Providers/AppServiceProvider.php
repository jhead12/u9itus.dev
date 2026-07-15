<?php

namespace App\Providers;

use Illuminate\Cache\Limit;
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
        RateLimiter::for('login', function ($request): Limit {
            $key = ($request->input('email') ? strtolower((string) $request->input('email')) : '')
                . '|' . $request->ip();

            return Limit::perMinute(5)->by($key);
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
