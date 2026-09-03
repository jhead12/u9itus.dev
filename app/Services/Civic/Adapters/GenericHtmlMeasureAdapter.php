<?php

namespace App\Services\Civic\Adapters;

use App\Models\ElectionDataSource;
use App\Services\Civic\BallotMeasureAdapter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Heuristic HTML extractor for county / city sample-ballot and "measures on
 * your ballot" pages. Not tied to one vendor — these listings are stylized
 * enough ("Measure A", "Proposition 64", "Question 3" as a heading, followed by
 * the ballot question) that one careful parser covers voteinfo.net, Granicus,
 * and most home-grown county pages.
 *
 * Deliberately conservative: it only accepts short heading-like nodes that
 * *start* with a measure label + designator, caps the result count (a page that
 * yields dozens of "measures" has almost certainly matched navigation), and
 * returns [] rather than guessing when the shape is unfamiliar. Whatever it
 * does return still flows through BallotMeasureWriter's blank-fill / provenance
 * rules, so it can't overwrite Ballotpedia- or human-authored measures.
 */
class GenericHtmlMeasureAdapter implements BallotMeasureAdapter
{
    /** A heading like "Measure A", "Proposition 64:", "Question 3 —", "Charter Amendment B". */
    private const HEADING_RE = '/^\W*(?:(?:charter|constitutional)\s+)?(?:measure|proposition|prop\.?|question|amendment|issue|referendum|bond(?:\s+measure)?)\s+([A-Z0-9]{1,4})\b/i';

    private const MONTHS = 'january|february|march|april|may|june|july|august|september|october|november|december';

    private const MAX_MEASURES = 60;

    public function key(): string
    {
        return 'generic_html';
    }

    public function fetchMeasures(ElectionDataSource $row): array
    {
        $url = $row->ballot_measures_url ?: $row->sample_ballot_url;
        if (! $url) {
            return [];
        }

        $html = $this->fetch($url);
        if ($html === null) {
            return [];
        }

        $electionDate = $this->parseElectionDate($html) ?? $this->parseElectionDate($url);
        $measures = $this->extract($html);

        return array_map(fn (array $m) => $m + ['source_url' => $url, 'election_date' => $electionDate], $measures);
    }

    private function fetch(string $url): ?string
    {
        try {
            $response = Http::withHeaders(['User-Agent' => (string) config('civic.verifier.user_agent', 'U9itus-civic-registry/1.0')])
                ->timeout((int) config('civic.verifier.timeout', 12))
                ->withOptions(['allow_redirects' => ['max' => 5]])
                ->get($url);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $body = (string) $response->body();

        return trim($body) === '' ? null : $body;
    }

    /**
     * @return list<array{title: string, measure_number: string, summary: ?string}>
     */
    private function extract(string $html): array
    {
        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($dom);
        // Heading-ish nodes only — never a whole section/article/body.
        $nodes = $xpath->query('//h1|//h2|//h3|//h4|//h5|//h6|//dt|//legend|//caption|//summary|//th|//td|//li|//strong|//b');
        if ($nodes === false) {
            return [];
        }

        $out = [];
        $seen = [];

        foreach ($nodes as $node) {
            $text = $this->clean($node->textContent);
            if ($text === '' || mb_strlen($text) > 240) {
                continue;
            }
            if (! preg_match(self::HEADING_RE, $text, $m)) {
                continue;
            }

            $number = strtoupper($m[1]);
            $title = Str::limit($text, 220, '');
            $dedupeKey = $number.'|'.mb_strtolower($title);
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;

            $out[] = [
                'title' => $title,
                'measure_number' => $number,
                'summary' => $this->summaryAfter($node),
            ];

            if (count($out) > self::MAX_MEASURES) {
                return []; // matched boilerplate — don't trust any of it
            }
        }

        return $out;
    }

    /**
     * Text of the blocks that follow the heading node, up to the next heading
     * or ~800 chars. Handles both "<h3>Measure A</h3><p>…</p>" and table rows
     * ("<td>Measure A</td><td>…</td>").
     */
    private function summaryAfter(\DOMNode $node): ?string
    {
        $anchor = in_array(strtolower($node->nodeName), ['strong', 'b'], true) && $node->parentNode
            ? $node->parentNode
            : $node;

        $parts = [];
        for ($sib = $anchor->nextSibling; $sib !== null; $sib = $sib->nextSibling) {
            if (! $sib instanceof \DOMElement) {
                continue;
            }
            $name = strtolower($sib->nodeName);
            if (in_array($name, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'dt'], true)) {
                break;
            }
            $text = $this->clean($sib->textContent);
            if ($text !== '' && ! preg_match(self::HEADING_RE, $text)) {
                $parts[] = $text;
            }
            if (mb_strlen(implode(' ', $parts)) > 800) {
                break;
            }
        }

        $summary = $this->clean(implode(' ', $parts));

        return $summary === '' ? null : Str::limit($summary, 1000);
    }

    private function clean(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5)) ?? '');
    }

    /** Pull an election date out of page text or a URL slug ("november-3-2026"). */
    private function parseElectionDate(string $haystack): ?string
    {
        $h = str_replace(['-', '_', '/'], ' ', strtolower($haystack));

        if (preg_match('/\b('.self::MONTHS.')\s+(\d{1,2})\s+(20\d\d)\b/', $h, $m)) {
            return $this->toDate("{$m[1]} {$m[2]} {$m[3]}");
        }
        if (preg_match('/\b(20\d\d)\s(0[1-9]|1[0-2])\s(0[1-9]|[12]\d|3[01])\b/', $h, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }

        return null;
    }

    private function toDate(string $value): ?string
    {
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
