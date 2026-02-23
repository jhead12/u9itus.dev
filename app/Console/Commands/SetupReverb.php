<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * reverb:setup
 *
 * Interactive Phase 11 setup wizard.
 *
 * Generates cryptographically random Reverb keys, appends them to .env,
 * and prints the matching VITE_ exports needed in .env so the frontend
 * Echo client can connect.
 *
 * Usage:
 *   php artisan reverb:setup
 *   php artisan reverb:setup --non-interactive   # auto-generates everything
 *
 * After running this command:
 *   1. Run `php artisan config:clear`
 *   2. Run `npm install` (once to pull laravel-echo + pusher-js)
 *   3. Run `npm run build` (or `npm run dev`)
 *   4. Start the Reverb server: `php artisan reverb:start`
 */
class SetupReverb extends Command
{
    protected $signature = 'reverb:setup
                            {--non-interactive : Generate all values automatically without prompting}';

    protected $description = 'Generate Reverb credentials and append them to .env (Phase 11 setup)';

    public function handle(): int
    {
        $this->info('');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('  U9itus — Phase 11: Reverb WebSocket Setup');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('');

        $nonInteractive = $this->option('non-interactive');

        // Generate values
        $appId     = Str::lower(Str::random(8));
        $appKey    = Str::random(32);
        $appSecret = Str::random(32);

        if (! $nonInteractive) {
            $host   = $this->ask('Reverb host (leave blank for localhost)', 'localhost');
            $port   = $this->ask('Reverb port', '8080');
            $scheme = $this->choice('Reverb scheme', ['http', 'https'], 0);
        } else {
            $host   = 'localhost';
            $port   = '8080';
            $scheme = 'http';
        }

        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            $this->error('.env file not found. Run: cp .env.example .env && php artisan key:generate');
            return self::FAILURE;
        }

        $envContent = file_get_contents($envPath);

        // Check for existing REVERB_ section to avoid duplication
        if (str_contains($envContent, 'REVERB_APP_ID=')) {
            if (! $this->confirm('REVERB_* keys already exist in .env. Overwrite them?', false)) {
                $this->warn('Skipped — existing keys preserved.');
                return self::SUCCESS;
            }
            // Strip the existing block
            $envContent = preg_replace('/\n?# --- Reverb.*?# --- End Reverb[^\n]*/s', '', $envContent);
        }

        $block = <<<ENV

# --- Reverb WebSocket Server (Phase 11) ---
BROADCAST_DRIVER=reverb
REVERB_APP_ID={$appId}
REVERB_APP_KEY={$appKey}
REVERB_APP_SECRET={$appSecret}
REVERB_HOST={$host}
REVERB_PORT={$port}
REVERB_SCHEME={$scheme}

# Vite exposes these to the browser (Echo client)
VITE_REVERB_APP_KEY={$appKey}
VITE_REVERB_HOST={$host}
VITE_REVERB_PORT={$port}
VITE_REVERB_SCHEME={$scheme}
# --- End Reverb ---
ENV;

        file_put_contents($envPath, $envContent . $block);

        $this->info('');
        $this->table(
            ['Variable', 'Value'],
            [
                ['REVERB_APP_ID',     $appId],
                ['REVERB_APP_KEY',    $appKey],
                ['REVERB_APP_SECRET', str_repeat('*', strlen($appSecret))],
                ['REVERB_HOST',       $host],
                ['REVERB_PORT',       $port],
                ['REVERB_SCHEME',     $scheme],
            ]
        );

        $this->info('');
        $this->info('✅  Credentials appended to .env');
        $this->info('');
        $this->line('Next steps:');
        $this->line('  1. php artisan config:clear');
        $this->line('  2. composer require laravel/reverb');
        $this->line('  3. npm install && npm run build');
        $this->line('  4. php artisan reverb:start');
        $this->info('');

        return self::SUCCESS;
    }
}
