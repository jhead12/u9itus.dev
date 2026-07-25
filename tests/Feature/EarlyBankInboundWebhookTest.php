<?php

use App\Models\Citizen;
use App\Models\EarlyBankEarning;
use App\Models\Politician;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['voter', 'politician', 'citizen'] as $role) {
        \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => $role,
            'guard_name' => 'web',
        ]);
    }
});

// ── Helpers ─────────────────────────────────────────────────────────────────

function eb_inbound_secret(): string
{
    return 'inbound-eb-secret';
}

function eb_sign_payload(array $payload, string $secret): array
{
    $body      = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $timestamp = (string) time();
    $signature = hash_hmac('sha256', $timestamp . '.' . $body, $secret);

    return [
        'body'      => $body,
        'timestamp' => $timestamp,
        'signature' => 't=' . $timestamp . ',v1=' . $signature,
    ];
}

function eb_inbound_headers(array $payload, string $secret = 'inbound-eb-secret'): array
{
    $signed = eb_sign_payload($payload, $secret);

    return [
        'Authorization'           => 'Bearer test-eb-token',
        'Content-Type'            => 'application/json',
        'X-EarlyBank-Timestamp'   => $signed['timestamp'],
        'X-EarlyBank-Signature'   => $signed['signature'],
    ];
}

function eb_api_token(): void
{
    Config::set('services.earlybank.api_token', 'test-eb-token');
    Config::set('services.earlybank.webhook_secret', eb_inbound_secret());
}

function eb_make_voter_with_eb_member(string $email = 'ebvoter@example.com'): Voter
{
    $user = User::factory()->create([
        'email'     => $email,
        'user_type' => 'voter',
    ]);
    $user->assignRole('voter');

    $voter = Voter::factory()->create([
        'user_id' => $user->id,
        'email'   => $email,
        'earlybank_own_member_uuid' => Str::uuid()->toString(),
    ]);

    return $voter;
}

function eb_make_politician_with_eb_member(): Politician
{
    $user = User::factory()->create([
        'email'     => 'ebpol@example.com',
        'user_type' => 'politician',
    ]);
    $user->assignRole('politician');

    return Politician::factory()->create([
        'user_id' => $user->id,
        'earlybank_own_member_uuid' => Str::uuid()->toString(),
    ]);
}

function eb_make_citizen_with_eb_member(): Citizen
{
    $user = User::factory()->create([
        'email'     => 'ebcitizen@example.com',
        'user_type' => 'citizen',
    ]);
    $user->assignRole('citizen');

    return Citizen::factory()->create([
        'user_id' => $user->id,
        'earlybank_own_member_uuid' => Str::uuid()->toString(),
    ]);
}

// ── Signature verification ─────────────────────────────────────────────────

test('inbound webhook accepts a valid signature', function () {
    eb_api_token();
    $voter = eb_make_voter_with_eb_member();

    $payload = [
        'event'    => EarlyBankEarning::EVENT_PAYOUT_COMMISSION,
        'event_id' => Str::uuid()->toString(),
        'data'     => [
            'earlybank_member_id' => $voter->earlybank_own_member_uuid,
            'voter_uuid'          => $voter->uuid,
            'payout_amount'       => 1.23,
        ],
    ];

    $this->withHeaders(eb_inbound_headers($payload))
        ->postJson('/api/v1/earlybank/webhook', $payload)
        ->assertOk()
        ->assertJsonPath('status', 'processed');

    expect(EarlyBankEarning::count())->toBe(1);
});

test('inbound webhook rejects a missing signature', function () {
    eb_api_token();
    Config::set('services.earlybank.webhook_secret', eb_inbound_secret());

    $this->withHeaders(['Authorization' => 'Bearer test-eb-token'])
        ->postJson('/api/v1/earlybank/webhook', ['event' => EarlyBankEarning::EVENT_PAYOUT_COMMISSION])
        ->assertUnauthorized()
        ->assertJsonPath('error', 'invalid_signature');
});

