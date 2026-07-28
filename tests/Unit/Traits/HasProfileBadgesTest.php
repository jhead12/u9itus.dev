<?php

use App\Models\Politician;
use App\Models\PoliticianTopic;
use App\Models\ProfileBadge;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Helpers ──────────────────────────────────────────────────────────────────

function makeTopic(array $attrs = []): PoliticianTopic
{
    return PoliticianTopic::create(array_merge([
        'name'  => 'Healthcare',
        'slug'  => 'healthcare',
        'icon'  => '🏥',
        'sort_order' => 0,
        'is_active'  => true,
    ], $attrs));
}

function badgeVoter(array $attrs = []): Voter
{
    return Voter::factory()->create(array_merge([
        'is_verified' => true,
        'is_active'   => true,
    ], $attrs));
}

function badgePolitician(array $attrs = []): Politician
{
    return Politician::factory()->create($attrs);
}

// ── addBadge ─────────────────────────────────────────────────────────────────

test('voter can self-declare a badge', function () {
    $voter = badgeVoter();
    $topic = makeTopic();

    $badge = $voter->addBadge($topic->id);

    expect($badge)->toBeInstanceOf(ProfileBadge::class)
        ->and($badge->badge_type)->toBe('self_declared')
        ->and($badge->topic_id)->toBe($topic->id);

    $this->assertDatabaseHas('profile_badges', [
        'badgeable_type' => Voter::class,
        'badgeable_id'   => $voter->id,
        'topic_id'       => $topic->id,
        'badge_type'     => 'self_declared',
    ]);
});

test('politician can self-declare a badge', function () {
    $politician = badgePolitician();
    $topic      = makeTopic(['name' => 'Climate Action', 'slug' => 'climate-action']);

    $badge = $politician->addBadge($topic->id);

    expect($badge->badgeable_type)->toBe(Politician::class)
        ->and($badge->badgeable_id)->toBe($politician->id);
});

test('addBadge is idempotent — duplicate call does not create second row', function () {
    $voter = badgeVoter();
    $topic = makeTopic();

    $voter->addBadge($topic->id);
    $voter->addBadge($topic->id); // second call

    expect($voter->badges()->count())->toBe(1);
});

// ── removeSelfDeclaredBadge ───────────────────────────────────────────────────

test('voter can remove a self-declared badge', function () {
    $voter = badgeVoter();
    $topic = makeTopic();

    $voter->addBadge($topic->id, 'self_declared');
    $result = $voter->removeSelfDeclaredBadge($topic->id);

    expect($result)->toBeTrue();
    $this->assertDatabaseMissing('profile_badges', [
        'badgeable_id' => $voter->id,
        'topic_id'     => $topic->id,
    ]);
});

test('removing a non-existent badge returns false', function () {
    $voter = badgeVoter();
    $topic = makeTopic();

    $result = $voter->removeSelfDeclaredBadge($topic->id);

    expect($result)->toBeFalse();
});

test('earned badge cannot be removed via removeSelfDeclaredBadge', function () {
    $voter = badgeVoter();
    $topic = makeTopic();

    $voter->grantEarnedBadge($topic->id, 'earned_views', 5);
    $voter->removeSelfDeclaredBadge($topic->id); // should not delete earned badge

    $this->assertDatabaseHas('profile_badges', [
        'badgeable_id' => $voter->id,
        'topic_id'     => $topic->id,
        'badge_type'   => 'earned_views',
    ]);
});

// ── grantEarnedBadge ─────────────────────────────────────────────────────────

test('system can grant an earned_views badge', function () {
    $voter = badgeVoter();
    $topic = makeTopic();

    $badge = $voter->grantEarnedBadge($topic->id, 'earned_views', 5);

    expect($badge->badge_type)->toBe('earned_views')
        ->and($badge->earned_threshold)->toBe(5)
        ->and($badge->earned_at)->not->toBeNull()
        ->and($badge->is_public)->toBeTrue();
});

test('grantEarnedBadge is idempotent', function () {
    $voter = badgeVoter();
    $topic = makeTopic();

    $voter->grantEarnedBadge($topic->id, 'earned_views', 5);
    $voter->grantEarnedBadge($topic->id, 'earned_views', 10); // second call, different threshold

    // Should still only have one badge
    expect($voter->badges()->count())->toBe(1);
});

