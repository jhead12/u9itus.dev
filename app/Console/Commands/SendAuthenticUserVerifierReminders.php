<?php

namespace App\Console\Commands;

use App\Mail\AuthenticUserVerifierReminderMail;
use App\Models\Voter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendAuthenticUserVerifierReminders extends Command
{
    protected $signature = 'notifications:authentic-user-verifier-reminders
                            {--dry-run : Print recipients without queueing emails}
                            {--limit=0 : Maximum recipients to process}';

    protected $description = 'Notify legacy voter accounts to complete Authentic User Verifier via Stripe Connect.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(0, (int) $this->option('limit'));

        $query = Voter::query()
            ->with('user:id,first_name,last_name,name,email,user_type,kyc_status,kyc_document_path,idme_verified_at')
            ->whereHas('user', function ($q) {
                $q->where('user_type', 'voter')
                    ->where(function ($legacy) {
                        $legacy->whereNotNull('kyc_document_path')
                            ->orWhereNotNull('idme_verified_at')
                            ->orWhereIn('kyc_status', ['pending', 'approved', 'rejected']);
                    });
            })
            ->where(function ($stripe) {
                $stripe->whereNull('stripe_account_id')
                    ->orWhere('stripe_account_id', '')
                    ->orWhereNull('stripe_account_status')
                    ->orWhere('stripe_account_status', '!=', 'active');
            })
            ->orderBy('id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $voters = $query->get();

        if ($voters->isEmpty()) {
            $this->info('No legacy voters require Authentic User Verifier reminders.');
            return self::SUCCESS;
        }

        $sent = 0;
        $failed = 0;

        // The start route is POST-only (state-mutating). Email links must point
        // to the earnings page, where the banner exposes a CSRF-protected form.
        $startUrl = route('voter.earnings');
        $payoutUrl = route('voter.earnings');

        foreach ($voters as $voter) {
            $user = $voter->user;
            $email = (string) ($user?->email ?: $voter->email ?: '');
            if ($email === '') {
                continue;
            }

            if ($dryRun) {
                $this->line('[DRY] ' . $email . ' (voter_id=' . $voter->id . ')');
                $sent++;
                continue;
            }

            try {
                Mail::to($email)->queue(new AuthenticUserVerifierReminderMail(
                    user: $user,
                    payoutUrl: $payoutUrl,
                    startUrl: $startUrl,
                ));
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('Failed to queue Authentic User Verifier reminder', [
                    'voter_id' => $voter->id,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info('Done - ' . $sent . ' reminders queued, ' . $failed . ' failed.');

        return self::SUCCESS;
    }
}
