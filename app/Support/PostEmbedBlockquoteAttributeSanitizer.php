<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Restricts the "class" attribute on <blockquote> to the two values
 * PostEmbedService's X/Twitter and TikTok oEmbed responses use. Scoped to
 * blockquote only (not the shared post-body class allow-list) so it can't
 * widen what's allowed on p/li/h2/h3.
 */
class PostEmbedBlockquoteAttributeSanitizer implements AttributeSanitizerInterface
{
    private const ALLOWED_CLASSES = [
        'twitter-tweet',
        'tiktok-embed',
    ];

    public function getSupportedElements(): ?array
    {
        return ['blockquote'];
    }

    public function getSupportedAttributes(): ?array
    {
        return ['class'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        return in_array($value, self::ALLOWED_CLASSES, true) ? $value : null;
    }
}
