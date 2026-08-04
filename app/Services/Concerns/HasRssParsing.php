<?php

namespace App\Services\Concerns;

use Illuminate\Http\Client\Response;

/**
 * Parses a Google News / C-SPAN style RSS feed response into a flat array
 * of article rows. Generic to any {QUERY}-driven RSS feed shape used by
 * config/news_sources.php — not specific to candidate or district lookups,
 * so both CandidateNewsService and DistrictNewsService share it instead of
 * duplicating the XML-walking logic.
 */
trait HasRssParsing
{
    /**
     * @return array<int, array<string, mixed>>
     */
    protected function parseRssResponse(Response $response, string $providerId): array
    {
        $xml = @simplexml_load_string($response->body());

        if ($xml === false || ! isset($xml->channel->item)) {
            return [];
        }

        $articles = [];

        foreach ($xml->channel->item as $item) {
            $sourceUrl = (string) ($item->link ?? '');
            if ($sourceUrl === '') {
                continue;
            }

            $pubDate = null;
            try {
                $pubDate = \Carbon\Carbon::parse((string) ($item->pubDate ?? ''));
            } catch (\Throwable) {
                // leave null
            }

            // Google News RSS wraps publisher name in <source>; C-SPAN uses <author>
            $sourceName = (string) ($item->source
                ?? $item->author
                ?? $item->children('dc', true)->creator
                ?? '');
            if ($sourceName === '') {
                // Derive a display name from the config label if we can
                $allSources = array_merge(
                    config('news_sources.national', []),
                    ...array_values(config('news_sources.state', [])),
                );
                foreach ($allSources as $src) {
                    if (($src['id'] ?? '') === $providerId) {
                        $sourceName = $src['label'];
                        break;
                    }
                }
            }

            $articles[] = [
                'headline'     => strip_tags((string) ($item->title ?? '')),
                'source_name'  => $sourceName ?: null,
                'source_url'   => $sourceUrl,
                'snippet'      => strip_tags((string) ($item->description ?? '')),
                'image_url'    => null,
                'published_at' => $pubDate,
                'provider'     => $providerId,
                'source_hash'  => hash('sha256', $sourceUrl),
            ];
        }

        return $articles;
    }
}
