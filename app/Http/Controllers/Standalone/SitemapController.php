<?php

namespace App\Http\Controllers\Standalone;

use App\Enums\CivicEventStatus;
use App\Http\Controllers\Controller;
use App\Models\CivicEvent;
use App\Models\Committee;
use App\Models\NeighborhoodGroup;
use App\Models\Politician;
use App\Models\PoliticianTopic;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Generates a dynamic XML sitemap for U9itus.
 *
 * Includes:
 *   - Static public pages (map, district lookup, politicians directory)
 *   - Active published profiles, enriched PACs, groups, events, and blog content
 *
 * Cached for 6 hours to keep DB load minimal.
 */
class SitemapController extends Controller
{
    private const CACHE_TTL = 21_600; // 6 hours in seconds

    public function index(Request $request): Response
    {
        $xml = Cache::remember('sitemap_xml_v2:'.sha1(config('app.url')), self::CACHE_TTL, function () {
            return $this->buildXml();
        });

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=utf-8')
            ->header('X-Robots-Tag', 'noindex'); // sitemap itself shouldn't be indexed
    }

    private function buildXml(): string
    {
        $base = rtrim(config('app.url', 'https://u9itus.com'), '/');

        $urls = [];

        // ── Static pages ────────────────────────────────────────────────────
        $statics = [
            ['loc' => '/',                  'priority' => '1.0',  'freq' => 'weekly'],
            ['loc' => '/map',               'priority' => '0.9',  'freq' => 'daily'],
            ['loc' => '/politicians',       'priority' => '0.9',  'freq' => 'daily'],
            ['loc' => '/blog',              'priority' => '0.9',  'freq' => 'daily'],
            ['loc' => '/district-lookup',   'priority' => '0.8',  'freq' => 'weekly'],
            ['loc' => '/pacs',              'priority' => '0.8',  'freq' => 'daily'],
            ['loc' => '/groups',            'priority' => '0.7',  'freq' => 'weekly'],
            ['loc' => '/events',            'priority' => '0.7',  'freq' => 'daily'],
            ['loc' => '/earn',              'priority' => '0.7',  'freq' => 'monthly'],
        ];

        foreach ($statics as $page) {
            $urls[] = $this->urlTag($base.$page['loc'], null, $page['freq'], $page['priority']);
        }

        // ── Politician public profiles ────────────────────────────────────
        // Only include politicians with a published public page and a slug.
        Politician::query()
            ->publiclyVisible()
            ->whereNotNull('slug')
            ->select(['slug', 'updated_at'])
            ->orderBy('updated_at', 'desc')
            ->chunk(200, function ($politicians) use ($base, &$urls) {
                foreach ($politicians as $politician) {
                    $urls[] = $this->urlTag(
                        $base.'/p/'.$politician->slug,
                        $politician->updated_at?->toAtomString(),
                        'weekly',
                        '0.7'
                    );
                }
            });

        // ── Blog topic archives ───────────────────────────────────────────────
        PoliticianTopic::query()
            ->where('is_active', true)
            ->whereIn('id', function ($query) {
                $query->select('post_topic.topic_id')->from('post_topic')
                    ->whereIn('post_id', Post::query()->published()->select('id'));
            })
            ->whereNotNull('slug')
            ->select(['slug', 'updated_at'])
            ->orderBy('updated_at', 'desc')
            ->chunk(200, function ($topics) use ($base, &$urls) {
                foreach ($topics as $topic) {
                    $urls[] = $this->urlTag(
                        $base.'/blog/topic/'.$topic->slug,
                        $topic->updated_at?->toAtomString(),
                        'weekly',
                        '0.6'
                    );
                }
            });

        // ── Published blog posts ──────────────────────────────────────────────
        Post::query()
            ->published()
            ->whereNotNull('slug')
            ->select(['slug', 'updated_at', 'canonical_url'])
            ->orderBy('published_at', 'desc')
            ->chunk(200, function ($posts) use ($base, &$urls) {
                foreach ($posts as $post) {
                    $url = $base.'/blog/'.$post->slug;
                    if ($post->canonical_url && $post->canonical_url !== $url) {
                        continue;
                    }
                    $urls[] = $this->urlTag(
                        $base.'/blog/'.$post->slug,
                        $post->updated_at?->toAtomString(),
                        'monthly',
                        '0.6'
                    );
                }
            });

        // Include only enriched PACs, as required by the public controller.
        Committee::listable()->with('profile')->chunkById(200, function ($committees) use ($base, &$urls) {
            foreach ($committees as $committee) {
                $urls[] = $this->urlTag($base.'/pacs/'.$committee->publicSlug(),
                    $committee->profile->updated_at?->toAtomString(), 'weekly', '0.7');
            }
        });

        CivicEvent::where('status', CivicEventStatus::Published)->whereNotNull('slug')
            ->chunkById(200, function ($events) use ($base, &$urls) {
                foreach ($events as $event) {
                    $urls[] = $this->urlTag($base.'/events/'.$event->slug,
                        $event->updated_at?->toAtomString(), 'weekly', '0.6');
                }
            });

        NeighborhoodGroup::whereNotNull('slug')->chunkById(200, function ($groups) use ($base, &$urls) {
            foreach ($groups as $group) {
                $scope = $group->scopeUrlSegment();
                $path = '/groups/'.$group->slug.($scope ? '/'.$scope : '');
                $urls[] = $this->urlTag($base.$path, $group->updated_at?->toAtomString(), 'weekly', '0.6');
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

    private function urlTag(string $loc, ?string $lastmod, string $changefreq, string $priority): string
    {
        $loc = htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $modified = $lastmod ? '<lastmod>'.htmlspecialchars($lastmod, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</lastmod>' : '';

        return <<<XML
  <url>
    <loc>{$loc}</loc>
    {$modified}
    <changefreq>{$changefreq}</changefreq>
    <priority>{$priority}</priority>
  </url>
XML;
    }
}
