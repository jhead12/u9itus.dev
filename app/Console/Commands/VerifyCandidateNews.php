<?php

namespace App\Console\Commands;

use App\Services\CandidateNewsService;
use Illuminate\Console\Command;

class VerifyCandidateNews extends Command
{
    protected $signature = 'candidates:verify-news
        {--limit=500      : Max stored articles to verify}
        {--politician=    : Optional politician ID}
        {--dry-run        : Report only, do not write updates}';

    protected $description = 'Re-verify stored candidate news relevance and assign issue/topic keys.';

    public function handle(CandidateNewsService $newsService): int
    {
        $limit = max(1, (int) ($this->option('limit') ?? 500));
        $politicianId = $this->option('politician') !== null ? (int) $this->option('politician') : null;
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[dry-run] candidates:verify-news currently reports only from live pass counts.');
            $this->line('Skipping writes.');
            return self::SUCCESS;
        }

        $result = $newsService->reverifyStoredArticles($limit, $politicianId);

        $this->info(sprintf(
            'News verification done: processed=%d, verified=%d, rejected=%d',
            (int) ($result['processed'] ?? 0),
            (int) ($result['verified'] ?? 0),
            (int) ($result['rejected'] ?? 0),
        ));

        return self::SUCCESS;
    }
}
