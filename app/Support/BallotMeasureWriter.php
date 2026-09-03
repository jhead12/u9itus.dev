<?php

namespace App\Support;

use App\Models\BallotMeasure;
use Illuminate\Support\Str;

/**
 * Shared upsert for `ballot_measures`, used by every automated ingest path
 * (civic:pull-measures from the VIP feed, civic:scrape-measures from HTML
 * adapters). Keeps one dedup identity and one "don't clobber provenance" rule
 * in a single place.
 *
 * Dedup identity: state + title + election_date (calendar day) — the same key
 * ImportBallotMeasures and AdminBallotMeasureController::store use.
 */
class BallotMeasureWriter
{
    /**
     * Insert or merge one measure. Without $refresh only blank columns are
     * filled; `source` is never overwritten, so a Ballotpedia- or
     * human-authored row keeps its provenance even when an automated pass
     * matches it.
     *
     * @param  array<string, mixed>  $attrs  ballot_measures column values (must include state + title)
     * @return 'created'|'updated'|'unchanged'
     */
    public function upsert(array $attrs, bool $refresh = false, bool $dryRun = false): string
    {
        $number = $attrs['measure_number'] ?? null;

        $existing = BallotMeasure::query()
            ->where('state', $attrs['state'])
            ->when(
                ($attrs['election_date'] ?? null) !== null,
                fn ($q) => $q->whereDate('election_date', $attrs['election_date']),
                fn ($q) => $q->whereNull('election_date'),
            )
            // Match on the exact title, or — within the same state + election —
            // on the measure number, so a fuller title from a second source
            // (e.g. Wikipedia vs. a hand CSV) enriches the row instead of
            // duplicating it.
            ->where(function ($q) use ($attrs, $number) {
                $q->where('title', $attrs['title']);
                if ($number !== null && $number !== '') {
                    $q->orWhere('measure_number', $number);
                }
            })
            ->first();

        if ($existing === null) {
            if (! $dryRun) {
                BallotMeasure::create($attrs);
            }

            return 'created';
        }

        $changes = [];
        foreach ($attrs as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $current = $existing->{$key};
            $isBlank = $current === null || $current === '';
            if (($isBlank || $refresh) && $current != $value) {
                $changes[$key] = $value;
            }
        }

        unset($changes['source']); // provenance is set once, at creation

        if ($changes === []) {
            return 'unchanged';
        }

        if (! $dryRun) {
            $existing->update($changes);
        }

        return 'updated';
    }

    /** "Proposition 1: …" / "Measure A —" / "Question 3" → "1" / "A" / "3" */
    public static function parseMeasureNumber(string $title): ?string
    {
        if (preg_match('/\b(?:prop(?:osition)?|measure|question|amendment|issue|referendum)\s+([A-Z0-9]{1,4})\b/i', $title, $m)) {
            return strtoupper($m[1]);
        }

        return null;
    }

    /**
     * Normalize a scraped/parsed measure into ballot_measures column values.
     * yes_meaning / no_meaning / status are passed through when the source
     * supplies them (Wikipedia's "result of a Yes vote" column, a status of
     * approved/defeated); otherwise they fall back to null / 'upcoming'.
     *
     * @param  array{title: string, measure_number?: ?string, summary?: ?string, yes_meaning?: ?string, no_meaning?: ?string, status?: ?string, source_url?: ?string}  $measure
     * @return array<string, mixed>|null null when there's no usable title
     */
    public static function normalize(array $measure, string $state, ?string $county, ?string $electionDate, string $source, ?string $fallbackUrl = null): ?array
    {
        $title = trim((string) ($measure['title'] ?? ''));
        if ($title === '') {
            return null;
        }

        $clean = fn ($v, int $len) => ($s = trim((string) ($v ?? ''))) !== '' ? Str::limit($s, $len) : null;

        return [
            'state' => $state,
            'county' => $county ? Str::limit($county, 100, '') : null,
            'measure_number' => $measure['measure_number'] ?? self::parseMeasureNumber($title),
            'title' => Str::limit($title, 255, ''),
            'summary' => $clean($measure['summary'] ?? null, 1000),
            'yes_meaning' => $clean($measure['yes_meaning'] ?? null, 1000),
            'no_meaning' => $clean($measure['no_meaning'] ?? null, 1000),
            'election_date' => $electionDate,
            'status' => in_array($measure['status'] ?? null, ['upcoming', 'passed', 'failed'], true)
                ? $measure['status']
                : 'upcoming',
            'source' => $source,
            'source_url' => $measure['source_url'] ?? $fallbackUrl,
        ];
    }
}
