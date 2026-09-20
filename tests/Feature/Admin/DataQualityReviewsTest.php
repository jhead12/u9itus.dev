<?php

use App\Models\Politician;
use App\Models\PoliticianCleanupReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

function dqAdmin(): User
{
    $user = User::factory()->create(['platform' => 'standalone']);
    $user->assignRole('admin');
    skipOnboarding($user, 'admin');

    return $user;
}

function dqPolitician(string $name, array $over = []): Politician
{
    $p = Politician::factory()->make(array_merge([
        'full_name' => $name, 'state' => 'CA', 'political_office' => 'Mayor', 'governance_level' => 'city',
        'user_id' => null, 'is_active' => true, 'slug' => str($name)->slug().'-'.fake()->unique()->numerify('#####'),
    ], $over));
    $p->saveQuietly();

    return $p;
}

function mergeReview(Politician $keep, Politician $dupe): PoliticianCleanupReview
{
    return PoliticianCleanupReview::enqueue('merge', $keep->id, $dupe->id, [
        'survivor' => ['id' => $keep->id, 'full_name' => $keep->full_name],
        'duplicate' => ['id' => $dupe->id, 'full_name' => $dupe->full_name],
    ], 'Detected by politicians:dedupe');
}

it('approving a merge keeps the review in the history instead of cascading it away', function () {
    $keep = dqPolitician('Jane Doe');
    $dupe = dqPolitician('Jane Doe');
    $review = mergeReview($keep, $dupe);

    $this->actingAs(dqAdmin())->post(route('admin.data-quality.approve', $review))->assertSessionHasNoErrors();

    expect(Politician::whereKey($dupe->id)->exists())->toBeFalse();
    $kept = PoliticianCleanupReview::find($review->id);
    expect($kept)->not->toBeNull()
        ->and($kept->status)->toBe('approved')
        ->and($kept->duplicate_politician_id)->toBeNull()
        ->and($kept->payload['duplicate']['id'])->toBe($dupe->id);
});

it('a bulk action with an id that no longer exists skips it instead of rejecting the whole request', function () {
    $keep = dqPolitician('Jane Doe');
    $good = mergeReview($keep, dqPolitician('Jane Doe'));
    $stale = mergeReview($keep, dqPolitician('Jane Doe'));
    $staleId = $stale->id;
    $stale->delete(); // removed after the page was rendered

    $response = $this->actingAs(dqAdmin())->post(route('admin.data-quality.bulk-action'), [
        'action' => 'approve', 'review_ids' => [$good->id, $staleId],
    ]);

    $response->assertSessionHasNoErrors();
    expect(PoliticianCleanupReview::find($good->id)->status)->toBe('approved')
        ->and(session('success'))->toContain('1')->toContain('skipped');
});

it('a bulk approve of reviews that share one duplicate applies the first and skips the obsolete rest', function () {
    $a = dqPolitician('Jane Doe');
    $b = dqPolitician('Jane Doe');
    $shared = dqPolitician('Jane Doe');
    $first = mergeReview($a, $shared);
    $second = mergeReview($b, $shared);

    $this->actingAs(dqAdmin())->post(route('admin.data-quality.bulk-action'), [
        'action' => 'approve', 'review_ids' => [$first->id, $second->id],
    ])->assertSessionHasNoErrors();

    expect(Politician::whereKey($shared->id)->exists())->toBeFalse()
        ->and(Politician::whereKey([$a->id, $b->id])->count())->toBe(2)
        ->and(PoliticianCleanupReview::find($first->id)->status)->toBe('approved')
        ->and(PoliticianCleanupReview::find($second->id)->status)->toBe('rejected')
        ->and(PoliticianCleanupReview::find($second->id)->reason)->toContain('was merged into')
        ->and(session('success'))->toContain('skipped');
});

it('a merge whose survivor was already removed is marked obsolete rather than erroring', function () {
    $keep = dqPolitician('Jane Doe');
    $review = mergeReview($keep, dqPolitician('Jane Doe'));
    Politician::whereKey($keep->id)->delete(); // e.g. merged away overnight by the FEC-id step

    // SQLite (tests) does not cascade the review away as MySQL would, so the handler must cope.
    if (PoliticianCleanupReview::find($review->id) === null) {
        expect(true)->toBeTrue();

        return;
    }

    $this->actingAs(dqAdmin())->post(route('admin.data-quality.approve', $review))
        ->assertSessionHasErrors('error');

    expect(PoliticianCleanupReview::find($review->id)->status)->toBe('rejected')
        ->and(PoliticianCleanupReview::find($review->id)->reason)->toContain('no longer exists');
});

it('merging a profile away does not re-point a pending deactivate review at the survivor', function () {
    $real = dqPolitician('Greg Abbott', ['political_office' => 'Governor', 'governance_level' => 'state', 'state' => 'TX']);
    $dupe = dqPolitician('Greg Abbott', ['political_office' => 'Governor', 'governance_level' => 'state', 'state' => 'TX']);
    $deactivate = PoliticianCleanupReview::enqueue('deactivate', $dupe->id, null, ['source' => 'flag-suspect-profiles'], 'headline text');
    $merge = mergeReview($real, $dupe);

    $this->actingAs(dqAdmin())->post(route('admin.data-quality.approve', $merge))->assertSessionHasNoErrors();

    $retired = PoliticianCleanupReview::find($deactivate->id);
    expect($retired->status)->toBe('rejected')
        ->and($retired->reason)->toContain('merged into')
        ->and(Politician::find($real->id)->is_active)->toBeTrue(); // the real profile is untouched
});

it('a reversed pair cannot turn into a self-merge that deletes the survivor', function () {
    $a = dqPolitician('Jane Doe');
    $b = dqPolitician('Jane Doe');
    $forward = mergeReview($a, $b);
    $reversed = mergeReview($b, $a);

    $this->actingAs(dqAdmin())->post(route('admin.data-quality.bulk-action'), [
        'action' => 'approve', 'review_ids' => [$forward->id, $reversed->id],
    ])->assertSessionHasNoErrors();

    expect(Politician::whereKey($a->id)->exists())->toBeTrue()
        ->and(Politician::whereKey($b->id)->exists())->toBeFalse()
        ->and(PoliticianCleanupReview::find($reversed->id)->status)->toBe('rejected');
});

it('the index renders and filters by review type', function () {
    $admin = dqAdmin();
    mergeReview(dqPolitician('Jane Doe'), dqPolitician('Jane Doe'));
    PoliticianCleanupReview::enqueue('deactivate', dqPolitician('Ghost Headline')->id, null, [], 'Created from a news headline');

    $this->actingAs($admin)->get(route('admin.data-quality.index'))
        ->assertOk()
        ->assertSee('All Types (2)')
        ->assertSee('Merge Duplicate (1)')
        ->assertSee('Deactivate (1)');

    $this->actingAs($admin)->get(route('admin.data-quality.index', ['type' => 'deactivate']))
        ->assertOk()
        ->assertSee('Ghost Headline')
        ->assertDontSee('Jane Doe');

    $this->actingAs($admin)->get(route('admin.data-quality.index', ['type' => 'bogus', 'status' => 'approved']))
        ->assertOk();
});
