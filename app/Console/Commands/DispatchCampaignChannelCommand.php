<?php

namespace App\Console\Commands;

use App\Jobs\DispatchCampaignChannel as DispatchJob;
use App\Models\CitizenCampaign;
use App\Models\PoliticalCampaign;
use Illuminate\Bus\Dispatcher as BusDispatcher;
use Illuminate\Console\Command;

/**
 * Dispatch a campaign to a marketing channel.
 *
 * Usage:
 *   php artisan marketing:dispatch political 42 email
 *   php artisan marketing:dispatch citizen 7 email --sync
 *
 * Defaults to queueing a DispatchCampaignChannel job (async). --sync runs the
 * dispatch inline so ops can watch the outcome without a worker running.
 *
 * House-style command alongside the enricher commands — the marketing system
 * is meant to be driven by jobs/workflows, but a manual command is the right
 * ops escape hatch and the cleanest way to exercise the channel end-to-end in
 * tests and local dev.
 */
class DispatchCampaignChannelCommand extends Command
{
    protected $signature = 'marketing:dispatch
                            {campaign_type : political|citizen}
                            {campaign_id : Campaign ID}
                            {channel : Marketing channel key (e.g. email)}
                            {--sync : Run inline instead of queueing a job}';

    protected $description = 'Dispatch a campaign to a marketing channel (queue or --sync)';

    public function handle(BusDispatcher $bus): int
    {
        $type = (string) $this->argument('campaign_type');
        $id = (int) $this->argument('campaign_id');
        $channel = (string) $this->argument('channel');

        if (! in_array($type, ['political', 'citizen'], true)) {
            $this->error('campaign_type must be "political" or "citizen".');
            return self::FAILURE;
        }

        $campaign = $type === 'political'
            ? PoliticalCampaign::find($id)
            : CitizenCampaign::find($id);

        if (! $campaign) {
            $this->error("Campaign {$type} #{$id} not found.");
            return self::FAILURE;
        }

        if ($this->option('sync')) {
            $this->info("Dispatching {$type} #{$id} → {$channel} (sync)…");
            $job = new DispatchJob($type, $id, $channel);
            $job->handle(app(\App\Services\Marketing\ChannelDispatcher::class));
            $this->info('Done. See campaign_dispatches for per-recipient status.');
            return self::SUCCESS;
        }

        $bus->dispatch(new DispatchJob($type, $id, $channel));
        $this->info("Queued dispatch: {$type} #{$id} → {$channel}.");
        return self::SUCCESS;
    }
}