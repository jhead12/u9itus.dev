<?php

namespace App\Services\CandidateDiscovery;

use App\Contracts\CandidateDiscoverySource;
use App\Models\Politician;
use App\Services\Concerns\HasRssParsing;
use Carbon\Carbon;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Discovers new Senate/Governor/House candidate signals from Google News RSS.
 *
 * This is a *discovery* source, not a verification source: it never trusts a
 * name it extracts from an RSS headline — every lead it returns is destined
 * for CandidateVerificationRegistry before it can be promoted. See
 * config/candidate_discovery_sources.php for the query templates/offices.
 *
 * Senate/Governor are statewide — one query per state per template. House is
 * per-district instead (a state-name query can't distinguish districts), and
 * runs a rotating slice of districts per call rather than all ~435 every
 * time — see house_batch_divisor in the config.
 */
class RssCandidateDiscoverySource implements CandidateDiscoverySource
{
    use HasRssParsing;

    /** Headline words too generic to ever be part of a candidate's name. */
    private const NAME_STOPWORDS = [
        'The', 'A', 'An', 'U.S.', 'US', 'State', 'Senate', 'House', 'Governor',
        'Democratic', 'Republican', 'Democrat', 'Primary', 'Election', 'Campaign',
        'Wins', 'Files', 'Announces', 'Launches', 'For', 'In', 'Of', 'And', 'To',
    ];

    public function key(): string
    {
        return 'rss_google_news';
    }

    /**
     * @param  array<string, mixed>  $options  {state?:string, office?:string}
     * @return array<int, array{full_name:string, state:?string, office_hint:?string,
     *   source_url:string, published_at:?Carbon, raw:array<string,mixed>}>
     */
    public function discover(array $options = []): array
    {
        $states = config('u9itus.us_states', []);
        $offices = config('candidate_discovery_sources.offices', []);
        $templates = config('candidate_discovery_sources.query_templates', []);
        $houseTemplates = config('candidate_discovery_sources.house_query_templates', []);
        $rssUrlTemplate = (string) config('candidate_discovery_sources.rss_url');

        $stateFilter = isset($options['state']) ? strtoupper((string) $options['state']) : null;
        $officeFilter = $options['office'] ?? null;

        $requests = [];
        foreach ($states as $abbr => $stateName) {
            if ($stateFilter !== null && $abbr !== $stateFilter) {
                continue;
            }

            foreach ($offices as $office => $officeSlug) {
                if ($officeFilter !== null && $officeSlug !== $officeFilter) {
                    continue;
                }

                if ($officeSlug === 'house') {
                    array_push($requests, ...$this->houseRequestsFor($abbr, $stateName, $office, $houseTemplates, $rssUrlTemplate));

                    continue;
                }

                foreach ($templates as $templateKey => $template) {
                    $query = str_replace(['{STATE_NAME}', '{OFFICE}'], [$stateName, $office], $template);
                    $url = str_replace('{QUERY}', rawurlencode($query), $rssUrlTemplate);

                    $requests[] = [
                        'state' => $abbr,
                        'office' => $office,
                        'template_key' => $templateKey,
                        'url' => $url,
                    ];
                }
            }
        }

        if ($requests === []) {
            return [];
        }

        $responses = Http::pool(fn (Pool $pool) => collect($requests)
            ->map(fn (array $req, int $index) => $pool->as((string) $index)->timeout(10)->get($req['url']))
            ->all());

        $leads = [];
        foreach ($requests as $index => $req) {
            $response = $responses[(string) $index] ?? null;
            if ($response instanceof \Throwable || ! $response || ! $response->successful()) {
                continue;
            }

            $providerId = $this->key() . ':' . $req['template_key'];
            foreach ($this->parseRssResponse($response, $providerId) as $article) {
                $name = $this->extractCandidateName((string) ($article['headline'] ?? ''));
                if ($name === null) {
                    continue;
                }

                $leads[] = [
                    'full_name' => $name,
                    'state' => $req['state'],
                    'office_hint' => $req['office'],
                    'source_url' => (string) $article['source_url'],
                    'published_at' => $article['published_at'] ?? null,
                    'raw' => $article,
                ];
            }
        }

        return $leads;
    }

    /**
     * Builds one request per house_query_templates entry for a rotating
     * slice of this state's known districts (house_batch_divisor), instead
     * of every district every run — see the class docblock.
     *
     * @param  array<string, string>  $templates  house_query_templates
     * @return array<int, array{state: string, office: string, template_key: string, url: string}>
     */
    private function houseRequestsFor(string $stateAbbr, string $stateName, string $office, array $templates, string $rssUrlTemplate): array
    {
        if ($templates === []) {
            return [];
        }

        $divisor = max(1, (int) config('candidate_discovery_sources.house_batch_divisor', 7));
        $slice = now()->dayOfYear % $divisor;

        $requests = [];
        foreach ($this->districtsForState($stateAbbr) as $index => $districtCode) {
            if ($index % $divisor !== $slice) {
                continue;
            }

            $number = (int) preg_replace('/\D/', '', substr($districtCode, strrpos($districtCode, '-') + 1));
            if ($number < 1) {
                continue;
            }

            $ordinal = $number . match (true) {
                $number % 100 >= 11 && $number % 100 <= 13 => 'th',
                $number % 10 === 1 => 'st',
                $number % 10 === 2 => 'nd',
                $number % 10 === 3 => 'rd',
                default => 'th',
            };

            foreach ($templates as $templateKey => $template) {
                $query = str_replace(
                    ['{STATE_NAME}', '{DISTRICT_ORDINAL}', '{DISTRICT_CODE}'],
                    [$stateName, $ordinal, $districtCode],
                    $template
                );
                $url = str_replace('{QUERY}', rawurlencode($query), $rssUrlTemplate);

                $requests[] = [
                    'state' => $stateAbbr,
                    'office' => $office,
                    'template_key' => $templateKey,
                    'url' => $url,
                ];
            }
        }

        return $requests;
    }

    /**
     * Known district codes (e.g. "CA-12") for a state, sourced from existing
     * federal Representative rows — every district has an incumbent even
     * before we've tracked any challenger, so this needs no separate
     * congressional-district reference table.
     *
     * @return array<int, string>
     */
    private function districtsForState(string $stateAbbr): array
    {
        return Politician::query()
            ->where('state', $stateAbbr)
            ->where('governance_level', 'Federal')
            ->whereRaw('LOWER(political_office) LIKE ?', ['%representative%'])
            ->whereNotNull('district')
            ->where('district', '!=', '')
            ->distinct()
            ->orderBy('district')
            ->pluck('district')
            ->all();
    }

    /**
     * Light heuristic only — the leading Title-Case word span in the headline
     * (after stripping the trailing " - Publisher" suffix Google News adds),
     * skipping generic words. Never trusted directly; verification confirms it.
     */
    private function extractCandidateName(string $headline): ?string
    {
        $headline = trim(preg_replace('/\s+-\s+[^-]+$/', '', $headline) ?? $headline);

        if (preg_match('/^((?:[A-Z][\w.\'-]*\s*){2,4})/u', $headline, $m) !== 1) {
            return null;
        }

        $words = array_values(array_filter(explode(' ', trim($m[1]))));
        $words = array_filter($words, fn (string $w) => ! in_array($w, self::NAME_STOPWORDS, true));

        if (count($words) < 2) {
            return null;
        }

        return implode(' ', $words);
    }
}
