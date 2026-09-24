<?php

namespace App\Services;

use App\Models\CongressFloorSpeech;
use App\Models\Politician;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;

/**
 * Imports what members of Congress said in the daily Congressional Record from GovInfo
 * (CREC collection). One MODS call per day lists every article with its section and its
 * speaking members' Bioguide IDs and printed labels ("Mr. KENNEDY of New York"); the
 * article text is then fetched only for substantive sections with a speaker who has a
 * profile here, and split at those labels so each member keeps only their own words.
 */
class CongressionalRecordImporter
{
    // Record sections that carry debate or statements of position. Everything else is
    // procedure (prayer, cloture, calendars, messages) or tributes (honoring, recognizing).
    public const SECTIONS = [
        'HOUSE' => ['ALLOTHER', 'VOTEEXPLAIN'],
        'SENATE' => ['ALLOTHER', 'SLEGISLATIVE', 'SEXECSESSION', 'SSTATEMENTS', 'SSTATEMENTSIND', 'VOTEEXPLAIN'],
        'EXTENSIONS' => ['ALLOTHER'],
    ];

    // Shorter segments are colloquy ("I yield back") rather than a statement.
    private const MIN_WORDS = 60;

    private const MODS_NS = 'http://www.loc.gov/mods/v3';

    private const USER_AGENT = 'U9itus-civic-enrichment/1.0 (+https://u9itus.dev/about)';

    protected ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.congress.api_key');
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * @return array{articles: int, speeches: int, skipped: int}|null null when the Record
     *                                                                has no issue that day
     */
    public function importDay(Carbon $date, bool $refresh = false): ?array
    {
        $packageId = 'CREC-'.$date->toDateString();
        $mods = $this->fetchMods($packageId);
        if ($mods === null) {
            return null;
        }

        $linked = array_flip(Politician::query()->whereNotNull('bioguide_id')->distinct()->pluck('bioguide_id')->all());
        $stats = ['articles' => 0, 'speeches' => 0, 'skipped' => 0];

        foreach ($this->articles($mods) as $article) {
            $speakers = array_intersect_key($article['speakers'], $linked);
            if ($speakers === []) {
                continue;
            }
            if (! $refresh && CongressFloorSpeech::where('granule_id', $article['granule_id'])->exists()) {
                $stats['skipped']++;

                continue;
            }

            $stats['articles']++;
            foreach ($this->splitBySpeaker($this->fetchText($article['html_url']), $article['speakers']) as $bioguide => $body) {
                $words = str_word_count($body);
                if (! isset($speakers[$bioguide]) || $words < self::MIN_WORDS) {
                    continue;
                }

                $speech = CongressFloorSpeech::firstOrNew(['granule_id' => $article['granule_id'], 'bioguide_id' => $bioguide]);
                $changed = $speech->body !== $body;
                $speech->fill([
                    'chamber' => $article['chamber'],
                    'kind' => $article['kind'],
                    'spoken_on' => $article['date'],
                    'record_time' => $article['time'],
                    'title' => $article['title'],
                    'record_section' => $article['section'],
                    'citation' => $article['citation'],
                    'body' => $body,
                    'word_count' => $words,
                    'source_url' => $article['html_url'],
                ]);
                if ($changed) {
                    // New wording needs a fresh topic/position read.
                    $speech->fill(['topic_key' => null, 'topic_confidence' => null, 'stance' => null, 'position_summary' => null, 'quote' => null, 'analysis_method' => null, 'analyzed_at' => null]);
                }
                $speech->save();
                $stats['speeches']++;
            }
        }

        return $stats;
    }

    /**
     * Substantive articles from a day's MODS, with speakers as [bioguide => printed label].
     *
     * @return list<array{granule_id: string, title: string, chamber: string, kind: string, section: string, date: string, time: ?string, citation: ?string, html_url: string, speakers: array<string, string>}>
     */
    public function articles(SimpleXMLElement $mods): array
    {
        $mods->registerXPathNamespace('m', self::MODS_NS);
        $articles = [];

        foreach ($mods->xpath('//m:relatedItem[@type="constituent"]') ?: [] as $item) {
            $item->registerXPathNamespace('m', self::MODS_NS);
            $text = fn (string $path) => trim((string) ($item->xpath($path)[0] ?? ''));

            $class = strtoupper($text('m:extension/m:granuleClass'));
            $section = strtoupper($text('m:extension/m:subGranuleClass'));
            $htmlUrl = $text('m:location/m:url[@displayLabel="HTML rendition"]');
            if (! in_array($section, self::SECTIONS[$class] ?? [], true) || $htmlUrl === '') {
                continue;
            }

            $speakers = [];
            foreach ($item->xpath('m:extension/m:congMember[@role="SPEAKING"]') ?: [] as $member) {
                $member->registerXPathNamespace('m', self::MODS_NS);
                $bioguide = trim((string) $member['bioGuideId']);
                $label = trim((string) ($member->xpath('m:name[@type="parsed"]')[0] ?? ''));
                if ($bioguide !== '' && $label !== '') {
                    $speakers[$bioguide] = $label;
                }
            }
            if ($speakers === []) {
                continue;
            }

            $chamber = $class === 'EXTENSIONS'
                ? 'house' // Extensions of Remarks are House members' written statements.
                : strtolower($class);
            $time = (string) ($item->xpath('m:extension/m:time/@from')[0] ?? '');

            $articles[] = [
                'granule_id' => $text('m:extension/m:accessId'),
                'title' => mb_substr(preg_replace('/\s+/', ' ', $text('m:titleInfo/m:title')), 0, 500),
                'chamber' => $chamber,
                'kind' => $class === 'EXTENSIONS' ? 'written' : 'floor',
                'section' => $section,
                'date' => $text('m:extension/m:granuleDate'),
                'time' => preg_match('/^\d{2}:\d{2}:\d{2}$/', $time) ? $time : null,
                'citation' => $text('m:identifier[@type="preferred citation"]') ?: null,
                'html_url' => $htmlUrl,
                'speakers' => $speakers,
            ];
        }

        return array_values(array_filter($articles, fn (array $a) => $a['granule_id'] !== '' && $a['date'] !== ''));
    }

