<?php

use App\Enums\ApprovalStatus;
use App\Enums\CitizenAdType;
use App\Models\Citizen;
use App\Models\CitizenCampaign;
use Database\Seeders\CitizenTierPricingSeeder;

it('boots uuid and defaults standard citizen pricing tier', function () {
    $this->seed(CitizenTierPricingSeeder::class);

    $citizen = Citizen::factory()->create();

    $campaign = CitizenCampaign::factory()->create([
        'citizen_id' => $citizen->id,
        'citizen_ad_type' => CitizenAdType::LocalBusiness->value,
        'revenue_per_view' => null,
        'voter_payout_per_view' => null,
    ]);

    expect($campaign->uuid)->not->toBeNull();
    expect((float) $campaign->revenue_per_view)->toBe(0.75);
    expect((float) $campaign->voter_payout_per_view)->toBe(0.50);
});

it('applies ballot_issue pricing tier and forces admin approval + no daily cap', function () {
    $this->seed(CitizenTierPricingSeeder::class);

    $citizen = Citizen::factory()->create();

    $campaign = CitizenCampaign::factory()->create([
        'citizen_id' => $citizen->id,
        'citizen_ad_type' => CitizenAdType::BallotIssue->value,
        'pac_registration_id' => 'PAC-12345',
        'revenue_per_view' => null,
        'voter_payout_per_view' => null,
        'daily_view_cap' => 500,
        'approval_status' => ApprovalStatus::Approved->value,
    ]);

    expect((float) $campaign->revenue_per_view)->toBe(1.00);
    expect((float) $campaign->voter_payout_per_view)->toBe(0.50);
    expect($campaign->daily_view_cap)->toBeNull();
    expect($campaign->approval_status)->toBe(ApprovalStatus::Pending);
});

it('belongs to a citizen and is listed via citizen->campaigns()', function () {
    $citizen = Citizen::factory()->create();
    $campaign = CitizenCampaign::factory()->create(['citizen_id' => $citizen->id]);

    expect($campaign->citizen->id)->toBe($citizen->id);
    expect($citizen->campaigns()->pluck('id'))->toContain($campaign->id);
});
