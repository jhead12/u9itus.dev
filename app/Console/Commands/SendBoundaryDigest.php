<?php

namespace App\Console\Commands;

use App\Mail\BoundaryDigestMail;
use App\Models\Voter;
use App\Services\BoundaryDigestMatchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends each opted-in voter a digest of new candidate activity (news,
 * endorsements, popular YouTube clips) for the districts/cities they've
 * favorited on the map — see App\Mail\BoundaryDigestMail and
 * App\Services\BoundaryDigestMatchService.
 *
 * Content-driven cadence, not a fixed weekly send: this command runs daily
 * (see routes/console.php), but each voter is only actually emailed once
 * either of these is true —
 *   - FLOOR: it's been >= self::FLOOR_DAYS since their last send (never go
 *     silent, even if there's barely anything new).
 *   - BURST: it's been >= self::BURST_MIN_DAYS since their last send AND
 *     they've accumulated >= self::BURST_ITEM_THRESHOLD new items (news +
 *     endorsements + video clips) — lets a high-news period (e.g. an
 *     election approaching for one of their saved districts) trigger an
 *     earlier send automatically, with no manual "election mode" toggle to
 *     maintain.
 * A voter's own last_digest_sent_at anchors both the eligibility check and
 * the `$since` cutoff passed to BoundaryDigestMatchService, so content never
 * gets skipped between sends — it just accumulates until the next one goes
 * out. Each email is also capped at BoundaryDigestMatchService::MAX_SECTIONS
 * boundaries, busiest first, with the rest summarized as a "+N more" line.
 *
 * Covers two populations:
 *  - Real voters with notification_preferences.email_boundary_digest = true.
 *  - Pending (user_id-null) guest voters who confirmed the lightweight
 *    inline opt-in (digest_opt_in_pending + digest_confirmed_at set) — see
 *    GuestDigestOptInController.
 */
class SendBoundaryDigest extends Command
{
    protected $signature = 'notifications:boundary-digest
                                {--dry-run : Print counts without sending emails}';

    protected $description = 'Send saved-places digest emails to voters who opted in, on a content-driven cadence';

    /** Never let a voter go longer than this without an email, content permitting. */
    private const FLOOR_DAYS = 7;

    /** Minimum days between sends even during a burst — caps max frequency. */
    private const BURST_MIN_DAYS = 2;

    /** New items (news + endorsements + videos) needed to trigger an early/burst send. */
    private const BURST_ITEM_THRESHOLD = 5;

    public function handle(BoundaryDigestMatchService $matchService): int
    {
        if (! BoundaryDigestMail::isEnabled()) {
            $this->info('boundary_digest email template is inactive — skipping entirely.');
            return self::SUCCESS;
        }

        $dryRun = $this->option('dry-run');

        $this->info('Checking saved-places digest eligibility (' . ($dryRun ? 'DRY RUN' : 'LIVE') . ')…');

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
        $notYetDue = 0;
        $failed = 0;

        foreach ($voters as $voter) {
            if (empty($voter->email)) {
                $skipped++;
                continue;
            }

            $since = $voter->last_digest_sent_at ?? now()->subDays(self::FLOOR_DAYS);
            $daysSinceLastSend = $voter->last_digest_sent_at
                ? $voter->last_digest_sent_at->diffInDays(now())
                : self::FLOOR_DAYS;

            $content = $matchService->contentForVoter($voter, $since);

            if ($content['sections'] === []) {
                $skipped++;
                continue;
            }

            $isDue = $daysSinceLastSend >= self::FLOOR_DAYS
                || ($daysSinceLastSend >= self::BURST_MIN_DAYS && $content['total_item_count'] >= self::BURST_ITEM_THRESHOLD);

            if (! $isDue) {
                $notYetDue++;
                continue;
            }

            $periodLabel = $since->format('M j') . ' – ' . now()->format('M j, Y');

            if ($dryRun) {
                $this->line(
                    "  [DRY] {$voter->email} — " . count($content['sections']) . ' boundaries, '
                    . $content['total_item_count'] . ' items, ' . $daysSinceLastSend . 'd since last send'
                );
                $sent++;
                continue;
            }

            try {
                Mail::to($voter->email)->queue(new BoundaryDigestMail(
                    $voter,
                    $content['sections'],
                    $periodLabel,
                    $content['remaining']
                ));
                $voter->update(['last_digest_sent_at' => now()]);
                $sent++;
            } catch (\Exception $e) {
                $failed++;
                Log::error('SendBoundaryDigest: failed to queue email', [
                    'voter_id' => $voter->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Done — {$sent} emails queued, {$skipped} skipped (no updates), {$notYetDue} not yet due, {$failed} failed.");

        return self::SUCCESS;
    }
}
