<?php

namespace App\Console\Commands;

use App\Services\PoliticalPaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessViewerPayouts extends Command
{
    protected $signature = 'payouts:process-viewer';

    protected $description = 'Process eligible voter payouts (scheduled or manual cron execution).';

    public function __construct(private readonly PoliticalPaymentService $paymentService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $results = $this->paymentService->processBatchPayouts(
                triggeredByAdminId: null,
                triggerSource: 'command',
            );

            $message = sprintf(
                'Viewer payouts processed: %d paid, $%.2f total, %d skipped.',
                (int) ($results['processed'] ?? 0),
                (float) ($results['total_paid'] ?? 0),
                (int) ($results['skipped'] ?? 0),
            );

            $this->info($message);
            Log::info('payouts:process-viewer completed', $results);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Viewer payouts failed: ' . $e->getMessage());
            Log::error('payouts:process-viewer failed', [
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }
    }
}
