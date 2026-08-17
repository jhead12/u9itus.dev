<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Restricts the "data-list" attribute on <li> to Quill's fixed set of list
 * types. Quill 2.x renders both bullet and ordered lists as <ol><li
 * data-list="...">, using this attribute (via CSS) to decide whether to draw
 * a bullet or a number — without it, a bullet list silently renders as a
 * numbered list.
 */
class PostListAttributeSanitizer implements AttributeSanitizerInterface
{
    private const ALLOWED_VALUES = [
        'bullet',
        'ordered',
        'checked',
        'unchecked',
    ];

    public function getSupportedElements(): ?array
    {
        return ['li'];
    }

    public function getSupportedAttributes(): ?array
    {
        return ['data-list'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        return in_array($value, self::ALLOWED_VALUES, true) ? $value : null;
    }
}
