<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Finds a headshot on the candidate's own website.
 *
 * Campaign sites publish a social-share image (og:image / twitter:image) that is very
 * often the candidate's portrait, but sometimes a logo or banner — so obvious logos are
 * rejected here and politicians:validate-profile-photos remains the safety net that
 * quarantines anything that is not a face.
 */
class CandidateWebsitePhotoFinder
{
    private const USER_AGENT = 'U9itus-civic-enrichment/1.0 (+https://u9itus.dev/about)';

    private const MAX_HTML_BYTES = 400000;

    /** Filename hints that the image is site branding, not a person. */
    private const NON_PORTRAIT_HINTS = ['logo', 'wordmark', 'favicon', 'icon', 'banner', 'header', 'sprite', 'placeholder', 'default', 'blank', 'seal', 'flag_of', 'yard-sign', 'yardsign'];

    public function find(?string $websiteUrl): ?string
    {
        $page = $this->normalizePageUrl($websiteUrl);
        if ($page === null) {
            return null;
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => self::USER_AGENT, 'Accept' => 'text/html'])
                ->get($page);

            if (! $response->ok()) {
                return null;
            }

            $html = substr($response->body(), 0, self::MAX_HTML_BYTES);
        } catch (\Throwable $e) {
            Log::debug('CandidateWebsitePhotoFinder: page fetch failed', ['url' => $page, 'error' => $e->getMessage()]);

            return null;
        }

        foreach ($this->candidateImages($html) as $raw) {
            $image = $this->absolutize($raw, $page);
            if ($image !== null && ! $this->looksLikeBranding($image) && $this->isReachableImage($image)) {
                return $image;
            }
        }

        return null;
    }

    /**
     * Social-share images in order of how reliably they are the person's portrait.
     *
     * @return array<int, string>
     */
    protected function candidateImages(string $html): array
    {
        $found = [];

        if (preg_match_all('/<meta\b[^>]*>/i', $html, $tags)) {
            foreach ($tags[0] as $tag) {
                if (! preg_match('/\b(?:property|name)\s*=\s*["\'](og:image(?::secure_url)?|twitter:image(?::src)?)["\']/i', $tag, $key)
                    || ! preg_match('/\bcontent\s*=\s*["\']([^"\']+)["\']/i', $tag, $content)) {
                    continue;
                }

                $found[] = html_entity_decode(trim($content[1]));
            }
        }

        return array_values(array_unique(array_filter($found)));
    }

    protected function normalizePageUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '' || ! str_contains($host, '.') || $this->isInternalHost($host)) {
            return null;
        }

        return $url;
    }

    /** The URL comes from scraped data, so never let it point the server at itself or its network. */
    protected function isInternalHost(string $host): bool
    {
        if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return true;
        }

        return filter_var($host, FILTER_VALIDATE_IP) !== false
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    protected function absolutize(string $image, string $pageUrl): ?string
    {
        if (str_starts_with($image, '//')) {
            $image = 'https:'.$image;
        } elseif (! preg_match('#^https?://#i', $image)) {
            $parts = parse_url($pageUrl);
            $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');
            $image = $origin.'/'.ltrim($image, '/');
        }

        // Stored photos are rendered on an https page.
        $image = preg_replace('#^http://#i', 'https://', $image);

        return mb_strlen($image) <= 2000 && $this->normalizePageUrl($image) !== null ? $image : null;
    }

    protected function looksLikeBranding(string $imageUrl): bool
    {
        $path = strtolower((string) parse_url($imageUrl, PHP_URL_PATH));
        if (str_ends_with($path, '.svg') || str_ends_with($path, '.ico')) {
            return true;
        }

        $file = basename($path);
        foreach (self::NON_PORTRAIT_HINTS as $hint) {
            if (str_contains($file, $hint)) {
                return true;
            }
        }

        return false;
    }

    protected function isReachableImage(string $imageUrl): bool
    {
        try {
            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->head($imageUrl);

            return $response->ok() && str_starts_with(strtolower((string) $response->header('Content-Type')), 'image/');
        } catch (\Throwable) {
            return false;
        }
    }
}
