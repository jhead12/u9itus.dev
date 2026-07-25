<?php

use App\Models\Politician;
use App\Models\PoliticianTopic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    $this->topic = PoliticianTopic::create([
        'name' => 'Healthcare',
        'slug' => 'healthcare',
        'is_active' => true,
        'sort_order' => 1,
        'badge_color' => '#ef4444',
    ]);
});

it('filters the directory by a topic slug via the badge relationship, not just bio text', function () {
    // Badged for Healthcare, but the word "healthcare" appears nowhere in bio.
    $badged = Politician::factory()->create([
        'full_name' => 'Taylor Smith',
        'slug' => 'taylor-smith',
        'bio' => 'Lifelong baseball fan and jazz enthusiast.',
        'page_published' => true,
        'is_active' => true,
    ]);
    $badged->addBadge($this->topic->id, 'inferred_discourse', ['is_public' => true]);

    // Not badged; bio mentions transport only.
    Politician::factory()->create([
        'full_name' => 'Robin Transport',
        'slug' => 'robin-transport',
        'bio' => 'Focused on buses and mobility.',
        'page_published' => true,
        'is_active' => true,
    ]);

    $response = $this->get(route('politicians.directory', ['topic' => 'healthcare']));

    $response->assertOk();
    $response->assertSee('Taylor Smith');
    $response->assertDontSee('Robin Transport');

    // The one-click topic chip row renders the Healthcare topic.
    $response->assertSee('Healthcare', false);
});

it('still matches free-text topics that are not a catalog slug', function () {
    Politician::factory()->create([
        'full_name' => 'Casey Zoning',
        'slug' => 'casey-zoning',
        'bio' => 'Advocate for neighborhood zoning reform.',
        'page_published' => true,
        'is_active' => true,
    ]);

    // 'zoning' is not a politician_topics slug → falls back to bio LIKE match.
    $response = $this->get(route('politicians.directory', ['topic' => 'zoning']));

    $response->assertOk();
    $response->assertSee('Casey Zoning');
});
