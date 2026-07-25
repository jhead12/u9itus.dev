<?php

use App\Jobs\DraftMarketingPost;
use App\Models\Politician;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function enableCommand(): void
{
    config([
        'u9itus.marketing.enabled'          => true,
        'u9itus.marketing.drafting.enabled' => true,
    ]);
}

function publishedPolitician(array $overrides = []): Politician
{
    return Politician::factory()->create(array_merge([
        'page_published' => true,
        'is_active'      => true,
        'full_name'      => 'Test Politician',
    ], $overrides));
}

test('the command dispatches one draft job per eligible politician', function () {
    Queue::fake();
    enableCommand();

    publishedPolitician();
    publishedPolitician();
    publishedPolitician();
    // A page-unpublished politician is excluded from the query.
    Politician::factory()->create(['page_published' => false, 'is_active' => true]);

    $exit = Artisan::call('marketing:draft-posts', ['--limit' => 20]);

    expect($exit)->toBe(0);
    Queue::assertPushed(DraftMarketingPost::class, 3);
});

test('the command is a no-op when drafting is disabled', function () {
    Queue::fake();
    config([
        'u9itus.marketing.enabled'          => true,
        'u9itus.marketing.drafting.enabled' => false,
    ]);

    publishedPolitician();

    $exit = Artisan::call('marketing:draft-posts', ['--limit' => 20]);

    expect($exit)->toBe(0);
    Queue::assertNotPushed(DraftMarketingPost::class);
    expect(Artisan::output())->toContain('disabled');
});

test('the --politician option targets a single politician by id', function () {
    Queue::fake();
    enableCommand();

    $target = publishedPolitician();
    publishedPolitician();

    $exit = Artisan::call('marketing:draft-posts', ['--politician' => $target->id]);

    expect($exit)->toBe(0);
    Queue::assertPushed(DraftMarketingPost::class, 1);
    Queue::assertPushed(DraftMarketingPost::class, fn (DraftMarketingPost $job) => $job->politicianId === $target->id);
});

test('the --source option is passed through to the dispatched job', function () {
    Queue::fake();
    enableCommand();

    $p = publishedPolitician();

    Artisan::call('marketing:draft-posts', ['--source' => 'news']);

    Queue::assertPushed(DraftMarketingPost::class, fn (DraftMarketingPost $job) => $job->sourceFilter === 'news');
});

test('an invalid --source value fails the command', function () {
    Queue::fake();
    enableCommand();

    publishedPolitician();

    $exit = Artisan::call('marketing:draft-posts', ['--source' => 'bogus']);

    expect($exit)->toBe(1);
    Queue::assertNotPushed(DraftMarketingPost::class);
});