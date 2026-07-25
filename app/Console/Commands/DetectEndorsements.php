<?php

namespace App\Console\Commands;

use App\Services\CandidateNewsService;
use Illuminate\Console\Command;

class DetectEndorsements extends Command
{
    protected $signature = 'candidates:detect-endorsements
        {--limit=300         : Max stored articles to (re)scan per run}
        {--politician-id=    : Scan only this politician}';

    protected $description = 'Backfill/re-scan already-stored verified news articles for real public endorsements (e.g. "Governor endorses").';

    public function handle(CandidateNewsService $newsService): int
    {
        $limit = (int) $this->option('limit');
        $politicianId = $this->option('politician-id') ? (int) $this->option('politician-id') : null;

        $result = $newsService->detectEndorsementsForStoredArticles($limit, $politicianId);

        $this->info("Scanned {$result['processed']} article(s) for endorsements.");

        return self::SUCCESS;
    }
}
