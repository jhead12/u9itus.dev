<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Restricts the "class" attribute on post body block elements to Quill's
 * fixed set of text-alignment classes, so it can't be used to smuggle
 * arbitrary CSS classes into rendered post HTML.
 */
class PostAlignmentAttributeSanitizer implements AttributeSanitizerInterface
{
    private const ALLOWED_CLASSES = [
        'ql-align-center',
        'ql-align-right',
        'ql-align-justify',
    ];

    public function getSupportedElements(): ?array
    {
        return ['p', 'li', 'h2', 'h3'];
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