    /**
     * Splits Record text into each member's own words. A turn starts at a paragraph that
     * opens with a speaker's printed label ("  Mr. JACK. ") and ends at the next label,
     * including the presiding officer's ("  The SPEAKER pro tempore. ").
     *
     * @param  array<string, string>  $speakers  bioguide => printed label
     * @return array<string, string> bioguide => paragraphs
     */
    public function splitBySpeaker(string $text, array $speakers): array
    {
        $text = $this->clean($text);
        $byLabel = [];
        foreach ($speakers as $bioguide => $label) {
            $byLabel[mb_strtolower(preg_replace('/\s+/', ' ', $label))] = $bioguide;
        }

        $labels = implode('|', array_map(fn (string $label) => str_replace(' ', '\s+', preg_quote($label, '/')), $speakers));
        $pattern = '/^ {2}('.$labels.'|The [A-Z][A-Za-z ]+?(?: \([^)]*\))?|(?:Mr|Ms|Mrs|Miss|Dr)\. [A-Z][A-Za-z\'-]+(?: of [A-Z][A-Za-z]+(?: [A-Z][A-Za-z]+)*)?)\. /m';
        preg_match_all($pattern, $text, $markers, PREG_OFFSET_CAPTURE);

        $turns = [];
        foreach ($markers[1] as $i => [$label, $offset]) {
            $start = $offset + strlen($label) + 2;
            $end = $markers[0][$i + 1][1] ?? strlen($text);
            $bioguide = $byLabel[mb_strtolower(preg_replace('/\s+/', ' ', $label))] ?? null;
            if ($bioguide !== null) {
                $turns[$bioguide][] = substr($text, $start, $end - $start);
            }
        }

        // A lone speaker whose statement never repeats their label (common in Extensions).
        if ($turns === [] && count($speakers) === 1) {
            $turns[array_key_first($speakers)][] = $text;
        }

        return array_map(fn (array $parts) => $this->paragraphs(implode("\n  ", $parts)), $turns);
    }

    protected function clean(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);
        $text = str_replace("\r", '', $text);
        // Page breaks, the GPO header block, and stage directions such as
        // "(Mr. Kennedy of New York was recognized to address the House for 5 minutes.)".
        $text = preg_replace('/^\s*\[\[Page [^\]]*\]\]\s*$/m', '', $text);
        $text = preg_replace('/^\s*\[(Congressional Record|House|Senate|Extensions of Remarks|Pages?)[^\]]*\]\s*$/mi', '', $text);
        $text = preg_replace('/^.*(From the Congressional Record Online|Congressional Record, Volume).*$/m', '', $text);
        $text = preg_replace('/^ {2}\((?:[^()]|\([^()]*\))*\)\s*$/m', '', $text);

        return $text;
    }

    /** Rejoins the Record's hard-wrapped lines; a two-space indent starts a paragraph. */
    protected function paragraphs(string $text): string
    {
        $paragraphs = preg_split('/\n {2}(?=\S)/', "\n".$text);

        return trim(implode("\n\n", array_filter(array_map(
            fn (string $p) => trim(preg_replace('/\s+/', ' ', $p)),
            $paragraphs,
        ), fn (string $p) => $p !== '' && $p !== '<all>')));
    }

    protected function fetchMods(string $packageId): ?SimpleXMLElement
    {
        $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(60)->retry(2, 1000, throw: false)
            ->get("https://api.govinfo.gov/packages/{$packageId}/mods", ['api_key' => $this->apiKey]);
        if ($response->status() === 404) {
            return null;
        }
        if (! $response->successful()) {
            throw new RuntimeException("GovInfo MODS for {$packageId} failed (HTTP {$response->status()}).");
        }

        $xml = simplexml_load_string($response->body());
        if ($xml === false) {
            throw new RuntimeException("GovInfo MODS for {$packageId} was not valid XML.");
        }

        return $xml;
    }

    protected function fetchText(string $url): string
    {
        $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(30)->retry(2, 1000, throw: false)->get($url);
        if (! $response->successful()) {
            throw new RuntimeException("Congressional Record text {$url} failed (HTTP {$response->status()}).");
        }

        return $response->body();
    }
}
