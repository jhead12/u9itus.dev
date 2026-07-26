<?php

use App\Jobs\ProcessAccountDeletionStripeCleanupJob;
use App\Models\CampaignTransaction;
use App\Models\DeletedAccount;
use App\Models\Politician;
use App\Models\PoliticianPaymentMethod;
use App\Models\User;
use App\Models\Voter;
use App\Services\StripePaymentService;
use App\Services\UserDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('archiveAndDelete refunds unused politician credits, snapshots payment methods, and queues Stripe cleanup', function () {
    Queue::fake();

    $stripe = Mockery::mock(StripePaymentService::class);
    $stripe->shouldReceive('createRefundForPaymentIntent')
        ->once()
        ->with('pi_del_test', 40.00, Mockery::any())
        ->andReturn((object) ['id' => 're_del_test', 'status' => 'succeeded']);
    app()->instance(StripePaymentService::class, $stripe);

    $admin = User::factory()->create(['user_type' => 'admin']);
    $user  = User::factory()->create(['user_type' => 'politician']);
    $politician = Politician::factory()->create(['user_id' => $user->id, 'credit_balance' => 0.00]);

    $campaignBilling = app(\App\Services\CampaignBillingService::class);
    $campaignBilling->addCredits($politician, 40.00, ['transaction_type' => 'purchase']);

    CampaignTransaction::create([
        'politician_id'            => $politician->id,
        'transaction_type'         => 'charge',
        'amount'                   => 40.00,
        'currency'                 => 'USD',
        'stripe_payment_intent_id' => 'pi_del_test',
        'status'                   => 'succeeded',
        'metadata'                 => ['credits_amount' => 40.00, 'payment_mode' => 'test'],
    ]);

    PoliticianPaymentMethod::create([
        'politician_id'             => $politician->id,
        'stripe_customer_id'        => 'cus_del_test',
        'stripe_payment_method_id'  => 'pm_del_test',
        'label'                     => 'Visa ending 4242',
        'is_default'                => true,
    ]);

    $service = app(UserDeletionService::class);
    $record  = $service->archiveAndDelete($user, $admin, 'test cleanup', '127.0.0.1');

    expect($record)->toBeInstanceOf(DeletedAccount::class)
        ->and(User::find($user->id))->toBeNull()
        ->and(Politician::find($politician->id))->toBeNull()
        ->and($record->stripe_cleanup_status)->toBe('pending');

    $plan = $record->stripe_cleanup_plan;
    expect($plan['refunds']['politician']['refunded_transaction_ids'])->toHaveCount(1)
        ->and($plan['refunds']['politician']['errors'])->toBeEmpty()
        ->and($plan['payment_methods'][0]['stripe_customer_id'])->toBe('cus_del_test')
        ->and($plan['payment_methods'][0]['stripe_payment_method_id'])->toBe('pm_del_test')
        ->and($plan['connect_account_id'])->toBeNull();

    Queue::assertPushed(ProcessAccountDeletionStripeCleanupJob::class, function ($job) use ($record) {
        return $job->deletedAccount->id === $record->id;
    });
});

test('archiveAndDelete snapshots a voter Connect account id for the cleanup job', function () {
    Queue::fake();

    $admin = User::factory()->create(['user_type' => 'admin']);
    $user  = User::factory()->create(['user_type' => 'voter']);
    $voter = Voter::factory()->create(['user_id' => $user->id, 'stripe_account_id' => 'acct_del_test']);

    $service = app(UserDeletionService::class);
    $record  = $service->archiveAndDelete($user, $admin, 'admin cleanup', '127.0.0.1');

    expect(Voter::find($voter->id))->toBeNull()
        ->and($record->stripe_cleanup_status)->toBe('pending')
        ->and($record->stripe_cleanup_plan['connect_account_id'])->toBe('acct_del_test');

    Queue::assertPushed(ProcessAccountDeletionStripeCleanupJob::class);
});

test('archiveAndDelete skips Stripe cleanup entirely when there is nothing to clean up', function () {
    Queue::fake();

    $admin = User::factory()->create(['user_type' => 'admin']);
    $user  = User::factory()->create(['user_type' => 'voter']);
    Voter::factory()->create(['user_id' => $user->id, 'stripe_account_id' => null]);

    $service = app(UserDeletionService::class);
    $record  = $service->archiveAndDelete($user, $admin, 'admin cleanup', '127.0.0.1');

    expect($record->stripe_cleanup_status)->toBe('not_applicable');

    Queue::assertNotPushed(ProcessAccountDeletionStripeCleanupJob::class);
});
