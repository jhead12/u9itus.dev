<?php

use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\AdminTwoFactorService;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin', 'web');
});

function makeStandaloneAdmin(array $overrides = []): User
{
    $admin = User::factory()->create(array_merge([
        'platform' => 'standalone',
        'user_type' => 'admin',
        'email_verified_at' => now(),
    ], $overrides));

    $admin->assignRole('admin');
    skipOnboarding($admin, 'admin');

    return $admin;
}

function setAdminTwoFactorPolicy(bool $enabled): void
{
    PlatformSetting::updateOrCreate(
        ['key' => 'admin_2fa_enforced', 'user_tier' => null],
        [
            'value' => $enabled ? '1' : '0',
            'type' => 'boolean',
            'category' => 'general',
            'is_active' => true,
            'description' => 'Test policy override',
        ]
    );
}

function fakeTwoFactorSecret(): string
{
    return str_repeat('A', 32);
}

test('policy off allows admin dashboard without 2fa enrollment', function () {
    $admin = makeStandaloneAdmin();
    setAdminTwoFactorPolicy(false);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk();
});

test('policy on redirects unenrolled admin to setup', function () {
    $admin = makeStandaloneAdmin([
        'admin_two_factor_secret' => null,
        'admin_two_factor_confirmed_at' => null,
    ]);

    setAdminTwoFactorPolicy(true);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('admin.2fa.setup'));
});

test('policy on redirects enrolled but unverified admin to challenge', function () {
    $admin = makeStandaloneAdmin([
        'admin_two_factor_secret' => fakeTwoFactorSecret(),
        'admin_two_factor_confirmed_at' => now(),
    ]);

    setAdminTwoFactorPolicy(true);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('admin.2fa.challenge'));
});

test('enabling admin 2fa generates and stores recovery codes', function () {
    $admin = makeStandaloneAdmin([
        'admin_two_factor_secret' => null,
        'admin_two_factor_confirmed_at' => null,
        'admin_two_factor_recovery_codes' => null,
    ]);

    $mock = Mockery::mock(AdminTwoFactorService::class);
    $mock->shouldReceive('verifyCode')->once()->andReturnTrue();
    $mock->shouldReceive('generateRecoveryCodes')->once()->andReturn([
        'ABCD-EFGH',
        'JKLM-NPQR',
    ]);
    $this->app->instance(AdminTwoFactorService::class, $mock);

    $this->actingAs($admin)
        ->withSession(['admin_2fa_setup_secret' => fakeTwoFactorSecret()])
        ->post(route('admin.2fa.setup.enable'), ['code' => '123456'])
        ->assertRedirect();

    $admin->refresh();

    expect($admin->hasAdminTwoFactorEnabled())->toBeTrue();
    expect($admin->admin_two_factor_recovery_codes)->toBe([
        'ABCD-EFGH',
        'JKLM-NPQR',
    ]);
});

test('recovery code can complete challenge and is consumed', function () {
    $admin = makeStandaloneAdmin([
        'admin_two_factor_secret' => fakeTwoFactorSecret(),
        'admin_two_factor_confirmed_at' => now(),
        'admin_two_factor_recovery_codes' => ['ABCD-EFGH', 'JKLM-NPQR'],
    ]);

    setAdminTwoFactorPolicy(true);

    $mock = Mockery::mock(AdminTwoFactorService::class);
    $mock->shouldReceive('consumeRecoveryCode')->once()->andReturn(['JKLM-NPQR']);
    $this->app->instance(AdminTwoFactorService::class, $mock);

    $this->actingAs($admin)
        ->post(route('admin.2fa.challenge.verify'), ['code' => 'ABCD-EFGH'])
        ->assertRedirect(route('admin.dashboard'));

    $admin->refresh();

    expect($admin->admin_two_factor_recovery_codes)->toBe(['JKLM-NPQR']);
});

test('admin can rotate recovery codes with password and authenticator check', function () {
    $admin = makeStandaloneAdmin([
        'admin_two_factor_secret' => fakeTwoFactorSecret(),
        'admin_two_factor_confirmed_at' => now(),
        'admin_two_factor_recovery_codes' => ['ABCD-EFGH'],
    ]);

    $mock = Mockery::mock(AdminTwoFactorService::class);
    $mock->shouldReceive('verifyCode')->once()->andReturnTrue();
    $mock->shouldReceive('generateRecoveryCodes')->once()->andReturn(['WXYZ-2345', '6789-BCDF']);
    $this->app->instance(AdminTwoFactorService::class, $mock);

    $this->actingAs($admin)
        ->post(route('admin.2fa.setup.recovery.rotate'), [
            'current_password' => 'password',
            'code' => '123456',
        ])
        ->assertRedirect();

    $admin->refresh();

    expect($admin->admin_two_factor_recovery_codes)->toBe(['WXYZ-2345', '6789-BCDF']);
});

test('admin 2fa setup page shows qr code when renderer succeeds', function () {
    $admin = makeStandaloneAdmin([
        'admin_two_factor_secret' => null,
        'admin_two_factor_confirmed_at' => null,
    ]);

    $mock = Mockery::mock(AdminTwoFactorService::class);
    $mock->shouldReceive('getOtpAuthUrl')
        ->once()
        ->andReturn('otpauth://totp/U9itus:test@example.com?secret=' . fakeTwoFactorSecret() . '&issuer=U9itus');
    $mock->shouldReceive('renderOtpAuthQrSvg')
        ->once()
        ->andReturn('<svg viewBox="0 0 100 100"><rect width="100" height="100" fill="#000"/></svg>');
    $this->app->instance(AdminTwoFactorService::class, $mock);

    $response = $this->actingAs($admin)
        ->withSession(['admin_2fa_setup_secret' => fakeTwoFactorSecret()])
        ->get(route('admin.2fa.setup'));

    $response->assertOk();
    $response->assertSee('Scan this QR code with Google Authenticator/Authy.');
    $response->assertSee('<svg viewBox="0 0 100 100">', false);
    $response->assertDontSee('QR code preview is unavailable right now.');
});
