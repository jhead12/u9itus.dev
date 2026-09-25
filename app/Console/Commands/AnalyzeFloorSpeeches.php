<?php

namespace App\Console\Commands;

use App\Models\CongressFloorSpeech;
use App\Services\BillTopicResolver;
use App\Services\IssueClassifierService;
use Illuminate\Console\Command;

/**
 * Tags imported floor speeches with an issue topic, and — when the Claude fallback is
 * configured — the position taken and a verbatim quote. Without an Anthropic key the
 * keyword tier still tags topics, so badges and the speeches page work either way.
 *
 * A speech in debate on a bill takes the bill's topic (BillTopicResolver): a roll call
 * on it an editor tagged, else the bill's own title or Congress.gov policy area. Claude
 * then reads only the position and quote. Speeches not about a bill are read as before.
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

    /** Confidence given to a topic taken from the bill: an editor's tag, or Congress.gov's filing. */
    private const SOURCE_CONFIDENCE = ['vote' => 1.0, 'bill' => 0.85];

    public function handle(IssueClassifierService $classifier, BillTopicResolver $bills): int
    {
        $speeches = CongressFloorSpeech::query()
            ->when(! $this->option('reanalyze'), fn ($q) => $q->whereNull('analyzed_at'))
            ->orderByDesc('spoken_on')
            ->limit((int) $this->option('limit'))
            ->get();

        $counts = ['vote' => 0, 'bill' => 0, 'llm' => 0, 'keyword' => 0, 'untagged' => 0];
        foreach ($speeches as $speech) {
            $fromBill = $speech->bill_refs ? $bills->resolve($speech->bill_refs) : null;
            $analysis = $classifier->analyzeStatement($speech->title, $speech->body, $fromBill['topic_slug'] ?? null);
            if ($analysis !== null) {
                $method = 'llm';
                $fields = [
                    'topic_key' => $analysis['topic_slug'],
                    'topic_confidence' => $analysis['topic_slug'] ? $analysis['confidence'] : null,
                    'stance' => $analysis['stance'],
                    'position_summary' => $analysis['position'],
                    'quote' => $analysis['quote'],
                    'topic_stance' => $analysis['topic_stance'] ?? null,
                ];
            } else {
                $method = 'keyword';
                $match = $this->keywordTopic($classifier, $speech);
                $fields = ['topic_key' => $match['topic_slug'] ?? null, 'topic_confidence' => $match['confidence'] ?? null];
            }

            $source = $fields['topic_key'] ? $method : null;
            if ($fromBill !== null) {
                $source = $fromBill['source'];
                $fields['topic_key'] = $fromBill['topic_slug'];
                $fields['topic_confidence'] = self::SOURCE_CONFIDENCE[$source];
            }

            $speech->update($fields + ['topic_source' => $source, 'analysis_method' => $method, 'analyzed_at' => now()]);
            $counts[$source ?? 'untagged']++;
        }

        $this->info("Analyzed {$speeches->count()} speeches: {$counts['vote']} from tagged votes, {$counts['bill']} from the bill debated, "
            ."{$counts['llm']} tagged by Claude, {$counts['keyword']} by keywords, {$counts['untagged']} with no issue topic.");

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
