<?php

use App\Models\User;
use App\Models\Politician;
use App\Models\PoliticalCampaign;
use App\Models\Voter;
use App\Models\VoterWatchReport;
use App\Services\BallotpediaService;
use App\Services\FECService;
use App\Services\OpenSecretsService;
use App\Services\VoteSmartService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guest can browse politician directory in view only mode', function () {
    Politician::factory()->create([
        'full_name' => 'Avery Stone',
        'slug' => 'avery-stone',
        'user_id' => null,
        'page_published' => true,
        'is_active' => true,
    ]);

    $claimedUser = User::factory()->create([
        'user_type' => 'politician',
    ]);

    Politician::factory()->create([
        'user_id' => $claimedUser->id,
        'full_name' => 'Claimed Official',
        'slug' => 'claimed-official',
        'page_published' => true,
        'is_active' => true,
    ]);

    Politician::factory()->create([
        'full_name' => 'Hidden Candidate',
        'slug' => 'hidden-candidate',
        'page_published' => false,
        'is_active' => true,
    ]);

    $response = $this->get(route('politicians.directory'));

    $response->assertOk();
    $response->assertSee('Avery Stone');
    $response->assertSee('Claimed Official');
    $response->assertDontSee('Hidden Candidate');
    $response->assertSee('Unclaimed Profile');
    $response->assertSee('Public directory is view-only for earnings.', false);
    $response->assertSee('watch active public campaign videos', false);
    $response->assertSee('commissions are only available after creating a voter account', false);
    $response->assertSee('Create Free Account');
    $response->assertDontSee('Earn Money Watching');
});

test('guest public politician profile stays in preview mode without earning copy', function () {
    $politician = Politician::factory()->create([
        'full_name' => 'Jordan Vale',
        'slug' => 'abcde-jordan-vale',
        'page_published' => true,
        'is_active' => true,
    ]);

    PoliticalCampaign::factory()->active()->create([
        'politician_id' => $politician->id,
        'title' => 'Jordan Vale for Reform',
        'message_summary' => 'A public message for voters.',
        'media_url' => 'https://youtu.be/dQw4w9WgXcQ',
    ]);

    PoliticalCampaign::factory()->create([
        'politician_id' => $politician->id,
        'title' => 'Jordan Vale Town Hall Recap',
        'message_summary' => 'A previous campaign update for public review.',
        'media_url' => 'https://youtu.be/dQw4w9WgXcQ',
        'status' => 'completed',
        'approval_status' => 'approved',
        'completed_at' => now()->subDays(5),
    ]);

    $response = $this->get(route('politician.public.show', ['slug' => $politician->slug]));

    $response->assertOk();
    $response->assertSee('Guest preview mode');
    $response->assertSee('Public Preview');
    $response->assertSee('Unclaimed Profile');
    $response->assertSee('currently unclaimed and generated from public records', false);
    $response->assertSee('Campaign Videos & Updates');
    $response->assertSee('Running Campaigns');
    $response->assertSee('Past Campaigns');
    $response->assertSee('Research &amp; Records', false);
    $response->assertSee('C-SPAN Video Search');
    $response->assertSee('Guests can browse current and past public campaign videos here to learn how this candidate is communicating over time.', false);
    $response->assertSee('Create free account for full access');
    $response->assertSee('Jordan Vale Town Hall Recap');
    $response->assertDontSee('Earn $0.25');
    $response->assertDontSee('Start Earning');
    $response->assertDontSee('Sign up to earn commissions from views');
});

test('guest can filter directory by district and topic', function () {
    $housingCandidate = Politician::factory()->create([
        'full_name' => 'Taylor Housing',
        'slug' => 'taylor-housing',
        'district' => 'CA-12',
        'page_published' => true,
        'is_active' => true,
    ]);

    PoliticalCampaign::factory()->active()->create([
        'politician_id' => $housingCandidate->id,
        'title' => 'Affordable Housing Now',
        'message_summary' => 'Housing affordability and zoning reform.',
    ]);

    $otherCandidate = Politician::factory()->create([
        'full_name' => 'Robin Transport',
        'slug' => 'robin-transport',
        'district' => 'CA-30',
        'page_published' => true,
        'is_active' => true,
    ]);

    PoliticalCampaign::factory()->active()->create([
        'politician_id' => $otherCandidate->id,
        'title' => 'Transit Expansion',
        'message_summary' => 'Public transportation and mobility.',
    ]);

    $districtResponse = $this->get(route('politicians.directory', ['district' => 'CA-12']));
    $districtResponse->assertOk();
    $districtResponse->assertSee('Taylor Housing');
    $districtResponse->assertDontSee('Robin Transport');

    $topicResponse = $this->get(route('politicians.directory', ['topic' => 'housing']));
    $topicResponse->assertOk();
    $topicResponse->assertSee('Taylor Housing');
    $topicResponse->assertDontSee('Robin Transport');
});

