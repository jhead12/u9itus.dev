<?php

namespace App\Services;

use App\Models\Voter;
use Illuminate\Http\Request;

/**
 * Device Fingerprint Service — Phase 8
 *
 * Builds and validates a server-side device fingerprint from HTTP request
 * signals.  The fingerprint is intentionally coarse-grained so that normal
 * browser updates (patch versions, minor Accept changes) don't produce false
 * positives, while still catching headless scrapers and fresh sessions opened
 * by the same physical device from a different account.
 *
 * Component signals collected:
 *  • User-agent family (browser + major version, OS family)
 *  • Accept-Language header (normalised to base locale, e.g. "en")
 *  • Screen-size hint from the X-Screen-Size custom header (set by the
 *    heartbeat JS on the voter watch page)
 *  • Platform hint from Sec-CH-UA-Platform (modern browsers)
 *
 * The client-side watch page JavaScript sends additional canvas / WebGL
 * fingerprint data via X-Device-Fingerprint.  When that header is present
 * the server-side fingerprint is blended with it for a stronger composite.
 */
class DeviceFingerprintService
{
    /**
     * Build a composite fingerprint string from the incoming request.
     *
     * This value is stable across requests from the same device/browser
     * but changes meaningfully when the device or browser family changes.
     */
    public function generate(Request $request): string
    {
        $components = [
            $this->normaliseUserAgent($request->userAgent() ?? ''),
            $this->normaliseLanguage($request->header('Accept-Language', '')),
            $request->header('Sec-CH-UA-Platform', ''),
            $request->header('X-Screen-Size', ''),       // sent by heartbeat JS
        ];

        // Blend in the JS-side fingerprint if the client provided one.
        $clientSide = $request->header('X-Device-Fingerprint')
                   ?? $request->input('device_fingerprint', '');

        if (!empty($clientSide)) {
            $components[] = substr($clientSide, 0, 64); // truncate to prevent injection
        }

        return hash('sha256', implode('|', $components));
    }

    /**
     * Compare a new fingerprint against the voter's stored fingerprint.
     *
     * Returns one of:
     *  'match'    — same device (or no stored fingerprint yet)
     *  'new'      — voter has no stored fingerprint; the new one should be saved
     *  'mismatch' — fingerprint changed — potentially a different device
     */
    public function compare(string $incoming, Voter $voter): string
    {
        if (empty($voter->device_fingerprint)) {
            return 'new';
        }

        return hash_equals($voter->device_fingerprint, $incoming) ? 'match' : 'mismatch';
    }

    /**
     * Persist the fingerprint to the voter record (only when 'new').
     */
    public function storeIfNew(string $fingerprint, Voter $voter): void
    {
        if (empty($voter->device_fingerprint)) {
            $voter->update(['device_fingerprint' => $fingerprint]);
        }
    }

    /**
     * Analyse the raw User-Agent for headless browser / bot signatures.
     *
     * @return array{is_bot: bool, reason: string|null}
     */
    public function analyseUserAgent(string $ua): array
    {
        if (empty($ua)) {
            return ['is_bot' => true, 'reason' => 'empty_user_agent'];
        }

        $uaLower = strtolower($ua);

        $botKeywords = [
            'headlesschrome', 'phantomjs', 'slimerjs', 'selenium',
            'webdriver', 'puppeteer', 'playwright', 'bot', 'spider',
            'crawl', 'scraper', 'curl/', 'wget/', 'python-requests',
            'go-http-client', 'java/', 'okhttp', 'axios/', 'node-fetch',
        ];

        foreach ($botKeywords as $kw) {
            if (str_contains($uaLower, $kw)) {
                return ['is_bot' => true, 'reason' => "ua_keyword:{$kw}"];
            }
        }

        // Real browsers always include at least one of Mozilla or AppleWebKit or Gecko.
        $hasRealBrowserMarker = str_contains($uaLower, 'mozilla/')
            || str_contains($uaLower, 'applewebkit')
            || str_contains($uaLower, 'gecko');

        if (! $hasRealBrowserMarker) {
            return ['is_bot' => true, 'reason' => 'no_browser_marker'];
        }

        return ['is_bot' => false, 'reason' => null];
    }

    // ─── Internal helpers ────────────────────────────────────────────────────

    /**
     * Extract browser-family + major-version + OS-family from a full UA string.
     * e.g. "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36
     *        (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
     *      → "chrome:120|windows"
     */
    private function normaliseUserAgent(string $ua): string
    {
        $ua = strtolower($ua);

        // Browser family + major version
        $browser = 'unknown:0';
        if (preg_match('/(chrome|firefox|safari|edg)\/([\d]+)/i', $ua, $m)) {
            // Distinguish Edge from Chrome
            if (str_contains($ua, 'edg/') || str_contains($ua, 'edge/')) {
                $browser = 'edge:' . ($m[2]);
            } else {
                $browser = strtolower($m[1]) . ':' . $m[2];
            }
        }

        // OS family
        $os = match (true) {
            str_contains($ua, 'windows') => 'windows',
            str_contains($ua, 'macintosh') || str_contains($ua, 'mac os') => 'mac',
            str_contains($ua, 'android') => 'android',
            str_contains($ua, 'iphone') || str_contains($ua, 'ipad') => 'ios',
            str_contains($ua, 'linux') => 'linux',
            default => 'unknown',
        };

        return "{$browser}|{$os}";
    }

    /**
     * Reduce Accept-Language to its primary locale code.
     * "en-US,en;q=0.9,es;q=0.8" → "en"
     */
    private function normaliseLanguage(string $header): string
    {
        if (empty($header)) {
            return 'unknown';
        }

        $primary = explode(',', $header)[0];       // "en-US"
        $lang    = explode('-', $primary)[0];       // "en"
        $lang    = explode(';', $lang)[0];          // strip q-value if present

        return strtolower(trim($lang));
    }
}
