<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\Politician;
use Illuminate\Http\Request;

/**
 * Phase 13 — Public Politician Profile Pages
 *
 * Serves the public-facing campaign page at /p/{slug}.
 * No authentication required.
 */
class PublicProfileController extends Controller
{
    /**
     * Display the politician's public profile page.
     *
     * Slug format: {5-char-uuid-prefix}-{seo-readable-name}
     * e.g. /p/a3f9b-mayor-john-smith-chicago
     */
    public function show(Request $request, string $slug)
    {
        $politician = Politician::where('slug', $slug)
            ->where('page_published', true)
            ->where('is_active', true)
            ->firstOrFail();

        // Eager-load what we need for the public page
        $politician->load(['page', 'initiatives' => fn($q) => $q->published()->ordered()]);

        // Page config (use defaults if politician hasn't saved one yet)
        $page = $politician->page ?? new \App\Models\PoliticianPage(\App\Models\PoliticianPage::defaults($politician->id));

        // Active campaigns to feature on the page
        $campaigns = $politician->campaigns()
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->take(6)
            ->get();

        $initiatives = $politician->initiatives;

        // Build Open Graph meta
        $ogTitle       = $politician->full_name . ' — ' . ($politician->political_office ?? 'Politician');
        $ogDescription = $politician->bio
            ? \Illuminate\Support\Str::limit($politician->bio, 160)
            : "Watch {$politician->full_name}'s political messages and earn money on U9itus.";
        $ogImage       = $page->hero_banner_url ?? $politician->profile_photo_url ?? null;
        $ogUrl         = route('politician.public.show', $slug);

        return view('standalone.public.profile', compact(
            'politician',
            'page',
            'campaigns',
            'initiatives',
            'ogTitle',
            'ogDescription',
            'ogImage',
            'ogUrl'
        ));
    }
}
