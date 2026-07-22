<?php

use App\Enums\DispatchStatus;
use App\Mail\CampaignChannelMail;
use App\Models\CampaignDispatch;
use App\Models\MarketingChannel;
use App\Models\PoliticalCampaign;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function dispatchEmailForCampaign(string $type, int $id): int
{
    return \Illuminate\Support\Facades\Artisan::call('marketing:dispatch', [
        'campaign_type' => $type,
        'campaign_id'   => $id,
        'channel'       => 'email',
        '--sync'         => true,
    ]);
}

test('the email channel is seeded and active', function () {
    $channel = MarketingChannel::where('key', 'email')->first();

    expect($channel)->not->toBeNull()
        ->and($channel->is_first_party)->toBeTrue()
        ->and($channel->status->value)->toBe('active')
        ->and($channel->provider_class)->toBe(\App\Services\Marketing\Channels\EmailChannel::class);
});

test('marketing:dispatch --sync dispatches email to the targeted audience only', function () {
    Mail::fake();

    $campaign = PoliticalCampaign::factory()->create([
        'target_states'           => ['CA'],
        'target_districts'         => null,
        'target_governance_levels' => null,
    ]);
    $caVoter = Voter::factory()->create(['state' => 'CA', 'email' => 'ca@example.test']);
    $txVoter = Voter::factory()->create(['state' => 'TX', 'email' => 'tx@example.test']);

    $exit = dispatchEmailForCampaign('political', $campaign->id);

    expect($exit)->toBe(0);

    $dispatched = CampaignDispatch::where('campaign_id', $campaign->id)->get();

    expect($dispatched)->toHaveCount(1)
        ->and($dispatched->first()->voter_id)->toBe($caVoter->id)
        ->and($dispatched->first()->status)->toBe(DispatchStatus::Dispatched)
        ->and($dispatched->first()->provider_message_id)->not->toBeNull()
        ->and($dispatched->first()->dispatched_at)->not->toBeNull();

    Mail::assertSent(CampaignChannelMail::class, fn ($mail) => $mail->hasTo('ca@example.test'));
    Mail::assertNotSent(CampaignChannelMail::class, fn ($mail) => $mail->hasTo('tx@example.test'));
});

test('recipient without an email is skipped, not failed', function () {
    Mail::fake();

    $campaign = PoliticalCampaign::factory()->create([
        'target_states'           => ['CA'],
        'target_districts'         => null,
        'target_governance_levels' => null,
    ]);
    Voter::factory()->create(['state' => 'CA', 'email' => null]);

    dispatchEmailForCampaign('political', $campaign->id);

    $dispatch = CampaignDispatch::where('campaign_id', $campaign->id)->first();

    expect($dispatch->status)->toBe(DispatchStatus::Skipped)
        ->and($dispatch->error_message)->toContain('email');
});

test('re-running dispatch is idempotent — no duplicate rows, no double send', function () {
    Mail::fake();

    $campaign = PoliticalCampaign::factory()->create([
        'target_states' => ['CA'],
    ]);
    Voter::factory()->create(['state' => 'CA', 'email' => 'ca@example.test']);

    dispatchEmailForCampaign('political', $campaign->id);
    dispatchEmailForCampaign('political', $campaign->id);

    // One DB row total (second run reuses it), and only one mail sent across
    // both runs — the channel did not re-send for the already-terminal row.
    expect(CampaignDispatch::where('campaign_id', $campaign->id)->count())->toBe(1);
    expect(Mail::sent(CampaignChannelMail::class)->count())->toBe(1);
});

test('marketing disabled flag short-circuits dispatch', function () {
    Mail::fake();
    config(['u9itus.marketing.enabled' => false]);

    $campaign = PoliticalCampaign::factory()->create(['target_states' => ['CA']]);
    Voter::factory()->create(['state' => 'CA', 'email' => 'ca@example.test']);

    dispatchEmailForCampaign('political', $campaign->id);

    expect(CampaignDispatch::count())->toBe(0);
    Mail::assertNothingSent();
});