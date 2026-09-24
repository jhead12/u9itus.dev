<?php

namespace App\Console\Commands;

use App\Models\CandidateNewsArticle;
use App\Services\IssueClassifierService;
use Illuminate\Console\Command;

/**
 * Re-runs issue tagging over stored verified articles, e.g. after topic keywords
 * change. New articles are tagged at fetch time; this catches up the backlog.
 *
 * Usage:
 *   php artisan candidates:retag-news-topics --dry-run
 *   php artisan candidates:retag-news-topics
 */
class RetagNewsTopics extends Command
{
    protected $signature = 'candidates:retag-news-topics {--dry-run : Report changes without writing}';

    protected $description = 'Re-tag verified candidate news articles with issue topics using the current topic keywords.';

    public function handle(IssueClassifierService $classifier): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $stats = ['checked' => 0, 'tagged' => 0, 'changed' => 0];

        CandidateNewsArticle::query()
            ->where('verification_status', 'verified')
            ->select(['id', 'headline', 'snippet', 'topic_key', 'topic_confidence'])
            ->chunkById(500, function ($articles) use ($classifier, $dryRun, &$stats) {
                foreach ($articles as $article) {
                    $stats['checked']++;
                    $match = $classifier->confidentKeywordMatch(trim($article->headline.' '.$article->snippet));
                    $slug = $match['topic_slug'] ?? null;
                    $stats['tagged'] += $slug !== null ? 1 : 0;

                    if ($slug === $article->topic_key) {
                        continue;
                    }
                    $stats['changed']++;
                    if (! $dryRun) {
                        $article->updateQuietly(['topic_key' => $slug, 'topic_confidence' => $match['confidence'] ?? null]);
                    }
                }
            });

        $this->info(sprintf(
            '%sChecked %d verified articles: %d now tagged, %d changed.',
            $dryRun ? '[dry-run] ' : '', $stats['checked'], $stats['tagged'], $stats['changed'],
        ));

        return self::SUCCESS;
    }
}