// ── hasBadgeForTopic ─────────────────────────────────────────────────────────

test('hasBadgeForTopic returns true when badge exists', function () {
    $voter = badgeVoter();
    $topic = makeTopic();

    $voter->addBadge($topic->id);

    expect($voter->hasBadgeForTopic($topic->id))->toBeTrue();
});

test('hasBadgeForTopic returns false when badge does not exist', function () {
    $voter = badgeVoter();
    $topic = makeTopic();

    expect($voter->hasBadgeForTopic($topic->id))->toBeFalse();
});

// ── publicBadges ─────────────────────────────────────────────────────────────

test('publicBadges only returns is_public badges', function () {
    $voter       = badgeVoter();
    $publicTopic = makeTopic(['name' => 'Public Topic', 'slug' => 'public']);
    $privateTopic= makeTopic(['name' => 'Private Topic', 'slug' => 'private']);

    $voter->addBadge($publicTopic->id, 'self_declared', ['is_public' => true]);
    $voter->badges()->create([
        'topic_id'   => $privateTopic->id,
        'badge_type' => 'self_declared',
        'is_public'  => false,
        'earned_at'  => now(),
    ]);

    expect($voter->publicBadges()->count())->toBe(1)
        ->and($voter->publicBadges()->first()->topic_id)->toBe($publicTopic->id);
});

// ── addBadge default visibility ────────────────────────────────────────────────

test('addBadge defaults new self-declared badges to private', function () {
    $voter = badgeVoter();
    $topic = makeTopic();

    $badge = $voter->addBadge($topic->id);

    expect($badge->is_public)->toBeFalse();
});

test('addBadge respects an explicit is_public override', function () {
    $voter = badgeVoter();
    $topic = makeTopic();

    $badge = $voter->addBadge($topic->id, 'self_declared', ['is_public' => true]);

    expect($badge->is_public)->toBeTrue();
});

// ── setBadgeVisibility ───────────────────────────────────────────────────────

test('voter can flip a self-declared badge to public', function () {
    $voter = badgeVoter();
    $topic = makeTopic();
    $voter->addBadge($topic->id); // private by default

    $result = $voter->setBadgeVisibility($topic->id, true);

    expect($result)->toBeTrue();
    $this->assertDatabaseHas('profile_badges', [
        'badgeable_id' => $voter->id,
        'topic_id'     => $topic->id,
        'is_public'    => true,
    ]);
});

test('voter can flip a self-declared badge back to private', function () {
    $voter = badgeVoter();
    $topic = makeTopic();
    $voter->addBadge($topic->id, 'self_declared', ['is_public' => true]);

    $result = $voter->setBadgeVisibility($topic->id, false);

    expect($result)->toBeTrue();
    $this->assertDatabaseHas('profile_badges', [
        'badgeable_id' => $voter->id,
        'topic_id'     => $topic->id,
        'is_public'    => false,
    ]);
});

test('setBadgeVisibility cannot change an earned badge', function () {
    $voter = badgeVoter();
    $topic = makeTopic();
    $voter->grantEarnedBadge($topic->id, 'earned_views', 5);

    $result = $voter->setBadgeVisibility($topic->id, false);

    expect($result)->toBeFalse();
    $this->assertDatabaseHas('profile_badges', [
        'badgeable_id' => $voter->id,
        'topic_id'     => $topic->id,
        'badge_type'   => 'earned_views',
        'is_public'    => true,
    ]);
});

test('setBadgeVisibility returns false for a non-existent badge', function () {
    $voter = badgeVoter();
    $topic = makeTopic();

    expect($voter->setBadgeVisibility($topic->id, true))->toBeFalse();
});

test('flipping a self-declared badge private removes it from publicBadges', function () {
    $voter = badgeVoter();
    $topic = makeTopic();
    $voter->addBadge($topic->id, 'self_declared', ['is_public' => true]);

    expect($voter->publicBadges()->count())->toBe(1);

    $voter->setBadgeVisibility($topic->id, false);

    expect($voter->publicBadges()->count())->toBe(0);
});
