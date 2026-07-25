<?php

namespace App\Services\Marketing;

use App\Enums\DispatchStatus;
use App\Models\CampaignChannel;
use App\Models\CampaignDispatch;
use App\Models\MarketingChannel;
use App\Models\Voter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates one (campaign × channel) send: resolve the audience, create a
 * queued CampaignDispatch row per recipient, then hand each to the channel and
 * record the result. Designed to run from a queued job (DispatchCampaignChannel)
 * so the per-recipient loop is async and resumable.
 *
 * The dispatch UUID is the idempotency key across the whole flow — re-running a
 * job for the same (campaign, channel) pair finds existing non-terminal rows
 * and skips re-creating them, so a retry never double-sends.
 */
class ChannelDispatcher
{
    public function __construct(
        protected AudienceService $audience,
        protected ChannelRegistry $registry,
    ) {
    }

    /**
     * @param  Model  $campaign  PoliticalCampaign|CitizenCampaign
     * @param  string  $channelKey  marketing_channels.key
     * @return array{dispatched:int, skipped:int, failed:int, total:int}
     */
    public function dispatchCampaign(Model $campaign, string $channelKey): array
    {
        if (! config('u9itus.marketing.enabled', true)) {
            Log::info('ChannelDispatcher: marketing disabled, skipping dispatch', [
                'channel' => $channelKey,
            ]);
            return ['dispatched' => 0, 'skipped' => 0, 'failed' => 0, 'total' => 0];
        }

        $channelRow = MarketingChannel::where('key', $channelKey)->first();
        if (! $channelRow) {
            Log::warning('ChannelDispatcher: channel not registered', ['key' => $channelKey]);
            return ['dispatched' => 0, 'skipped' => 0, 'failed' => 0, 'total' => 0];
        }

        $plugin = $this->registry->resolveRow($channelRow);
        if ($plugin === null) {
            Log::warning('ChannelDispatcher: channel not resolvable', ['key' => $channelKey]);
            return ['dispatched' => 0, 'skipped' => 0, 'failed' => 0, 'total' => 0];
        }

        if (! $plugin->isConfigured()) {
            Log::info('ChannelDispatcher: channel not configured, skipping campaign', [
                'key' => $channelKey,
            ]);
            return ['dispatched' => 0, 'skipped' => 0, 'failed' => 0, 'total' => 0];
        }

        $summary = ['dispatched' => 0, 'skipped' => 0, 'failed' => 0, 'total' => 0];

        $this->audience->forCampaign($campaign)
            ->select('id')
            ->chunkById(500, function ($voterIds) use ($campaign, $channelRow, $plugin, &$summary): void {
                foreach ($voterIds as $row) {
                    $voter = Voter::find($row->id);
                    if (! $voter) {
                        continue;
                    }

                    $summary['total']++;
                    $this->dispatchOne($campaign, $channelRow, $voter, $plugin, $summary);
                }
            });

        return $summary;
    }

    /**
     * Create (or reuse, for idempotency) the CampaignDispatch row and send it
     * through the channel. Wrapped in a transaction so the row and the send
     * outcome are consistent.
     *
     * @param  array{dispatched:int, skipped:int, failed:int, total:int}  $summary
     */
    protected function dispatchOne(
        Model $campaign,
        MarketingChannel $channelRow,
        Voter $voter,
        \App\Contracts\MarketingChannel $plugin,
        array &$summary,
    ): void {
        DB::transaction(function () use ($campaign, $channelRow, $voter, $plugin, &$summary): void {
            // Idempotency: one row per (campaign, channel, voter). A prior
            // terminal attempt is left as-is; only create if absent.
            $existing = CampaignDispatch::where('campaign_type', $campaign->getMorphClass())
                ->where('campaign_id', $campaign->id)
                ->where('marketing_channel_id', $channelRow->id)
                ->where('voter_id', $voter->id)
                ->lockForUpdate()
                ->first();

            if ($existing && $existing->status !== DispatchStatus::Queued) {
                // Already sent/skipped — don't re-send.
                $summary['total']--; // we incremented optimistically above
                return;
            }

            $dispatch = $existing ?? CampaignDispatch::create([
                'campaign_type'       => $campaign->getMorphClass(),
                'campaign_id'         => $campaign->id,
                'marketing_channel_id' => $channelRow->id,
                'voter_id'            => $voter->id,
                'channel_type'        => $channelRow->channel_type->value,
                'status'              => DispatchStatus::Queued,
                'payload'              => null,
            ]);

            // Avoid 3 re-queries per recipient: the models are already in scope.
            $dispatch->setRelation('campaign', $campaign);
            $dispatch->setRelation('voter', $voter);
            $dispatch->setRelation('marketingChannel', $channelRow);
            $payload = DispatchPayload::fromDispatch($dispatch);
            $result = $plugin->deliver($payload);

            $dispatch->update([
                'status'              => $result->status,
                'provider_message_id' => $result->providerMessageId,
                'cost_cents'          => $result->costCents,
                'error_message'       => $result->errorMessage,
                'dispatched_at'       => in_array($result->status, [DispatchStatus::Dispatched, DispatchStatus::Delivered]) ? now() : null,
                'delivered_at'        => $result->status === DispatchStatus::Delivered ? now() : null,
                'bounced_at'          => $result->status === DispatchStatus::Bounced ? now() : null,
            ]);

            match ($result->status) {
                DispatchStatus::Dispatched, DispatchStatus::Delivered => $summary['dispatched']++,
                DispatchStatus::Skipped => $summary['skipped']++,
                default => $summary['failed']++,
            };
        });
    }
}