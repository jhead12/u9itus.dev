<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
