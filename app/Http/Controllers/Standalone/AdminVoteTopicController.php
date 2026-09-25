<?php

namespace App\Http\Controllers\Standalone;

use App\Models\CongressVote;
use App\Models\CongressVoteTopic;
use App\Models\Politician;
use App\Models\PoliticianTopic;
use App\Services\BadgeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

/**
 * Editor tagging of roll-call votes to a topic, such as war powers resolutions to the
 * Iran conflict. The yes/no alone does not say which side a member took, so the editor
 * states what a yea vote means and how the badge describes each vote. Saving re-syncs
 * the vote badges of every member who voted.
 */
class AdminVoteTopicController
{
    public function index(Request $request)
    {
        $show = $request->query('show') === 'all' ? 'all' : 'tagged';
        $search = trim((string) $request->query('q', ''));

        $votes = CongressVote::query()
            ->with(['topicTags.topic', 'topicTags.taggedBy:id,name'])
            ->when($show === 'tagged' && $search === '', fn ($query) => $query->has('topicTags'))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('title', 'like', "%{$search}%")
                ->orWhere('question', 'like', "%{$search}%")
                ->orWhere('bill_number', 'like', "%{$search}%")))
            ->orderByDesc('voted_at')
            ->paginate(25)
            ->withQueryString();

        $topics = PoliticianTopic::active()->get()->filter->hasStanceLabels()->values();

        return view('standalone.admin.vote-topics', compact('votes', 'topics', 'show', 'search'));
    }

    public function store(Request $request, CongressVote $vote, BadgeService $badges)
    {
        $data = $request->validate([
            'topic_id' => ['required', Rule::exists('politician_topics', 'id')->where('is_active', true)],
            'yea_stance' => ['required', Rule::in(['support', 'oppose'])],
            'yea_label' => ['required', 'string', 'max:255'],
            'nay_label' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        CongressVoteTopic::updateOrCreate(
            ['congress_vote_id' => $vote->id, 'topic_id' => $data['topic_id']],
            [
                'yea_stance' => $data['yea_stance'],
                'yea_label' => trim($data['yea_label']),
                'nay_label' => trim($data['nay_label']),
                'note' => $data['note'] ?? null,
                'tagged_by_user_id' => $request->user()->id,
            ],
        );
        $count = $this->resyncVoters($vote, $badges);

        return back()->with('success', "Tagged. Badges updated for {$count} ".str('member')->plural($count).'.');
    }

    public function destroy(CongressVoteTopic $tag, BadgeService $badges)
    {
        $vote = $tag->congressVote;
        $tag->delete();
        $count = $this->resyncVoters($vote, $badges);

        return back()->with('success', "Tag removed. Badges updated for {$count} ".str('member')->plural($count).'.');
    }

    private function resyncVoters(CongressVote $vote, BadgeService $badges): int
    {
        $politicians = Politician::query()
            ->whereIn('bioguide_id', $vote->memberVotes()->select('bioguide_id'))
            ->get();

        foreach ($politicians as $politician) {
            $badges->syncVoteBadges($politician);
            // The public profile page is cached; clear it so the badge shows immediately.
            Cache::forget("profile.page.seo-v2.{$politician->id}");
        }

        return $politicians->count();
    }
}
