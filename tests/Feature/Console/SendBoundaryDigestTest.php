<?php

use App\Mail\BoundaryDigestMail;
use App\Models\CandidateNewsArticle;
use App\Models\Politician;
use App\Models\PoliticianEndorsement;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function digestVoterWithUser(?string $lastSentAgo = null): Voter
{
    $user = User::factory()->create(['platform' => 'standalone']);
    $user->assignRole('voter');
    $user->notificationPreference()->create(['email_boundary_digest' => true]);

    return Voter::factory()->create([
        'user_id' => $user->id,
        'last_digest_sent_at' => $lastSentAgo ? now()->sub($lastSentAgo) : null,
    ]);
}

function digestBoundaryFor(Voter $voter, string $state = 'CA', string $district = '12'): Politician
{
    $politician = Politician::factory()->create([
        'page_published' => true,
        'is_active' => true,
        'state' => $state,
        'district' => "{$state}-{$district}",
    ]);

    $voter->favoriteBoundaries()->create([
        'boundary_type' => 'district',
        'state_abbr' => $state,
        'district_number' => $district,
        'label' => "{$state}'s {$district}th District",
    ]);

    return $politician;
}

function digestEndorsement(Politician $politician, string $groupKey = 'teachers'): PoliticianEndorsement
{
    return PoliticianEndorsement::create([
        'politician_id' => $politician->id,
        'group_key' => $groupKey,
        'label' => 'Endorsed by Teachers Association',
        'matched_phrase' => 'endorsed by',
        'confidence' => 0.90,
        'status' => 'detected',
    ]);
}

beforeEach(function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);
});

test('a voter who has never been sent a digest and has content gets one, and last_digest_sent_at is set', function () {
    Mail::fake();
    $voter = digestVoterWithUser();
    $politician = digestBoundaryFor($voter);
    digestEndorsement($politician);

    $this->artisan('notifications:boundary-digest')->assertExitCode(0);

    Mail::assertQueued(BoundaryDigestMail::class, fn ($mail) => $mail->voter->is($voter));
    expect($voter->fresh()->last_digest_sent_at)->not->toBeNull();
});

test('a voter with no new content is skipped entirely', function () {
    Mail::fake();
    $voter = digestVoterWithUser();
    digestBoundaryFor($voter); // politician exists but has no news/endorsements/videos

    $this->artisan('notifications:boundary-digest')->assertExitCode(0);

    Mail::assertNothingQueued();
    expect($voter->fresh()->last_digest_sent_at)->toBeNull();
});

test('a voter sent one day ago with modest content is not yet due', function () {
    Mail::fake();
    $voter = digestVoterWithUser('1 day');
    $politician = digestBoundaryFor($voter);
    digestEndorsement($politician); // 1 item — below the burst threshold

    $this->artisan('notifications:boundary-digest')->assertExitCode(0);

    Mail::assertNothingQueued();
});

test('a voter sent two days ago with a content burst gets an early send', function () {
    Mail::fake();
    $voter = digestVoterWithUser('2 days');
    $politician = digestBoundaryFor($voter);
    digestEndorsement($politician, 'teachers');
    digestEndorsement($politician, 'nurses');
    CandidateNewsArticle::factory()->count(3)->create([
        'politician_id' => $politician->id,
        'published_at' => now()->subHours(6),
    ]);
    // 2 endorsements + 3 news = 5 items, meeting BURST_ITEM_THRESHOLD.

    $this->artisan('notifications:boundary-digest')->assertExitCode(0);

    Mail::assertQueued(BoundaryDigestMail::class, fn ($mail) => $mail->voter->is($voter));
});

test('a voter sent seven days ago gets an email even with just one item (floor rule)', function () {
    Mail::fake();
    $voter = digestVoterWithUser('7 days');
    $politician = digestBoundaryFor($voter);
    digestEndorsement($politician);

    $this->artisan('notifications:boundary-digest')->assertExitCode(0);

    Mail::assertQueued(BoundaryDigestMail::class, fn ($mail) => $mail->voter->is($voter));
});

test('sections beyond the cap are summarized as a remaining count, busiest boundaries kept', function () {
    Mail::fake();
    $voter = digestVoterWithUser();

    // 8 favorited boundaries, each with one active politician + one endorsement —
    // more than BoundaryDigestMatchService::MAX_SECTIONS (6).
    foreach (range(1, 8) as $i) {
        $politician = digestBoundaryFor($voter, 'CA', (string) $i);
        digestEndorsement($politician, "group-{$i}");
    }

    $this->artisan('notifications:boundary-digest')->assertExitCode(0);

    Mail::assertQueued(BoundaryDigestMail::class, function ($mail) {
        return count($mail->sections) === 6 && $mail->remainingCount === 2;
    });
});

test('dry run does not send mail or update last_digest_sent_at', function () {
    Mail::fake();
    $voter = digestVoterWithUser();
    $politician = digestBoundaryFor($voter);
    digestEndorsement($politician);

    $this->artisan('notifications:boundary-digest', ['--dry-run' => true])->assertExitCode(0);

    Mail::assertNothingQueued();
    expect($voter->fresh()->last_digest_sent_at)->toBeNull();
});
