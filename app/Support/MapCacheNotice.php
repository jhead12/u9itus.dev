<?php

namespace App\Support;

use Illuminate\Console\Command;

/**
 * The map's per-state payload is cached for an hour. With the "file" cache store each
 * server keeps its own copy, so a command run from a laptop (railway run) can only clear
 * its own — the live site keeps the old payload until the hour is up.
 */
class MapCacheNotice
{
    public static function afterWrite(Command $command): void
    {
        if (config('cache.default') !== 'file') {
            return;
        }

        $command->warn('Cache store is "file": the live site keeps its own copy of the map for up to an hour. Set CACHE_STORE=database on Railway so commands run from here refresh it at once.');
    }
}
