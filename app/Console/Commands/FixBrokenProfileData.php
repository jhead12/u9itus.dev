<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Scans all published politician profiles for data that causes 500 errors
 * on the public profile page, and optionally repairs it.
 *
 * Usage:
 *   php artisan politicians:fix-broken-profiles          # dry-run (report only)
 *   php artisan politicians:fix-broken-profiles --fix    # write repairs to DB
 */
class FixBrokenProfileData extends Command
{
    protected $signature = 'politicians:fix-broken-profiles
        {--fix : Actually write repairs to the database (default is dry-run)}';

    protected $description = 'Find and fix politician profile records with malformed JSON or data that causes 500 errors.';

    public function handle(): int
    {
        $fix = (bool) $this->option('fix');

        $this->info('Scanning published politician profiles for bad data…');

        $rows = DB::table('politicians')
            ->where('page_published', true)
            ->where('is_active', true)
            ->select('id', 'slug', 'full_name', 'video_links', 'page_settings')
            ->get();

        $problems = [];

        foreach ($rows as $row) {
            $issues = [];

            // Check video_links JSON validity
            if ($row->video_links !== null && $row->video_links !== '' && $row->video_links !== 'null') {
                $decoded = json_decode($row->video_links, true);
                if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                    $issues['video_links'] = $row->video_links;
                }
            }

            // Check page_settings JSON validity
            if ($row->page_settings !== null && $row->page_settings !== '' && $row->page_settings !== 'null') {
                $decoded = json_decode($row->page_settings, true);
                if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                    $issues['page_settings'] = $row->page_settings;
                }
            }

            if (! empty($issues)) {
                $problems[] = ['row' => $row, 'issues' => $issues];
            }
        }

        if (empty($problems)) {
            $this->info('✓ No broken profile data found.');
            return self::SUCCESS;
        }

        $this->warn(count($problems) . ' profile(s) with bad data:');
        foreach ($problems as $p) {
            $this->line('  [' . $p['row']->id . '] /p/' . $p['row']->slug . ' (' . $p['row']->full_name . ')');
            foreach ($p['issues'] as $col => $val) {
                $this->line('    ' . $col . ': ' . substr((string) $val, 0, 80) . (strlen((string) $val) > 80 ? '…' : ''));
            }
        }

        if (! $fix) {
            $this->comment('Re-run with --fix to null out the malformed columns (profiles will still load, just without the bad data).');
            return self::SUCCESS;
        }

        $fixed = 0;
        foreach ($problems as $p) {
            $update = [];
            if (isset($p['issues']['video_links'])) {
                $update['video_links'] = null;
            }
            if (isset($p['issues']['page_settings'])) {
                $update['page_settings'] = null;
            }
            DB::table('politicians')->where('id', $p['row']->id)->update($update);
            $this->info('  Fixed: ' . $p['row']->slug);
            $fixed++;
        }

        $this->info("✓ Fixed {$fixed} record(s).");
        return self::SUCCESS;
    }
}
