<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LOCAL DEV ONLY — copies public candidate data from a production MySQL
 * database into the local dev database, so the map/panels have something
 * real to render instead of an empty local DB.
 *
 * Read-only against the source: only ever SELECTs from production. Refuses
 * to run outside APP_ENV=local as a safety rail against ever being invoked
 * against a deployed environment.
 *
 * Usage:
 *   php artisan dev:pull-prod-data --url="mysql://user:pass@host:port/db"
 *   php artisan dev:pull-prod-data --url=... --tables=politicians
 */
class DevPullProdMapData extends Command
{
    protected $signature = 'dev:pull-prod-data
        {--url= : Source MySQL DSN (mysql://user:pass@host:port/db)}
        {--tables=politicians,election_candidate_records : Comma-separated tables to copy}';

    protected $description = 'LOCAL ONLY: copy public candidate data from a production MySQL DB into the local dev DB.';

    /**
     * Columns to null out on import — either a foreign key to a production
     * `users` row that won't exist locally, or a token/credential with no
     * value for local UI testing.
     */
    private const SCRUB_COLUMNS = [
        'politicians' => [
            'user_id', 'claim_token', 'wallet_address', 'metoken_address',
            'verification_token', 'stripe_customer_id', 'earlybank_stripe_connect_account_id',
        ],
    ];

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Refusing to run outside the local environment.');
            return self::FAILURE;
        }

        $url = $this->option('url');
        if (! $url) {
            $this->error('Pass --url=mysql://user:pass@host:port/db');
            return self::FAILURE;
        }

        $parts = parse_url($url);
        $dbname = ltrim($parts['path'] ?? '', '/');
        $dsn = "mysql:host={$parts['host']};port={$parts['port']};dbname={$dbname};charset=utf8mb4";

        try {
            $pdo = new \PDO($dsn, $parts['user'] ?? null, $parts['pass'] ?? null);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\Throwable $e) {
            $this->error("Could not connect to source DB: {$e->getMessage()}");
            return self::FAILURE;
        }

        foreach (explode(',', (string) $this->option('tables')) as $table) {
            $table = trim($table);
            if ($table === '') {
                continue;
            }

            $this->info("Pulling {$table}...");

            $localColumns = Schema::getColumnListing($table);
            if (empty($localColumns)) {
                $this->warn("  Table {$table} doesn't exist locally — skipping. Run migrations first.");
                continue;
            }

            $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(\PDO::FETCH_ASSOC);
            $scrub = self::SCRUB_COLUMNS[$table] ?? [];

            $prepared = array_map(function (array $row) use ($localColumns, $scrub) {
                // Only keep columns that exist locally, and fill any
                // local-only columns the source row doesn't have with null.
                $filtered = array_intersect_key($row, array_flip($localColumns));
                foreach ($localColumns as $col) {
                    if (! array_key_exists($col, $filtered)) {
                        $filtered[$col] = null;
                    }
                }
                foreach ($scrub as $col) {
                    if (array_key_exists($col, $filtered)) {
                        $filtered[$col] = null;
                    }
                }
                return $filtered;
            }, $rows);

            DB::table($table)->delete();
            foreach (array_chunk($prepared, 200) as $chunk) {
                DB::table($table)->insert($chunk);
            }

            $this->info('  ' . count($prepared) . ' row(s) imported.');
        }

        return self::SUCCESS;
    }
}
