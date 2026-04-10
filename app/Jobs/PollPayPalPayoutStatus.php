<?php

namespace App\Jobs;

use App\Services\PayPalPayoutReconciliationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PollPayPalPayoutStatus implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public string $batchReference)
    {
    }

    public function handle(PayPalPayoutReconciliationService $reconciliationService): void
    {
        $result = $reconciliationService->reconcileBatchByReference($this->batchReference);

        Log::info('Polled PayPal payout batch status', [
            'batch_reference' => $this->batchReference,
            'result' => $result,
        ]);
    }
}
