<?php

namespace App\Support;

use Illuminate\Console\Command;

/**
 * File caches cannot be invalidated across machines. The state-candidates endpoint
 * bypasses them; a shared store allows it to cache safely across cleanup runs.
 */
class MapCacheNotice
{
    public static function afterWrite(Command $command): void
    {
        if (config('cache.default') !== 'file') {
            return;
        }

        $command->line('Cache store is "file": map candidate data is read fresh. Use a shared CACHE_STORE=database for caching across web and cleanup processes.');
    }
}
