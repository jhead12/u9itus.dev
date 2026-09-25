<?php

use App\Models\CongressFloorSpeech;
use App\Models\CongressMemberVote;
use App\Models\CongressVote;
use App\Models\CongressVoteTopic;
use App\Models\Politician;
use App\Models\PoliticianTopic;
use App\Models\PoliticianTopicSignal;
use App\Models\User;
use App\Services\BadgeService;
use App\Services\IssueClassifierService;
use App\Services\PoliticianTopicSignalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    config(['u9itus.issues.signal_threshold' => 1.0, 'services.anthropic.api_key' => null, 'u9itus.issues.llm_fallback' => false]);
    // Seeded by the stances migration.
    $this->iran = PoliticianTopic::where('slug', 'iran-conflict')->sole();
    $this->dataCenters = PoliticianTopic::where('slug', 'data-centers')->sole();
    $this->education = PoliticianTopic::create(['name' => 'Education', 'slug' => 'education', 'is_active' => true, 'sort_order' => 2]);
    $this->badges = app(BadgeService::class);
});

function warPowersVote(array $memberVotes, string $votedAt = '2026-06-27'): CongressVote
{
    static $roll = 100;
    $vote = CongressVote::create([
        'chamber' => 'senate', 'congress' => 119, 'session' => 2, 'roll_number' => $roll++, 'voted_at' => $votedAt,
        'question' => 'On the Joint Resolution', 'title' => 'A joint resolution to direct the removal of United States Armed Forces from hostilities against Iran',
        'bill_number' => 'S.J.Res. 59', 'result' => 'Rejected',
    ]);
    foreach ($memberVotes as $bioguide => $position) {
        CongressMemberVote::create(['congress_vote_id' => $vote->id, 'bioguide_id' => $bioguide, 'vote' => $position]);
    }

    return $vote;
}

function tagWarPowers(CongressVote $vote, PoliticianTopic $topic): CongressVoteTopic
{
    // Yea on a war powers resolution is a vote to limit the action: the "oppose" side.
    return CongressVoteTopic::create([
        'congress_vote_id' => $vote->id, 'topic_id' => $topic->id, 'yea_stance' => 'oppose',
        'yea_label' => 'Voted to limit military action against Iran',
        'nay_label' => 'Voted against limiting military action against Iran',
    ]);
}

function senator(string $bioguide): Politician
{
    return Politician::factory()->create(['bioguide_id' => $bioguide, 'page_published' => true, 'is_active' => true]);
}

test('the migration adds Data Centers as an issue and the Iran conflict as a current event, both with a position', function () {
    expect($this->dataCenters->kind)->toBe('issue')
        ->and($this->dataCenters->hasStanceLabels())->toBeTrue()
        ->and($this->iran->kind)->toBe(PoliticianTopic::KIND_CURRENT_EVENT)
        ->and($this->iran->auto_earned_only)->toBeTrue()
        ->and($this->iran->voter_selectable)->toBeFalse()
        ->and($this->education->hasStanceLabels())->toBeFalse();
});

test('a tagged war powers vote gives each member a badge describing how they voted', function () {
    $yea = senator('K000384');
    $nay = senator('R000001');
    $absent = senator('A000001');
    tagWarPowers(warPowersVote(['K000384' => 'yea', 'R000001' => 'nay', 'A000001' => 'not_voting']), $this->iran);

    expect($this->badges->syncVoteBadges($yea))->toBe(1)
        ->and($this->badges->syncVoteBadges($nay))->toBe(1)
        ->and($this->badges->syncVoteBadges($absent))->toBe(0);

    $yeaBadge = $yea->badges()->sole();
    expect($yeaBadge->badge_type)->toBe('roll_call_vote')
        ->and($yeaBadge->topic_id)->toBe($this->iran->id)
        ->and($yeaBadge->stance)->toBe('oppose')
        ->and($yeaBadge->stance_label)->toBe('Voted to limit military action against Iran')
        ->and($yeaBadge->is_public)->toBeTrue();

    $nayBadge = $nay->badges()->sole();
    expect($nayBadge->stance)->toBe('support')
        ->and($nayBadge->stance_label)->toBe('Voted against limiting military action against Iran')
        ->and($absent->badges()->count())->toBe(0);
});

test('votes on both sides of the same event show a split record, and repeat votes are counted', function () {
    $split = senator('S000001');
    $steady = senator('T000001');
    tagWarPowers(warPowersVote(['S000001' => 'yea', 'T000001' => 'nay'], '2026-06-01'), $this->iran);
    tagWarPowers(warPowersVote(['S000001' => 'nay', 'T000001' => 'nay'], '2026-07-01'), $this->iran);

    $this->badges->syncVoteBadges($split);
    $this->badges->syncVoteBadges($steady);

    expect($split->badges()->sole()->stance)->toBe('mixed')
        ->and($split->badges()->sole()->stance_label)->toBe('Split record across 2 Iran Conflict votes')
        ->and($steady->badges()->sole()->stance_label)->toBe('Voted against limiting military action against Iran (2 votes)');
});

