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
 * Fires a GitHub repository_dispatch event to trigger the
 * repair-broken-profile workflow for a specific politician slug.
 *
 * Deduped via cache: a slug that already has a repair in flight will
 * not trigger a second dispatch for 30 minutes.
 */
class DispatchProfileRepairWorkflow implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 10;

    public function __construct(
        public readonly string $slug,
        public readonly string $path,
    ) {}

    public function handle(): void
    {
        $token = config('services.github.repair_token');
        $repo  = config('services.github.repo');

        if (empty($token) || empty($repo)) {
            Log::warning('DispatchProfileRepairWorkflow: GITHUB_REPAIR_TOKEN or GITHUB_REPO not configured — skipping.', [
                'slug' => $this->slug,
            ]);
            return;
        }

        // Dedupe: do not re-dispatch the same slug within 30 minutes.
        $cacheKey = 'profile_repair_dispatched:' . $this->slug;
        if (Cache::has($cacheKey)) {
            Log::info('DispatchProfileRepairWorkflow: repair already dispatched recently, skipping.', [
                'slug' => $this->slug,
            ]);
            return;
        }

        $response = Http::withToken($token)
            ->withHeaders(['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28'])
            ->post("https://api.github.com/repos/{$repo}/dispatches", [
                'event_type'     => 'profile.repair',
                'client_payload' => [
                    'slug' => $this->slug,
                    'path' => $this->path,
                ],
            ]);

        if ($response->successful()) {
            // 204 No Content is the normal success response for repository_dispatch.
            Cache::put($cacheKey, true, now()->addMinutes(30));
            Log::info('DispatchProfileRepairWorkflow: dispatched profile.repair event.', [
                'slug' => $this->slug,
                'repo' => $repo,
            ]);
        } else {
            Log::error('DispatchProfileRepairWorkflow: GitHub dispatch failed.', [
                'slug'   => $this->slug,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            $this->fail(new \RuntimeException(
                "GitHub dispatch returned HTTP {$response->status()} for slug [{$this->slug}]"
            ));
        }
    }
}
