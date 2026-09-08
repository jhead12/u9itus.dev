<?php

namespace App\Support;

class Seo
{
    // Only content-changing parameters belong in a canonical. Tracking and
    // refresh parameters must never create another indexable URL.
    private const FILTERS = [
        'politicians.directory' => ['q', 'state', 'city', 'district', 'zip', 'topic', 'party', 'level', 'status', 'sort'],
        'pacs.directory' => ['q', 'type', 'party', 'state'],
        'groups.directory' => ['q', 'state', 'city', 'scope'],
        'events.index' => ['q', 'location', 'topic'],
        'blog.index' => ['topic'],
        'blog.topic' => [],
        'blog.author' => [],
        'politician.public.news' => ['q', 'mode', 'sort', 'from', 'to', 'source'],
    ];

    public static function canonical(?string $base = null): string
    {
        $base = $base ?: url()->current();
        $route = request()->route()?->getName();
        if (! array_key_exists($route ?? '', self::FILTERS) || str_contains($base, '?')) {
            return $base;
        }

        $query = self::filters();
        $page = filter_var(request()->query('page'), FILTER_VALIDATE_INT);
        if ($page !== false && $page > 1) {
            $query['page'] = $page;
        }
        ksort($query);

        return $base.($query ? '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
    }

    public static function robots(): string
    {
        return self::filters() || (request()->routeIs('district.lookup') && request()->filled('address'))
            ? 'noindex, follow' : 'index, follow';
    }

    private static function filters(): array
    {
        $keys = self::FILTERS[request()->route()?->getName() ?? ''] ?? [];

        return array_filter(request()->only($keys), fn ($value) => is_scalar($value) && trim((string) $value) !== '');
    }
}
