<?php

namespace App\Console\Commands;

use App\Models\Politician;
use Illuminate\Console\Command;

class AdminDataHealth extends Command
{
    protected $signature = 'admin:data-health
        {--limit=10 : Maximum example record IDs to show for each issue}';

    protected $description = 'Check politician directory data for records likely to break public browsing.';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $publishedPoliticians = Politician::query()
            ->where('page_published', true)
            ->get(['id', 'slug', 'full_name']);

        $checks = [
            [
                'key' => 'published_missing_slug',
                'label' => 'Published politicians missing a usable slug',
                'matches' => $publishedPoliticians->filter(function (Politician $politician) {
                    return trim((string) $politician->slug) === '';
                }),
            ],
            [
                'key' => 'published_missing_name',
                'label' => 'Published politicians missing a usable full name',
                'matches' => $publishedPoliticians->filter(function (Politician $politician) {
                    return trim((string) $politician->full_name) === '';
                }),
            ],
        ];

        $issues = [];

        foreach ($checks as $check) {
            $count = $check['matches']->count();

            if ($count === 0) {
                continue;
            }

            $issues[] = [
                'key' => $check['key'],
                'label' => $check['label'],
                'count' => $count,
                'example_ids' => $check['matches']
                    ->sortBy('id')
                    ->take($limit)
                    ->pluck('id')
                    ->all(),
            ];
        }

        $unpublishedClaimedCount = Politician::query()
            ->whereNotNull('user_id')
            ->where('page_published', false)
            ->count();

        if ($issues === []) {
            $this->info('Data health check passed. No published politician directory issues detected.');

            if ($unpublishedClaimedCount > 0) {
                $this->line(sprintf(
                    'Info: %d claimed politician profile(s) are currently unpublished.',
                    $unpublishedClaimedCount
                ));
            }

            return self::SUCCESS;
        }

        $this->error('Data health check found politician directory issues.');

        foreach ($issues as $issue) {
            $exampleSuffix = $issue['example_ids'] === []
                ? ''
                : ' Examples: #' . implode(', #', $issue['example_ids']);

            $this->line(sprintf(
                '- %s [%s]: %d affected.%s',
                $issue['label'],
                $issue['key'],
                $issue['count'],
                $exampleSuffix
            ));
        }

        if ($unpublishedClaimedCount > 0) {
            $this->line(sprintf(
                'Info: %d claimed politician profile(s) are currently unpublished.',
                $unpublishedClaimedCount
            ));
        }

        return self::FAILURE;
    }
}
