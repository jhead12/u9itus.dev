<?php

use App\Jobs\MatchPoliticianToElectionData;
use App\Models\CandidateMatchReview;
use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use App\Models\User;
use App\Services\PoliticianElectionMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function adminForCandidateMatchTests(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $user = User::factory()->create([
        'user_type' => 'admin',
    ]);

    $user->assignRole('admin');
    skipOnboarding($user, 'admin');

    return $user;
}

test('creating politician dispatches candidate matching job', function () {
    Queue::fake();

    Politician::factory()->create([
        'full_name' => 'Alex Johnson',
        'state' => 'TX',
        'governance_level' => 'City',
        'political_office' => 'Mayor',
        'city' => 'Austin',
    ]);

    Queue::assertPushed(MatchPoliticianToElectionData::class);
});

test('matcher creates pending review for medium confidence match', function () {
    $candidateName = 'Alex Johnson';

    $politician = Politician::factory()->create([
        'full_name' => $candidateName,
        'state' => 'TX',
        'governance_level' => 'City',
        'political_office' => 'Mayor',
        'city' => null,
        'district' => null,
        'party_affiliation' => 'Independent',
    ]);

    ElectionCandidateRecord::factory()->create([
        'source' => 'county_feed',
        'external_candidate_id' => 'cand-1001',
        'full_name' => $candidateName,
        'state' => 'TX',
        'governance_level' => 'City',
        'political_office' => 'Mayor',
        'city' => null,
        'district' => null,
        'party_affiliation' => 'Democratic',
    ]);

    app(PoliticianElectionMatcher::class)->match($politician);

    $review = CandidateMatchReview::where('politician_id', $politician->id)->first();

    expect($review)->not->toBeNull();
    expect($review->status)->toBe(CandidateMatchReview::STATUS_PENDING);
    expect((float) $review->match_score)->toBeGreaterThanOrEqual(PoliticianElectionMatcher::REVIEW_THRESHOLD);
});

test('admin can approve pending candidate review and create identity link', function () {
    $admin = adminForCandidateMatchTests();

    $review = CandidateMatchReview::factory()->create();

    $response = $this->actingAs($admin)
        ->post(route('admin.candidate-matches.approve', $review));

    $response->assertRedirect();

    $this->assertDatabaseHas('candidate_match_reviews', [
        'id' => $review->id,
        'status' => CandidateMatchReview::STATUS_APPROVED,
        'reviewed_by_user_id' => $admin->id,
    ]);

    $this->assertDatabaseHas('candidate_identity_links', [
        'politician_id' => $review->politician_id,
        'election_candidate_record_id' => $review->election_candidate_record_id,
        'link_source' => 'admin',
        'linked_by_user_id' => $admin->id,
    ]);
});

test('admin can run election candidate import from admin feature', function () {
    $admin = adminForCandidateMatchTests();

    $relativePath = 'imports/test-local-elections.json';
    $absolutePath = storage_path('app/' . $relativePath);
    $directory = dirname($absolutePath);

    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    file_put_contents($absolutePath, json_encode([
        [
            'external_candidate_id' => 'loc-100',
            'full_name' => 'Jamie Rivera',
            'political_office' => 'City Council Member',
            'governance_level' => 'City',
            'state' => 'TX',
            'city' => 'Austin',
            'district' => 'District 4',
            'party_affiliation' => 'Independent',
            'election_date' => now()->addMonths(3)->toDateString(),
        ],
    ], JSON_THROW_ON_ERROR));

    $response = $this->actingAs($admin)
        ->post(route('admin.candidate-matches.import'), [
            'source' => 'local_feed',
            'file' => $relativePath,
            'dry_run' => false,
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('election_candidate_records', [
        'source' => 'local_feed',
        'external_candidate_id' => 'loc-100',
        'full_name' => 'Jamie Rivera',
        'state' => 'TX',
    ]);
});

test('admin can upload json file for election import from admin feature', function () {
    $admin = adminForCandidateMatchTests();

    $content = json_encode([
        [
            'external_candidate_id' => 'loc-200',
            'full_name' => 'Taylor Brooks',
            'political_office' => 'School Board Trustee',
            'governance_level' => 'School Board',
            'state' => 'CA',
            'city' => 'Oakland',
            'district' => 'District 2',
            'party_affiliation' => 'Independent',
            'election_date' => now()->addMonths(4)->toDateString(),
        ],
    ], JSON_THROW_ON_ERROR);

    $upload = UploadedFile::fake()->createWithContent('local-elections.json', $content);

    $response = $this->actingAs($admin)
        ->post(route('admin.candidate-matches.import'), [
            'source' => 'local_feed',
            'file_upload' => $upload,
            'dry_run' => false,
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('election_candidate_records', [
        'source' => 'local_feed',
        'external_candidate_id' => 'loc-200',
        'full_name' => 'Taylor Brooks',
        'state' => 'CA',
    ]);
});