test('inbound webhook rejects an invalid signature', function () {
    eb_api_token();

    $payload = [
        'event' => EarlyBankEarning::EVENT_PAYOUT_COMMISSION,
        'data'  => [],
    ];

    $this->withHeaders(array_merge(
        eb_inbound_headers($payload),
        ['X-EarlyBank-Signature' => 't=' . time() . ',v1=' . str_repeat('0', 64)]
    ))
        ->postJson('/api/v1/earlybank/webhook', $payload)
        ->assertUnauthorized()
        ->assertJsonPath('error', 'invalid_signature');
});

test('inbound webhook rejects a stale timestamp', function () {
    eb_api_token();

    $payload = [
        'event' => EarlyBankEarning::EVENT_PAYOUT_COMMISSION,
        'data'  => [],
    ];
    $body      = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $timestamp = (string) (time() - 400); // older than 5-minute window
    $signature = hash_hmac('sha256', $timestamp . '.' . $body, eb_inbound_secret());

    $this->withHeaders([
        'Authorization'         => 'Bearer test-eb-token',
        'X-EarlyBank-Timestamp' => $timestamp,
        'X-EarlyBank-Signature' => 't=' . $timestamp . ',v1=' . $signature,
    ])
        ->postJson('/api/v1/earlybank/webhook', $payload)
        ->assertUnauthorized();
});

// ── Event processing ─────────────────────────────────────────────────────────

test('payout.commission creates an earlybank_earnings row for a voter', function () {
    eb_api_token();
    $voter = eb_make_voter_with_eb_member();

    $eventId = Str::uuid()->toString();
    $payload = [
        'event'    => EarlyBankEarning::EVENT_PAYOUT_COMMISSION,
        'event_id' => $eventId,
        'data'     => [
            'earlybank_member_id' => $voter->earlybank_own_member_uuid,
            'voter_uuid'          => $voter->uuid,
            'payout_amount'       => 5.50,
            'external_reference'  => 'eb-batch-001',
        ],
    ];

    $this->withHeaders(eb_inbound_headers($payload))
        ->postJson('/api/v1/earlybank/webhook', $payload)
        ->assertOk();

    $earning = EarlyBankEarning::first();
    expect($earning)->not->toBeNull()
        ->and($earning->event_type)->toBe(EarlyBankEarning::EVENT_PAYOUT_COMMISSION)
        ->and($earning->voter_id)->toBe($voter->id)
        ->and((float) $earning->payout_amount)->toBe(5.50)
        ->and($earning->earlybank_event_id)->toBe($eventId);
});

test('payout.bonus creates an earlybank_earnings row for a politician', function () {
    eb_api_token();
    $politician = eb_make_politician_with_eb_member();

    $payload = [
        'event'    => EarlyBankEarning::EVENT_PAYOUT_BONUS,
        'event_id' => Str::uuid()->toString(),
        'data'     => [
            'earlybank_member_id' => $politician->earlybank_own_member_uuid,
            'voter_uuid'          => null,
            'bonus_amount'        => 10.00,
        ],
    ];

    $this->withHeaders(eb_inbound_headers($payload))
        ->postJson('/api/v1/earlybank/webhook', $payload)
        ->assertOk();

    expect(EarlyBankEarning::where('politician_id', $politician->id)->exists())->toBeTrue();
});

test('member.status syncs subscription status on a citizen', function () {
    eb_api_token();
    $citizen = eb_make_citizen_with_eb_member();

    $payload = [
        'event'    => EarlyBankEarning::EVENT_MEMBER_STATUS,
        'event_id' => Str::uuid()->toString(),
        'data'     => [
            'earlybank_member_id'  => $citizen->earlybank_own_member_uuid,
            'subscription_status'  => EarlyBankEarning::SUBSCRIPTION_PAID,
        ],
    ];

    $this->withHeaders(eb_inbound_headers($payload))
        ->postJson('/api/v1/earlybank/webhook', $payload)
        ->assertOk();

    $citizen->refresh();
    expect($citizen->earlybank_subscription_status)->toBe(EarlyBankEarning::SUBSCRIPTION_PAID);

    expect(EarlyBankEarning::where('citizen_id', $citizen->id)
        ->where('event_type', EarlyBankEarning::EVENT_MEMBER_STATUS)
        ->exists())->toBeTrue();
});

