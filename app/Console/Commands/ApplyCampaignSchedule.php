<?php

namespace App\Console\Commands;

use App\Mail\CampaignQuestionDigestMail;
use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Models\CampaignAuditLog;
use App\Models\PoliticalCampaign;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Phase 14 — Campaign Scheduling
 *
 * Runs on a schedule (every 5 minutes) and performs two actions:
 *
 * 1. ACTIVATE   — Promotes 'scheduled' campaigns whose scheduled_start_at has
 *                 passed and whose approved_status is 'approved' → sets status
 *                 to 'active'.
 *
 * 2. AUTO-PAUSE — Pauses 'active' campaigns whose scheduled_end_at has passed
 *                 → sets status to 'paused'.
 *
 * Both actions write an entry to campaign_audit_logs so the immutable admin
 * audit trail captures every automated transition.
 */
class ApplyCampaignSchedule extends Command
{
    protected $signature   = 'campaigns:apply-schedule';
    protected $description = 'Activate scheduled campaigns whose window has opened; auto-pause campaigns whose window has closed.';

    public function handle(): int
    {
        $now        = Carbon::now();
        $activated  = 0;
        $paused     = 0;

        // Use the first admin user as the "system" actor for the audit trail.
        // The admin_id column is non-nullable, so we skip the audit entry if
        // no admin user exists yet (e.g. during initial seeding).
        $systemAdminId = User::role('admin')->value('id');

        // ── 1. Activate scheduled campaigns ─────────────────────────────────
        $toActivate = PoliticalCampaign::query()
            ->where('status', CampaignStatus::Scheduled->value)
            ->where('approval_status', ApprovalStatus::Approved->value)
            ->where('scheduled_start_at', '<=', $now)
            ->whereColumn('views_completed', '<', 'total_views_requested')
            ->get();

        foreach ($toActivate as $campaign) {
            $campaign->update([
                'status'     => CampaignStatus::Active,
                'started_at' => $campaign->started_at ?? $now,
            ]);

            if ($systemAdminId) {
                CampaignAuditLog::create([
                    'campaign_id' => $campaign->id,
                    'admin_id'    => $systemAdminId,
                    'action'      => 'activated_by_schedule',
                    'reason'      => "Auto-activated at {$now->toDateTimeString()} (scheduled_start_at: {$campaign->scheduled_start_at})",
                ]);
            }

            $activated++;
            $this->line("  ✓ Activated campaign #{$campaign->id} \"{$campaign->title}\"");
        }

        // ── 2. Auto-pause campaigns past their scheduled_end_at ──────────────
        $toExpire = PoliticalCampaign::query()
            ->where('status', CampaignStatus::Active->value)
            ->whereNotNull('scheduled_end_at')
            ->where('scheduled_end_at', '<=', $now)
            ->get();

        foreach ($toExpire as $campaign) {
            $campaign->update(['status' => CampaignStatus::Paused]);

            $this->queueQuestionDigest($campaign);

            if ($systemAdminId) {
                CampaignAuditLog::create([
                    'campaign_id' => $campaign->id,
                    'admin_id'    => $systemAdminId,
                    'action'      => 'paused_by_schedule',
                    'reason'      => "Auto-paused at {$now->toDateTimeString()} (scheduled_end_at: {$campaign->scheduled_end_at})",
                ]);
            }

            $paused++;
            $this->line("  ⏸ Auto-paused campaign #{$campaign->id} \"{$campaign->title}\"");
        }

        if ($activated === 0 && $paused === 0) {
            $this->line('  No schedule transitions to apply.');
        } else {
            $this->info("Schedule applied: {$activated} activated, {$paused} paused.");
            Log::info('campaigns:apply-schedule', compact('activated', 'paused'));
        }

        return self::SUCCESS;
    }

    private function queueQuestionDigest(PoliticalCampaign $campaign): void
    {
        $campaign->loadMissing('politician.user');

        $politicianUser = $campaign->politician?->user;
        if (! $politicianUser instanceof User || empty($politicianUser->email)) {
            return;
        }

        $preferences = $politicianUser->notificationPreference()->firstOrCreate([])->refresh();
        if (! $preferences->isEnabled('email', 'campaign_status')) {
            return;
        }

        $questions = $campaign->voterWatchReports()
            ->messages()
            ->with('voter:id,full_name,email')
            ->oldest('created_at')
            ->get();

        if ($questions->isEmpty()) {
            return;
        }

        try {
            Mail::to($politicianUser->email)->queue(new CampaignQuestionDigestMail($campaign, $questions));
        } catch (\Throwable $e) {
            Log::warning('Failed to queue campaign question digest', [
                'campaign_id' => $campaign->id,
                'user_id' => $politicianUser->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