test('claimed politician profile does not show unclaimed badge', function () {
    $user = User::factory()->create([
        'user_type' => 'politician',
    ]);

    $politician = Politician::factory()->create([
        'user_id' => $user->id,
        'full_name' => 'Casey Jordan',
        'slug' => 'casey-jordan',
        'page_published' => true,
        'is_active' => true,
    ]);

    $response = $this->get(route('politician.public.show', ['slug' => $politician->slug]));

    $response->assertOk();
    $response->assertDontSee('Unclaimed Profile');
    $response->assertDontSee('currently unclaimed and generated from public records', false);
});

test('directory unclaimed-only filter returns only unclaimed profiles', function () {
    Politician::factory()->create([
        'user_id' => null,
        'full_name' => 'Unclaimed Candidate',
        'slug' => 'unclaimed-candidate',
        'page_published' => true,
        'is_active' => true,
    ]);

    $claimedUser = User::factory()->create([
        'user_type' => 'politician',
    ]);

    Politician::factory()->create([
        'user_id' => $claimedUser->id,
        'full_name' => 'Claimed Candidate',
        'slug' => 'claimed-candidate',
        'page_published' => true,
        'is_active' => true,
    ]);

    $response = $this->get(route('politicians.directory', ['unclaimed' => 1]));

    $response->assertOk();
    $response->assertSee('Unclaimed Candidate');
    $response->assertDontSee('Claimed Candidate');
});

