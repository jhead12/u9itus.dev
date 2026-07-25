<?php

use App\Models\Citizen;
use App\Models\Politician;
use App\Models\Post;
use App\Models\User;
use App\Enums\PostStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    if (class_exists(\Spatie\Permission\Models\Role::class)) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'citizen', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'politician', 'guard_name' => 'web']);
    }
});

function makeCitizenUser(): User
{
    $user = User::factory()->create(['platform' => 'standalone']);
    $user->assignRole('citizen');
    Citizen::factory()->create(['user_id' => $user->id]);
    skipOnboarding($user, 'citizen');
    return $user->load('citizen');
}

it('allows a citizen to create a draft post', function (): void {
    $user = makeCitizenUser();
    $citizen = $user->citizen;

    $response = $this->actingAs($user)->post(route('citizen.posts.store'), [
        'title' => 'A test post',
        'subtitle' => 'Optional subtitle',
        'body' => '<p>Hello world</p>',
        'excerpt' => 'Short excerpt',
    ]);

    $response->assertRedirect();

    $post = Post::query()->where('title', 'A test post')->first();
    expect($post)->not->toBeNull();
    expect($post->status->value)->toBe(PostStatus::Draft->value);
    expect($post->author_type)->toBe(Citizen::class);
    expect($post->author_id)->toBe($citizen->id);
});

it('allows a citizen to publish a draft post', function (): void {
    $user = makeCitizenUser();
    $citizen = $user->citizen;

    $post = Post::factory()->create([
        'author_type' => Citizen::class,
        'author_id' => $citizen->id,
        'status' => PostStatus::Draft,
    ]);

    $response = $this->actingAs($user)->post(route('citizen.posts.publish', $post));
    $response->assertRedirect();

    $post->refresh();
    expect($post->status->value)->toBe(PostStatus::Published->value);
    expect($post->published_at)->not->toBeNull();
});

it('prevents one citizen from editing another citizen post', function (): void {
    $user = makeCitizenUser();

    $otherCitizen = Citizen::factory()->create();
    $post = Post::factory()->create([
        'author_type' => Citizen::class,
        'author_id' => $otherCitizen->id,
        'status' => PostStatus::Draft,
    ]);

    $response = $this->actingAs($user)->get(route('citizen.posts.edit', $post));
    $response->assertForbidden();
});

it('shows published posts on the public blog index', function (): void {
    $citizen = Citizen::factory()->create();
    Post::factory()->create([
        'author_type' => Citizen::class,
        'author_id' => $citizen->id,
        'status' => PostStatus::Published,
        'published_at' => now()->subHour(),
        'title' => 'Visible Post',
    ]);

    Post::factory()->create([
        'author_type' => Citizen::class,
        'author_id' => $citizen->id,
        'status' => PostStatus::Draft,
        'title' => 'Hidden Post',
    ]);

    $response = $this->get(route('blog.index'));
    $response->assertOk();
    $response->assertSee('Visible Post');
    $response->assertDontSee('Hidden Post');
});

it('shows a single published post page with seo meta', function (): void {
    $citizen = Citizen::factory()->create();
    $post = Post::factory()->create([
        'author_type' => Citizen::class,
        'author_id' => $citizen->id,
        'status' => PostStatus::Published,
        'published_at' => now()->subHour(),
        'title' => 'Single Post',
        'body' => '<p>The body content.</p>',
    ]);

    $response = $this->get(route('blog.show', $post));
    $response->assertOk();
    $response->assertSee('Single Post');
    $response->assertSee('og:title');
});
