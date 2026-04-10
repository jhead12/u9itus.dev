<?php

namespace App\Services;

use App\Enums\ViewPaymentStatus;
use App\Models\ViewSession;
use App\Models\Voter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayPalPayoutReconciliationService
{
    public function __construct(private readonly PayPalPayoutService $paypalService)
    {
    }

    public function reconcileBatchByReference(string $batchReference): array
    {
        $batchStatus = $this->paypalService->getBatchPayoutStatus($batchReference);

        $items = (array) ($batchStatus['items'] ?? []);
        if ($items === []) {
            return ['updated' => 0, 'pending' => 0, 'rejected' => 0, 'paid' => 0];
        }

        $updated = 0;
        $pendingCount = 0;
        $rejectedCount = 0;
        $paidCount = 0;

        foreach ($items as $item) {
            $itemStatus = strtoupper((string) ($item['transaction_status'] ?? $item['payout_item']['transaction_status'] ?? ''));
            $senderItemId = (string) ($item['payout_item']['sender_item_id'] ?? '');
            $payoutItemId = (string) ($item['payout_item_id'] ?? $item['payout_item']['payout_item_id'] ?? '');

            if ($senderItemId === '') {
                continue;
            }

            $result = $this->applyItemStatus(
                batchReference: $batchReference,
                senderItemId: $senderItemId,
                itemStatus: $itemStatus,
                payoutItemId: $payoutItemId,
            );

            $updated += $result['updated'];
            $pendingCount += $result['pending'];
            $rejectedCount += $result['rejected'];
            $paidCount += $result['paid'];
        }

        return [
            'updated' => $updated,
            'pending' => $pendingCount,
            'rejected' => $rejectedCount,
            'paid' => $paidCount,
        ];
    }

    public function reconcileSingleItemEvent(string $batchReference, string $itemStatus, ?string $payoutItemId = null): array
    {
        return $this->applyItemStatus(
            batchReference: $batchReference,
            senderItemId: $batchReference,
            itemStatus: strtoupper($itemStatus),
            payoutItemId: (string) ($payoutItemId ?? ''),
        );
    }

    private function applyItemStatus(string $batchReference, string $senderItemId, string $itemStatus, string $payoutItemId = ''): array
    {
        $finalState = $this->mapPayPalItemStatusToFinalState($itemStatus);

        $sessions = ViewSession::query()
            ->where('processor_executed', 'paypal')
            ->where(function ($query) use ($batchReference, $senderItemId) {
                $query->where('processor_reference', $batchReference)
                    ->orWhere('processor_reference', $senderItemId);
            })
            ->whereIn('payment_status', [
                ViewPaymentStatus::Pending->value,
                ViewPaymentStatus::Approved->value,
            ])
            ->get();

        if ($sessions->isEmpty()) {
            return ['updated' => 0, 'pending' => 0, 'rejected' => 0, 'paid' => 0];
        }

        if ($finalState === 'pending') {
            return ['updated' => 0, 'pending' => $sessions->count(), 'rejected' => 0, 'paid' => 0];
        }

        DB::transaction(function () use ($sessions, $finalState, $payoutItemId, $senderItemId, $itemStatus) {
            foreach ($sessions as $session) {
                $reference = $payoutItemId !== '' ? $payoutItemId : $senderItemId;
                $session->update([
                    'payment_status' => $finalState === 'paid'
                        ? ViewPaymentStatus::Paid
                        : ViewPaymentStatus::Rejected,
                    'paid_at' => $finalState === 'paid' ? now() : null,
                    'processor_reference' => $reference,
                ]);
            }

            if ($finalState === 'paid') {
                $sessionByVoter = $sessions->groupBy('voter_id');
                foreach ($sessionByVoter as $voterId => $voterSessions) {
                    /** @var Voter|null $voter */
                    $voter = Voter::find($voterId);
                    if (! $voter) {
                        continue;
                    }

                    $amount = (float) $voterSessions->sum('voter_payout_amount');
                    if ($amount <= 0) {
                        continue;
                    }

                    $voter->decrement('pending_earnings', $amount);
                    $voter->increment('total_earned', $amount);
                }
            }

            Log::info('PayPal payout item reconciled', [
                'item_status' => $itemStatus,
                'final_state' => $finalState,
                'session_count' => $sessions->count(),
            ]);
        });

        return [
            'updated' => $sessions->count(),
            'pending' => 0,
            'rejected' => $finalState === 'rejected' ? $sessions->count() : 0,
            'paid' => $finalState === 'paid' ? $sessions->count() : 0,
        ];
    }

    private function mapPayPalItemStatusToFinalState(string $status): string
    {
        return match (strtoupper($status)) {
            'SUCCESS', 'SUCCEEDED' => 'paid',
            'FAILED', 'RETURNED', 'UNCLAIMED', 'DENIED', 'BLOCKED' => 'rejected',
            default => 'pending',
        };
    }
}
