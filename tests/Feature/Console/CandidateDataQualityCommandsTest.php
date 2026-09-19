<?php

use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function auditCandidate(array $attributes = []): Politician
{
    return Politician::factory()->create(array_merge([
        'full_name' => 'Jane Carter', 'political_office' => 'Governor', 'governance_level' => 'State',
        'state' => 'CA', 'party_affiliation' => 'Democratic', 'term_status' => 'running', 'is_running_candidate' => true,
    ], $attributes));
}

test('audit dry run reports violations without applying requested fixes', function () {
    $candidate = auditCandidate();
    DB::table('politicians')->where('id', $candidate->id)->update(['governance_level' => 'Federal']);
    $report = storage_path('app/qa/audit-test-'.Str::uuid().'.json');
    $this->artisan('politicians:audit-data-integrity', ['--fix' => true, '--deactivate' => true, '--dry-run' => true, '--report' => $report])->assertExitCode(1);
    expect($candidate->refresh()->governance_level)->toBe('Federal');
    $data = json_decode(file_get_contents($report), true);
    expect($data['scanned'])->toBe(1)->and($data['flagged'])->toBe(1)->and($data['dryRun'])->toBeTrue();
    unlink($report);
    $this->artisan('politicians:audit-data-integrity', ['--fix' => true])->assertSuccessful();
    expect($candidate->refresh()->governance_level)->toBe('State');
});

test('default audit reaches records after the first 5000', function () {
    $candidate = auditCandidate();
    $attributes = $candidate->getAttributes();
    unset($attributes['id'], $attributes['referral_code']);
    for ($batch = 0; $batch < 50; $batch++) {
        $rows = [];
        for ($i = 0; $i < 100; $i++) {
            $rows[] = array_merge($attributes, ['uuid' => (string) Str::uuid(), 'slug' => (string) Str::uuid()]);
        }
        DB::table('politicians')->insert($rows);
    }
    $last = Politician::orderByDesc('id')->first();
    DB::table('politicians')->where('id', $last->id)->update(['governance_level' => 'Federal']);
    $this->artisan('politicians:audit-data-integrity', ['--fix' => true])->assertSuccessful();
    expect($last->refresh()->governance_level)->toBe('State');
});

test('audit does not clear a serving officials legitimate candidacy', function () {
    $candidate = auditCandidate(['term_status' => 'seated', 'is_running_candidate' => true]);
    $this->artisan('politicians:audit-data-integrity', ['--fix' => true])->assertSuccessful();
    expect($candidate->refresh()->is_running_candidate)->toBeTrue();
});

test('cross-office cleanup never invents an elimination for missing or past dates', function ($date) {
    auditCandidate(['term_status' => 'seated', 'political_office' => 'Lieutenant Governor']);
    $record = ElectionCandidateRecord::factory()->create([
        'full_name' => 'Jane Carter', 'state' => 'CA', 'political_office' => 'Governor',
        'governance_level' => 'State', 'election_date' => $date, 'payload' => ['source' => 'test'],
    ]);
    $this->artisan('politicians:clean-cross-office-ecrs', ['--scope' => 'all'])->assertSuccessful();
    expect($record->refresh()->payload)->toBe(['source' => 'test']);
})->with([null, '2020-01-01']);

test('reconciliation never labels a challenger lost merely for being absent from the officeholder feed', function () {
    Illuminate\Support\Facades\Http::fake([
        '*legislators-current.json' => Illuminate\Support\Facades\Http::response([], 200),
        '*legislators-historical.json' => Illuminate\Support\Facades\Http::response([], 200),
    ]);
    $candidate = auditCandidate(['political_office' => 'U.S. Representative', 'governance_level' => 'Federal']);
    $this->artisan('politicians:reconcile-status', ['--election-date' => '2020-11-03'])->assertSuccessful();
    expect($candidate->refresh()->term_status)->toBe('running')->and($candidate->is_running_candidate)->toBeTrue();
});
