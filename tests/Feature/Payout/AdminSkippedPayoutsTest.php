<?php

namespace Tests\Feature\Payout;

use App\Models\PayoutRun;
use App\Models\PayoutRunSkippedItem;
use App\Models\User;
use App\Models\Voter;
use App\Services\PoliticalPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminSkippedPayoutsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_force_pay_below_minimum_from_skipped_diagnostics(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        skipOnboarding($admin, 'admin');

        $voter = Voter::factory()->create();
        $run = PayoutRun::create([
            'trigger_source' => 'admin',
            'min_payout_amount' => 5.00,
            'fraud_hold_hours' => 48,
        ]);

        $item = PayoutRunSkippedItem::create([
            'payout_run_id' => $run->id,
            'voter_id' => $voter->id,
            'reason_bucket' => 'below_min',
            'amount' => 2.50,
            'processor_selected' => 'wallet',
            'reason_detail' => 'Below minimum threshold',
        ]);

        $paymentServiceMock = Mockery::mock(PoliticalPaymentService::class);
        $paymentServiceMock->shouldReceive('forcePayBelowMinimum')
            ->once()
            ->with(
                Mockery::on(fn ($i) => $i instanceof PayoutRunSkippedItem && $i->id === $item->id),
                $admin->id,
                'Approved by finance admin'
            )
            ->andReturn([
                'processor' => 'wallet',
                'reference' => 'force-ref',
                'amount' => 2.50,
            ]);

        $this->app->instance(PoliticalPaymentService::class, $paymentServiceMock);

        $response = $this->actingAs($admin)->withoutMiddleware([
            \Spatie\Permission\Middleware\RoleMiddleware::class,
            \App\Http\Middleware\CheckAdminOnboarding::class,
            \App\Http\Middleware\EnsureAdminTwoFactorVerified::class,
        ])->post(
            route('admin.payouts.skipped.force-pay', $item),
            ['reason' => 'Approved by finance admin']
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }
}
