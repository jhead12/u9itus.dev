<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\Politician;
use App\Services\BadgeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Manages profile badge self-declaration for voters and politicians.
 * Routes are split by role middleware — see standalone.php.
 */
class BadgeController extends Controller
{
    public function __construct(private BadgeService $badgeService) {}

    // ── Voter badge routes ────────────────────────────────────────────────

    /**
     * POST /voter/badges/{topic}
     * Voter self-declares a topic badge on their profile.
     */
    public function voterStore(Request $request, int $topicId): RedirectResponse
    {
        try {
            $topic = $this->badgeService->resolveSelectableTopic($topicId);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['badge' => $e->getMessage()])->withInput();
        }

        $voter = $request->user()->voter;

        if (! $voter) {
            abort(403, 'No voter profile found.');
        }

        $voter->addBadge($topic->id, 'self_declared');

        return back()->with('success', "🏅 \"{$topic->name}\" badge added to your profile.");
    }

    /**
     * DELETE /voter/badges/{topic}
     * Voter removes a self-declared badge.
     */
    public function voterDestroy(Request $request, int $topicId): RedirectResponse
    {
        $voter = $request->user()->voter;

        if (! $voter) {
            abort(403, 'No voter profile found.');
        }

        $voter->removeSelfDeclaredBadge($topicId);

        return back()->with('success', 'Badge removed from your profile.');
    }

    /**
     * PUT /voter/badges/{topic}/visibility
     * Voter toggles the public/private visibility of a self-declared badge.
     * Earned/inferred badges are always public and cannot be changed here.
     */
    public function voterUpdateVisibility(Request $request, int $topicId): RedirectResponse
    {
        $validated = $request->validate([
            'is_public' => ['required', 'boolean'],
        ]);

        $voter = $request->user()->voter;

        if (! $voter) {
            abort(403, 'No voter profile found.');
        }

        $updated = $voter->setBadgeVisibility($topicId, (bool) $validated['is_public']);

        if (! $updated) {
            return back()->withErrors(['badge' => 'Only self-declared badges can have their visibility changed.']);
        }

        return back()->with('success', $validated['is_public']
            ? '🔓 Badge is now public on your profile.'
            : '🔒 Badge is now private.');
    }

    // ── Politician badge routes ───────────────────────────────────────────

    /**
     * POST /politician/badges/{topic}
     * Politician declares support for a topic.
     */
    public function politicianStore(Request $request, int $topicId): RedirectResponse
    {
        try {
            $topic = $this->badgeService->resolveSelectableTopic($topicId);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['badge' => $e->getMessage()])->withInput();
        }

        $politician = $request->user()->politician;

        if (! $politician) {
            abort(403, 'No politician profile found.');
        }

        $politician->addBadge($topic->id, 'self_declared');

        return back()->with('success', "🏅 \"{$topic->name}\" badge added to your profile.");
    }

    /**
     * DELETE /politician/badges/{topic}
     * Politician removes a topic support badge.
     */
    public function politicianDestroy(Request $request, int $topicId): RedirectResponse
    {
        $politician = $request->user()->politician;

        if (! $politician) {
            abort(403, 'No politician profile found.');
        }

        $politician->removeSelfDeclaredBadge($topicId);

        return back()->with('success', 'Badge removed from your profile.');
    }
}
