<?php

use App\Models\DataReport;
use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

function reportPayload(array $over = []): array
{
    return array_merge([
        'subject_type' => 'election_candidate_record',
        'subject_id' => 42,
        'subject_name' => 'WI Candidate David',
        'state' => 'ca',
        'problem' => 'not_a_person',
        'message' => 'This is not a real candidate.',
        'page_url' => 'https://www.u9itus.com/map?state=CA',
        'source_label' => 'News discovery (unverified)',
    ], $over);
}

it('stores a report and hashes the reporter instead of keeping the IP', function () {
    $this->postJson('/api/v1/data-reports', reportPayload())->assertCreated();

    $report = DataReport::first();
    expect($report->status)->toBe('pending')
        ->and($report->state)->toBe('CA')
        ->and($report->subject_name)->toBe('WI Candidate David')
        ->and($report->reporter_hash)->toHaveLength(64)
        ->and($report->reporter_hash)->not->toContain('127.0.0.1');
});

it('counts a repeat of the same report from the same visitor once', function () {
    $this->postJson('/api/v1/data-reports', reportPayload())->assertCreated();
    $this->postJson('/api/v1/data-reports', reportPayload())->assertCreated();

    expect(DataReport::count())->toBe(1);
});

it('silently drops honeypot submissions', function () {
    $this->postJson('/api/v1/data-reports', reportPayload(['website' => 'http://spam.example']))->assertCreated();

    expect(DataReport::count())->toBe(0);
});

it('rejects unknown problems and subject types', function () {
    $this->postJson('/api/v1/data-reports', reportPayload(['problem' => 'lol']))->assertStatus(422);
    $this->postJson('/api/v1/data-reports', reportPayload(['subject_type' => 'users']))->assertStatus(422);
    expect(DataReport::count())->toBe(0);
});

it('requires a description for "something else"', function () {
    $this->postJson('/api/v1/data-reports', reportPayload(['problem' => 'other', 'message' => '']))->assertStatus(422);
    $this->postJson('/api/v1/data-reports', reportPayload(['problem' => 'other', 'message' => 'Wrong photo']))->assertCreated();
});

it('rate limits a burst of submissions', function () {
    foreach (range(1, 6) as $i) {
        $this->postJson('/api/v1/data-reports', reportPayload(['subject_id' => $i]))->assertCreated();
    }
    $this->postJson('/api/v1/data-reports', reportPayload(['subject_id' => 99]))->assertStatus(429);
});

it('stamps map cards with a plain-language source and a last-updated time', function () {
    Politician::factory()->create([
        'full_name' => 'Linda Sánchez', 'state' => 'CA', 'district' => 'CA-38',
        'governance_level' => 'federal', 'political_office' => 'U.S. Representative',
        'term_status' => 'seated', 'is_active' => true, 'verified_official' => false,
        'slug' => 'linda-sanchez-1',
    ]);
    ElectionCandidateRecord::factory()->create([
        'full_name' => 'Jane Q Public', 'state' => 'CA', 'political_office' => 'Governor',
        'governance_level' => 'state', 'source' => 'ballotpedia',
        'election_date' => now()->addMonths(2)->toDateString(), 'payload' => ['status' => 'running'],
    ]);

    $json = $this->getJson('/api/v1/map/state-candidates?state=CA')->assertOk()->json();

    $house = collect($json['house_candidates'])->flatten(1)->firstWhere('full_name', 'Linda Sánchez');
    expect($house['source_label'])->toBe('U9itus public records')
        ->and($house['updated_at'])->not->toBeNull();

    $gov = collect($json['offices'])->firstWhere('office', 'Governor')['candidates'][0];
    expect($gov['source_label'])->toBe('Ballotpedia')
        ->and($gov['updated_at'])->not->toBeNull();
});

it('lets an admin resolve a report and keeps non-admins out', function () {
    if (class_exists(Role::class)) {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }
    $report = DataReport::create(['subject_type' => 'other', 'problem' => 'other', 'message' => 'x', 'status' => 'pending']);

    $this->get('/admin/data-reports')->assertRedirect();

    $admin = User::factory()->create(['platform' => 'standalone', 'user_type' => 'admin']);
    if (method_exists($admin, 'assignRole')) {
        $admin->assignRole('admin');
    }
    skipOnboarding($admin, 'admin');

    $this->actingAs($admin)->get('/admin/data-reports')->assertOk()->assertSee('Data Reports');
    $this->actingAs($admin)->patch("/admin/data-reports/{$report->id}", ['status' => 'resolved', 'resolution_note' => 'fixed'])
        ->assertRedirect();

    expect($report->fresh()->status)->toBe('resolved')
        ->and($report->fresh()->resolved_by_user_id)->toBe($admin->id);
});
