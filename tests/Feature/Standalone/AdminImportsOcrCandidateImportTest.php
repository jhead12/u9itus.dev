<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

test('admin can OCR import candidate records from text package', function () {
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
    $response->assertSessionHas('ocr_import_count', 2);

    $this->assertDatabaseHas('election_candidate_records', [
        'source' => 'local_gov_ocr',
        'full_name' => 'Jamie Rivera',
        'state' => 'CA',
        'district' => 'Ward 2',
        'party_affiliation' => 'Democratic',
    ]);

    $this->assertDatabaseHas('election_candidate_records', [
        'source' => 'local_gov_ocr',
        'full_name' => 'Taylor Brooks',
        'state' => 'CA',
        'district' => 'Ward 2',
        'party_affiliation' => 'Independent',
    ]);
});
