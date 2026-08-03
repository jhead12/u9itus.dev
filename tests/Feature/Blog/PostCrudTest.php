<?php

use App\Enums\PostStatus;
use App\Models\Citizen;
use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    if (class_exists(Role::class)) {
        Role::firstOrCreate(['name' => 'citizen', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'politician', 'guard_name' => 'web']);
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

it('stores an uploaded featured image and ignores the raw url field', function (): void {
    Storage::fake('public');
    $user = makeCitizenUser();

    $response = $this->actingAs($user)->post(route('citizen.posts.store'), [
        'title' => 'Post with a featured image',
        'featured_image_file' => UploadedFile::fake()->image('featured.jpg', 200, 200),
        'featured_image_url' => 'https://example.com/should-be-overridden.jpg',
    ]);

    $response->assertRedirect();

    $post = Post::query()->where('title', 'Post with a featured image')->firstOrFail();
    expect($post->featured_image_url)->not->toBe('https://example.com/should-be-overridden.jpg');
    expect($post->featured_image_url)->toContain('/storage/');
});

it('flashes an error and saves without an image when the disk write fails', function (): void {
    $user = makeCitizenUser();

    $diskMock = Mockery::mock(Filesystem::class);
    $diskMock->shouldReceive('put')->andReturn(false);
    Storage::shouldReceive('disk')->with('public')->andReturn($diskMock);

    $response = $this->actingAs($user)->post(route('citizen.posts.store'), [
        'title' => 'Image upload failure test',
        'featured_image_file' => UploadedFile::fake()->image('featured.jpg', 200, 200),
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');

    $post = Post::query()->where('title', 'Image upload failure test')->firstOrFail();
    expect($post->featured_image_url)->toBeNull();
});

it('deletes the previous uploaded featured image when replaced on update', function (): void {
    Storage::fake('public');
    $user = makeCitizenUser();

    $create = $this->actingAs($user)->post(route('citizen.posts.store'), [
        'title' => 'Replaceable image post',
        'featured_image_file' => UploadedFile::fake()->image('first.jpg', 200, 200),
    ]);
    $create->assertRedirect();

    $post = Post::query()->where('title', 'Replaceable image post')->firstOrFail();
    $originalUrl = $post->featured_image_url;
    $originalPath = ltrim(parse_url($originalUrl, PHP_URL_PATH), '/');
    $originalPath = str_starts_with($originalPath, 'storage/') ? substr($originalPath, strlen('storage/')) : $originalPath;
    Storage::disk('public')->assertExists($originalPath);

    $this->actingAs($user)->put(route('citizen.posts.update', $post), [
        'title' => $post->title,
        'featured_image_file' => UploadedFile::fake()->image('second.jpg', 200, 200),
    ])->assertRedirect();

    $post->refresh();
    expect($post->featured_image_url)->not->toBe($originalUrl);
    Storage::disk('public')->assertMissing($originalPath);
});

it('lets an author upload an inline image for the post body editor', function (): void {
    Storage::fake('public');
    $user = makeCitizenUser();

    $response = $this->actingAs($user)->post(route('citizen.posts.images'), [
        'image' => UploadedFile::fake()->image('inline.png', 300, 200),
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['url']);
    expect($response->json('url'))->toContain('/storage/');
});

it('rejects a non-image file from the inline image upload endpoint', function (): void {
    Storage::fake('public');
    $user = makeCitizenUser();

    $response = $this->actingAs($user)->post(route('citizen.posts.images'), [
        'image' => UploadedFile::fake()->create('not-an-image.txt', 10, 'text/plain'),
    ]);

    $response->assertSessionHasErrors('image');
});

it('rejects the inline image upload endpoint for guests', function (): void {
    Storage::fake('public');

    $response = $this->post(route('citizen.posts.images'), [
        'image' => UploadedFile::fake()->image('inline.png', 300, 200),
    ]);

    $response->assertRedirect(route('login'));
});

it('background-saves without a redirect and reports fresh slug-based urls after a title change', function (): void {
    $user = makeCitizenUser();
    $citizen = $user->citizen;

    $post = Post::factory()->create([
        'author_type' => Citizen::class,
        'author_id' => $citizen->id,
        'status' => PostStatus::Draft,
        'title' => 'Original Title',
        'slug' => 'original-title',
    ]);

    // Changing the title regenerates the slug (Post::boot()), which is exactly
    // what makes every slug-keyed URL on the edit page stale mid-session.
    $response = $this->actingAs($user)
        ->post(route('citizen.posts.update', $post), [
            '_method' => 'PUT',
            'title' => 'A Brand New Title',
            '_background_save' => '1',
        ]);

    $response->assertOk();
    $response->assertJson(['saved' => true]);
    $response->assertSessionMissing('success');
    $response->assertSessionMissing('error');

    $post->refresh();
    expect($post->title)->toBe('A Brand New Title');
    expect($post->slug)->not->toBe('original-title');

    $json = $response->json();
    expect($json['updateUrl'])->toContain($post->slug);
    expect($json['publishUrl'])->toContain($post->slug);
    expect($json['publicUrl'])->toContain($post->slug);
    expect($json['updateUrl'])->not->toContain('original-title');
});
