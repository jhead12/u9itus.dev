<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\Politician;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Manages a voter's favorited politicians list.
 */
class FavoriteController extends Controller
{
    /**
     * GET /voter/favorites
     * Show the voter's full list of favorited politicians.
     */
    public function index(Request $request)
    {
        $voter = $request->user()->voter;

        if (! $voter) {
            abort(403, 'No voter profile found.');
        }

        $favorites = $voter->favoritePoliticians()
            ->with('badges.topic')
            ->orderByPivot('favorited_at', 'desc')
            ->paginate(20);

        $savedArticles = $voter->savedArticles()
            ->with('politician:id,full_name,slug')
            ->orderByPivot('saved_at', 'desc')
            ->paginate(20, ['*'], 'articles_page');

        return view('standalone.voter.favorites.index', compact('favorites', 'savedArticles'));
    }

    /**
     * GET /voter/favorites/panel
     * HTML fragment for the slide-in favorites side panel (AJAX).
     */
    public function panel(Request $request)
    {
        $voter = $request->user()->voter;

        if (! $voter) {
            abort(403, 'No voter profile found.');
        }

        $favorites = $voter->favoritePoliticians()
            ->orderByPivot('favorited_at', 'desc')
            ->limit(15)
            ->get();

        $savedArticles = $voter->savedArticles()
            ->with('politician:id,full_name,slug')
            ->orderByPivot('saved_at', 'desc')
            ->limit(5)
            ->get();

        return view('standalone.voter.favorites.panel', compact('favorites', 'savedArticles'));
    }

    /**
     * POST /voter/favorites/{politician}
     * Add a politician to the voter's favorites.
     */
    public function store(Request $request, int $politicianId): RedirectResponse
    {
        $voter = $request->user()->voter;

        if (! $voter) {
            abort(403, 'No voter profile found.');
        }

        $politician = Politician::findOrFail($politicianId);

        // sync-style: attach only if not already present
        if (! $voter->favoritePoliticians()->where('politician_id', $politician->id)->exists()) {
            $voter->favoritePoliticians()->attach($politician->id, ['favorited_at' => now()]);
        }

        return back()->with('success', "You are now following {$politician->full_name}.");
    }

    /**
     * DELETE /voter/favorites/{politician}
     * Remove a politician from the voter's favorites.
     */
    public function destroy(Request $request, int $politicianId)
    {
        $voter = $request->user()->voter;

        if (! $voter) {
            abort(403, 'No voter profile found.');
        }

        $voter->favoritePoliticians()->detach($politicianId);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Removed from your followed politicians.');
    }

    /**
     * POST /voter/articles/{articleId}/save
     * Save (like) a news article.
     */
    public function saveArticle(Request $request, int $articleId)
    {
        $voter = $request->user()->voter;

        if (! $voter) {
            abort(403, 'No voter profile found.');
        }

        $article = \App\Models\CandidateNewsArticle::findOrFail($articleId);

        if (! $voter->savedArticles()->where('candidate_news_article_id', $article->id)->exists()) {
            $voter->savedArticles()->attach($article->id, ['saved_at' => now()]);
        }

        return response()->json(['saved' => true]);
    }

    /**
     * DELETE /voter/articles/{articleId}/save
     * Remove a saved news article.
     */
    public function unsaveArticle(Request $request, int $articleId)
    {
        $voter = $request->user()->voter;

        if (! $voter) {
            abort(403, 'No voter profile found.');
        }

        $voter->savedArticles()->detach($articleId);

        return response()->json(['saved' => false]);
    }
}
