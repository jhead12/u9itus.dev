<?php

namespace App\Support\Workspace;

use App\Models\BallotMeasure;
use App\Models\Citizen;
use App\Models\DistrictNewsArticle;
use App\Models\StateElectionDate;

/**
 * Builds render data for a single citizen workspace widget, scoped to one
 * citizen's own data/interests/state — see WorkspaceDataProvider for the
 * admin equivalent this mirrors.
 */
final class CitizenWorkspaceDataProvider
{
    /** @return array<string, mixed> */
    public function forKey(string $key, Citizen $citizen): array
    {
        return match ($key) {
            'citizen_activity_snapshot' => $this->activitySnapshot($citizen),
            'citizen_topics_badges' => $this->topicsBadges($citizen),
            'citizen_local_news' => $this->localNews($citizen),
            'citizen_voting_updates' => $this->votingUpdates($citizen),
            'citizen_campaigns_overview' => $this->campaignsOverview($citizen),
            default => [],
        };
    }

    private function activitySnapshot(Citizen $citizen): array
    {
        $counts = [
            'campaigns' => $citizen->campaigns()->count(),
            'posts' => $citizen->posts()->count(),
            'events' => $citizen->events()->count(),
        ];

        return [
            'counts' => $counts,
            'max' => max([1, ...array_values($counts)]),
            'credit_balance' => (float) $citizen->credit_balance,
        ];
    }

    private function topicsBadges(Citizen $citizen): array
    {
        $badges = $citizen->badges()->with('topic')->get()->filter(fn ($badge) => $badge->topic !== null);

        return [
            'badges' => $badges,
            'available_topics' => \App\Models\PoliticianTopic::active()
                ->where('voter_selectable', true)
                ->whereNotIn('id', $badges->pluck('topic_id'))
                ->get(),
        ];
    }

    /**
     * News matched to the citizen's self-declared interests. Falls back to
     * state-wide verified news when they haven't picked any interests yet,
     * so the widget is never empty on first visit.
     */
    private function localNews(Citizen $citizen): array
    {
        $topicSlugs = $citizen->badges()->with('topic')->get()
            ->pluck('topic.slug')->filter()->values()->all();

        $query = DistrictNewsArticle::query()->verified();

        if ($citizen->state) {
            $query->forState($citizen->state);
        }

        $personalized = ! empty($topicSlugs);
        if ($personalized) {
            $query->forTopics($topicSlugs);
        }

        $articles = $query->recent(8)->get(['headline', 'source_name', 'source_url', 'state', 'matched_locality', 'published_at', 'topic_key']);

        // A citizen with interests but no matching articles yet still sees
        // something rather than an empty widget.
        if ($personalized && $articles->isEmpty()) {
            $articles = DistrictNewsArticle::query()->verified()
                ->when($citizen->state, fn ($q) => $q->forState($citizen->state))
                ->recent(8)
                ->get(['headline', 'source_name', 'source_url', 'state', 'matched_locality', 'published_at', 'topic_key']);
            $personalized = false;
        }

        return ['articles' => $articles, 'personalized' => $personalized];
    }

    private function votingUpdates(Citizen $citizen): array
    {
        return [
            'upcoming_elections' => $citizen->state ? StateElectionDate::upcomingForState($citizen->state) : [],
            'recent_ballot_measures' => BallotMeasure::query()
                ->when($citizen->state, fn ($q) => $q->where('state', strtoupper($citizen->state)))
                ->latest()
                ->limit(6)
                ->get(['state', 'title', 'level', 'county', 'locality', 'election_date']),
        ];
    }

    private function campaignsOverview(Citizen $citizen): array
    {
        return [
            'campaigns' => $citizen->campaigns()->latest()->limit(6)->get([
                'id', 'uuid', 'title', 'status', 'views_completed', 'total_views_requested', 'amount_spent', 'total_budget',
            ]),
        ];
    }
}
