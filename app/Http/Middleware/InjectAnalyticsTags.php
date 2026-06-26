<?php

namespace App\Http\Middleware;

use App\Services\PlatformSettingsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Injects Google Analytics 4 (gtag.js), Google Tag Manager, and Google Ads
 * conversion snippets into every HTML response.
 *
 * IDs are resolved in this order:
 *   1. `config('services.google.*')` (env-backed)
 *   2. `PlatformSettingsService::get('google_analytics_id' | 'google_tag_manager_id' | 'google_ads_conversion_id')`
 *
 * Admins are excluded so internal traffic does not pollute reporting.
 */
class InjectAnalyticsTags
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! $this->shouldInject($request, $response)) {
            return $response;
        }

        [$gaId, $gtmId, $adsId] = $this->resolveIds();

        if (! $gaId && ! $gtmId && ! $adsId) {
            return $response;
        }

        $content = (string) $response->getContent();

        if ($content === '' || stripos($content, '</head>') === false) {
            return $response;
        }

        $headSnippet = $this->buildHeadSnippet($gaId, $gtmId, $adsId);
        $bodySnippet = $this->buildBodySnippet($gtmId);

        if ($headSnippet !== '') {
            $content = $this->injectOnce($content, '</head>', $headSnippet . "\n</head>");
        }

        if ($bodySnippet !== '' && stripos($content, '<body') !== false) {
            // Insert immediately after the opening <body ...> tag.
            $content = preg_replace(
                '/(<body\b[^>]*>)/i',
                '$1' . "\n" . $bodySnippet,
                $content,
                1
            ) ?? $content;
        }

        $response->setContent($content);

        return $response;
    }

    /**
     * @return array{0:?string,1:?string,2:?string}
     */
    protected function resolveIds(): array
    {
        // Cache the platform-settings lookups for 60s to avoid a DB hit per request.
        return Cache::remember('analytics_tags:ids', 60, function (): array {
            $gaId  = trim((string) (config('services.google.analytics_id')
                ?: PlatformSettingsService::get('google_analytics_id', null, '')));
            $gtmId = trim((string) (config('services.google.tag_manager_id')
                ?: PlatformSettingsService::get('google_tag_manager_id', null, '')));
            $adsId = trim((string) (config('services.google.ads_conversion_id')
                ?: PlatformSettingsService::get('google_ads_conversion_id', null, '')));

            return [
                $gaId  !== '' ? $gaId  : null,
                $gtmId !== '' ? $gtmId : null,
                $adsId !== '' ? $adsId : null,
            ];
        });
    }

    protected function shouldInject(Request $request, Response $response): bool
    {
        // Only inject into successful HTML responses for GET requests on the web side.
        if ($request->isMethod('GET') === false) {
            return false;
        }

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 400) {
            return false;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');
        if ($contentType !== '' && stripos($contentType, 'text/html') === false) {
            return false;
        }

        if ($request->ajax() || $request->wantsJson()) {
            return false;
        }

        // Skip admin sessions so admin activity does not pollute analytics.
        try {
            if (Auth::check() && Auth::user()?->hasRole('admin')) {
                return false;
            }
        } catch (\Throwable) {
            // Role checks shouldn't break page rendering.
        }

        // Skip Laravel internal paths.
        $path = $request->path();
        if ($path === 'up' || str_starts_with($path, 'api/') || str_starts_with($path, 'telescope')) {
            return false;
        }

        return true;
    }

    protected function buildHeadSnippet(?string $gaId, ?string $gtmId, ?string $adsId): string
    {
        $parts = [];

        // GTM <head> snippet (loads the container).
        if ($gtmId) {
            $gtmJs = htmlspecialchars($gtmId, ENT_QUOTES, 'UTF-8');
            $parts[] = <<<HTML
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','{$gtmJs}');</script>
<!-- End Google Tag Manager -->
HTML;
        }

        // gtag.js loader — load once, configure each tag.
        $configIds = array_values(array_filter([$gaId, $adsId]));

        if (! empty($configIds)) {
            $loaderId = htmlspecialchars($configIds[0], ENT_QUOTES, 'UTF-8');
            $configs  = '';

            foreach ($configIds as $id) {
                $safe    = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
                $configs .= "  gtag('config', '{$safe}');\n";
            }

            $parts[] = <<<HTML
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id={$loaderId}"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
{$configs}</script>
HTML;
        }

        return implode("\n", $parts);
    }

    protected function buildBodySnippet(?string $gtmId): string
    {
        if (! $gtmId) {
            return '';
        }

        $safe = htmlspecialchars($gtmId, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id={$safe}"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
HTML;
    }

    protected function injectOnce(string $haystack, string $needle, string $replacement): string
    {
        $pos = stripos($haystack, $needle);
        if ($pos === false) {
            return $haystack;
        }

        return substr($haystack, 0, $pos) . $replacement . substr($haystack, $pos + strlen($needle));
    }
}
