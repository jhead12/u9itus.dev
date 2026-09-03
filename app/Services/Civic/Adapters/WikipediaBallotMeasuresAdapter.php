<?php

namespace App\Services\Civic\Adapters;

use App\Models\ElectionDataSource;
use App\Services\Civic\BallotMeasureAdapter;
use App\Support\BallotMeasureWriter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Parses the per-state tables in Wikipedia's "<year> United States ballot
 * measures" article — one page that covers every state, kept current, CC-BY-SA
 * (keep the source_url visible as attribution).
 *
 * Each state section is `<h3 id="StateName">` followed by a
 * `<table class="wikitable">` with columns:
 *   Origin | Status | Measure | Description (Result of a "yes" vote) | Date | …
 *
 * The "Description" column is literally "what a Yes vote does", so it fills
 * `yes_meaning` — the one field the Civic feed never gives us.
 *
 * Registered for state registry rows (election_data_sources.platform_template
 * = 'wikipedia'); civic:scrape-measures runs it once per state and it returns
 * only that state's measures. The article HTML is fetched once per process.
 */
class WikipediaBallotMeasuresAdapter implements BallotMeasureAdapter
{
    private const API = 'https://en.wikipedia.org/w/api.php';

    private const MAX_PER_STATE = 40;

    /** @var array<string, \DOMDocument> parsed article by title, for the life of the process (successes only) */
    private array $docCache = [];

    /** Set after a fetch fails so the remaining states in a run don't re-hit a down API. */
    private bool $articleUnavailable = false;

    public function key(): string
    {
        return 'wikipedia';
    }

    public function fetchMeasures(ElectionDataSource $row): array
    {
        $stateName = config('u9itus.us_states.'.$row->state);
        if (! $stateName) {
            return [];
        }

        $year = (int) config('civic.wikipedia.year', 2026);
        $title = (string) (config('civic.wikipedia.article') ?: "{$year} United States ballot measures");
        $electionDate = $this->statutoryGeneralDate($year);

        $doc = $this->article($title);
        if ($doc === null) {
            return [];
        }

        $table = $this->stateTable($doc, $stateName);
        if ($table === null) {
            return [];
        }

        $anchor = str_replace(' ', '_', $stateName);
        $sourceUrl = 'https://en.wikipedia.org/wiki/'.rawurlencode(str_replace(' ', '_', $title))."#{$anchor}";

        return $this->parseTable($table, $electionDate, $sourceUrl);
    }

    private function article(string $title): ?\DOMDocument
    {
        if (isset($this->docCache[$title])) {
            return $this->docCache[$title];
        }
        if ($this->articleUnavailable) {
            return null; // an earlier state in this run already failed the fetch
        }

        try {
            $response = Http::withHeaders(['User-Agent' => (string) config('civic.verifier.user_agent', 'U9itus-civic-registry/1.0')])
                ->timeout((int) config('civic.verifier.timeout', 12))
                ->retry(2, 800, throw: false)
                ->get(self::API, [
                    'action' => 'parse',
                    'page' => $title,
                    'prop' => 'text',
                    'format' => 'json',
                    'redirects' => 1,
                    'disabletoc' => 1,
                ]);
        } catch (ConnectionException) {
            $this->articleUnavailable = true;

            return null;
        }

        // MediaWiki returns parse.text as {"*": "<html>"}. Laravel's dot-path
        // json() treats the "*" as a wildcard, so reach in directly.
        $json = $response->successful() ? ($response->json() ?? []) : [];
        $html = $json['parse']['text']['*'] ?? ($json['parse']['text'] ?? null);
        if (! is_string($html) || trim($html) === '') {
            $this->articleUnavailable = true; // don't hammer a down API for the rest of the run

            return null;
        }

        $dom = new \DOMDocument;
        $prev = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div>'.$html.'</div>');
        libxml_use_internal_errors($prev);

        return $this->docCache[$title] = $dom;
    }

    /** The first wikitable after the state's heading, or null if the section has none. */
    private function stateTable(\DOMDocument $doc, string $stateName): ?\DOMElement
    {
        $xpath = new \DOMXPath($doc);
        $heading = $xpath->query('//*[self::h2 or self::h3 or self::h4][@id='.$this->xpathLiteral($stateName).']')->item(0)
            ?? $xpath->query('//*[self::h2 or self::h3 or self::h4][@id='.$this->xpathLiteral(str_replace(' ', '_', $stateName)).']')->item(0);

        if (! $heading instanceof \DOMElement) {
            return null;
        }

        // Walk forward from the heading's block wrapper. Stop at the next
        // heading wrapper (section has no table) or return the first wikitable.
        $node = $heading->parentNode; // usually <div class="mw-heading">
        for ($sib = $node?->nextSibling; $sib !== null; $sib = $sib->nextSibling) {
            if (! $sib instanceof \DOMElement) {
                continue;
            }
            $class = $sib->getAttribute('class');
            if (str_contains($class, 'mw-heading')) {
                return null; // next state started before any table
            }
            if (strtolower($sib->nodeName) === 'table' && str_contains($class, 'wikitable')) {
                return $sib;
            }
            // A wikitable nested one level down (rare wrapping).
            $inner = (new \DOMXPath($sib->ownerDocument))->query('.//table[contains(@class,"wikitable")]', $sib)->item(0);
            if ($inner instanceof \DOMElement) {
                return $inner;
            }
        }

        return null;
    }

