<?php

namespace App\Console\Commands;

use App\Models\CongressFloorSpeech;
use App\Services\IssueClassifierService;
use Illuminate\Console\Command;

/**
 * Tags imported floor speeches with an issue topic, and — when the Claude fallback is
 * configured — the position taken and a verbatim quote. Without an Anthropic key the
 * keyword tier still tags topics, so badges and the speeches page work either way.
 *
 * Usage:
 *   php artisan congress:analyze-floor-speeches --limit=300
 *   php artisan congress:analyze-floor-speeches --reanalyze --limit=50
 */
class AnalyzeFloorSpeeches extends Command
{
    protected $signature = 'congress:analyze-floor-speeches
        {--limit=300   : Max speeches per run (newest first)}
        {--reanalyze   : Include speeches analyzed before}';

    protected $description = 'Tag Congressional Record speeches with issue topic, position and quote.';

    public function handle(IssueClassifierService $classifier): int
    {
        $speeches = CongressFloorSpeech::query()
            ->when(! $this->option('reanalyze'), fn ($q) => $q->whereNull('analyzed_at'))
            ->orderByDesc('spoken_on')
            ->limit((int) $this->option('limit'))
            ->get();

        $counts = ['llm' => 0, 'keyword' => 0, 'untagged' => 0];
        foreach ($speeches as $speech) {
            $analysis = $classifier->analyzeStatement($speech->title, $speech->body);
            if ($analysis !== null) {
                $method = 'llm';
                $fields = [
                    'topic_key' => $analysis['topic_slug'],
                    'topic_confidence' => $analysis['topic_slug'] ? $analysis['confidence'] : null,
                    'stance' => $analysis['stance'],
                    'position_summary' => $analysis['position'],
                    'quote' => $analysis['quote'],
                ];
            } else {
                $method = 'keyword';
                $match = $this->keywordTopic($classifier, $speech);
                $fields = ['topic_key' => $match['topic_slug'] ?? null, 'topic_confidence' => $match['confidence'] ?? null];
            }

            $speech->update($fields + ['analysis_method' => $method, 'analyzed_at' => now()]);
            $fields['topic_key'] ? $counts[$method]++ : $counts['untagged']++;
        }

        $this->info("Analyzed {$speeches->count()} speeches: {$counts['llm']} tagged by Claude, {$counts['keyword']} by keywords, {$counts['untagged']} with no issue topic.");

        return self::SUCCESS;
    }

    /**
     * Without Claude, only the title decides ("Introduction of the Gun Safety Incentive
     * Act"). A speech body mentions many issues in passing, and keyword counts over it
     * tag the wrong one often enough that leaving the speech untagged is better.
     */
    private function keywordTopic(IssueClassifierService $classifier, CongressFloorSpeech $speech): ?array
    {
        return $classifier->confidentKeywordMatch($speech->title);
    }
}
