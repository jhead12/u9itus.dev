<?php

use App\Models\CampaignTransaction;
use App\Models\PoliticalCampaign;
use App\Models\Politician;
use App\Models\ViewSession;
use App\Models\Voter;
use App\Models\VoterWatchReport;
use App\Services\TransactionEngagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.timezone' => 'UTC']);
});

test('it aggregates invoice engagement metrics using date-window attribution', function () {
    $politician = Politician::factory()->create();

    $campaign = PoliticalCampaign::factory()->create([
        'politician_id' => $politician->id,
        'title' => 'Transit Plan',
    ]);

    $windowStart = now()->subDay()->startOfHour();

    $invoice = CampaignTransaction::query()->create([
        'campaign_id' => null,
        'politician_id' => $politician->id,
        'transaction_type' => 'charge',
        'amount' => 100.00,
        'currency' => 'usd',
        'status' => 'succeeded',
        'metadata' => [
            'payment_mode' => 'test',
            'credits_amount' => 97.50,
            'stripe_fee' => 2.50,
        ],
    ]);
    DB::table('campaign_transactions')->where('id', $invoice->id)->update([
        'created_at' => $windowStart,
        'updated_at' => $windowStart,
    ]);
    $invoice->refresh();

    // Next invoice appears before +7 days, so attribution window should end here.
    $nextInvoiceTime = $windowStart->copy()->addDays(3);
    $nextInvoice = CampaignTransaction::query()->create([
        'campaign_id' => null,
        'politician_id' => $politician->id,
        'transaction_type' => 'charge',
        'amount' => 80.00,
        'currency' => 'usd',
        'status' => 'succeeded',
        'metadata' => ['payment_mode' => 'test'],
    ]);
    DB::table('campaign_transactions')->where('id', $nextInvoice->id)->update([
        'created_at' => $nextInvoiceTime,
        'updated_at' => $nextInvoiceTime,
    ]);

    $voter = Voter::factory()->create();

    $sessionA = ViewSession::factory()->create([
        'political_campaign_id' => $campaign->id,
        'voter_id' => $voter->id,
        'status' => 'completed',
        'watch_time_seconds' => 120,
        'completion_percentage' => 100,
    ]);
    DB::table('view_sessions')->where('id', $sessionA->id)->update([
        'created_at' => $windowStart->copy()->addHours(6),
        'updated_at' => $windowStart->copy()->addHours(6),
    ]);

    $sessionB = ViewSession::factory()->create([
        'political_campaign_id' => $campaign->id,
        'voter_id' => $voter->id,
        'status' => 'in_progress',
        'watch_time_seconds' => 30,
        'completion_percentage' => 20,
    ]);
    DB::table('view_sessions')->where('id', $sessionB->id)->update([
        'created_at' => $windowStart->copy()->addHours(7),
        'updated_at' => $windowStart->copy()->addHours(7),
    ]);

    // Outside attribution window because next invoice cut off occurs at +3 days.
    $sessionOutsideWindow = ViewSession::factory()->create([
        'political_campaign_id' => $campaign->id,
        'voter_id' => $voter->id,
        'status' => 'completed',
        'watch_time_seconds' => 140,
        'completion_percentage' => 100,
    ]);
    DB::table('view_sessions')->where('id', $sessionOutsideWindow->id)->update([
        'created_at' => $windowStart->copy()->addDays(4),
        'updated_at' => $windowStart->copy()->addDays(4),
    ]);

    $reportA = VoterWatchReport::query()->create([
        'voter_id' => $voter->id,
        'campaign_id' => $campaign->id,
        'type' => 'message',
        'body' => 'What about transit?',
        'status' => 'open',
    ]);
    DB::table('voter_watch_reports')->where('id', $reportA->id)->update([
        'created_at' => $windowStart->copy()->addHours(8),
        'updated_at' => $windowStart->copy()->addHours(8),
    ]);

    $reportB = VoterWatchReport::query()->create([
        'voter_id' => $voter->id,
        'campaign_id' => $campaign->id,
        'type' => 'message',
        'body' => 'Can you clarify funding?',
        'status' => 'resolved',
        'campaign_reply' => 'Yes, details are on our policy page.',
    ]);
    DB::table('voter_watch_reports')->where('id', $reportB->id)->update([
        'created_at' => $windowStart->copy()->addHours(9),
        'updated_at' => $windowStart->copy()->addHours(9),
    ]);

    $reportIssue = VoterWatchReport::query()->create([
        'voter_id' => $voter->id,
        'campaign_id' => $campaign->id,
        'type' => 'issue',
        'issue_category' => 'video_not_playing',
        'body' => 'Video was buffering',
        'status' => 'open',
    ]);
    DB::table('voter_watch_reports')->where('id', $reportIssue->id)->update([
        'created_at' => $windowStart->copy()->addHours(10),
        'updated_at' => $windowStart->copy()->addHours(10),
    ]);

    $reportOutsideWindow = VoterWatchReport::query()->create([
        'voter_id' => $voter->id,
        'campaign_id' => $campaign->id,
        'type' => 'message',
        'body' => 'Outside window and should not count',
        'status' => 'open',
    ]);
    DB::table('voter_watch_reports')->where('id', $reportOutsideWindow->id)->update([
        'created_at' => $windowStart->copy()->addDays(4),
        'updated_at' => $windowStart->copy()->addDays(4),
    ]);

    $service = app(TransactionEngagementService::class);
    $snapshot = $service->aggregateForInvoice($invoice, $politician, 'test');

    expect($snapshot['metrics']['views_started'])->toBe(2)
        ->and($snapshot['metrics']['views_completed'])->toBe(1)
        ->and($snapshot['metrics']['avg_watch_time_seconds'])->toBe(75.0)
        ->and($snapshot['metrics']['avg_completion_percentage'])->toBe(60.0)
        ->and($snapshot['metrics']['question_interactions_asked'])->toBe(2)
        ->and($snapshot['metrics']['question_interactions_replied'])->toBe(1)
        ->and($snapshot['metrics']['issue_reports'])->toBe(1)
        ->and($snapshot['metrics']['replay_tracking_available'])->toBeFalse()
        ->and($snapshot['attribution']['next_invoice_cutoff_applied'])->toBeTrue()
        ->and($snapshot['campaigns'])->toHaveCount(1)
        ->and($snapshot['campaigns'][0]['title'])->toBe('Transit Plan');
});
