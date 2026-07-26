<?php

use App\Models\DeletedAccount;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('users:delete archives and deletes a user by email with --force', function () {
    Queue::fake();

    $admin = User::factory()->create(['user_type' => 'admin']);
    $user  = User::factory()->create(['user_type' => 'voter']);
    Voter::factory()->create(['user_id' => $user->id]);

    $this->artisan('users:delete', [
        'user'     => $user->email,
        '--admin'  => $admin->email,
        '--reason' => 'test cleanup',
        '--force'  => true,
    ])->assertExitCode(0);

    expect(User::find($user->id))->toBeNull()
        ->and(DeletedAccount::where('original_user_id', $user->id)->exists())->toBeTrue();
});

test('users:delete refuses to delete an admin account', function () {
    $admin  = User::factory()->create(['user_type' => 'admin']);
    $target = User::factory()->create(['user_type' => 'admin']);

    $this->artisan('users:delete', [
        'user'    => $target->email,
        '--admin' => $admin->email,
        '--force' => true,
    ])->assertExitCode(1);

    expect(User::find($target->id))->not->toBeNull();
});

test('users:delete refuses to let the acting admin delete themselves', function () {
    $admin = User::factory()->create(['user_type' => 'admin']);

    $this->artisan('users:delete', [
        'user'    => $admin->email,
        '--admin' => $admin->email,
        '--force' => true,
    ])->assertExitCode(1);
});

test('users:delete errors when the target user cannot be resolved', function () {
    $admin = User::factory()->create(['user_type' => 'admin']);

    $this->artisan('users:delete', [
        'user'    => 'nobody@example.test',
        '--admin' => $admin->email,
        '--force' => true,
    ])->assertExitCode(1);
});
