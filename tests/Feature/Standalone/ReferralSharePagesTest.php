<?php

use App\Models\Politician;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function politicianReferralUser(array $politicianOverrides = []): array
{
    Role::firstOrCreate(['name' => 'politician', 'guard_name' => 'web']);

    $user = User::factory()->create([
        'user_type' => 'politician',
    ]);
    $user->assignRole('politician');

    skipOnboarding($user, 'politician');

    $politician = Politician::factory()->create(array_merge([
        'user_id' => $user->id,
        'referral_code' => 'POLSHARE',
        'is_active' => true,
    ], $politicianOverrides));

    return [$user, $politician];
}

test('voter referral page renders email and social share links', function () {
    Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);

    $user = User::factory()->create([
        'user_type' => 'voter',
    ]);
    $user->assignRole('voter');

    skipOnboarding($user, 'voter');

    Voter::factory()->create([
        'user_id' => $user->id,
        'referral_code' => 'VOTERSHR',
        'is_verified' => true,
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->get(route('voter.referrals'));

    $response->assertOk();
    $response->assertSee('Email Draft');
    $response->assertSee('https://twitter.com/intent/tweet?text=Join%20U9itus%20as%20a%20voter%20using%20my%20referral%20link', false);
    $response->assertSee('https://twitter.com/intent/tweet?text=Join%20U9itus%20as%20a%20politician%20using%20my%20referral%20link', false);
    $response->assertSee('https://api.whatsapp.com/send?text=Join%20U9itus%20as%20a%20voter%20using%20my%20referral%20link', false);
    $response->assertSee('https://t.me/share/url?url=', false);
    $response->assertSee('mailto:?subject=Join%20U9itus%20as%20a%20voter%20with%20my%20referral%20link', false);
}

test('politician referral page renders email and social share links', function () {
    [$user] = politicianReferralUser();

    $response = $this->actingAs($user)->get(route('politician.referrals'));

    $response->assertOk();
    $response->assertSee('Email Draft');
    $response->assertSee('https://twitter.com/intent/tweet?text=Join%20U9itus%20as%20a%20voter%20using%20my%20referral%20link', false);
    $response->assertSee('https://twitter.com/intent/tweet?text=Join%20U9itus%20as%20a%20politician%20using%20my%20referral%20link', false);
    $response->assertSee('https://www.facebook.com/sharer/sharer.php?u=', false);
    $response->assertSee('https://api.whatsapp.com/send?text=Join%20U9itus%20as%20a%20politician%20using%20my%20referral%20link', false);
    $response->assertSee('mailto:?subject=Join%20U9itus%20as%20a%20politician%20with%20my%20referral%20link', false);
}