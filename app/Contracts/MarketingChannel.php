<?php

namespace App\Contracts;

use App\Enums\ChannelType;
use App\Services\Marketing\DispatchPayload;
use App\Services\Marketing\DispatchResult;

/**
 * A marketing channel plugin — one delivery mechanism a campaign can use to
 * reach an audience. The first-party `email` channel ships in this repo; the
 * marketplace vision is that third-party channels (direct mail, sms, digital
 * ads, door-knock canvassing, third-party webhooks) implement this same
 * contract and register through the marketing_channels table.
 *
 * The contract deliberately mirrors the enricher house style: a `key()` slug,
 * an `isConfigured()` guard (channels with missing API keys degrade to
 * `queued→skipped` rather than throwing), and a single `deliver()` entry
 * point that never throws — failures route into DispatchResult so the
 * dispatcher can persist them on the campaign_dispatches row.
 *
 * A channel MUST be idempotent on `payload->dispatchId` so a retried queue
 * job never double-sends. Channels MAY decline a recipient (territory check,
 * suppression list, missing address) by returning a `skipped` result.
 */
interface MarketingChannel
{
    /** Stable slug matching marketing_channels.key (e.g. "email"). */
    public function key(): string;

    /** Which ChannelType this plugin implements. */
    public function channelType(): ChannelType;

    /** Whether the channel has the credentials/config it needs to send. */
    public function isConfigured(): bool;

    /**
     * Deliver one payload to one recipient. Never throw — capture any error
     * as a `failed` DispatchResult so the caller records provenance.
     */
    public function deliver(DispatchPayload $payload): DispatchResult;
}