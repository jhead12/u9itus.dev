<?php

namespace App\Console\Commands;

use App\Models\Voter;
use App\Services\StripeConnectService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AuditStripeConnectAccounts extends Command
{
    protected $signature = 'stripe:audit-connect-accounts
                            {--dry-run : List stale accounts without clearing them}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Find voters whose stripe_account_id belongs to a different platform and clear the stale ID so they can re-onboard.';

    public function handle(StripeConnectService $connect): int
    {
        if (! $connect->isConfigured()) {
            $this->error('Stripe is not configured. Set STRIPE_SECRET and retry.');
            return self::FAILURE;
        }

        $dryRun = $this->option('dry-run');

        $voters = Voter::whereNotNull('stripe_account_id')->get();

        if ($voters->isEmpty()) {
            $this->info('No voters with a stripe_account_id found.');
            return self::SUCCESS;
        }

        $this->info("Checking {$voters->count()} voter(s) with a stripe_account_id…");

        $stale   = [];
        $checked = 0;

        foreach ($voters as $voter) {
            $checked++;
            $this->output->write("\r  Checked {$checked}/{$voters->count()}…");

            try {
                $connect->getAccountStatus($voter);
                // getAccountStatus throws on retrieval errors; if it returns we know
                // the account exists on this platform.
            } catch (\Stripe\Exception\InvalidRequestException $e) {
                $msg = strtolower($e->getMessage());
                if (
                    str_contains($msg, 'no such account') ||
                    str_contains($msg, 'no_such_account') ||
                    str_contains($msg, 'not connected to your platform') ||
                    str_contains($msg, 'account that is not connected')
                ) {
                    $stale[] = [
                        'voter_id'          => $voter->id,
                        'stripe_account_id' => $voter->stripe_account_id,
                        'email'             => $voter->email ?? '(no email)',
                        'error'             => $e->getMessage(),
                    ];
                } else {
                    // Unexpected Stripe error — log and skip rather than nuke the record.
                    Log::warning('stripe:audit-connect-accounts: unexpected error retrieving account', [
                        'voter_id'          => $voter->id,
                        'stripe_account_id' => $voter->stripe_account_id,
                        'error'             => $e->getMessage(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('stripe:audit-connect-accounts: error checking voter', [
                    'voter_id' => $voter->id,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();

        if (empty($stale)) {
            $this->info('All stripe_account_id values are valid on this platform. Nothing to clear.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(
            ['Voter ID', 'Stripe Account ID', 'Email'],
            array_map(fn ($r) => [$r['voter_id'], $r['stripe_account_id'], $r['email']], $stale)
        );

        $count = count($stale);
        $this->warn("{$count} stale account(s) found.");

        if ($dryRun) {
            $this->info('--dry-run: no changes made.');
            return self::SUCCESS;
        }

        if (
            ! $this->option('force') &&
            ! $this->confirm("Clear {$count} stale stripe_account_id(s) and reset stripe_account_status + is_verified?")
        ) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        $cleared = 0;
        foreach ($stale as $row) {
            Voter::whereKey($row['voter_id'])->update([
                'stripe_account_id'     => null,
                'stripe_account_status' => null,
                'is_verified'           => false,
            ]);

            if ($voter = Voter::find($row['voter_id'])) {
                $voter->user?->update(['is_verified' => false]);
            }

            Log::info('stripe:audit-connect-accounts: cleared stale stripe_account_id', [
                'voter_id'              => $row['voter_id'],
                'cleared_account_id'    => $row['stripe_account_id'],
            ]);

            $cleared++;
        }

        $this->info("Cleared {$cleared} stale account(s). Affected voters will re-onboard on their next Authentic User Verifier attempt.");

        return self::SUCCESS;
    }
}
