<?php

use App\Models\DeletedAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeDeletedAccount(array $overrides = []): DeletedAccount
{
    return DeletedAccount::create(array_merge([
        'original_user_id' => rand(1, 999999),
        'email'            => 'purge-test-' . uniqid() . '@example.test',
        'user_type'        => 'voter',
        'user_snapshot'    => ['first_name' => 'Test'],
        'deleted_at'       => now(),
    ], $overrides));
}

test('purges records older than the retention window and keeps recent ones', function () {
    $old = makeDeletedAccount(['deleted_at' => now()->subDays(120)]);
    $recent = makeDeletedAccount(['deleted_at' => now()->subDays(10)]);

    $this->artisan('deleted-accounts:purge', ['--days' => 90, '--force' => true])
        ->assertExitCode(0);

    expect(DeletedAccount::find($old->id))->toBeNull()
        ->and(DeletedAccount::find($recent->id))->not->toBeNull();
});

test('purges already-restored records too once past the retention window', function () {
    $restored = makeDeletedAccount([
        'deleted_at'   => now()->subDays(120),
        'restored_at'  => now()->subDays(100),
        'restored_user_id' => 999,
    ]);

    $this->artisan('deleted-accounts:purge', ['--days' => 90, '--force' => true])
        ->assertExitCode(0);

    expect(DeletedAccount::find($restored->id))->toBeNull();
});

test('--dry-run reports the count without deleting anything', function () {
    $old = makeDeletedAccount(['deleted_at' => now()->subDays(120)]);

    $this->artisan('deleted-accounts:purge', ['--days' => 90, '--dry-run' => true])
        ->assertExitCode(0);

    expect(DeletedAccount::find($old->id))->not->toBeNull();
});

test('falls back to the config retention window when --days is not given', function () {
    config(['u9itus.deleted_account_retention_days' => 30]);

    $old = makeDeletedAccount(['deleted_at' => now()->subDays(45)]);
    $recent = makeDeletedAccount(['deleted_at' => now()->subDays(10)]);

    $this->artisan('deleted-accounts:purge', ['--force' => true])
        ->assertExitCode(0);

    expect(DeletedAccount::find($old->id))->toBeNull()
        ->and(DeletedAccount::find($recent->id))->not->toBeNull();
});

test('rejects a non-positive --days value', function () {
    $this->artisan('deleted-accounts:purge', ['--days' => 0, '--force' => true])
        ->assertExitCode(1);
});
