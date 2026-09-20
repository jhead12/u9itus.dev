<?php

use App\Models\CandidateLead;
use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function hiddenJunkProfile(string $slug): Politician
{
    $politician = Politician::factory()->create(['full_name' => 'Ordinary Person', 'state' => 'TX', 'user_id' => null, 'fec_candidate_id' => null, 'is_active' => false, 'page_published' => false, 'slug' => $slug]);
    Politician::query()->whereKey($politician->id)->update(['full_name' => 'Election results']); // model guard refuses this name on save

    return $politician;
}

it('deletes hidden junk, keeps what has dependents or is visible, and only with --apply', function () {
    $junk = hiddenJunkProfile('junk-a');
    $withDependents = hiddenJunkProfile('junk-b');
    DB::table('politician_office_profiles')->insert(['politician_id' => $withDependents->id, 'office_title' => 'Mayor', 'created_at' => now(), 'updated_at' => now()]);
    $visible = Politician::factory()->create(['full_name' => 'Real Person', 'state' => 'TX', 'user_id' => null, 'is_active' => true, 'slug' => 'real']);

    $record = fn (string $name, string $ext) => tap(new ElectionCandidateRecord(['source' => 'candidate_discovery', 'external_candidate_id' => $ext, 'full_name' => $name, 'state' => 'TX', 'political_office' => 'Governor', 'election_date' => '2026-11-03', 'payload' => []]), fn ($r) => $r->saveQuietly());
    $junkRecord = $record('Job Creator', 'a');
    $goodRecord = $record('Gina Hinojosa', 'b');

    $lead = fn (string $status, int $daysAgo) => CandidateLead::create(['source_key' => 'rss', 'full_name' => 'X Y', 'state' => 'TX', 'source_url' => 'https://e.test/'.fake()->unique()->slug(), 'discovered_at' => now()->subDays($daysAgo), 'status' => $status]);
    $oldRejected = $lead(CandidateLead::STATUS_REJECTED, 60);
    $newRejected = $lead(CandidateLead::STATUS_REJECTED, 2);
    $oldPromoted = $lead(CandidateLead::STATUS_PROMOTED, 60);

    $this->artisan('db:purge-junk')->assertSuccessful();
    expect(Politician::find($junk->id))->not->toBeNull()->and(CandidateLead::find($oldRejected->id))->not->toBeNull();

    $this->artisan('db:purge-junk', ['--apply' => true, '--skip-backup' => true])->assertSuccessful();

    expect(Politician::find($junk->id))->toBeNull()
        ->and(Politician::find($withDependents->id))->not->toBeNull()
        ->and(Politician::find($visible->id))->not->toBeNull()
        ->and(ElectionCandidateRecord::find($junkRecord->id))->toBeNull()
        ->and(ElectionCandidateRecord::find($goodRecord->id))->not->toBeNull()
        ->and(CandidateLead::find($oldRejected->id))->toBeNull()
        ->and(CandidateLead::find($newRejected->id))->not->toBeNull()
        ->and(CandidateLead::find($oldPromoted->id))->not->toBeNull();
});

it('does not delete anything when the backup fails', function () {
    $junk = hiddenJunkProfile('junk-c');

    // SQLite: db:backup refuses, so --apply must stop before deleting.
    $this->artisan('db:purge-junk', ['--apply' => true])->assertFailed();

    expect(Politician::find($junk->id))->not->toBeNull();
});
