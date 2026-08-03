<?php

namespace App\Console\Commands;

use App\Mail\BoundaryDigestMail;
use App\Models\Voter;
use App\Services\BoundaryDigestMatchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Send each opted-in voter a weekly email about new candidate activity
 * (news, endorsements) for the districts/cities they've favorited on the map.
 *
 * Covers two populations:
 *  - Real voters with notification_preferences.email_boundary_digest = true.
 *  - Pending (user_id-null) guest voters who confirmed the lightweight
 *    inline opt-in (digest_opt_in_pending + digest_confirmed_at set) — see
 *    GuestDigestOptInController.
 *
 * Modeled on SendWeeklyVoterDigest's per-recipient try/catch + --dry-run
 * skeleton. Skips a voter entirely if none of their saved boundaries have
 * anything new this week (no "nothing happened" emails).
 */
class SendWeeklyBoundaryDigest extends Command
{
    protected $signature = 'notifications:boundary-digest
                                {--dry-run : Print counts without sending emails}';

    protected $description = 'Send weekly saved-places digest emails to voters who opted in';

    public function handle(BoundaryDigestMatchService $matchService): int
    {
        if (! BoundaryDigestMail::isEnabled()) {
            $this->info('boundary_digest email template is inactive — skipping entirely.');
            return self::SUCCESS;
        }

        $dryRun = $this->option('dry-run');
        $since = now()->subDays(7);
        $periodLabel = $since->format('M j') . ' – ' . now()->format('M j, Y');

        $this->info('Building weekly saved-places digest (' . ($dryRun ? 'DRY RUN' : 'LIVE') . ')…');

        $voters = Voter::query()
            ->whereHas('favoriteBoundaries')
            ->where(function ($query) {
                $query->whereHas('user.notificationPreference', function ($q) {
                    $q->where('email_boundary_digest', true);
                })->orWhere(function ($q) {
                    $q->whereNull('user_id')
                        ->where('digest_opt_in_pending', true)
                        ->whereNotNull('digest_confirmed_at');
                });
            })
            ->with('favoriteBoundaries')
            ->get();

        if ($voters->isEmpty()) {
            $this->info('No opted-in voters with saved boundaries found. Nothing sent.');
            return self::SUCCESS;
        }

        $sent = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($voters as $voter) {
            if (empty($voter->email)) {
                $skipped++;
                continue;
            }

            $sections = $matchService->contentForVoter($voter, $since);

            if ($sections === []) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line("  [DRY] {$voter->email} — " . count($sections) . ' boundaries with updates');
                $sent++;
                continue;
            }

            try {
                Mail::to($voter->email)->queue(new BoundaryDigestMail($voter, $sections, $periodLabel));
                $sent++;
            } catch (\Exception $e) {
                $failed++;
                Log::error('SendWeeklyBoundaryDigest: failed to queue email', [
                    'voter_id' => $voter->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Done — {$sent} emails queued, {$skipped} skipped (no updates), {$failed} failed.");

        return self::SUCCESS;
    }
}
