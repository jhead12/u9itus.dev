<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\Politician;
use App\Models\Post;
use App\Models\PoliticianTopic;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Generates a dynamic XML sitemap for U9itus.
 *
 * Includes:
 *   - Static public pages (map, district lookup, politicians directory)
 *   - All published politician profiles (/p/{slug})
 *
 * Cached for 6 hours to keep DB load minimal.
 */
class SitemapController extends Controller
{
    private const CACHE_TTL = 21_600; // 6 hours in seconds

    public function index(Request $request): Response
    {
        $xml = Cache::remember('sitemap_xml', self::CACHE_TTL, function () {
            return $this->buildXml();
        });

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=utf-8')
            ->header('X-Robots-Tag', 'noindex'); // sitemap itself shouldn't be indexed
    }

    private function buildXml(): string
    {
        $base = rtrim(config('app.url', 'https://u9itus.com'), '/');
        $now  = now()->toAtomString();

        $urls = [];

        // ── Static pages ────────────────────────────────────────────────────
        $statics = [
            ['loc' => '/',                  'priority' => '1.0',  'freq' => 'weekly'],
            ['loc' => '/map',               'priority' => '0.9',  'freq' => 'daily'],
            ['loc' => '/politicians',       'priority' => '0.9',  'freq' => 'daily'],
            ['loc' => '/blog',              'priority' => '0.9',  'freq' => 'daily'],
            ['loc' => '/district-lookup',   'priority' => '0.8',  'freq' => 'weekly'],
            ['loc' => '/register',          'priority' => '0.6',  'freq' => 'monthly'],
        ];

        foreach ($statics as $page) {
            $urls[] = $this->urlTag($base . $page['loc'], $now, $page['freq'], $page['priority']);
        }

        // ── Politician public profiles ────────────────────────────────────
        // Only include politicians with a published public page and a slug.
        Politician::query()
            ->where('page_published', true)
            ->whereNotNull('slug')
            ->select(['slug', 'updated_at'])
            ->orderBy('updated_at', 'desc')
            ->chunk(200, function ($politicians) use ($base, &$urls) {
                foreach ($politicians as $politician) {
                    $urls[] = $this->urlTag(
                        $base . '/p/' . $politician->slug,
                        $politician->updated_at?->toAtomString() ?? now()->toAtomString(),
                        'weekly',
                        '0.7'
                    );
                }
            });

        // ── Blog topic archives ───────────────────────────────────────────────
        PoliticianTopic::query()
            ->where('is_active', true)
            ->whereNotNull('slug')
            ->select(['slug', 'updated_at'])
            ->orderBy('updated_at', 'desc')
            ->chunk(200, function ($topics) use ($base, &$urls) {
                foreach ($topics as $topic) {
                    $urls[] = $this->urlTag(
                        $base . '/blog/topic/' . $topic->slug,
                        $topic->updated_at?->toAtomString() ?? now()->toAtomString(),
                        'weekly',
                        '0.6'
                    );
                }
            });

        // ── Published blog posts ──────────────────────────────────────────────
        Post::query()
            ->published()
            ->whereNotNull('slug')
            ->select(['slug', 'updated_at'])
            ->orderBy('published_at', 'desc')
            ->chunk(200, function ($posts) use ($base, &$urls) {
                foreach ($posts as $post) {
                    $urls[] = $this->urlTag(
                        $base . '/blog/' . $post->slug,
                        $post->updated_at?->toAtomString() ?? now()->toAtomString(),
                        'monthly',
                        '0.6'
                    );
                }
            });

        $inner = implode("\n", $urls);

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9
            http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">
{$inner}
</urlset>
XML;
    }

    private function urlTag(string $loc, string $lastmod, string $changefreq, string $priority): string
    {
        $loc = htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        return <<<XML
  <url>
    <loc>{$loc}</loc>
    <lastmod>{$lastmod}</lastmod>
    <changefreq>{$changefreq}</changefreq>
    <priority>{$priority}</priority>
  </url>
XML;
    }
}
