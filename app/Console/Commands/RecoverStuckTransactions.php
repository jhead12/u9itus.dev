<?php

namespace App\Console\Commands;

use App\Models\CampaignTransaction;
use App\Models\PoliticianCredit;
use App\Services\CampaignBillingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecoverStuckTransactions extends Command
{
    protected $signature = 'billing:recover-stuck
                            {--dry-run : List affected transactions without applying credits}
                            {--force : Skip confirmation prompt (required for non-interactive environments like Railway)}
                            {--politician= : Limit recovery to a specific politician ID}';

    protected $description = 'Find succeeded Stripe transactions with no credit ledger entry and re-apply the credits.';

    public function handle(CampaignBillingService $billing): int
    {
        $dryRun      = $this->option('dry-run');
        $politicianId = $this->option('politician');

        // Find succeeded transactions that have no matching politician_credits row
        $query = CampaignTransaction::where('status', 'succeeded')
            ->whereNotNull('politician_id')
            ->whereDoesntHave('credits');

        if ($politicianId) {
            $query->where('politician_id', $politicianId);
        }

        $stuck = $query->get();

        if ($stuck->isEmpty()) {
            $this->info('No stuck transactions found.');
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Politician ID', 'PI ID', 'Amount', 'Created At'],
            $stuck->map(fn ($tx) => [
                $tx->id,
                $tx->politician_id,
                $tx->stripe_payment_intent_id,
                '$' . number_format($tx->amount, 2),
                $tx->created_at,
            ])
        );

        if ($dryRun) {
            $this->warn("Dry-run mode — no changes made. Re-run without --dry-run to apply credits.");
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Apply credits for {$stuck->count()} transaction(s)?", true)) {
            $this->warn('Aborted. Re-run with --force to skip this prompt in non-interactive environments.');
            return self::SUCCESS;
        }

        $recovered = 0;
        $failed    = 0;

        foreach ($stuck as $tx) {
            try {
                DB::transaction(function () use ($tx, $billing) {
                    // Reset to pending so finalizePaymentIntent passes the idempotency guard
                    $tx->status = 'pending';
                    $tx->save();

                    $billing->finalizePaymentIntent($tx->stripe_payment_intent_id);
                });

                $this->line("  ✓  TX #{$tx->id} — credited \${$tx->amount} to politician #{$tx->politician_id}");
                $recovered++;
            } catch (\Throwable $e) {
                $this->error("  ✗  TX #{$tx->id} — {$e->getMessage()}");
                Log::error("billing:recover-stuck failed for TX #{$tx->id}: " . $e->getMessage());
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Done. Recovered: {$recovered}  /  Failed: {$failed}");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
