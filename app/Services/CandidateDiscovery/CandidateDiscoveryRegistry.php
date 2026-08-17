<?php

namespace App\Services\CandidateDiscovery;

use App\Contracts\CandidateDiscoverySource;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * Key -> class map of discovery sources, resolved through the container
 * (mirrors ChannelRegistry's resolve-by-class-string approach). Adding a new
 * discovery resource later is one entry here plus a class implementing
 * CandidateDiscoverySource — nothing else in the pipeline changes.
 */
class CandidateDiscoveryRegistry
{
    /** @var array<string, class-string<CandidateDiscoverySource>> */
    protected array $sources = [
        'rss_google_news' => RssCandidateDiscoverySource::class,
    ];

    public function resolve(string $key): ?CandidateDiscoverySource
    {
        $class = $this->sources[$key] ?? null;
        if ($class === null) {
            Log::warning('CandidateDiscoveryRegistry: unknown source key', ['key' => $key]);
            return null;
        }

        return App::make($class);
    }

    /**
     * @param  array<int, string>  $keys  Empty = all registered sources.
     * @return array<int, CandidateDiscoverySource>
     */
    public function resolveMany(array $keys = []): array
    {
        $wanted = $keys === [] ? array_keys($this->sources) : $keys;

        return array_values(array_filter(array_map(fn (string $key) => $this->resolve($key), $wanted)));
    }
}
