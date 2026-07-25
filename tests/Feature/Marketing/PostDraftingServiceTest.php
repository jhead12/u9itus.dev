<?php

use App\Enums\MarketingDraftStatus;
use App\Enums\MarketingSourceType;
use App\Enums\PostStatus;
use App\Models\CandidateNewsArticle;
use App\Models\MarketingPostDraft;
use App\Models\Politician;
use App\Models\PoliticianViralMoment;
use App\Models\Post;
use App\Services\Marketing\PostDraftingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function enableDraftingAgent(): void
{
    config([
        'u9itus.marketing.enabled'                    => true,
        'u9itus.marketing.drafting.enabled'           => true,
        'u9itus.marketing.drafting.moment_score_threshold' => 0.0,
        'services.anthropic.api_key'                  => 'test-key',
        'services.anthropic.model'                    => 'claude-haiku-4-5',
    ]);
}

function fakeClaudeReply(string $json): void
{
    Http::fake([
        'api.anthropic.com/v1/messages' => Http::response([
            'content' => [['type' => 'text', 'text' => $json]],
        ], 200),
    ]);
}

function validClaudeJson(string $title = 'Senator speaks on the floor', string $body = '<p>Senator called for action on the bill.</p><p>The clip drew wide attention.</p>'): string
{
    return json_encode([
        'title'            => $title,
        'subtitle'         => null,
        'excerpt'          => 'A recap of the senator\'s recent remarks.',
        'body'             => $body,
        'meta_description' => null,
    ]);
}

function draftingService(): PostDraftingService
{
    return new PostDraftingService();
}

test('drafts a PendingApproval Post from a viral moment', function () {
    enableDraftingAgent();
    fakeClaudeReply(validClaudeJson());

    $politician = Politician::factory()->create([
        'full_name'         => 'Jane Q. Public',
        'profile_photo_url' => 'https://images.test/politician.jpg',
    ]);
    PoliticianViralMoment::factory()->create([
        'politician_id'  => $politician->id,
        'source'         => 'cspan',
        'moment_score'   => 8.2,
        'thumbnail_url'  => 'https://images.test/thumb.jpg',
        'published_at'   => now()->subDays(2),
        'url'            => 'https://cspan.test/clip/123',
        'title'          => 'Senator speaks on the floor',
    ]);

    $result = draftingService()->draftForPolitician($politician->id);

    expect($result['status'])->toBe('posted')
        ->and($result['source_type'])->toBe(MarketingSourceType::ViralMoment->value)
        ->and($result['post_id'])->not->toBeNull();

    $post = Post::where('author_id', $politician->id)->first();
    expect($post)->not->toBeNull()
        ->and($post->status)->toBe(PostStatus::PendingApproval)
        ->and($post->author_type)->toBe(Politician::class)
        ->and($post->published_at)->toBeNull()
        ->and($post->body)->toContain('Auto-drafted by u9')
        ->and($post->body)->toContain('C-SPAN')
        ->and($post->featured_image_url)->toBe('https://images.test/thumb.jpg');

    $draft = MarketingPostDraft::where('politician_id', $politician->id)->first();
    expect($draft)->not->toBeNull()
        ->and($draft->status)->toBe(MarketingDraftStatus::Posted)
        ->and($draft->post_id)->toBe($post->id)
        ->and($draft->source_type)->toBe(MarketingSourceType::ViralMoment);
});

test('re-running for the same source is a dedup no-op', function () {
    enableDraftingAgent();
    fakeClaudeReply(validClaudeJson());

    $politician = Politician::factory()->create();
    PoliticianViralMoment::factory()->create([
        'politician_id' => $politician->id,
        'moment_score'  => 5.0,
        'published_at'  => now()->subDays(1),
    ]);

    $service = draftingService();
    $first = $service->draftForPolitician($politician->id);
    $second = $service->draftForPolitician($politician->id);

    // The already-posted source is filtered out at selection time, so the
    // second run has nothing new to draft (the `duplicate` path only fires
    // on a concurrent race, which the job's ShouldBeUnique lock prevents).
    expect($first['status'])->toBe('posted')
        ->and($second['status'])->toBe('skipped')
        ->and($second['error'])->toBe('no_fresh_source');

    expect(Post::where('author_id', $politician->id)->count())->toBe(1);
    expect(MarketingPostDraft::where('politician_id', $politician->id)->count())->toBe(1);
});

