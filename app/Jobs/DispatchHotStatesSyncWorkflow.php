<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Triggers workflow_dispatch on the map's state-scoped GitHub Actions
 * workflows (refresh-map-candidates, sync-census-demographics, the full
 * sync-candidates pipeline, and refresh-candidate-news) for whichever states
 * are currently trending on the 3-D map, so candidate/results/demographics/
 * news data for those states refreshes ahead of the next scheduled full sync.
 *
 * Fired by the map:sync-hot-states command, which already applies the
 * click-volume threshold and per-state dispatch cooldown — this job just
 * makes the GitHub API calls. Uses its own fine-grained PAT
 * (services.github.hotstate_token / GITHUB_HOTSTATE_TOKEN) with
 * repo → Actions → Write scope, kept separate from the profile-repair
 * dispatcher's token so the two blast radii can't cross.
 */
class DispatchHotStatesSyncWorkflow implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 10;

    /**
     * Workflow files to dispatch, each accepting a comma-separated `states`
     * input. sync-candidates.yml is the heavy full pipeline (Playwright
     * scraping, AI-vision photo validation, Claude enrichment) — every other
     * input on it (dry_run, include_results, election_year, ...) falls back
     * to that workflow's own declared default when omitted here.
     */
    private const WORKFLOWS = [
        'refresh-map-candidates.yml',
        'sync-census-demographics.yml',
        'sync-candidates.yml',
        'refresh-candidate-news.yml',
    ];

    /**
     * @param array<int, string> $states Two-letter state codes, e.g. ['CA', 'TX'].
     */
    public function __construct(public readonly array $states) {}

    public function handle(): void
    {
        if ($this->states === []) {
            return;
        }

        $token = config('services.github.hotstate_token');
        $repo = config('services.github.repo');
        $ref = config('services.github.ref', 'master');

        if (empty($token) || empty($repo)) {
            Log::warning('DispatchHotStatesSyncWorkflow: GITHUB_HOTSTATE_TOKEN or GITHUB_REPO not configured — skipping.', [
                'states' => $this->states,
            ]);

            return;
        }

        $statesParam = implode(',', $this->states);
        $failures = [];

        foreach (self::WORKFLOWS as $workflowFile) {
            $response = Http::withToken($token)
                ->withHeaders(['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28'])
                ->post("https://api.github.com/repos/{$repo}/actions/workflows/{$workflowFile}/dispatches", [
                    'ref' => $ref,
                    'inputs' => ['states' => $statesParam],
                ]);

            if ($response->successful()) {
                // 204 No Content is the normal success response for workflow_dispatch.
                Log::info('DispatchHotStatesSyncWorkflow: dispatched workflow for trending states.', [
                    'workflow' => $workflowFile,
                    'states' => $statesParam,
                ]);
            } else {
                Log::error('DispatchHotStatesSyncWorkflow: GitHub dispatch failed.', [
                    'workflow' => $workflowFile,
                    'states' => $statesParam,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                $failures[] = "{$workflowFile} (HTTP {$response->status()})";
            }
        }

        if ($failures !== []) {
            $this->fail(new \RuntimeException(
                'GitHub workflow_dispatch failed for: ' . implode(', ', $failures)
            ));
        }
    }
}
