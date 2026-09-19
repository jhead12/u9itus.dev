<?php

use App\Models\BallotMeasure;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function guideFile(string $body): string
{
    $path = tempnam(sys_get_temp_dir(), 'guide').'.txt';
    file_put_contents($path, $body);

    return $path;
}

it('imports measures from a guide file as local measures for the given place', function () {
    $file = guideFile("Measure A: School Bond\nRepairs classrooms.\nA YES vote means: Bonds are issued.\nA NO vote means: No bonds.\n\nMeasure B: Park Levy\nFunds parks.");

    $this->artisan('ballot-measures:import-guide', [
        'source' => $file, '--state' => 'ca', '--level' => 'district',
        '--county' => 'San Diego County', '--locality' => 'Lakeside USD', '--election-date' => '2026-11-03',
    ])->assertExitCode(0);
    @unlink($file);

    $a = BallotMeasure::where('measure_number', 'A')->sole();
    expect(BallotMeasure::count())->toBe(2)
        ->and($a->state)->toBe('CA')
        ->and($a->level)->toBe('district')
        ->and($a->county)->toBe('San Diego County')
        ->and($a->locality)->toBe('Lakeside USD')
        ->and($a->source)->toBe('voter_guide')
        ->and($a->yes_meaning)->toBe('Bonds are issued.')
        ->and($a->election_date->toDateString())->toBe('2026-11-03');
});

it('keeps two cities\' identically titled measures apart, and is idempotent', function () {
    $file = guideFile("Measure A: Housing Bond\nBuilds housing.");
    $common = ['source' => $file, '--state' => 'CA', '--level' => 'city', '--election-date' => '2026-11-03'];

    $this->artisan('ballot-measures:import-guide', $common + ['--locality' => 'Oakland'])->assertExitCode(0);
    $this->artisan('ballot-measures:import-guide', $common + ['--locality' => 'San Jose'])->assertExitCode(0);
    $this->artisan('ballot-measures:import-guide', $common + ['--locality' => 'Oakland'])->assertExitCode(0);
    @unlink($file);

    expect(BallotMeasure::where('title', 'Measure A: Housing Bond')->pluck('locality')->sort()->values()->all())->toBe(['Oakland', 'San Jose']);
});

it('--dry-run saves nothing', function () {
    $file = guideFile("Measure A: School Bond\nRepairs classrooms.");

    $this->artisan('ballot-measures:import-guide', ['source' => $file, '--state' => 'CA', '--county' => 'Alameda County', '--dry-run' => true])->assertExitCode(0);
    @unlink($file);

    expect(BallotMeasure::count())->toBe(0);
});

it('requires a place for a local guide and a valid level', function () {
    $file = guideFile("Measure A: School Bond\nRepairs classrooms.");

    $this->artisan('ballot-measures:import-guide', ['source' => $file, '--state' => 'CA', '--level' => 'city'])->assertExitCode(1);
    $this->artisan('ballot-measures:import-guide', ['source' => $file, '--state' => 'CA', '--level' => 'galaxy'])->assertExitCode(1);
    @unlink($file);

    expect(BallotMeasure::count())->toBe(0);
});

it('fails clearly when no measures are found', function () {
    $file = guideFile('Candidate statements only.');

    $this->artisan('ballot-measures:import-guide', ['source' => $file, '--state' => 'CA', '--county' => 'Alameda County'])
        ->expectsOutputToContain('No ballot measures were detected')
        ->assertExitCode(1);
    @unlink($file);
});
