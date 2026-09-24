<?php

namespace App\Console\Commands;

use App\Services\CandidateNewsService;
use Illuminate\Console\Command;

class VerifyCandidateNews extends Command
{
    protected $signature = 'candidates:verify-news
        {--limit=500      : Max stored articles to verify}
        {--politician=    : Optional politician ID}
        {--state=          : Two-letter state code — limit to one state}
        {--unchecked      : Only rows that never went through the relevance gate}
        {--dry-run        : Report what would change without writing}';

    protected $description = 'Re-verify stored candidate news relevance and assign issue/topic keys.';

    public function handle(CandidateNewsService $newsService): int
    {
        $limit = max(1, (int) ($this->option('limit') ?? 500));
        $politicianId = $this->option('politician') !== null ? (int) $this->option('politician') : null;
        $state = $this->option('state') ? strtoupper(trim((string) $this->option('state'))) : null;
        $dryRun = (bool) $this->option('dry-run');

        $result = $newsService->reverifyStoredArticles(
            $limit,
            $politicianId,
            $state,
            onlyUnchecked: (bool) $this->option('unchecked'),
            dryRun: $dryRun,
        );

        $this->info(sprintf(
            '%sNews verification done: processed=%d, verified=%d, rejected=%d, newly_rejected=%d, newly_verified=%d',
            $dryRun ? '[dry-run] ' : '',
            $result['processed'],
            $result['verified'],
            $result['rejected'],
            $result['newly_rejected'],
            $result['newly_verified'],
        ));

        if ($dryRun && $result['samples'] !== []) {
            $this->table(['id', 'candidate', 'to', 'reason', 'headline'], $result['samples']);
        }

        return self::SUCCESS;
    }
}
