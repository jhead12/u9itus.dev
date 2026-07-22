<?php

namespace App\Services\Marketing;

use App\Contracts\MarketingChannel as MarketingChannelContract;
use App\Enums\ChannelStatus;
use App\Models\MarketingChannel;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * Resolves a channel key (or a MarketingChannel registry row) to a live
 * App\Contracts\MarketingChannel instance.
 *
 * First-party channels are resolved from the registry row's provider_class
 * out of the container (so constructor dependencies — e.g. ZipCentroidService,
 * mail config — are autowired). Unknown / unconfigured channels resolve to
 * null and the dispatcher records a `failed` row rather than throwing.
 *
 * Third-party webhook channels (future) will resolve to a WebhookChannel
 * adapter that reads the row's config endpoint, sharing this same lookup.
 */
class ChannelRegistry
{
    /**
     * @return MarketingChannelContract|null  the plugin instance, or null if
     *         the channel is disabled, missing, or its class can't resolve.
     */
    public function resolve(string $key): ?MarketingChannelContract
    {
        $row = MarketingChannel::where('key', $key)->first();
        if (! $row) {
            Log::warning('ChannelRegistry: unknown channel key', ['key' => $key]);
            return null;
        }

        return $this->resolveRow($row);
    }

    public function resolveRow(MarketingChannel $row): ?MarketingChannelContract
    {
        if ($row->status !== ChannelStatus::Active) {
            Log::info('ChannelRegistry: channel not active', ['key' => $row->key]);
            return null;
        }

        $class = $row->provider_class;
        if (! $class || ! class_exists($class)) {
            Log::warning('ChannelRegistry: provider class missing', [
                'key'   => $row->key,
                'class' => $class,
            ]);
            return null;
        }

        try {
            $instance = App::make($class);
        } catch (\Throwable $e) {
            Log::warning('ChannelRegistry: could not build channel', [
                'key'   => $row->key,
                'class' => $class,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        if (! $instance instanceof MarketingChannelContract) {
            Log::warning('ChannelRegistry: provider does not implement MarketingChannel', [
                'key'   => $row->key,
                'class' => $class,
            ]);
            return null;
        }

        return $instance;
    }
}