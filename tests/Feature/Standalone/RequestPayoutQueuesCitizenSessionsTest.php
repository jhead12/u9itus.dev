<?php

use App\Enums\ViewPaymentStatus;
use App\Enums\ViewSessionStatus;
use App\Models\Citizen;
use App\Models\CitizenCampaign;
use App\Models\CitizenViewSession;
use App\Models\User;
use App\Models\Voter;
use App\Models\ViewSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * ARCH-CITIZEN entry-point guard: requestPayout must queue BOTH political
 * (ViewSession) and citizen (CitizenViewSession) completed sessions for
 * settlement by flipping them to Approved and stamping processor_selected.
 * The sibling Tests\Feature\Payout\CitizenPayoutSettlementTest proves the
 * downstream sweep settles both; this proves the HTTP entry point feeds it.
 */
function makePendingPoliticalSessions(Voter $voter, int $count, float $amount = 2.00): void
{
    for ($i = 0; $i < $count; $i++) {
        ViewSession::factory()->completed()->create([
            'voter_id'            => $voter->id,
            'payment_status'      => ViewPaymentStatus::Pending,
            'voter_payout_amount' => $amount,
        ]);
    }
}

function makePendingCitizenSessions(Voter $voter, int $count, float $amount = 2.00): void
{
    $campaign = CitizenCampaign::factory()->active()->create([
        'citizen_id' => Citizen::factory()->create()->id,
    ]);
    for ($i = 0; $i < $count; $i++) {
        CitizenViewSession::create([
            'citizen_campaign_id' => $campaign->id,
            'voter_id'             => $voter->id,
            'status'               => ViewSessionStatus::Completed,
            'completed_at'         => now(),
            'payment_status'       => ViewPaymentStatus::Pending,
            'voter_payout_amount' => $amount,
        ]);
    }
}

function requestPayoutVoter(): array
{
    Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);

    $user = User::factory()->create(['user_type' => 'voter']);
    $user->assignRole('voter');
    skipOnboarding($user, 'voter');

    $voter = Voter::factory()->create([
        'user_id'          => $user->id,
        'payment_method'   => 'wallet',
        'pending_earnings' => 8.00,
        'is_verified'       => true,
        'is_active'         => true,
    ]);

    return [$user, $voter];
}

beforeEach(function () {
    Cache::flush();
    config(['u9itus.min_payout_amount' => 5.00]);
});

test('request payout queues both political and citizen sessions as approved', function () {
    [$user, $voter] = requestPayoutVoter();
    makePendingPoliticalSessions($voter, 2, 2.00);
    makePendingCitizenSessions($voter, 2, 2.00);

    $response = $this->actingAs($user)->post(route('voter.earnings.payout'));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    // Both session types are flipped to Approved with the voter's processor.
    expect(ViewSession::where('voter_id', $voter->id)->where('payment_status', ViewPaymentStatus::Approved)->count())->toBe(2)
        ->and(ViewSession::where('voter_id', $voter->id)->where('processor_selected', 'wallet')->count())->toBe(2)
        ->and(CitizenViewSession::where('voter_id', $voter->id)->where('payment_status', ViewPaymentStatus::Approved)->count())->toBe(2)
        ->and(CitizenViewSession::where('voter_id', $voter->id)->where('processor_selected', 'wallet')->count())->toBe(2);
});

test('request payout still works for a voter with only citizen earnings', function () {
    // Regression guard: before the fix, requestPayout touched only
    // ViewSession, so a citizen-only voter queued nothing for settlement.
    [$user, $voter] = requestPayoutVoter();
    makePendingCitizenSessions($voter, 4, 2.00);

    $this->actingAs($user)->post(route('voter.earnings.payout'));

    expect(CitizenViewSession::where('voter_id', $voter->id)->where('payment_status', ViewPaymentStatus::Approved)->count())->toBe(4)
        ->and(ViewSession::where('voter_id', $voter->id)->where('payment_status', ViewPaymentStatus::Approved)->count())->toBe(0);
});