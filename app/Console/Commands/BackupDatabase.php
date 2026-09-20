<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Dump the MySQL database to a (gzipped) SQL file.
 *
 * It runs `mysqldump` from wherever the command runs, so to land a prod
 * backup on your own drive run it locally through Railway:
 *   railway run php artisan db:backup --path=/Volumes/PRO-BLADE/backups --keep=14
 *
 * Restore with: gunzip < file.sql.gz | mysql -h HOST -u USER -p DB
 */
class BackupDatabase extends Command
{
    protected $signature = 'db:backup
        {--path= : Directory to write into (default: storage/app/backups)}
        {--keep=0 : Keep only the newest N backups in that directory (0 = keep everything)}
        {--no-gzip : Write plain .sql instead of .sql.gz}';

    protected $description = 'Dump the database to a gzipped SQL file (uses mysqldump).';

    public function handle(): int
    {
        $config = config('database.connections.'.config('database.default'));

        if (! in_array($config['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            $this->error('db:backup only supports MySQL/MariaDB connections (current driver: '.($config['driver'] ?? 'unknown').').');

            return self::FAILURE;
        }

        $mysqldump = (new ExecutableFinder)->find('mysqldump');
        if (! $mysqldump) {
            $this->error('mysqldump was not found on PATH. Install the MySQL client tools first.');

            return self::FAILURE;
        }

        $dir = rtrim((string) ($this->option('path') ?: storage_path('app/backups')), '/');
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            $this->error("Could not create {$dir}.");

            return self::FAILURE;
        }

        $gzip = ! $this->option('no-gzip');
        $file = sprintf('%s/%s-%s.sql%s', $dir, $config['database'], now()->format('Y-m-d_His'), $gzip ? '.gz' : '');

        $process = new Process([
            $mysqldump,
            '--host='.$config['host'],
            '--port='.($config['port'] ?? 3306),
            '--user='.$config['username'],
            '--single-transaction',
            '--quick',
            '--no-tablespaces',
            '--routines',
            '--triggers',
            $config['database'],
        ], null, ['MYSQL_PWD' => (string) ($config['password'] ?? '')], null, null);

        $out = $gzip ? gzopen($file, 'wb9') : fopen($file, 'wb');
        if (! $out) {
            $this->error("Could not open {$file} for writing.");

            return self::FAILURE;
        }

        $this->line("Dumping {$config['database']} from {$config['host']} …");

        $write = $gzip ? 'gzwrite' : 'fwrite';
        $process->run(function (string $type, string $buffer) use ($out, $write) {
            if ($type === Process::OUT) {
                $write($out, $buffer);
            }
        });
        $gzip ? gzclose($out) : fclose($out);

        if (! $process->isSuccessful()) {
            @unlink($file);
            $this->error('mysqldump failed: '.trim($process->getErrorOutput()));

            return self::FAILURE;
        }

        $this->info(sprintf('Wrote %s (%s)', $file, $this->humanSize((int) filesize($file))));

        $this->prune($dir, (string) $config['database'], (int) $this->option('keep'));

        return self::SUCCESS;
    }

    private function prune(string $dir, string $database, int $keep): void
    {
        if ($keep < 1) {
            return;
        }

        $old = glob("{$dir}/{$database}-*.sql*") ?: [];
        rsort($old); // names embed a sortable timestamp, newest first

        foreach (array_slice($old, $keep) as $file) {
            if (@unlink($file)) {
                $this->line('Pruned '.basename($file));
            }
        }
    }

    private function humanSize(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB'] as $i => $unit) {
            if ($bytes < 1024 ** ($i + 1) || $unit === 'GB') {
                return round($bytes / 1024 ** $i, 1).' '.$unit;
            }
        }

        return $bytes.' B';
    }
}
