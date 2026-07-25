<?php

namespace App\Services\Marketing;

use App\Models\CampaignDispatch;

/**
 * Immutable payload handed to a MarketingChannel for one recipient.
 *
 * Built by ChannelDispatcher from a CampaignDispatch row so the channel
 * receives everything it needs (campaign copy, recipient contact, a stable
 * idempotency key) without re-querying the DB, and so a retried queue job
 * resolves to the same payload.
 */
final class DispatchPayload
{
    public function __construct(
        public readonly int $dispatchId,
        public readonly string $dispatchUuid,
        /** @var array<string, mixed> */
        public readonly array $recipient,
        /** @var array<string, mixed> */
        public readonly array $campaign,
        /** @var array<string, mixed> */
        public readonly array $channelConfig,
        public readonly ?string $idempotencyKey = null,
    ) {
    }

    public static function fromDispatch(CampaignDispatch $dispatch): self
    {
        $voter = $dispatch->voter;

        return new self(
            dispatchId: $dispatch->id,
            dispatchUuid: $dispatch->uuid,
            recipient: array_filter([
                'voter_id'   => $voter?->id,
                'uuid'       => $voter?->uuid,
                'name'       => $voter?->full_name,
                'email'      => $voter?->email,
                'phone'      => $voter?->phone,
                'state'      => $voter?->state,
                'city'       => $voter?->city,
                'zip_code'   => $voter?->zip_code,
                'district'   => $voter?->congressional_district,
            ], fn ($v) => $v !== null && $v !== ''),
            campaign: array_filter([
                'id'             => $dispatch->campaign?->id,
                'uuid'           => $dispatch->campaign?->uuid,
                'title'          => $dispatch->campaign?->title,
                'message_summary'=> $dispatch->campaign?->message_summary,
                'campaign_type'  => $dispatch->campaign?->campaign_type?->value,
                'kind'           => $dispatch->campaign_type, // political | citizen
            ], fn ($v) => $v !== null && $v !== ''),
            channelConfig: $dispatch->marketingChannel?->config ?? [],
            idempotencyKey: 'dispatch:' . $dispatch->uuid,
        );
    }
}