test('inbound webhook is idempotent by idempotency key', function () {
    eb_api_token();
    $voter = eb_make_voter_with_eb_member();

    $payload = [
        'event'           => EarlyBankEarning::EVENT_PAYOUT_COMMISSION,
        'event_id'        => Str::uuid()->toString(),
        'idempotency_key' => 'eb-key-' . Str::random(16),
        'data'            => [
            'earlybank_member_id' => $voter->earlybank_own_member_uuid,
            'voter_uuid'          => $voter->uuid,
            'payout_amount'       => 2.00,
        ],
    ];

    $this->withHeaders(eb_inbound_headers($payload))
        ->postJson('/api/v1/earlybank/webhook', $payload)
        ->assertOk();

    $this->withHeaders(eb_inbound_headers($payload))
        ->postJson('/api/v1/earlybank/webhook', $payload)
        ->assertOk();

    expect(EarlyBankEarning::count())->toBe(1);
});

test('politician.purchased creates an audit row without crediting a balance', function () {
    eb_api_token();
    $politician = eb_make_politician_with_eb_member();

    $payload = [
        'event'    => EarlyBankEarning::EVENT_POLITICIAN_PURCHASED,
        'event_id' => Str::uuid()->toString(),
        'data'     => [
            'earlybank_member_id' => $politician->earlybank_own_member_uuid,
            'politician_uuid'     => $politician->uuid,
            'purchase_amount'     => 100.00,
        ],
    ];

    $this->withHeaders(eb_inbound_headers($payload))
        ->postJson('/api/v1/earlybank/webhook', $payload)
        ->assertOk();

    expect(EarlyBankEarning::where('politician_id', $politician->id)
        ->where('event_type', EarlyBankEarning::EVENT_POLITICIAN_PURCHASED)
        ->exists())->toBeTrue();
});

// ── Aggregation ────────────────────────────────────────────────────────────

test('voter earnings summary includes earlybank earnings separately', function () {
    eb_api_token();
    $voter = eb_make_voter_with_eb_member();

    EarlyBankEarning::create([
        'event_type'          => EarlyBankEarning::EVENT_PAYOUT_COMMISSION,
        'voter_id'            => $voter->id,
        'earlybank_member_id' => $voter->earlybank_own_member_uuid,
        'payout_amount'       => 4.00,
        'commission_amount'   => 4.00,
        'status'              => EarlyBankEarning::STATUS_SETTLED,
        'idempotency_key'     => 'eb-sum-1',
        'payload'             => [],
    ]);

    EarlyBankEarning::create([
        'event_type'          => EarlyBankEarning::EVENT_PAYOUT_BONUS,
        'voter_id'            => $voter->id,
        'earlybank_member_id' => $voter->earlybank_own_member_uuid,
        'payout_amount'       => 1.00,
        'bonus_amount'        => 1.00,
        'status'              => EarlyBankEarning::STATUS_PENDING,
        'idempotency_key'     => 'eb-sum-2',
        'payload'             => [],
    ]);

    $summary = app(\App\Services\PoliticalViewService::class)->voterEarningsSummary($voter);

    expect($summary['earlybank_earnings_settled'])->toBe(4.0)
        ->and($summary['earlybank_earnings_pending'])->toBe(1.0)
        ->and($summary['earlybank_earnings_total'])->toBe(5.0);
});

test('voter resource includes earlybank earnings total', function () {
    $voter = eb_make_voter_with_eb_member();

    EarlyBankEarning::create([
        'event_type'          => EarlyBankEarning::EVENT_PAYOUT_COMMISSION,
        'voter_id'            => $voter->id,
        'earlybank_member_id' => $voter->earlybank_own_member_uuid,
        'payout_amount'       => 3.00,
        'commission_amount'   => 3.00,
        'status'              => EarlyBankEarning::STATUS_SETTLED,
        'idempotency_key'     => 'eb-res-1',
        'payload'             => [],
    ]);

    $resource = (new \App\Http\Resources\VoterResource($voter->load('earlybankEarnings')))->toArray(request());

    expect($resource['earlybank_earnings_total'])->toBe(3.0)
        ->and($resource['earlybank_subscription_status'])->toBeNull();
});

