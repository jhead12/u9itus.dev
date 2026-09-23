<?php

namespace App\Support\Workspace;

use App\Models\BallotMeasure;
use App\Models\CandidateMatchReview;
use App\Models\DistrictNewsArticle;
use App\Models\PoliticalCampaign;
use App\Models\PoliticianCleanupReview;
use App\Models\StateElectionDate;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Builds the render data for a single workspace widget. Kept as one class
 * (rather than a class-per-widget) since each widget here is a read-only
 * query or two against data other admin pages already own — see
 * WidgetCatalog for the key => widget mapping this switches on.
 */
final class WorkspaceDataProvider
{
    /** @return array<string, mixed> */
    public function forKey(string $key): array
    {
        return match ($key) {
            'stats_snapshot' => $this->statsSnapshot(),
            'pending_queues' => $this->pendingQueues(),
            'workflow_health' => $this->workflowHealth(),
            'local_news_feed' => $this->localNewsFeed(),
            'voting_updates' => $this->votingUpdates(),
            default => [],
        };
    }

    private function statsSnapshot(): array
    {
        return [
            'total_users' => User::count(),
            'total_politicians' => User::where('user_type', 'politician')->count(),
            'total_voters' => User::where('user_type', 'voter')->count(),
            'suspended_users' => User::whereNotNull('suspended_at')->count(),
        ];
    }

    private function pendingQueues(): array
    {
        return [
            'campaigns' => PoliticalCampaign::where('approval_status', 'pending')->count(),
            'candidate_matches' => CandidateMatchReview::where('status', CandidateMatchReview::STATUS_PENDING)->count(),
            'data_quality' => PoliticianCleanupReview::where('status', PoliticianCleanupReview::STATUS_PENDING)->count(),
            'kyc' => User::where('kyc_status', 'pending')->where('user_type', 'politician')->count(),
        ];
    }

    /**
     * Mirrors the checks in CheckPoliticiansCleanupHealth without duplicating
     * its notification/anomaly logic — just "when did each step last run,
     * and what did it find" for a human glancing at the workspace.
     */
    private function workflowHealth(): array
    {
        if (! Schema::hasTable('politician_cleanup_run_metrics')) {
            return ['steps' => []];
        }

        $steps = DB::table('politician_cleanup_run_metrics')
            ->select('step', DB::raw('MAX(started_at) as last_started_at'))
            ->groupBy('step')
            ->pluck('last_started_at', 'step');

        $rows = [];
        foreach ($steps as $step => $lastStartedAt) {
            $latest = DB::table('politician_cleanup_run_metrics')
                ->where('step', $step)
                ->orderByDesc('started_at')
                ->first();

            $rows[] = [
                'step' => $step,
                'last_started_at' => $lastStartedAt,
                'findings_count' => (int) ($latest->findings_count ?? 0),
                'exit_code' => (int) ($latest->exit_code ?? 0),
                'stale' => $lastStartedAt === null || Carbon::parse($lastStartedAt)->diffInHours(now()) > 26,
            ];
        }

        return ['steps' => $rows];
    }

    private function localNewsFeed(): array
    {
        return [
            'articles' => DistrictNewsArticle::query()
                ->verified()
                ->recent(8)
                ->get(['headline', 'source_name', 'source_url', 'state', 'matched_locality', 'published_at']),
        ];
    }

    private function votingUpdates(): array
    {
        return [
            'upcoming_elections' => StateElectionDate::query()
                ->where(function ($q) {
                    $q->whereNull('election_date')->orWhere('election_date', '>=', now()->toDateString());
                })
                ->orderBy('election_date')
                ->limit(6)
                ->get(['state', 'stage_name', 'election_date']),
            'recent_ballot_measures' => BallotMeasure::query()
                ->latest()
                ->limit(6)
                ->get(['state', 'title', 'level', 'county', 'locality', 'election_date']),
        ];
    }
}
