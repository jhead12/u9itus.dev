<?php

namespace App\Console\Commands;

use App\Exceptions\OcrCandidateImportException;
use App\Models\BallotMeasure;
use App\Services\VoterGuideMeasureExtractor;
use App\Support\BallotMeasureWriter;
use Illuminate\Console\Command;

/**
 * Extracts ballot measures from a voter guide — a local PDF / scan / text file, or a link to
 * one — for jurisdictions no automated source covers (city clerks, school districts, ...).
 *
 * Usage:
 *   php artisan ballot-measures:import-guide ~/guides/lakeside.pdf --state=CA --level=district \
 *       --county="San Diego County" --locality="Lakeside Union School District" --election-date=2026-11-03
 *   php artisan ballot-measures:import-guide https://example.gov/voter-guide.pdf --state=TX --level=city --locality=Austin --dry-run
 *
 * Text comes from the PDF text layer, else OCR. Parsing is heuristic, so use --dry-run first,
 * or use the admin page, which shows the result for review before anything is saved.
 */
class ImportBallotMeasuresFromGuide extends Command
{
    protected $signature = 'ballot-measures:import-guide
        {source              : Path to a PDF/scan/text file, or an http(s) link to one}
        {--state=            : Two-letter USPS state code (required)}
        {--level=county      : state, county, city or district}
        {--county=           : County the guide covers}
        {--locality=         : City or district name (for city/district guides)}
        {--election-date=    : YYYY-MM-DD}
        {--source-url=       : Where the guide is published (defaults to the link, when one was given)}
        {--refresh           : Overwrite fields on measures that already exist (default: fill blanks only)}
        {--dry-run           : Show what was found without saving}';

    protected $description = 'Extract ballot measures from a voter guide (local PDF/scan or link, with OCR) into ballot_measures.';

    public function handle(VoterGuideMeasureExtractor $extractor, BallotMeasureWriter $writer): int
    {
        $source = (string) $this->argument('source');
        $state = strtoupper(trim((string) $this->option('state')));
        $level = strtolower((string) $this->option('level'));
        $county = trim((string) $this->option('county')) ?: null;
        $locality = trim((string) $this->option('locality')) ?: null;
        $date = trim((string) $this->option('election-date')) ?: null;
        $isUrl = (bool) preg_match('#^https?://#i', $source);

        if (strlen($state) !== 2) {
            $this->error('--state is required (two-letter code).');

            return self::FAILURE;
        }
        if (! array_key_exists($level, BallotMeasure::LEVELS)) {
            $this->error('--level must be one of: '.implode(', ', array_keys(BallotMeasure::LEVELS)));

            return self::FAILURE;
        }
        if ($level !== 'state' && $county === null && $locality === null) {
            $this->error("A {$level}-level guide needs --county and/or --locality so the measures are tied to a place.");

            return self::FAILURE;
        }
        if ($date !== null && strtotime($date) === false) {
            $this->error('--election-date is not a valid date.');

            return self::FAILURE;
        }
        if (! $isUrl && ! is_readable($source)) {
            $this->error("Cannot read file: {$source}");

            return self::FAILURE;
        }

        try {
            $found = $isUrl ? $extractor->fromUrl($source) : $extractor->fromFile($source);
        } catch (OcrCandidateImportException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($found === []) {
            $this->warn('No ballot measures were detected (looked for headings like "Measure A" / "Proposition 3").');

            return self::FAILURE;
        }

        $sourceUrl = trim((string) $this->option('source-url')) ?: ($isUrl ? $source : null);
        $dryRun = (bool) $this->option('dry-run');
        $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0];

        foreach ($found as $measure) {
            $attrs = BallotMeasureWriter::normalize(
                $measure,
                state: $state,
                county: $county,
                electionDate: $date,
                source: 'voter_guide',
                fallbackUrl: $sourceUrl,
                level: $level,
                locality: $locality,
            );
            if ($attrs === null) {
                continue;
            }

            $result = $writer->upsert($attrs, (bool) $this->option('refresh'), $dryRun);
            $counts[$result]++;
            $this->line("  [{$attrs['state']}] {$attrs['title']} ({$result})".($dryRun ? ' — dry run' : ''));
        }

        $this->info(sprintf('Done. Found %d | created: %d | updated: %d | unchanged: %d%s',
            count($found), $counts['created'], $counts['updated'], $counts['unchanged'], $dryRun ? ' (dry-run — nothing saved)' : ''));

        return self::SUCCESS;
    }
}