    /**
     * @return list<array{title: string, measure_number: ?string, summary: ?string, yes_meaning: ?string, status: string, election_date: ?string, source_url: string}>
     */
    private function parseTable(\DOMElement $table, ?string $electionDate, string $sourceUrl): array
    {
        $rows = $table->getElementsByTagName('tr');
        if ($rows->length === 0) {
            return [];
        }

        // Header row: map column label → index.
        $cols = [];
        foreach (iterator_to_array($rows->item(0)->childNodes) as $cell) {
            if ($cell instanceof \DOMElement && in_array(strtolower($cell->nodeName), ['th', 'td'], true)) {
                $cols[] = strtolower($this->clean($cell->textContent));
            }
        }
        $idx = fn (string $needle) => $this->columnIndex($cols, $needle);

        $measureCol = $idx('measure');
        if ($measureCol === null) {
            return [];
        }
        $descCol = $idx('description') ?? $idx('result');
        $dateCol = $idx('date');
        $statusCol = $idx('status');

        $out = [];
        for ($r = 1; $r < $rows->length; $r++) {
            $cells = array_values(array_filter(
                iterator_to_array($rows->item($r)->childNodes),
                fn ($n) => $n instanceof \DOMElement && strtolower($n->nodeName) === 'td',
            ));
            if ($cells === []) {
                continue;
            }

            $title = isset($cells[$measureCol]) ? $this->clean($cells[$measureCol]->textContent) : '';
            if ($title === '' || mb_strlen($title) < 4) {
                continue;
            }

            $desc = $descCol !== null && isset($cells[$descCol]) ? $this->clean($cells[$descCol]->textContent) : null;
            $dateText = $dateCol !== null && isset($cells[$dateCol]) ? $this->clean($cells[$dateCol]->textContent) : '';
            $statusText = $statusCol !== null && isset($cells[$statusCol]) ? $this->clean($cells[$statusCol]->textContent) : '';

            $out[] = [
                'title' => Str::limit($title, 240, ''),
                'measure_number' => BallotMeasureWriter::parseMeasureNumber($title),
                'summary' => $desc,
                'yes_meaning' => $desc,
                'status' => $this->mapStatus($statusText),
                'election_date' => $this->parseDate($dateText) ?? $electionDate,
                'source_url' => $sourceUrl,
            ];

            if (count($out) >= self::MAX_PER_STATE) {
                break;
            }
        }

        return $out;
    }

    private function columnIndex(array $cols, string $needle): ?int
    {
        foreach ($cols as $i => $label) {
            if (str_contains($label, $needle)) {
                return $i;
            }
        }

        return null;
    }

    private function mapStatus(string $text): string
    {
        $t = strtolower($text);

        return match (true) {
            str_contains($t, 'approv') || str_contains($t, 'passed') || str_contains($t, 'enacted') => 'passed',
            str_contains($t, 'defeat') || str_contains($t, 'fail') || str_contains($t, 'reject') => 'failed',
            default => 'upcoming',
        };
    }

    private function parseDate(string $text): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        // Wikipedia date cells are usually "Nov 3" (year implied by the article).
        $year = (int) config('civic.wikipedia.year', 2026);
        $candidate = preg_match('/\b\d{4}\b/', $text) ? $text : "{$text} {$year}";

        try {
            return Carbon::parse($candidate)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function clean(string $text): string
    {
        // Drop citation markers like "[1]" then collapse whitespace.
        $text = preg_replace('/\[\s*\d+\s*\]/', '', html_entity_decode($text, ENT_QUOTES | ENT_HTML5));

        return trim(preg_replace('/\s+/u', ' ', $text ?? '') ?? '');
    }

    private function xpathLiteral(string $value): string
    {
        if (! str_contains($value, "'")) {
            return "'{$value}'";
        }

        return 'concat('.implode(", \"'\", ", array_map(fn ($p) => "'{$p}'", explode("'", $value))).')';
    }

    private function statutoryGeneralDate(int $year): ?string
    {
        if ($year % 2 !== 0) {
            return null;
        }

        return (new \DateTimeImmutable("first monday of november {$year}"))->modify('+1 day')->format('Y-m-d');
    }
}
