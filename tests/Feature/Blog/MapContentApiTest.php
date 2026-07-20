<?php

use App\Enums\PostStatus;
use App\Models\Citizen;
use App\Models\Post;
use App\Models\PoliticianTopic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'citizen', 'guard_name' => 'web']);
});

function makePublishedGeoPost(array $overrides = []): Post
{
    $user = User::factory()->create();
    $user->assignRole('citizen');
    $citizen = Citizen::factory()->create(['user_id' => $user->id]);

    return Post::factory()->create(array_merge([
        'author_type' => Citizen::class,
        'author_id'   => $citizen->id,
        'status'      => PostStatus::Published,
        'published_at'=> now()->subDay(),
        'latitude'    => 34.0522,
        'longitude'   => -118.2437,
        'location_name' => 'Los Angeles, CA',
    ], $overrides));
}

it('returns geo-tagged published posts within a viewport', function (): void {
    $post = makePublishedGeoPost(['title' => 'Map Post']);

    $response = $this->getJson('/api/v1/map/content?south=33&west=-119&north=35&east=-117');

    $response->assertOk();
    $response->assertJsonCount(1, 'posts');
    $response->assertJsonPath('posts.0.title', 'Map Post');
    $response->assertJsonPath('posts.0.type', 'post');
    $response->assertJsonPath('posts.0.url', route('blog.show', $post));
    $response->assertJsonPath('posts.0.lat', 34.0522);
    $response->assertJsonPath('posts.0.lng', -118.2437);
});

it('excludes posts outside the viewport', function (): void {
    makePublishedGeoPost(['title' => 'LA Post', 'latitude' => 34.0522, 'longitude' => -118.2437]);

    $response = $this->getJson('/api/v1/map/content?south=40&west=-74&north=41&east=-73');

    $response->assertOk();
    $response->assertJsonCount(0, 'posts');
});

it('excludes draft and non-geotagged posts', function (): void {
    makePublishedGeoPost(['title' => 'No Location', 'latitude' => null, 'longitude' => null]);
    makePublishedGeoPost(['title' => 'Draft Post', 'status' => PostStatus::Draft]);

    $response = $this->getJson('/api/v1/map/content?south=33&west=-119&north=35&east=-117');

    $response->assertOk();
    $response->assertJsonCount(0, 'posts');
});

it('filters posts by topic slug', function (): void {
    $topic = PoliticianTopic::create([
        'name' => 'Climate Action',
        'slug' => 'climate-action',
        'sort_order' => 0,
        'is_active' => true,
        'voter_selectable' => true,
        'auto_earned_only' => false,
    ]);

    $matched = makePublishedGeoPost(['title' => 'Climate Post']);
    $matched->topics()->attach($topic);

    makePublishedGeoPost(['title' => 'Other Post']);

    $response = $this->getJson('/api/v1/map/content?south=33&west=-119&north=35&east=-117&topic=climate-action');

    $response->assertOk();
    $response->assertJsonCount(1, 'posts');
    $response->assertJsonPath('posts.0.title', 'Climate Post');
});

it('validates viewport parameters', function (): void {
    $response = $this->getJson('/api/v1/map/content?south=invalid&west=-119&north=35&east=-117');

    $response->assertStatus(422);
});

it('respects the limit parameter and caps at 50', function (): void {
    foreach (range(1, 3) as $i) {
        makePublishedGeoPost([
            'title' => "Post {$i}",
            'latitude' => 34.0522 + ($i * 0.001),
            'longitude' => -118.2437 + ($i * 0.001),
        ]);
    }

    $response = $this->getJson('/api/v1/map/content?south=33&west=-119&north=35&east=-117&limit=2');

    $response->assertOk();
    $response->assertJsonCount(2, 'posts');
});
