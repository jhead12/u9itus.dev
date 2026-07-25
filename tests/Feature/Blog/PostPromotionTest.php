<?php

use App\Enums\PostStatus;
use App\Models\Citizen;
use App\Models\CitizenCredit;
use App\Models\Politician;
use App\Models\PoliticianCredit;
use App\Models\Post;
use App\Models\User;
use App\Services\PlatformSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    foreach (['citizen', 'politician', 'voter'] as $role) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }

    PlatformSettingsService::set('post_promotion_daily_credits', 2.0, [
        'type' => 'float',
        'description' => 'Daily cost to promote a blog post',
        'category' => 'pricing',
    ]);
});

function makeCitizenAuthor(array $overrides = []): array
{
    $user = User::factory()->create();
    $user->assignRole('citizen');
    $citizen = Citizen::factory()->create(array_merge(['user_id' => $user->id], $overrides));
    skipOnboarding($user, 'citizen');
    return [$user, $citizen];
}

function makePoliticianAuthor(): array
{
    $user = User::factory()->create();
    $user->assignRole('politician');
    $politician = Politician::factory()->create(['user_id' => $user->id]);
    skipOnboarding($user, 'politician');
    return [$user, $politician];
}

it('allows a citizen to promote a published post with sufficient credits', function (): void {
    [$user, $citizen] = makeCitizenAuthor();

    CitizenCredit::create([
        'citizen_id' => $citizen->id,
        'transaction_type' => 'purchase',
        'amount' => 100,
        'balance_after' => 100,
        'description' => 'Test credits',
    ]);
    $citizen->syncCreditBalance();

    $post = Post::factory()->create([
        'author_type' => Citizen::class,
        'author_id' => $citizen->id,
        'status' => PostStatus::Published,
        'published_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($user)->post(route('citizen.posts.promote', $post), ['days' => 3]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $post->refresh();
    expect($post->is_promoted)->toBeTrue();
    expect($post->promoted_until)->not->toBeNull();
    expect((float) $post->credit_spent)->toBe(6.0);

    $citizen->refresh();
    expect((float) $citizen->credit_balance)->toBe(94.0);
});

it('rejects promotion when citizen has insufficient credits', function (): void {
    [$user, $citizen] = makeCitizenAuthor();

    CitizenCredit::create([
        'citizen_id' => $citizen->id,
        'transaction_type' => 'purchase',
        'amount' => 1,
        'balance_after' => 1,
        'description' => 'Test credits',
    ]);
    $citizen->syncCreditBalance();

    $post = Post::factory()->create([
        'author_type' => Citizen::class,
        'author_id' => $citizen->id,
        'status' => PostStatus::Published,
        'published_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($user)->post(route('citizen.posts.promote', $post), ['days' => 3]);

    $response->assertRedirect();
    $response->assertSessionHas('error');

    $post->refresh();
    expect($post->is_promoted)->toBeFalse();
});

it('allows a politician to promote a published post', function (): void {
    [$user, $politician] = makePoliticianAuthor();

    PoliticianCredit::create([
        'politician_id' => $politician->id,
        'transaction_type' => 'purchase',
        'amount' => 100,
        'balance_after' => 100,
        'description' => 'Test credits',
    ]);
    $politician->syncCreditBalance();

    $post = Post::factory()->create([
        'author_type' => Politician::class,
        'author_id' => $politician->id,
        'status' => PostStatus::Published,
        'published_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($user)->post(route('politician.posts.promote', $post), ['days' => 2]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $post->refresh();
    expect($post->is_promoted)->toBeTrue();
    expect((float) $post->credit_spent)->toBe(4.0);
});

it('shows promoted posts in the voter ad-room', function (): void {
    [$user, $citizen] = makeCitizenAuthor();

    CitizenCredit::create([
        'citizen_id' => $citizen->id,
        'transaction_type' => 'purchase',
        'amount' => 100,
        'balance_after' => 100,
    ]);
    $citizen->syncCreditBalance();

    $post = Post::factory()->create([
        'author_type' => Citizen::class,
        'author_id' => $citizen->id,
        'status' => PostStatus::Published,
        'published_at' => now()->subDay(),
        'title' => 'Promoted Story',
    ]);

    $this->actingAs($user)->post(route('citizen.posts.promote', $post), ['days' => 1]);

    $voterUser = User::factory()->create();
    $voterUser->assignRole('voter');
    $voter = \App\Models\Voter::factory()->create(['user_id' => $voterUser->id]);
    skipOnboarding($voterUser, 'voter');

    $response = $this->actingAs($voterUser)->get(route('voter.ad-room'));

    $response->assertOk();
    $response->assertSee('Promoted Story');
    $response->assertSee('Latest from the community');
});

it('shows a featured promoted post on the topic page', function (): void {
    [$user, $citizen] = makeCitizenAuthor();

    CitizenCredit::create([
        'citizen_id' => $citizen->id,
        'transaction_type' => 'purchase',
        'amount' => 100,
        'balance_after' => 100,
    ]);
    $citizen->syncCreditBalance();

    $topic = \App\Models\PoliticianTopic::create([
        'name' => 'Local Voices',
        'slug' => 'local-voices',
        'sort_order' => 0,
        'is_active' => true,
        'voter_selectable' => true,
        'auto_earned_only' => false,
    ]);

    $post = Post::factory()->create([
        'author_type' => Citizen::class,
        'author_id' => $citizen->id,
        'status' => PostStatus::Published,
        'published_at' => now()->subDay(),
        'title' => 'Featured Local Story',
    ]);
    $post->topics()->attach($topic);

    $this->actingAs($user)->post(route('citizen.posts.promote', $post), ['days' => 1]);

    $response = $this->get(route('blog.topic', $topic->slug));

    $response->assertOk();
    $response->assertSee('Featured');
    $response->assertSee('Featured Local Story');
});
