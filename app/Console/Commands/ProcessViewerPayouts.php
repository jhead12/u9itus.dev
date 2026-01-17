<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AdAssignment;
use App\Services\PaymentService;

class ProcessViewerPayouts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payouts:process-viewer';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process payouts for viewers with approved payments';

    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        parent::__construct();
        $this->paymentService = $paymentService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Processing viewer payouts...');

        $approvedAssignments = AdAssignment::where('payment_status', 'approved')
            ->whereNull('paid_at')
            ->get();

        if ($approvedAssignments->isEmpty()) {
            $this->info('No approved payments to process.');
            return 0;
        }

        $count = 0;
        foreach ($approvedAssignments as $assignment) {
            try {
                // Mark as paid (actual payout would happen via PayPal API)
                $assignment->update([
                    'payment_status' => 'paid',
                    'paid_at' => now(),
                ]);
                
                $count++;
            } catch (\Exception $e) {
                $this->error("Failed to process payout for assignment {$assignment->id}: {$e->getMessage()}");
            }
        }

        $this->info("Processed {$count} payout(s).");
        return 0;
    }
}

