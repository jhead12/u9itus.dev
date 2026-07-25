<?php

use App\Console\Commands\SendMarketingDraftsDigest;
use App\Enums\MarketingDraftStatus;
use App\Enums\MarketingSourceType;
use App\Enums\PostStatus;
use App\Mail\MarketingDraftsDigestMail;
use App\Models\MarketingPostDraft;
use App\Models\Politician;
use App\Models\Post;
use App\Models\User;
use App\Services\PlatformSettingsService;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function enableDigest(): void
{
    config([
        'u9itus.marketing.enabled' => true,
        'u9itus.marketing.drafting.enabled' => true,
        'u9itus.marketing.drafting.digest_enabled' => true,
        'u9itus.marketing.drafting.digest_recipients' => null,
    ]);
}

function clearDigestWatermark(): void
{
    PlatformSettingsService::clearAllCache();
}

function setDigestWatermark(Carbon $at): void
{
    PlatformSettingsService::set(
        SendMarketingDraftsDigest::LAST_SENT_KEY,
        $at->toDateTimeString(),
        ['category' => 'marketing'],
    );
    PlatformSettingsService::clearCache(SendMarketingDraftsDigest::LAST_SENT_KEY);
}

function digestWatermark(): mixed
{
    return PlatformSettingsService::get(SendMarketingDraftsDigest::LAST_SENT_KEY);
}

function adminUser(string $email = 'admin@u9itus.test'): User
{
    return User::factory()->create([
        'user_type' => 'admin',
        'email' => $email,
    ]);
}

function createPostedDraft(Politician $politician, ?Carbon $createdAt = null, int $sourceId = 1): MarketingPostDraft
{
    $post = Post::factory()->create([
        'author_type' => Politician::class,
        'author_id' => $politician->id,
        'status' => PostStatus::PendingApproval,
        'published_at' => null,
    ]);

    $draft = MarketingPostDraft::create([
        'politician_id' => $politician->id,
        'post_id' => $post->id,
        'source_type' => MarketingSourceType::ViralMoment->value,
        'source_id' => $sourceId,
        'source_hash' => hash('sha256', $politician->id.'|viral_moment|'.$sourceId),
        'source_url' => 'https://cspan.test/clip/'.$sourceId,
        'source_title' => 'Floor clip '.$sourceId,
        'generated_title' => 'Senator speaks on the floor',
        'generated_excerpt' => 'A clip drew wide attention.',
        'generated_body' => '<p>Senator called for action.</p>',
        'featured_image_url' => 'https://images.test/x.jpg',
        'status' => MarketingDraftStatus::Posted->value,
        'generated_at' => now(),
    ]);

    if ($createdAt) {
        $draft->forceFill(['created_at' => $createdAt])->save();
    }

    return $draft->fresh();
}

test('sends a digest to admin users when new drafts exist and advances the watermark', function () {
    Mail::fake();
    enableDigest();
    clearDigestWatermark();

    $admin = adminUser('admin@u9itus.test');
    $politician = Politician::factory()->create([
        'full_name' => 'Jane Senator',
        'slug' => 'jane-senator',
        'page_published' => true,
    ]);

    createPostedDraft($politician, now()->subHour());

    expect(Artisan::call('marketing:drafts-digest'))->toBe(Command::SUCCESS);

    Mail::assertSent(MarketingDraftsDigestMail::class, 1);
    Mail::assertSent(MarketingDraftsDigestMail::class, fn ($mail) => $mail->hasTo($admin->email));

    // Watermark advanced to "now" so the next run excludes this draft.
    expect(digestWatermark())->not->toBeNull();
    $watermark = Carbon::parse(digestWatermark());
    expect($watermark->isSameDay(now()) && $watermark->diffInSeconds(now()) < 60)->toBeTrue();
});

test('sends nothing and still advances the watermark when there are no new drafts', function () {
    Mail::fake();
    enableDigest();
    clearDigestWatermark();

    setDigestWatermark(now()->subDay());
    $previous = digestWatermark();

    adminUser('admin@u9itus.test'); // admin present, but no drafts

    expect(Artisan::call('marketing:drafts-digest'))->toBe(Command::SUCCESS);

    Mail::assertNothingSent();
    expect(digestWatermark())->not->toBeNull();
    expect(Carbon::parse(digestWatermark())->gt(Carbon::parse($previous)))->toBeTrue();
});

test('only drafts created since the last watermark are included', function () {
    Mail::fake();
    enableDigest();
    clearDigestWatermark();

    // Watermark set 1 hour ago: an older draft is excluded, a fresh one included.
    setDigestWatermark(now()->subHour());

    adminUser('admin@u9itus.test');
    $politician = Politician::factory()->create([
        'full_name' => 'Old Then New',
        'page_published' => true,
    ]);

    createPostedDraft($politician, now()->subHours(5), $sourceId = 1); // before window
    createPostedDraft($politician, now()->subMinutes(10), $sourceId = 2); // inside window

    expect(Artisan::call('marketing:drafts-digest'))->toBe(Command::SUCCESS);

    // Exactly one mail, carrying the single in-window draft.
    Mail::assertSent(MarketingDraftsDigestMail::class, 1);
    Mail::assertSent(MarketingDraftsDigestMail::class, function (MarketingDraftsDigestMail $mail) {
        return $mail->drafts->count() === 1;
    });
});

test('dry run lists drafts and recipients but sends no email and does not advance the watermark', function () {
    Mail::fake();
    enableDigest();
    clearDigestWatermark();

    setDigestWatermark(now()->subDay());
    $previous = digestWatermark();

    adminUser('admin@u9itus.test');
    $politician = Politician::factory()->create([
        'full_name' => 'Dry Run Pol',
        'page_published' => true,
    ]);
    createPostedDraft($politician, now()->subMinutes(5));

    expect(Artisan::call('marketing:drafts-digest', ['--dry-run' => true]))->toBe(Command::SUCCESS);

    Mail::assertNothingSent();
    // Watermark unchanged.
    expect(digestWatermark())->toBe($previous);
});

test('uses the env recipient override when set and ignores admin users', function () {
    Mail::fake();
    enableDigest();
    clearDigestWatermark();

    config(['u9itus.marketing.drafting.digest_recipients' => 'a@x.test,b@y.test']);

    adminUser('ignored@u9itus.test'); // should not receive anything

    $politician = Politician::factory()->create([
        'full_name' => 'Env Recipient Pol',
        'page_published' => true,
    ]);
    createPostedDraft($politician, now()->subMinutes(5));

    expect(Artisan::call('marketing:drafts-digest'))->toBe(Command::SUCCESS);

    Mail::assertSent(MarketingDraftsDigestMail::class, 2);
    Mail::assertSent(MarketingDraftsDigestMail::class, fn ($mail) => $mail->hasTo('a@x.test'));
    Mail::assertSent(MarketingDraftsDigestMail::class, fn ($mail) => $mail->hasTo('b@y.test'));
});

test('does nothing when the digest is disabled', function () {
    Mail::fake();
    config([
        'u9itus.marketing.enabled' => true,
        'u9itus.marketing.drafting.enabled' => true,
        'u9itus.marketing.drafting.digest_enabled' => false,
    ]);
    clearDigestWatermark();

    adminUser('admin@u9itus.test');
    $politician = Politician::factory()->create(['page_published' => true]);
    createPostedDraft($politician, now()->subMinutes(5));

    expect(Artisan::call('marketing:drafts-digest'))->toBe(Command::SUCCESS);

    Mail::assertNothingSent();
    expect(digestWatermark())->toBeNull();
});
