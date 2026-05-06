<?php

use App\Jobs\ProcessOcrCandidateImportJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function adminForOcrCandidateImportTests(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $admin = User::factory()->create([
        'user_type' => 'admin',
    ]);

    $admin->assignRole('admin');
    skipOnboarding($admin, 'admin');

    return $admin;
}

test('admin OCR import queues a job and returns success flash', function () {
    Queue::fake();

    $admin = adminForOcrCandidateImportTests();

    $scanContent = implode("\n", [
        'Office: City Council Member',
        'District: Ward 2',
        'State: CA',
        'Jamie Rivera - Democratic',
        'Taylor Brooks (Independent)',
    ]);

    $upload = UploadedFile::fake()->createWithContent('local-package.txt', $scanContent);

    $response = $this->actingAs($admin)
        ->post(route('admin.imports.ocr-candidates'), [
            'source' => 'local_gov_ocr',
            'scan_upload' => $upload,
            'governance_level' => 'Local',
            'dry_run' => false,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    // Job must have been dispatched with the correct source/dry_run values
    Queue::assertPushed(ProcessOcrCandidateImportJob::class, function ($job) {
        return $job->source === 'local_gov_ocr' && $job->dryRun === false;
    });
});

test('admin OCR import validation rejects missing source', function () {
    Queue::fake();

    $admin = adminForOcrCandidateImportTests();

    $upload = UploadedFile::fake()->createWithContent('scan.txt', 'Candidate Name');

    $response = $this->actingAs($admin)
        ->post(route('admin.imports.ocr-candidates'), [
            'scan_upload' => $upload,
        ]);

    $response->assertSessionHasErrors('source');
    Queue::assertNothingPushed();
});

test('admin OCR import validation rejects missing file', function () {
    Queue::fake();

    $admin = adminForOcrCandidateImportTests();

    $response = $this->actingAs($admin)
        ->post(route('admin.imports.ocr-candidates'), [
            'source' => 'local_gov_ocr',
        ]);

    $response->assertSessionHasErrors('scan_upload');
    Queue::assertNothingPushed();
});
