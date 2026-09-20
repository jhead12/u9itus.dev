<?php

namespace App\Services;

use App\Models\Committee;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CommitteeWebsiteDiscoveryService
{
    public function __construct(private FECService $fec) {}

    /** @return array{url: string, source: string}|null */
    public function discoverFor(Committee $committee): ?array
    {
        $curated = config('committee_websites.'.$committee->fec_committee_id);
        if (is_array($curated) && ($url = $this->sanitize($curated['url'] ?? null))) {
            return ['url' => $url, 'source' => $curated['source']];
        }

        $filed = $committee->profile?->fec_website_url;
        if (! $filed && $this->fec->isConfigured()) {
            $filed = $this->fec->getCommitteeDetail($committee->fec_committee_id)['website'] ?? null;
        }
        if ($url = $this->sanitize($filed)) {
            return ['url' => $url, 'source' => 'https://www.fec.gov/data/committee/'.$committee->fec_committee_id.'/'];
        }

        $name = trim((string) $committee->name);
        if ($name === '' || $name === $committee->fec_committee_id) {
            return null;
        }

        // Use a named reference page, and only its explicitly labelled official link.
        $names = array_unique([$name, Str::title($name)]);
        foreach ($names as $pageName) {
            $source = 'https://ballotpedia.org/'.rawurlencode(str_replace(' ', '_', $pageName));
            $response = Http::timeout(15)->withOptions(['allow_redirects' => false])
                ->withUserAgent('u9itus-sync/1.0 (committee-website-discovery)')->get($source);
            if ($response->status() === 404) {
                continue;
            }
            $response->throw();
            if (! $response->ok()) {
                continue;
            }

            $document = new \DOMDocument;
            @$document->loadHTML('<?xml encoding="UTF-8">'.$response->body());
            $xpath = new \DOMXPath($document);
            foreach ($xpath->query('//a[@href]') as $link) {
                $label = trim(preg_replace('/\s+/', ' ', $link->textContent));
                if (! preg_match('/^(official (website|site)|'.preg_quote($name, '/').',? official (website|site))$/i', $label)) {
                    continue;
                }
                if ($url = $this->sanitize($link->getAttribute('href'))) {
                    return ['url' => $url, 'source' => $source];
                }
            }
        }

        return null;
    }

    private function sanitize(?string $url): ?string
    {
        $url = trim(html_entity_decode($url ?? ''));
        if ($url === '') {
            return null;
        }
        if (! str_contains($url, '://')) {
            $url = 'https://'.$url;
        }
        $parts = parse_url($url);
        if (! $parts || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
            || strlen($url) > 2048 || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        $host = strtolower($parts['host'] ?? '');
        if (! str_contains($host, '.') || filter_var($host, FILTER_VALIDATE_IP)
            || str_ends_with($host, '.local') || str_ends_with($host, '.localhost')) {
            return null;
        }
        foreach (['fec.gov', 'ballotpedia.org', 'wikipedia.org', 'opensecrets.org', 'facebook.com', 'x.com', 'twitter.com', 'instagram.com', 'youtube.com'] as $excluded) {
            if ($host === $excluded || str_ends_with($host, '.'.$excluded)) {
                return null;
            }
        }

        return $url;
    }
}