test('falls back to verified news when no eligible viral moment exists', function () {
    enableDraftingAgent();
    fakeClaudeReply(validClaudeJson('Council member announces initiative', '<p>The council member proposed a new measure.</p>'));

    $politician = Politician::factory()->create();
    CandidateNewsArticle::factory()->create([
        'politician_id'       => $politician->id,
        'verification_status' => 'verified',
        'published_at'        => now()->subDays(1),
        'source_name'         => 'The Daily Bugle',
        'image_url'           => 'https://images.test/news.jpg',
    ]);

    $result = draftingService()->draftForPolitician($politician->id);

    expect($result['status'])->toBe('posted')
        ->and($result['source_type'])->toBe(MarketingSourceType::News->value);

    $post = Post::where('author_id', $politician->id)->first();
    expect($post->featured_image_url)->toBe('https://images.test/news.jpg')
        ->and($post->body)->toContain('The Daily Bugle');
});

test('skips when the Anthropic API key is missing', function () {
    config([
        'u9itus.marketing.enabled'          => true,
        'u9itus.marketing.drafting.enabled' => true,
        'services.anthropic.api_key'        => null,
    ]);

    $politician = Politician::factory()->create();
    PoliticianViralMoment::factory()->create([
        'politician_id' => $politician->id,
        'moment_score'  => 5.0,
    ]);

    $result = draftingService()->draftForPolitician($politician->id);

    expect($result['status'])->toBe('skipped')
        ->and($result['error'])->toBe('not_configured');
    expect(Post::where('author_id', $politician->id)->count())->toBe(0);
    expect(MarketingPostDraft::count())->toBe(0);
});

test('marks a Failed draft row when Claude returns invalid JSON', function () {
    enableDraftingAgent();
    Http::fake([
        'api.anthropic.com/v1/messages' => Http::response([
            'content' => [['type' => 'text', 'text' => 'not json at all']],
        ], 200),
    ]);

    $politician = Politician::factory()->create();
    PoliticianViralMoment::factory()->create([
        'politician_id' => $politician->id,
        'moment_score'  => 5.0,
    ]);

    $result = draftingService()->draftForPolitician($politician->id);

    expect($result['status'])->toBe('failed')
        ->and($result['error'])->toBe('copy_generation_failed');
    expect(Post::where('author_id', $politician->id)->count())->toBe(0);

    $draft = MarketingPostDraft::where('politician_id', $politician->id)->first();
    expect($draft)->not->toBeNull()
        ->and($draft->status)->toBe(MarketingDraftStatus::Failed)
        ->and($draft->post_id)->toBeNull();
});

test('featured image falls back to the politician profile photo when the source has none', function () {
    enableDraftingAgent();
    fakeClaudeReply(validClaudeJson());

    $politician = Politician::factory()->create([
        'profile_photo_url' => 'https://images.test/profile.jpg',
    ]);
    PoliticianViralMoment::factory()->create([
        'politician_id'  => $politician->id,
        'moment_score'   => 5.0,
        'thumbnail_url'  => null,
        'published_at'   => now()->subDays(1),
    ]);

    draftingService()->draftForPolitician($politician->id);

    $post = Post::where('author_id', $politician->id)->first();
    expect($post->featured_image_url)->toBe('https://images.test/profile.jpg');
});

test('marketing disabled short-circuits the agent', function () {
    config([
        'u9itus.marketing.enabled'          => false,
        'u9itus.marketing.drafting.enabled' => true,
        'services.anthropic.api_key'        => 'test-key',
    ]);

    $politician = Politician::factory()->create();
    PoliticianViralMoment::factory()->create([
        'politician_id' => $politician->id,
        'moment_score'  => 5.0,
    ]);

    $result = draftingService()->draftForPolitician($politician->id);

    expect($result['status'])->toBe('skipped')
        ->and($result['error'])->toBe('not_configured');
    expect(Post::count())->toBe(0);
});