<?php

use App\Exceptions\OcrCandidateImportException;
use App\Jobs\ProcessOcrCandidateImportJob;
use App\Models\Politician;
use App\Services\OcrCandidateImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('ProcessOcrCandidateImportJob writes parsed json and calls artisan import command', function () {
    Storage::fake('local');
    Artisan::shouldReceive('call')
        ->once()
        ->with('elections:import-candidates', \Mockery::subset(['--source' => 'test_source']))
        ->andReturn(0);
    Artisan::shouldReceive('output')->once()->andReturn('2 candidates imported.');

    $records = [
        ['full_name' => 'Jane Doe', 'state' => 'CA', 'party_affiliation' => 'Democratic'],
        ['full_name' => 'John Smith', 'state' => 'CA', 'party_affiliation' => 'Republican'],
    ];

    $mockService = Mockery::mock(OcrCandidateImportService::class);
    $mockService->shouldReceive('extractCandidatesFromFile')
        ->once()
        ->andReturn($records);

    $this->app->instance(OcrCandidateImportService::class, $mockService);

    $job = new ProcessOcrCandidateImportJob(
        storedPath: '/tmp/fake-scan.txt',
        source: 'test_source',
        dryRun: false,
        defaults: ['state' => 'CA'],
    );

    $job->handle($mockService);

    // A parsed JSON file must have been written to local storage
    $files = Storage::disk('local')->files('imports/uploads');
    $jsonFiles = array_filter($files, fn ($f) => str_contains($f, 'candidate-ocr-parsed'));
    expect($jsonFiles)->not->toBeEmpty();

    $content = json_decode(Storage::disk('local')->get(array_values($jsonFiles)[0]), true);
    expect($content)->toHaveCount(2);
    expect($content[0]['full_name'])->toBe('Jane Doe');

    $this->assertDatabaseHas('politicians', [
        'full_name' => 'Jane Doe',
        'state' => 'CA',
        'party_affiliation' => 'Democratic',
        'verified_official' => false,
        'verification_status' => 'unverified',
        'is_active' => true,
        'page_published' => true,
    ]);

    $this->assertDatabaseHas('politicians', [
        'full_name' => 'John Smith',
        'state' => 'CA',
        'party_affiliation' => 'Republican',
        'verified_official' => false,
        'verification_status' => 'unverified',
        'is_active' => true,
        'page_published' => true,
    ]);
});

test('ProcessOcrCandidateImportJob does not call artisan when extraction throws', function () {
    Storage::fake('local');
    Artisan::shouldReceive('call')->never();

    $mockService = Mockery::mock(OcrCandidateImportService::class);
    $mockService->shouldReceive('extractCandidatesFromFile')
        ->once()
        ->andThrow(new OcrCandidateImportException('Tesseract not found'));

    $job = new ProcessOcrCandidateImportJob(
        storedPath: '/tmp/bad-scan.txt',
        source: 'test_source',
        dryRun: false,
        defaults: [],
    );

    $job->handle($mockService);

    // No parsed JSON should be written when extraction fails
    $files = Storage::disk('local')->allFiles('imports/uploads');
    expect($files)->toBeEmpty();
});

test('ProcessOcrCandidateImportJob respects dry_run flag passed to artisan', function () {
    Storage::fake('local');
    Artisan::shouldReceive('call')
        ->once()
        ->with('elections:import-candidates', \Mockery::subset(['--dry-run' => true]))
        ->andReturn(0);
    Artisan::shouldReceive('output')->once()->andReturn('');

    $mockService = Mockery::mock(OcrCandidateImportService::class);
    $mockService->shouldReceive('extractCandidatesFromFile')
        ->once()
        ->andReturn([['full_name' => 'Test Candidate', 'state' => 'TX']]);

    $this->app->instance(OcrCandidateImportService::class, $mockService);

    $job = new ProcessOcrCandidateImportJob(
        storedPath: '/tmp/scan.txt',
        source: 'local_gov_ocr',
        dryRun: true,
        defaults: [],
    );

    $job->handle($mockService);

    expect(Politician::count())->toBe(0);
});
