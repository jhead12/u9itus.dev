<?php

namespace App\Console\Commands;

use App\Models\Voter;
use App\Services\StripeConnectService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackfillStripeConnectBusinessProfile extends Command
{
    protected $signature = 'stripe:backfill-connect-business-profile
                            {--dry-run : List accounts that would be updated without changing them}';

    protected $description = 'Set the platform default business_profile (url + product_description) on existing voter Connect accounts that are missing it.';

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

        $updated = 0;
        $checked = 0;

        foreach ($voters as $voter) {
            $checked++;
            $this->output->write("\r  Checked {$checked}/{$voters->count()}…");

            try {
                if ($dryRun) {
                    continue;
                }

                if ($connect->backfillBusinessProfile($voter)) {
                    $updated++;
                    Log::info('stripe:backfill-connect-business-profile: updated business_profile', [
                        'voter_id' => $voter->id,
                        'stripe_account_id' => $voter->stripe_account_id,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('stripe:backfill-connect-business-profile: error updating voter', [
                    'voter_id' => $voter->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();

        if ($dryRun) {
            $this->info("--dry-run: would have checked {$voters->count()} account(s) for a missing business_profile.");
            return self::SUCCESS;
        }

        $this->info("Updated {$updated} account(s). The rest already had a business_profile set.");

        return self::SUCCESS;
    }
}
