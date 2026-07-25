<?php

namespace App\Services\Marketing;

use App\Enums\DispatchStatus;

/**
 * Outcome of a single MarketingChannel::deliver() call.
 *
 * The dispatcher maps this onto the CampaignDispatch row: status, the
 * provider's message id (for later webhook reconciliation), per-recipient
 * cost in cents (for spend + disbursement reporting), and any error/skip
 * reason. Channels return `skipped` (not `failed`) when a recipient was
 * intentionally declined so the dispatcher doesn't mark the run as errored.
 */
final class DispatchResult
{
    public function __construct(
        public readonly DispatchStatus $status,
        public readonly ?string $providerMessageId = null,
        public readonly ?int $costCents = null,
        public readonly ?string $errorMessage = null,
    ) {
    }

    public static function dispatched(string $providerMessageId, ?int $costCents = null): self
    {
        return new self(DispatchStatus::Dispatched, $providerMessageId, $costCents);
    }

    public static function delivered(?string $providerMessageId = null, ?int $costCents = null): self
    {
        return new self(DispatchStatus::Delivered, $providerMessageId, $costCents);
    }

    public static function skipped(string $reason): self
    {
        return new self(DispatchStatus::Skipped, null, null, $reason);
    }

    public static function failed(string $error): self
    {
        return new self(DispatchStatus::Failed, null, null, $error);
    }
}