test('authenticated politician can still preview their unpublished page', function () {
    $user = User::factory()->create([
        'user_type' => 'politician',
    ]);

    $politician = Politician::factory()->create([
        'user_id' => $user->id,
        'full_name' => 'Morgan Reed',
        'slug' => 'vwxyz-morgan-reed',
        'page_published' => false,
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)
        ->get(route('politician.public.show', ['slug' => $politician->slug]));

    $response->assertOk();
    $response->assertSee('Morgan Reed');
    $response->assertSee('Open Dashboard');
});

test('public profile shows answered voter questions', function () {
    $politician = Politician::factory()->create([
        'full_name' => 'Jamie Rivera',
        'slug' => 'jamie-rivera',
        'page_published' => true,
        'is_active' => true,
    ]);

    $campaign = PoliticalCampaign::factory()->active()->create([
        'politician_id' => $politician->id,
        'title' => 'Jamie Rivera Town Hall',
        'approval_status' => 'approved',
    ]);

    $voter = Voter::factory()->create();

    VoterWatchReport::create([
        'voter_id' => $voter->id,
        'campaign_id' => $campaign->id,
        'type' => 'message',
        'body' => 'How will you improve local transit access?',
        'status' => 'resolved',
        'admin_notes' => 'We will expand evening routes and reduce transfer times.',
        'resolved_at' => now()->subDay(),
    ]);

    VoterWatchReport::create([
        'voter_id' => $voter->id,
        'campaign_id' => $campaign->id,
        'type' => 'message',
        'body' => 'Will you support weekend service?',
        'status' => 'open',
        'admin_notes' => null,
    ]);

    $response = $this->get(route('politician.public.show', ['slug' => $politician->slug]));

    $response->assertOk();
    $response->assertSee('Answered Questions');
    $response->assertSee('How will you improve local transit access?');
    $response->assertSee('We will expand evening routes and reduce transfer times.');
    $response->assertDontSee('Will you support weekend service?');
});

test('verified public profile shows dig deeper source panels', function () {
    $politician = Politician::factory()->create([
        'full_name' => 'Morgan Hale',
        'slug' => 'morgan-hale',
        'page_published' => true,
        'is_active' => true,
        'verification_status' => 'verified',
        'show_ballotpedia_data' => true,
        'show_opensecrets_data' => true,
        'show_votesmart_data' => true,
        'show_fec_data' => true,
        'political_office' => 'US Senator',
    ]);

    app()->instance(BallotpediaService::class, new class {
        public function getDisplayData($politician): array
        {
            unset($politician);

            return [
                'source' => 'Ballotpedia',
                'source_url' => 'https://ballotpedia.org/example',
                'sections' => [
                    ['title' => 'Voting Record', 'items' => ['Record 1']],
                ],
            ];
        }
    });

    app()->instance(OpenSecretsService::class, new class {
        public function getDisplayData($politician): array
        {
            unset($politician);

            return [
                'source' => 'OpenSecrets',
                'source_url' => 'https://www.opensecrets.org/example',
                'summary' => ['receipts' => '$1,000,000'],
                'sections' => [
                    ['title' => 'Top Contributors', 'items' => [['name' => 'Group A']]],
                ],
            ];
        }
    });

    app()->instance(VoteSmartService::class, new class {
        public function getDisplayData($politician): array
        {
            unset($politician);

            return [
                'source' => 'Vote Smart',
                'source_url' => 'https://justfacts.votesmart.org/candidate/example',
                'sections' => [
                    ['title' => 'Issue Positions', 'items' => [['issue' => 'Housing', 'position' => 'Support']]],
                ],
            ];
        }
    });

    app()->instance(FECService::class, new class {
        public function getDisplayData($politician): array
        {
            unset($politician);

            return [
                'source' => 'Federal Election Commission',
                'source_url' => 'https://www.fec.gov/data/candidate/H0XX00000/',
                'summary' => ['receipts' => '$250,000'],
                'sections' => [
                    ['title' => 'Recent Filings', 'items' => [['form_type' => 'F3']]],
                ],
            ];
        }

        public function isFederalCandidate($politician): bool
        {
            unset($politician);

            return true;
        }
    });

    $response = $this->get(route('politician.public.show', ['slug' => $politician->slug]));

    $response->assertOk();
    $response->assertSee('Dig Deeper');
    $response->assertSee('Sources available');
    $response->assertSee('4 / 4');
    $response->assertSee('Ballotpedia');
    $response->assertSee('OpenSecrets');
    $response->assertSee('Vote Smart');
    $response->assertSee('Federal Election Commission');
});

    test('logged in voter sees referral share modal on unverified profiles only', function () {
        $user = User::factory()->create([
            'user_type' => 'voter',
        ]);

        $voter = Voter::factory()->create([
            'user_id' => $user->id,
            'referral_code' => 'VOTERX99',
        ]);

        expect($voter->referral_code)->toBe('VOTERX99');

        $unverifiedPolitician = Politician::factory()->create([
            'full_name' => 'Rowan North',
            'slug' => 'rowan-north',
            'page_published' => true,
            'is_active' => true,
            'verification_status' => 'pending',
        ]);

        $response = $this->actingAs($user)
            ->get(route('politician.public.show', ['slug' => $unverifiedPolitician->slug]));

        $response->assertOk();
        $response->assertSee('Share this profile with your referral code');
        $response->assertSee("Take a look at Rowan North's U9itus profile. If you join or claim the page, please use my referral link.");
        $response->assertSee(route('politician.public.show', [
            'slug' => $unverifiedPolitician->slug,
            'ref' => 'VOTERX99',
        ], false), false);
        $response->assertSee(route('register.politician', ['ref' => 'VOTERX99'], false), false);
        $response->assertSee('mailto:?subject=Take%20a%20look%20at%20Rowan%20North%20on%20U9itus', false);
        $response->assertSee('https://twitter.com/intent/tweet?text=Take%20a%20look%20at%20Rowan%20North%27s%20U9itus%20profile.', false);
        $response->assertSee('https://www.facebook.com/sharer/sharer.php?u=', false);
        $response->assertSee('https://api.whatsapp.com/send?text=Take%20a%20look%20at%20Rowan%20North%27s%20U9itus%20profile.', false);
        $response->assertSee('https://t.me/share/url?url=', false);

        $verifiedPolitician = Politician::factory()->create([
            'full_name' => 'Dana South',
            'slug' => 'dana-south',
            'page_published' => true,
            'is_active' => true,
            'verification_status' => 'verified',
        ]);

        $verifiedResponse = $this->actingAs($user)
            ->get(route('politician.public.show', ['slug' => $verifiedPolitician->slug]));

        $verifiedResponse->assertOk();
        $verifiedResponse->assertDontSee('Share this profile with your referral code');
    });

test('dig deeper shows federal-only message when fec is enabled for non-federal office', function () {
    $politician = Politician::factory()->create([
        'full_name' => 'Dana Price',
        'slug' => 'dana-price',
        'page_published' => true,
        'is_active' => true,
        'verification_status' => 'verified',
        'show_fec_data' => true,
        'political_office' => 'Mayor',
    ]);

    app()->instance(FECService::class, new class {
        public function getDisplayData($politician): ?array
        {
            unset($politician);

            return null;
        }

        public function isFederalCandidate($politician): bool
        {
            unset($politician);

            return false;
        }
    });

    $response = $this->get(route('politician.public.show', ['slug' => $politician->slug]));

    $response->assertOk();
    $response->assertSee('Dig Deeper');
    $response->assertSee('Federal Election Commission');
    $response->assertSee('FEC reporting applies to federal offices only.');
    $response->assertSee('0 / 1');
});
