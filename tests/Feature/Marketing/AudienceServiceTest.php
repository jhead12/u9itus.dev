<?php

use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Models\CitizenCampaign;
use App\Models\PoliticalCampaign;
use App\Models\Voter;
use App\Services\Marketing\AudienceService;
use App\Services\Marketing\ZipCentroidService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeVoter(array $overrides = []): Voter
{
    return Voter::factory()->create(array_merge([
        'is_active'         => true,
        'flagged_for_fraud' => false,
    ], $overrides));
}

// ── Political campaign targeting ──────────────────────────────────────────

test('political campaign with no targeting reaches every active voter', function () {
    $campaign = PoliticalCampaign::factory()->create([
        'target_states'            => null,
        'target_districts'          => null,
        'target_governance_levels'  => null,
    ]);
    makeVoter(['state' => 'CA']);
    makeVoter(['state' => 'TX']);

    $count = (new AudienceService(app(ZipCentroidService::class)))->forCampaign($campaign)->count();

    expect($count)->toBe(2);
});

test('political campaign filters by target_states', function () {
    $campaign = PoliticalCampaign::factory()->create([
        'target_states'           => ['CA'],
        'target_districts'         => null,
        'target_governance_levels' => null,
    ]);
    makeVoter(['state' => 'CA']);
    makeVoter(['state' => 'TX']);

    $states = (new AudienceService(app(ZipCentroidService::class)))
        ->forCampaign($campaign)->pluck('state')->all();

    expect($states)->toBe(['CA']);
});

test('political campaign filters by target_districts with normalized code match', function () {
    $campaign = PoliticalCampaign::factory()->create([
        'target_states'           => null,
        'target_districts'        => ['CA-12', 'NY-07'],
        'target_governance_levels'  => null,
    ]);
    makeVoter(['state' => 'CA', 'congressional_district' => 'CA-12']);
    makeVoter(['state' => 'NY', 'congressional_district' => 'ny-7']); // different format/case
    makeVoter(['state' => 'TX', 'congressional_district' => 'TX-01']);
    makeVoter(['state' => 'FL', 'congressional_district' => null]);   // excluded: unconfirmed

    $districts = (new AudienceService(app(ZipCentroidService::class)))
        ->forCampaign($campaign)->pluck('congressional_district')->sort()->values()->all();

    expect($districts)->toBe(['CA-12', 'ny-7']);
});

test('political campaign filters by target_governance_levels intersection', function () {
    $campaign = PoliticalCampaign::factory()->create([
        'target_states'           => null,
        'target_districts'         => null,
        'target_governance_levels' => ['federal', 'state'],
    ]);
    makeVoter(['preferred_governance_levels' => ['federal']]);
    makeVoter(['preferred_governance_levels' => ['state', 'local']]);
    makeVoter(['preferred_governance_levels' => ['local']]);
    makeVoter(['preferred_governance_levels' => null]);

    $count = (new AudienceService(app(ZipCentroidService::class)))
        ->forCampaign($campaign)->count();

    expect($count)->toBe(2);
});

test('political campaign excludes flagged and inactive voters', function () {
    $campaign = PoliticalCampaign::factory()->create([
        'target_states' => null,
    ]);
    makeVoter(['is_active' => false]);
    makeVoter(['flagged_for_fraud' => true]);
    makeVoter();

    $count = (new AudienceService(app(ZipCentroidService::class)))
        ->forCampaign($campaign)->count();

    expect($count)->toBe(1);
});

// ── Citizen campaign targeting ─────────────────────────────────────────────

test('citizen campaign with no target_zip reaches all active voters', function () {
    $campaign = CitizenCampaign::factory()->create([
        'target_zip'        => null,
        'target_zip_radius' => null,
    ]);
    makeVoter(['zip_code' => '90210']);
    makeVoter(['zip_code' => '10001']);

    $count = (new AudienceService(app(ZipCentroidService::class)))->forCampaign($campaign)->count();

    expect($count)->toBe(2);
});

test('citizen campaign exact-zip match includes null-zip voters for reach', function () {
    $campaign = CitizenCampaign::factory()->create([
        'target_zip'        => '90210',
        'target_zip_radius' => 0,
    ]);
    makeVoter(['zip_code' => '90210']);
    makeVoter(['zip_code' => '10001']);
    makeVoter(['zip_code' => null]);

    $zips = (new AudienceService(app(ZipCentroidService::class)))
        ->forCampaign($campaign)->pluck('zip_code')->all();

    expect($zips)->toBe(['90210', null]);
});

test('citizen campaign radius includes voters within radius and excludes others', function () {
    $campaign = CitizenCampaign::factory()->create([
        'target_zip'        => '90210',
        'target_zip_radius' => 10,
    ]);
    makeVoter(['zip_code' => '90210']);
    makeVoter(['zip_code' => '90211']);
    makeVoter(['zip_code' => '99999']);
    makeVoter(['zip_code' => null]);

    // Deterministic centroids: 90210 center, 90211 ~0.2mi away, 99999 far.
    $fake = new class extends ZipCentroidService {
        public function centroid(string $zip): ?array
        {
            return match ($zip) {
                '90210' => ['lat' => 34.0901, 'lng' => -118.4066],
                '90211' => ['lat' => 34.0910, 'lng' => -118.4040],
                '99999' => ['lat' => 40.7128, 'lng' => -74.0060],
                default  => null,
            };
        }
    };

    $zips = (new AudienceService($fake))
        ->forCampaign($campaign)->pluck('zip_code')->sort()->values()->all();

    expect($zips)->toBe(['90210', '90211']);
});

test('citizen campaign radius falls back to exact-zip when centroid unavailable', function () {
    $campaign = CitizenCampaign::factory()->create([
        'target_zip'        => '90210',
        'target_zip_radius' => 10,
    ]);
    makeVoter(['zip_code' => '90210']);
    makeVoter(['zip_code' => '90211']);

    $fake = new class extends ZipCentroidService {
        public function centroid(string $zip): ?array
        {
            return null; // simulate geocoder outage
        }
    };

    $zips = (new AudienceService($fake))
        ->forCampaign($campaign)->pluck('zip_code')->all();

    expect($zips)->toBe(['90210']);
});