<?php

namespace App\Console\Commands;

use App\Enums\MarketingDraftStatus;
use App\Mail\MarketingDraftsDigestMail;
use App\Models\MarketingPostDraft;
use App\Models\User;
use App\Services\PlatformSettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendMarketingDraftsDigest extends Command
{
    protected $signature = 'marketing:drafts-digest
        {--dry-run : Print the drafts and recipients without sending email}';

    protected $description = 'Email admins a daily digest of new PendingApproval marketing drafts created since the last digest.';

    /** Platform-settings key holding the last successful digest timestamp. */
    public const LAST_SENT_KEY = 'marketing_drafts_digest_last_sent_at';

    public function handle(): int
    {
        if (!config('u9itus.marketing.enabled', true)) {
            $this->info('Marketing disabled (u9itus.marketing.enabled).');
            return self::SUCCESS;
        }

        if (!config('u9itus.marketing.drafting.digest_enabled', true)) {
            $this->info('Marketing drafts digest disabled (u9itus.marketing.drafting.digest_enabled).');
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $since = $this->lastSentAt();

        $drafts = $this->newDraftsSince($since);

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Digest window: since {$since->toDateTimeString()} — {$drafts->count()} new draft(s).");

        if ($drafts->isEmpty()) {
            $this->info('No new drafts to report.');
            // Advance the watermark even on an empty run so the window stays
            // "since last run" — but only when live (dry-run must not mutate).
            if (!$dryRun) {
                $this->advanceWatermark();
            }
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->line('  Recipients:');
            foreach ($this->recipients() as $email) {
                $this->line("    • {$email}");
            }
            $this->line('  Drafts:');
            foreach ($drafts as $draft) {
                $this->line("    • [{$draft->source_type->value}] {$draft->politician?->full_name} — {$draft->generated_title}");
            }
            return self::SUCCESS;
        }

        $recipients = $this->recipients();

        if ($recipients->isEmpty()) {
            $this->warn('No admin recipients configured — set MARKETING_DRAFTS_DIGEST_RECIPIENTS or ensure admin users with emails exist.');
            Log::warning('marketing:drafts-digest: no recipients, skipping send.');
            return self::SUCCESS;
        }

        $sent = 0;
        $failed = 0;

        foreach ($recipients as $email) {
            try {
                Mail::to($email)->send(new MarketingDraftsDigestMail($drafts));
                $sent++;
                $this->line("  ✓ sent to {$email}");
            } catch (\Throwable $e) {
                $failed++;
                $this->warn("  ✗ {$email}: {$e->getMessage()}");
                Log::warning('marketing:drafts-digest send failed', [
                    'recipient' => $email,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        // Advance the watermark only if the run completed without total
        // failure — a fully failed send leaves the window intact so the next
        // run retries the same drafts.
        if ($sent > 0) {
            $this->advanceWatermark();
        }

        $this->info("Done. Sent: {$sent} | Failed: {$failed}");

        return self::SUCCESS;
    }

    /** The timestamp of the last successful digest, or 24h ago on first run. */
    protected function lastSentAt(): Carbon
    {
        $stored = PlatformSettingsService::get(self::LAST_SENT_KEY);

        if (!empty($stored)) {
            try {
                return Carbon::parse($stored);
            } catch (\Throwable $e) {
                Log::warning('marketing:drafts-digest: unparseable last_sent_at, falling back to 24h.', [
                    'value' => $stored,
                ]);
            }
        }

        return now()->subDay();
    }

    protected function advanceWatermark(): void
    {
        PlatformSettingsService::set(self::LAST_SENT_KEY, now()->toDateTimeString(), [
            'description' => 'Last successful marketing drafts digest sent timestamp.',
            'category'    => 'marketing',
        ]);
    }

    /**
     * Posted drafts created since the watermark, newest last, with the
     * politician + post relations eager-loaded for the email view.
     *
     * @return Collection<int, MarketingPostDraft>
     */
    protected function newDraftsSince(Carbon $since): Collection
    {
        return MarketingPostDraft::query()
            ->where('status', MarketingDraftStatus::Posted->value)
            ->where('created_at', '>=', $since)
            ->with(['politician', 'post'])
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Digest recipients: an explicit comma-separated env override, else every
     * admin user with an email address.
     *
     * @return Collection<int, string>
     */
    protected function recipients(): Collection
    {
        $env = (string) config('u9itus.marketing.drafting.digest_recipients', '');
        if (trim($env) !== '') {
            return collect(array_filter(array_map('trim', explode(',', $env))))
                ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL) !== false)
                ->values();
        }

        return User::admins()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->pluck('email')
            ->unique()
            ->values();
    }
}