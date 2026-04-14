<?php

namespace App\Console\Commands;

use Database\Seeders\RoleSeeder;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;

class EnsureRoles extends Command
{
    protected $signature = 'roles:ensure';

    protected $description = 'Idempotently provision the core application roles (admin, politician, voter). '
        . 'Safe to re-run on every deploy — uses firstOrCreate throughout.';

    public function handle(): int
    {
        try {
            $this->call(RoleSeeder::class);
            $this->info('roles:ensure — core roles verified.');
        } catch (QueryException $e) {
            // If the roles/permissions tables haven't been migrated yet (e.g. first deploy),
            // log a warning rather than failing the whole startup sequence.
            $this->warn('roles:ensure — roles table not ready yet, skipping: ' . $e->getMessage());
        }

        return self::SUCCESS;
    }
}
