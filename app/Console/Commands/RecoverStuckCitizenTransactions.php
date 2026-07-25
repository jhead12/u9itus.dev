<?php

namespace App\Console\Commands;

use App\Models\CitizenTransaction;
use App\Services\CitizenBillingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecoverStuckCitizenTransactions extends Command
{
    protected $signature = 'billing:recover-stuck-citizen
                            {--dry-run : List affected transactions without applying credits}
                            {--force : Skip confirmation prompt (required for non-interactive environments like Railway)}
                            {--citizen= : Limit recovery to a specific citizen ID}';

    protected $description = 'Find succeeded citizen Stripe transactions with no credit ledger entry and re-apply the credits.';

    public function handle(CitizenBillingService $billing): int
    {
        $dryRun     = $this->option('dry-run');
        $citizenId  = $this->option('citizen');

        // Find succeeded transactions that have no matching citizen_credits row
        $query = CitizenTransaction::where('status', 'succeeded')
            ->whereNotNull('citizen_id')
            ->whereDoesntHave('credits');

        if ($citizenId) {
            $query->where('citizen_id', $citizenId);
        }

        $stuck = $query->get();

        if ($stuck->isEmpty()) {
            $this->info('No stuck citizen transactions found.');
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Citizen ID', 'PI ID', 'Amount', 'Created At'],
            $stuck->map(fn ($tx) => [
                $tx->id,
                $tx->citizen_id,
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

                $this->line("  ✓  TX #{$tx->id} — credited \${$tx->amount} to citizen #{$tx->citizen_id}");
                $recovered++;
            } catch (\Throwable $e) {
                $this->error("  ✗  TX #{$tx->id} — {$e->getMessage()}");
                Log::error("billing:recover-stuck-citizen failed for TX #{$tx->id}: " . $e->getMessage());
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Done. Recovered: {$recovered}  /  Failed: {$failed}");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}