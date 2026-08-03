<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Turns a pasted YouTube / Instagram / SoundCloud / X (Twitter) / TikTok URL
 * into safe, embeddable HTML for a blog post body.
 *
 * Security model: the returned HTML is always built by this class from a
 * fixed template, never from arbitrary user-supplied markup — the only user
 * input is the URL, and it's validated against a strict per-platform regex
 * before it's allowed to influence the output at all. Post::htmlSanitizer()
 * re-validates the result independently before it's ever persisted, so a bug
 * here can't smuggle anything past that second, unrelated check.
 *
 * YouTube, Instagram, and SoundCloud all expose a stable, documented iframe
 * embed URL, so those are built directly with no network call. X and TikTok
 * have no equivalent — their only supported embed mechanism is their oEmbed
 * API — so for those two this makes a server-side HTTP call to the
 * platform's own oEmbed endpoint and keeps only the resulting <blockquote>
 * (the script tag they also return is dropped; X's oEmbed response embeds
 * the actual tweet text as real HTML, so the un-scripted blockquote alone is
 * still meaningfully readable rather than blank).
 */
class PostEmbedService
{
    private const YOUTUBE_PATTERN = '/^https?:\/\/(?:www\.)?(?:m\.)?(?:youtube\.com\/(?:watch\?v=|shorts\/|embed\/)|youtu\.be\/)([\w-]{6,32})/i';

    private const SOUNDCLOUD_PATTERN = '/^https?:\/\/(?:www\.)?soundcloud\.com\/[\w\-\/]+$/i';

    private const INSTAGRAM_PATTERN = '/^https?:\/\/(?:www\.)?instagram\.com\/(p|reel|tv)\/([\w-]+)/i';

    private const TWITTER_PATTERN = '/^https?:\/\/(?:www\.)?(?:twitter\.com|x\.com)\/\w+\/status\/\d+/i';

    private const TIKTOK_PATTERN = '/^https?:\/\/(?:www\.)?tiktok\.com\/@[\w.-]+\/video\/\d+/i';

    public function resolve(string $url): ?string
    {
        $url = trim($url);

        if ($url === '' || ! preg_match('/^https?:\/\//i', $url)) {
            return null;
        }

        return $this->youtube($url)
            ?? $this->instagram($url)
            ?? $this->soundcloud($url)
            ?? $this->twitter($url)
            ?? $this->tiktok($url);
    }

    private function youtube(string $url): ?string
    {
        if (! preg_match(self::YOUTUBE_PATTERN, $url, $m)) {
            return null;
        }

        $id = e($m[1]);

        return '<iframe src="https://www.youtube.com/embed/'.$id.'" width="560" height="315" '
            .'frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; '
            .'gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>';
    }

    private function instagram(string $url): ?string
    {
        if (! preg_match(self::INSTAGRAM_PATTERN, $url, $m)) {
            return null;
        }

        $type = e($m[1]);
        $id = e($m[2]);

        return '<iframe src="https://www.instagram.com/'.$type.'/'.$id.'/embed" width="400" height="480" '
            .'frameborder="0" allowfullscreen></iframe>';
    }

    private function soundcloud(string $url): ?string
    {
        if (! preg_match(self::SOUNDCLOUD_PATTERN, $url)) {
            return null;
        }

        $encoded = urlencode($url);

        return '<iframe width="100%" height="166" frameborder="0" allow="autoplay" '
            .'src="https://w.soundcloud.com/player/?url='.$encoded
            .'&color=%23ff5500&auto_play=false&show_teaser=false"></iframe>';
    }

    private function twitter(string $url): ?string
    {
        if (! preg_match(self::TWITTER_PATTERN, $url)) {
            return null;
        }

        $html = $this->fetchOembedHtml('https://publish.twitter.com/oembed', [
            'url' => $url,
            'omit_script' => 'true',
            'dnt' => 'true',
        ], 'PostEmbedService: X/Twitter oEmbed failed');

        return $html !== null ? $this->flattenToInlineBlockquote($html) : null;
    }

    private function tiktok(string $url): ?string
    {
        if (! preg_match(self::TIKTOK_PATTERN, $url)) {
            return null;
        }

        $html = $this->fetchOembedHtml('https://www.tiktok.com/oembed', [
            'url' => $url,
        ], 'PostEmbedService: TikTok oEmbed failed');

        return $html !== null ? $this->flattenToInlineBlockquote($html) : null;
    }

    /**
     * X and TikTok's oEmbed responses both wrap their fallback content in
     * block-level tags (<p>, <section>) nested inside the outer <blockquote>.
     * Quill's Delta model has no representation for a block element nested
     * inside another block element — dangerouslyPasteHTML() silently splits
     * the content into multiple sibling <blockquote>s and drops the class
     * attribute in the process (confirmed live: a real X embed came back as
     * two separate unstyled blockquotes). Unwrapping every nested block tag
     * down to plain inline content (text + <a>) keeps the whole thing as one
     * blockquote with only inline children, which round-trips correctly.
     */
    private function flattenToInlineBlockquote(string $html): string
    {
        $html = $this->stripScriptTags($html);
        // Opening tags just get dropped; closing tags become a space so text
        // that was on separate lines/blocks (e.g. tweet text vs. attribution)
        // doesn't visually run together once unwrapped.
        $html = preg_replace('/<(?:p|section|div)\b[^>]*>/i', '', $html) ?? $html;
        $html = preg_replace('/<\/(?:p|section|div)>/i', ' ', $html) ?? $html;

        return trim(preg_replace('/\s+/', ' ', $html) ?? $html);
    }

    /**
     * @param  array<string,string>  $query
     */
    private function fetchOembedHtml(string $endpoint, array $query, string $logMessage): ?string
    {
        try {
            $response = Http::timeout(8)->get($endpoint, $query);

            if (! $response->successful()) {
                Log::warning($logMessage, ['status' => $response->status()]);

                return null;
            }

            $html = $response->json('html');

            return is_string($html) && $html !== '' ? $html : null;
        } catch (\Throwable $e) {
            Log::warning($logMessage, ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function stripScriptTags(string $html): string
    {
        return trim(preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html);
    }
}
