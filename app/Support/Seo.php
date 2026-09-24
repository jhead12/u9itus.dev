<?php

namespace App\Support;

use App\Models\Politician;
use Illuminate\Support\Str;

class Seo
{
    // Only content-changing parameters belong in a canonical. Tracking and
    // refresh parameters must never create another indexable URL.
    private const FILTERS = [
        'politicians.directory' => ['q', 'state', 'city', 'district', 'zip', 'topic', 'party', 'level', 'status', 'sort', 'unclaimed'],
        'pacs.directory' => ['q', 'type', 'party', 'state'],
        'groups.directory' => ['q', 'state', 'city', 'scope'],
        'events.index' => ['q', 'location', 'topic'],
        'blog.index' => ['topic'],
        'blog.topic' => [],
        'blog.author' => [],
        'politician.public.news' => ['q', 'mode', 'sort', 'from', 'to', 'source'],
        'politician.public.speeches' => ['q', 'topic'],
    ];

    // Account and security screens have nothing a searcher wants to land on.
    private const PRIVATE_ROUTES = [
        'login', 'register', 'register.*', 'password.*', 'verification.*',
        'phone.verify', '2fa.*', 'admin.login', 'admin.2fa.*', 'portal-pick',
    ];

    // Import boilerplate that thousands of unclaimed profiles share verbatim.
    private const PLACEHOLDER_SENTENCE = '/[^.]*\\b(unclaimed profile|available for verified claim)\\b[^.]*\\.?/i';

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
        if (request()->routeIs(...self::PRIVATE_ROUTES)) {
            return 'noindex, nofollow';
        }

        return self::filters() || (request()->routeIs('district.lookup') && request()->filled('address'))
            ? 'noindex, follow' : 'index, follow';
    }

    // A bio that is only import boilerplate would give every unclaimed profile
    // the same description, so describe the person from their record instead.
    public static function profileDescription(Politician $politician): string
    {
        $bio = trim(preg_replace('/\\s+/', ' ', preg_replace(self::PLACEHOLDER_SENTENCE, '', strip_tags((string) $politician->bio))));
        if ($bio !== '') {
            return Str::limit($bio, 160);
        }

        $office = $politician->political_office ?: 'public office';
        $district = $politician->district && ! str_contains($office, (string) $politician->district)
            ? " ({$politician->district})" : '';
        $role = trim(($politician->party_affiliation ?? '').' '.($politician->is_running_candidate && $politician->term_status !== 'seated' ? 'candidate for ' : '').$office);
        $place = $politician->state ? " in {$politician->state}" : '';

        return Str::limit("{$politician->full_name}: {$role}{$district}{$place}. See positions, campaign finance filings, voting record and news on U9itus.", 160);
    }

    private static function filters(): array
    {
        $keys = self::FILTERS[request()->route()?->getName() ?? ''] ?? [];

        return array_filter(request()->only($keys), fn ($value) => is_scalar($value) && trim((string) $value) !== '');
    }
}
