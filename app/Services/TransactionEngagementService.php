<?php

namespace App\Services;

use App\Models\CampaignTransaction;
use App\Models\PoliticalCampaign;
use App\Models\Politician;
use App\Models\ViewSession;
use App\Models\VoterWatchReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TransactionEngagementService
{
    private const DEFAULT_ATTRIBUTION_WINDOW_DAYS = 7;

    /**
     * Build an invoice-level engagement snapshot using date-window attribution.
     *
     * The snapshot is marked as estimated because sessions are not directly
     * linked to the funding transaction in the current schema.
     */
    public function aggregateForInvoice(CampaignTransaction $transaction, Politician $politician, string $paymentMode): array
    {
        [$windowStart, $windowEnd, $cutoffByNextInvoice] = $this->resolveAttributionWindow(
            $transaction,
            $politician->id,
            $paymentMode
        );

        $campaigns = $this->resolveCampaigns($transaction, $politician->id, $windowStart, $windowEnd);
        $campaignIds = $campaigns->pluck('id')->all();

        $metrics = [
            'views_started' => 0,
            'views_completed' => 0,
            'avg_watch_time_seconds' => 0,
            'avg_completion_percentage' => 0,
            'question_interactions_asked' => 0,
            'question_interactions_replied' => 0,
            'issue_reports' => 0,
            'replay_presses' => null,
            'replay_tracking_available' => false,
            'heatmap_available' => false,
        ];

        $campaignBreakdown = [];

        if (! empty($campaignIds)) {
            $sessionQuery = ViewSession::query()
                ->whereIn('political_campaign_id', $campaignIds)
                ->whereBetween('created_at', [$windowStart, $windowEnd]);

            $sessionAggregate = (clone $sessionQuery)
                ->selectRaw('COUNT(*) as views_started')
                ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as views_completed")
                ->selectRaw('AVG(watch_time_seconds) as avg_watch_time_seconds')
                ->selectRaw('AVG(completion_percentage) as avg_completion_percentage')
                ->first();

            $questionQuery = VoterWatchReport::query()
                ->whereIn('campaign_id', $campaignIds)
                ->whereBetween('created_at', [$windowStart, $windowEnd]);

            $questionMessages = (clone $questionQuery)->where('type', 'message');

            $metrics['views_started'] = (int) ($sessionAggregate->views_started ?? 0);
            $metrics['views_completed'] = (int) ($sessionAggregate->views_completed ?? 0);
            $metrics['avg_watch_time_seconds'] = round((float) ($sessionAggregate->avg_watch_time_seconds ?? 0), 1);
            $metrics['avg_completion_percentage'] = round((float) ($sessionAggregate->avg_completion_percentage ?? 0), 1);
            $metrics['question_interactions_asked'] = (int) (clone $questionMessages)->count();
            $metrics['question_interactions_replied'] = (int) (clone $questionMessages)
                ->whereNotNull('campaign_reply')
                ->where('campaign_reply', '!=', '')
                ->count();
            $metrics['issue_reports'] = (int) (clone $questionQuery)->where('type', 'issue')->count();

            $campaignSessionAggregate = (clone $sessionQuery)
                ->selectRaw('political_campaign_id as campaign_id')
                ->selectRaw('COUNT(*) as views_started')
                ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as views_completed")
                ->selectRaw('AVG(watch_time_seconds) as avg_watch_time_seconds')
                ->selectRaw('AVG(completion_percentage) as avg_completion_percentage')
                ->groupBy('political_campaign_id')
                ->get()
                ->keyBy('campaign_id');

            $campaignQuestionAggregate = (clone $questionQuery)
                ->selectRaw('campaign_id')
                ->selectRaw("SUM(CASE WHEN type = 'message' THEN 1 ELSE 0 END) as question_interactions_asked")
                ->selectRaw("SUM(CASE WHEN type = 'message' AND campaign_reply IS NOT NULL AND campaign_reply != '' THEN 1 ELSE 0 END) as question_interactions_replied")
                ->groupBy('campaign_id')
                ->get()
                ->keyBy('campaign_id');

            $campaignBreakdown = $campaigns->map(function (PoliticalCampaign $campaign) use ($campaignSessionAggregate, $campaignQuestionAggregate): array {
                $session = $campaignSessionAggregate->get($campaign->id);
                $question = $campaignQuestionAggregate->get($campaign->id);

                return [
                    'campaign_id' => $campaign->id,
                    'campaign_uuid' => $campaign->uuid,
                    'title' => $campaign->title,
                    'views_started' => (int) ($session->views_started ?? 0),
                    'views_completed' => (int) ($session->views_completed ?? 0),
                    'avg_watch_time_seconds' => round((float) ($session->avg_watch_time_seconds ?? 0), 1),
                    'avg_completion_percentage' => round((float) ($session->avg_completion_percentage ?? 0), 1),
                    'question_interactions_asked' => (int) ($question->question_interactions_asked ?? 0),
                    'question_interactions_replied' => (int) ($question->question_interactions_replied ?? 0),
                ];
            })->values()->all();
        }

        return [
            'invoice' => [
                'id' => $transaction->id,
                'uuid' => $transaction->uuid,
                'amount' => (float) $transaction->amount,
                'currency' => strtoupper((string) ($transaction->currency ?? 'usd')),
                'created_at' => $transaction->created_at?->toIso8601String(),
                'status' => $transaction->status,
                'transaction_type' => $transaction->transaction_type,
                'credits_amount' => (float) ($transaction->metadata['credits_amount'] ?? 0),
                'stripe_fee' => (float) ($transaction->metadata['stripe_fee'] ?? 0),
            ],
            'attribution' => [
                'estimated' => true,
                'method' => 'date_window',
                'window_days' => self::DEFAULT_ATTRIBUTION_WINDOW_DAYS,
                'window_start' => $windowStart->toIso8601String(),
                'window_end' => $windowEnd->toIso8601String(),
                'next_invoice_cutoff_applied' => $cutoffByNextInvoice,
            ],
            'metrics' => $metrics,
            'campaigns' => $campaignBreakdown,
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: bool}
     */
    private function resolveAttributionWindow(CampaignTransaction $transaction, int $politicianId, string $paymentMode): array
    {
        $windowStart = ($transaction->created_at ?? now())->copy();
        $windowEnd = $windowStart->copy()->addDays(self::DEFAULT_ATTRIBUTION_WINDOW_DAYS);

        $nextInvoice = CampaignTransaction::query()
            ->where('politician_id', $politicianId)
            ->where('transaction_type', 'charge')
            ->where('status', 'succeeded')
            ->where('id', '!=', $transaction->id)
            ->where('created_at', '>', $windowStart)
            ->where('metadata->payment_mode', $paymentMode)
            ->orderBy('created_at')
            ->first();

        $cutoffByNextInvoice = false;

        if ($nextInvoice?->created_at instanceof Carbon && $nextInvoice->created_at->lt($windowEnd)) {
            $windowEnd = $nextInvoice->created_at->copy();
            $cutoffByNextInvoice = true;
        }

        return [$windowStart, $windowEnd, $cutoffByNextInvoice];
    }

    private function resolveCampaigns(
        CampaignTransaction $transaction,
        int $politicianId,
        Carbon $windowStart,
        Carbon $windowEnd
    ): Collection {
        if (! empty($transaction->campaign_id)) {
            return PoliticalCampaign::query()
                ->where('id', $transaction->campaign_id)
                ->where('politician_id', $politicianId)
                ->get(['id', 'uuid', 'title']);
        }

        return PoliticalCampaign::query()
            ->where('politician_id', $politicianId)
            ->where(function ($query) use ($windowStart, $windowEnd) {
                $query->whereHas('viewSessions', function ($viewQuery) use ($windowStart, $windowEnd) {
                    $viewQuery->whereBetween('created_at', [$windowStart, $windowEnd]);
                })->orWhereHas('voterWatchReports', function ($reportQuery) use ($windowStart, $windowEnd) {
                    $reportQuery->whereBetween('created_at', [$windowStart, $windowEnd]);
                });
            })
            ->get(['id', 'uuid', 'title']);
    }
}
