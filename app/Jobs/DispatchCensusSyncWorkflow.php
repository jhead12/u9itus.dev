<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fires workflow_dispatch on sync-census-demographics.yml, scoped to a
 * single state, when the city-census drawer (politician-drawer.js, city
 * view → Economy tab) asks for a city with no city_demographics row yet.
 *
 * Deliberately narrower than DispatchHotStatesSyncWorkflow (which also
 * dispatches the heavy sync-candidates.yml pipeline) — a missing census
 * row only needs the census sync to run.
 *
 * Deduped via cache: a state that already has a sync in flight will not
 * trigger a second dispatch for 6 hours (matches the region-demographics
 * cache TTL — no point re-dispatching faster than that data can change).
 */
class DispatchCensusSyncWorkflow implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 10;

    public function __construct(public readonly string $state) {}

    public function handle(): void
    {
        $token = config('services.github.hotstate_token');
        $repo = config('services.github.repo');
        $ref = config('services.github.ref', 'master');

        if (empty($token) || empty($repo)) {
            Log::warning('DispatchCensusSyncWorkflow: GITHUB_HOTSTATE_TOKEN or GITHUB_REPO not configured — skipping.', [
                'state' => $this->state,
            ]);

            return;
        }

        $cacheKey = 'census_sync_dispatched:' . $this->state;
        if (Cache::has($cacheKey)) {
            Log::info('DispatchCensusSyncWorkflow: sync already dispatched recently, skipping.', [
                'state' => $this->state,
            ]);

            return;
        }

        $response = Http::withToken($token)
            ->withHeaders(['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28'])
            ->post("https://api.github.com/repos/{$repo}/actions/workflows/sync-census-demographics.yml/dispatches", [
                'ref' => $ref,
                'inputs' => ['states' => $this->state],
            ]);

        if ($response->successful()) {
            // 204 No Content is the normal success response for workflow_dispatch.
            Cache::put($cacheKey, true, now()->addHours(6));
            Log::info('DispatchCensusSyncWorkflow: dispatched census sync for state.', [
                'state' => $this->state,
            ]);
        } else {
            Log::error('DispatchCensusSyncWorkflow: GitHub dispatch failed.', [
                'state' => $this->state,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $this->fail(new \RuntimeException(
                "GitHub workflow_dispatch returned HTTP {$response->status()} for state [{$this->state}]"
            ));
        }
    }
}
