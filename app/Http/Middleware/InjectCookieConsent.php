<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Injects a self-contained cookie-consent notice immediately before </body> on
 * every HTML page response.
 *
 * The banner is intentionally framework-free (inline CSS + vanilla JS) so it
 * renders identically across the app's many standalone Blade documents, which
 * variously ship Tailwind, Bootstrap, or no CSS framework at all.
 *
 * Dismissal is remembered in localStorage (key: `u9_cookie_consent`) with a
 * cookie mirror (`u9_cookie_consent`, 1 year) so the server can also suppress
 * the initial render and avoid a flash for returning visitors.
 *
 * Mirrors the guard logic in {@see InjectAnalyticsTags}.
 */
class InjectCookieConsent
{
    public const COOKIE_NAME = 'u9_cookie_consent';

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! $this->shouldInject($request, $response)) {
            return $response;
        }

        $content = (string) $response->getContent();

        if ($content === '' || stripos($content, '</body>') === false) {
            return $response;
        }

        // Already acknowledged on a previous visit — render nothing.
        if (in_array($request->cookie(self::COOKIE_NAME), ['accepted', 'declined'], true)) {
            return $response;
        }

        $snippet = $this->buildSnippet();

        $pos = strripos($content, '</body>');
        $content = substr($content, 0, $pos) . $snippet . substr($content, $pos);

        // setContent() overwrites $response->original with the raw HTML string,
        // discarding the underlying View/renderable. Restore it afterwards so
        // framework internals — and test assertions like assertViewIs() /
        // assertViewHas() — that inspect the original response value keep working.
        $original = $response->original;
        $response->setContent($content);
        $response->original = $original;

        return $response;
    }

    protected function shouldInject(Request $request, Response $response): bool
    {
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

        $path = $request->path();
        if ($path === 'up' || str_starts_with($path, 'api/') || str_starts_with($path, 'telescope')) {
            return false;
        }

        return true;
    }

    protected function buildSnippet(): string
    {
        $privacyUrl = htmlspecialchars(route('privacy-policy'), ENT_QUOTES, 'UTF-8');
        $cookieName = self::COOKIE_NAME;

        return <<<HTML
<!-- Cookie consent notice -->
<div id="u9-cookie-consent" role="region" aria-label="Cookie notice" hidden style="position:fixed;left:0;right:0;bottom:0;z-index:2147483000;box-sizing:border-box;padding:16px;background:#0f172a;border-top:1px solid #1e293b;box-shadow:0 -8px 24px rgba(0,0,0,.35);font-family:ui-sans-serif,system-ui,-apple-system,'Inter',Segoe UI,Roboto,Helvetica,Arial,sans-serif;">
  <div style="max-width:1120px;margin:0 auto;display:flex;flex-wrap:wrap;align-items:center;gap:12px 20px;">
    <p style="flex:1 1 320px;margin:0;color:#cbd5e1;font-size:13px;line-height:1.6;">
      We use cookies to keep you signed in, remember your preferences, and understand how the site is used.
      <strong style="color:#fff;font-weight:600;">We do not sell or share your personal data with third parties for their own marketing.</strong>
      See our <a href="{$privacyUrl}" style="color:#34d399;text-decoration:underline;">Privacy Policy</a> for details.
    </p>
    <div style="display:flex;gap:10px;flex:0 0 auto;">
      <button type="button" data-u9-cookie="decline" style="cursor:pointer;border:1px solid #334155;background:transparent;color:#cbd5e1;font-size:13px;font-weight:600;padding:9px 16px;border-radius:8px;">Decline non-essential</button>
      <button type="button" data-u9-cookie="accept" style="cursor:pointer;border:0;background:#059669;color:#fff;font-size:13px;font-weight:600;padding:9px 18px;border-radius:8px;">Got it</button>
    </div>
  </div>
</div>
<script>(function(){
  var KEY = '{$cookieName}';
  var el = document.getElementById('u9-cookie-consent');
  if (!el) return;
  var stored;
  try { stored = window.localStorage.getItem(KEY); } catch (e) { stored = null; }
  if (stored === 'accepted' || stored === 'declined') { el.parentNode.removeChild(el); return; }
  el.hidden = false;
  function dismiss(choice) {
    try { window.localStorage.setItem(KEY, choice); } catch (e) {}
    try {
      var d = new Date(); d.setFullYear(d.getFullYear() + 1);
      document.cookie = KEY + '=' + (choice === 'accepted' ? 'accepted' : 'declined') +
        ';expires=' + d.toUTCString() + ';path=/;SameSite=Lax' +
        (location.protocol === 'https:' ? ';Secure' : '');
    } catch (e) {}
    if (el && el.parentNode) el.parentNode.removeChild(el);
  }
  el.addEventListener('click', function(ev) {
    var t = ev.target.closest('[data-u9-cookie]');
    if (!t) return;
    dismiss(t.getAttribute('data-u9-cookie') === 'accept' ? 'accepted' : 'declined');
  });
})();</script>
<!-- End cookie consent notice -->
HTML;
    }
}