// ── End-to-end: view completion → outbound voter.earned → inbound payout.commission ──

test('a referred voter completing a view leads to an earlybank_earnings row and updates the referrer dashboard', function () {
    eb_api_token();
    Config::set('services.earlybank.enabled', true);
    Config::set('services.earlybank.webhook_url', 'https://fake-eb.test/webhook');

    // The referrer holds their own EB membership and can receive commissions.
    $referrer = eb_make_voter_with_eb_member('eb-referrer@example.com');

    // The referred voter was attributed to the referrer's EB member UUID and
    // has completed Stripe Connect onboarding, so commission events will fire.
    $referredUser = User::factory()->create(['email' => 'eb-referred@example.com', 'user_type' => 'voter']);
    $referredUser->assignRole('voter');
    $referredVoter = Voter::factory()->create([
        'user_id'              => $referredUser->id,
        'email'                => $referredUser->email,
        'earlybank_member_id'  => $referrer->earlybank_own_member_uuid,
        'stripe_account_status' => 'active',
    ]);

    $campaign = \App\Models\PoliticalCampaign::factory()->create();
    $session = \App\Models\ViewSession::factory()->completed()->create([
        'political_campaign_id' => $campaign->id,
        'voter_id'              => $referredVoter->id,
        'voter_payout_amount'   => 0.25,
    ]);
    $session->setRelation('voter', $referredVoter);

    \Illuminate\Support\Facades\Http::fake(['https://fake-eb.test/webhook' => \Illuminate\Support\Facades\Http::response('ok', 200)]);

    // Step 1: the view completes and u9itus notifies Early-bank of the 10% commission.
    app(\App\Services\EarlyBankWebhookService::class)->notifyViewSessionCompleted($session);

    \Illuminate\Support\Facades\Http::assertSent(function ($request) use ($referrer) {
        $body = json_decode($request->body(), true);

        return ($body['event'] ?? '') === 'voter.earned'
            && ($body['data']['earlybank_member_id'] ?? '') === $referrer->earlybank_own_member_uuid;
    });

    // Step 2: Early-bank settles the commission and reports it back inbound.
    $commissionAmount = round(0.25 * 0.10, 2);
    $payload = [
        'event'    => EarlyBankEarning::EVENT_PAYOUT_COMMISSION,
        'event_id' => Str::uuid()->toString(),
        'data'     => [
            'earlybank_member_id' => $referrer->earlybank_own_member_uuid,
            'voter_uuid'          => $referredVoter->uuid,
            'payout_amount'       => $commissionAmount,
            'external_reference'  => 'eb-e2e-batch',
        ],
    ];

    $this->withHeaders(eb_inbound_headers($payload))
        ->postJson('/api/v1/earlybank/webhook', $payload)
        ->assertOk();

    // Step 3: the referrer's earlybank_earnings ledger reflects the settled commission.
    $this->assertDatabaseHas('earlybank_earnings', [
        'voter_id'      => $referrer->id,
        'event_type'    => EarlyBankEarning::EVENT_PAYOUT_COMMISSION,
        'payout_amount' => $commissionAmount,
    ]);

    $referrer->refresh();
    expect((float) $referrer->earlybankEarnings()->sum('payout_amount'))->toBe($commissionAmount);

    // Step 4: the referrer's own referral dashboard surfaces this Early-bank total.
    skipOnboarding($referrer->user, 'voter');
    $response = $this->actingAs($referrer->user)->get(route('voter.referrals'));

    $response->assertOk();
    $response->assertSee('Early-bank Commissions');
    $response->assertSee('$' . number_format($commissionAmount, 2));
});