test('a vote record replaces an inferred badge but not a self-declared one, and goes away with its tag', function () {
    $inferred = senator('I000001');
    $declared = senator('D000001');
    $inferred->addBadge($this->iran->id, 'inferred_discourse', ['is_public' => true]);
    $declared->addBadge($this->iran->id, 'self_declared');
    $tag = tagWarPowers(warPowersVote(['I000001' => 'yea', 'D000001' => 'yea']), $this->iran);

    $this->badges->syncVoteBadges($inferred);
    $this->badges->syncVoteBadges($declared);

    expect($inferred->badges()->sole()->badge_type)->toBe('roll_call_vote')
        ->and($declared->badges()->sole()->badge_type)->toBe('self_declared')
        ->and($declared->badges()->sole()->stance_label)->toBeNull();

    $tag->delete();
    $this->badges->syncVoteBadges($inferred);

    expect($inferred->badges()->count())->toBe(0);
});

test('inferred badges show a position only when speeches clearly take one on a topic that defines sides', function () {
    $politician = senator('P000001');
    $signal = fn (PoliticianTopic $topic, int $support, int $oppose) => PoliticianTopicSignal::create([
        'politician_id' => $politician->id, 'topic_id' => $topic->id, 'total_score' => 2.0,
        'support_count' => $support, 'oppose_count' => $oppose,
    ]);

    $this->badges->grantInferredBadges($politician, collect([
        $signal($this->dataCenters, 3, 0),
        $signal($this->education, 4, 0),
    ]));

    $dataBadge = $politician->badges()->where('topic_id', $this->dataCenters->id)->sole();
    expect($dataBadge->stance)->toBe('support')
        ->and($dataBadge->stance_label)->toBe('Supports data center development')
        ->and($politician->badges()->where('topic_id', $this->education->id)->sole()->stance)->toBeNull();

    // Speeches that lean 2-to-1 are not clear enough; the position is cleared on the next run.
    $this->badges->grantInferredBadges($politician, collect([
        PoliticianTopicSignal::where(['politician_id' => $politician->id, 'topic_id' => $this->dataCenters->id])->first()->fill(['support_count' => 2, 'oppose_count' => 1]),
    ]));

    expect($dataBadge->fresh()->stance)->toBeNull()
        ->and($dataBadge->fresh()->stance_label)->toBeNull();
});

test('floor speeches count toward a topic position in the signal rollup', function () {
    $politician = senator('P000002');
    foreach (['support', 'support', 'oppose', null] as $i => $stance) {
        CongressFloorSpeech::create([
            'granule_id' => "DC{$i}", 'bioguide_id' => 'P000002', 'chamber' => 'senate', 'spoken_on' => now()->subDays(5)->toDateString(),
            'title' => 'DATA CENTERS', 'body' => 'Text.', 'source_url' => 'https://example.gov',
            'topic_key' => 'data-centers', 'topic_confidence' => 0.9, 'topic_stance' => $stance,
        ]);
    }

    $signal = app(PoliticianTopicSignalService::class)->compute($politician)->firstWhere('topic_id', $this->dataCenters->id);

    expect($signal->support_count)->toBe(2)
        ->and($signal->oppose_count)->toBe(1)
        ->and($signal->floor_speech_count)->toBe(4);
});

test('speech analysis keeps a topic position only for topics that define sides', function () {
    config(['services.anthropic.api_key' => 'sk-test', 'u9itus.issues.llm_fallback' => true]);
    $reply = fn (int $topicId) => Http::response(['content' => [['text' => json_encode([
        'topic_id' => $topicId, 'confidence' => 0.9, 'stance' => 'oppose', 'position' => 'Opposes the moratorium.',
        'quote' => null, 'topic_stance' => 'support',
    ])]]]);

    Http::fakeSequence('api.anthropic.com/*')->pushResponse($reply($this->dataCenters->id))->pushResponse($reply($this->education->id));
    $classifier = new IssueClassifierService;

    expect($classifier->analyzeStatement('DATA CENTER MORATORIUM', 'We should not stop building data centers.')['topic_stance'])->toBe('support')
        ->and($classifier->analyzeStatement('SCHOOLS', 'Our schools need help.')['topic_stance'])->toBeNull();
});

test('an editor tags a vote and every member who voted gets their badge right away', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'staff:Civic votes', 'guard_name' => 'web']);
    $role->givePermissionTo(Permission::findOrCreate('civic.view', 'web'), Permission::findOrCreate('civic.edit', 'web'));
    $editor = User::factory()->create(['platform' => 'standalone', 'user_type' => 'admin']);
    $editor->assignRole('admin', $role->name);
    skipOnboarding($editor, 'admin');

    $member = senator('K000384');
    $vote = warPowersVote(['K000384' => 'yea']);

    $this->actingAs($editor)->get(route('admin.vote-topics.index', ['q' => 'Iran']))
        ->assertOk()
        ->assertSee('S.J.Res. 59');

    $this->actingAs($editor)->post(route('admin.vote-topics.store', $vote), [
        'topic_id' => $this->iran->id, 'yea_stance' => 'oppose',
        'yea_label' => 'Voted to limit military action against Iran',
        'nay_label' => 'Voted against limiting military action against Iran',
    ])->assertRedirect()->assertSessionHas('success');

    expect($member->badges()->sole()->stance_label)->toBe('Voted to limit military action against Iran');

    $this->actingAs($editor)->delete(route('admin.vote-topics.destroy', CongressVoteTopic::sole()))->assertRedirect();

    expect($member->badges()->count())->toBe(0);
});
