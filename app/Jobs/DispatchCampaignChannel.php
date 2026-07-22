<?php

namespace App\Jobs;

use App\Models\CitizenCampaign;
use App\Models\PoliticalCampaign;
use App\Services\Marketing\ChannelDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Dispatches one campaign to one marketing channel asynchronously. Chunked
 * audience resolution + per-recipient sends happen off the request path.
 *
 * Idempotent on (campaign, channel): ChannelDispatcher reuses existing
 * CampaignDispatch rows, so re-dispatching the same job is a no-op for any
 * recipient already sent/skipped.
 */
class DispatchCampaignChannel implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    public function __construct(
        public readonly string $campaignType, // 'political' | 'citizen'
        public readonly int $campaignId,
        public readonly string $channelKey,
    ) {
    }

    public function handle(ChannelDispatcher $dispatcher): void
    {
        $campaign = $this->resolveCampaign();
        if (! $campaign) {
            Log::warning('DispatchCampaignChannel: campaign not found', [
                'type' => $this->campaignType,
                'id'   => $this->campaignId,
            ]);
            return;
        }

        $summary = $dispatcher->dispatchCampaign($campaign, $this->channelKey);

        Log::info('DispatchCampaignChannel: complete', array_merge($summary, [
            'type'    => $this->campaignType,
            'id'      => $this->campaignId,
            'channel' => $this->channelKey,
        ]));
    }

    protected function resolveCampaign(): ?object
    {
        return match ($this->campaignType) {
            'political' => PoliticalCampaign::find($this->campaignId),
            'citizen'   => CitizenCampaign::find($this->campaignId),
            default     => null,
        };
    }
}