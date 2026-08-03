<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Restricts <iframe src> on post bodies to the exact fixed-domain embed URLs
 * PostEmbedService generates (YouTube, Instagram, SoundCloud) — never an
 * arbitrary iframe src, which would let a post embed literally any page.
 */
class PostEmbedIframeAttributeSanitizer implements AttributeSanitizerInterface
{
    private const ALLOWED_SRC_PATTERNS = [
        '/^https:\/\/www\.youtube\.com\/embed\/[\w-]{6,32}$/',
        '/^https:\/\/www\.instagram\.com\/(p|reel|tv)\/[\w-]+\/embed$/',
        '/^https:\/\/w\.soundcloud\.com\/player\/\?url=[^&\s"]+.*$/',
    ];

    public function getSupportedElements(): ?array
    {
        return ['iframe'];
    }

    public function getSupportedAttributes(): ?array
    {
        return ['src'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        foreach (self::ALLOWED_SRC_PATTERNS as $pattern) {
            if (preg_match($pattern, $value) === 1) {
                return $value;
            }
        }

        return null;
    }
}
