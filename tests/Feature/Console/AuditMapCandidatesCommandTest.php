<?php

use App\Models\Politician;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function auditRow(string $name, array $attrs = []): Politician
{
    return Politician::factory()->create(array_merge([
        'full_name' => $name, 'state' => 'CA', 'district' => 'CA-38',
        'governance_level' => 'federal', 'political_office' => 'U.S. Representative',
        'term_status' => 'running', 'is_active' => true,
    ], $attrs));
}

it('reports bad names and duplicate people without changing anything', function () {
    $junk = auditRow('WI Candidate David');
    auditRow('Steven Bradford');
    auditRow('Steve Bradford');

    $report = tempnam(sys_get_temp_dir(), 'audit');
    $this->artisan('map:audit-candidates', ['--state' => 'CA', '--report' => $report])
        ->expectsOutputToContain('1 bad names, 1 duplicate groups')
        ->assertExitCode(0);

    $json = json_decode(file_get_contents($report), true);
    unlink($report);

    expect($json['bad_names'][0]['id'])->toBe($junk->id)
        ->and($json['bad_names'][0]['hidden_on_map'])->toBeTrue()
        ->and($json['duplicates'][0]['rows'])->toHaveCount(2)
        ->and($junk->fresh()->is_active)->toBeTrue();
});

it('exits non-zero on findings only when asked to', function () {
    auditRow('Huntington Beach');

    $this->artisan('map:audit-candidates', ['--state' => 'CA'])->assertExitCode(0);
    $this->artisan('map:audit-candidates', ['--state' => 'CA', '--fail-on-findings' => true])->assertExitCode(1);
});
