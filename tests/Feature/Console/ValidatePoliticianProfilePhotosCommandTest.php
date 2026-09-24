<?php

use App\Models\Politician;
use App\Models\PoliticianPhotoQuarantine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function seedPhotoPolitician(?string $validatedAgo): Politician
{
    return Politician::create([
        'uuid' => Str::uuid(),
        'full_name' => 'Jane Doe',
        'state' => 'CA',
        'political_office' => 'Governor',
        'governance_level' => 'State',
        'is_running_candidate' => false,
        'term_status' => 'seated',
        'is_active' => true,
        'page_published' => true,
        'verified_official' => false,
        'user_id' => null,
        'slug' => 'jane-doe',
        'profile_photo_url' => 'https://example.test/photo.jpg',
        'profile_photo_last_validated_at' => $validatedAgo ? now()->sub($validatedAgo) : null,
    ]);
}

test('excludes a photo validated within the stale window by default', function () {
    seedPhotoPolitician('1 hour');

    Artisan::call('politicians:validate-profile-photos', ['--skip-ai' => true, '--dry-run' => true]);

    expect(Artisan::output())->toContain('Inspecting 0 politician profile photo(s)');
});

test('includes a photo that has never been validated', function () {
    seedPhotoPolitician(null);

    Artisan::call('politicians:validate-profile-photos', ['--skip-ai' => true, '--dry-run' => true]);

    expect(Artisan::output())->toContain('Inspecting 1 politician profile photo(s)');
});

test('includes a photo whose validation is older than --stale-hours', function () {
    seedPhotoPolitician('800 hours');

    Artisan::call('politicians:validate-profile-photos', ['--skip-ai' => true, '--dry-run' => true]);

    expect(Artisan::output())->toContain('Inspecting 1 politician profile photo(s)');
});

test('--stale-hours=0 disables the freshness filter', function () {
    seedPhotoPolitician('1 hour');

    Artisan::call('politicians:validate-profile-photos', ['--skip-ai' => true, '--dry-run' => true, '--stale-hours' => 0]);

    expect(Artisan::output())->toContain('Inspecting 1 politician profile photo(s)');
});

test('records a flagged photo without needing --quarantine-only', function () {
    $politician = seedPhotoPolitician(null);
    $politician->update(['profile_photo_url' => 'https://example.test/Seal_of_Oregon.png']);

    Artisan::call('politicians:validate-profile-photos', ['--skip-ai' => true]);

    expect($politician->fresh()->profile_photo_status)->toBe('quarantined')
        ->and(PoliticianPhotoQuarantine::where('politician_id', $politician->id)->count())->toBe(1);
});

test('a photo the check could not classify is marked needs_review, not quarantined', function () {
    seedPhotoPolitician(null);

    Artisan::call('politicians:validate-profile-photos', ['--skip-ai' => true]);

    expect(Politician::sole()->profile_photo_status)->toBe('needs_review')
        ->and(Politician::sole()->profile_photo_url)->not->toBeNull();
});

test('--apply-quarantine clears confident results and requeues unclassified ones', function () {
    $bad = seedPhotoPolitician(null);
    $bad->update(['profile_photo_url' => 'https://example.test/Flag_of_Maine.png', 'profile_photo_status' => 'quarantined']);
    PoliticianPhotoQuarantine::create([
        'politician_id' => $bad->id, 'photo_url' => 'https://example.test/Flag_of_Maine.png',
        'status' => 'pending', 'validator' => 'heuristic', 'confidence' => 0.95, 'reason' => 'url indicates logo/symbol',
    ]);

    $unclassified = Politician::create(array_merge($bad->only(['full_name', 'state', 'political_office', 'governance_level']), [
        'uuid' => Str::uuid(), 'slug' => 'john-roe', 'is_active' => true, 'page_published' => true,
        'profile_photo_url' => 'https://example.test/headshot.jpg', 'profile_photo_status' => 'quarantined',
        'profile_photo_last_validated_at' => now(),
    ]));
    PoliticianPhotoQuarantine::create([
        'politician_id' => $unclassified->id, 'photo_url' => 'https://example.test/headshot.jpg',
        'status' => 'pending', 'validator' => 'heuristic', 'confidence' => 0, 'reason' => 'image fetch failed',
    ]);

    Artisan::call('politicians:validate-profile-photos', ['--apply-quarantine' => true]);

    $bad->refresh();
    $unclassified->refresh();
    expect($bad->profile_photo_url)->toBeNull()
        ->and($bad->profile_photo_status)->toBe('auto_cleared')
        ->and(PoliticianPhotoQuarantine::where('politician_id', $bad->id)->value('photo_url'))->toBe('https://example.test/Flag_of_Maine.png')
        ->and($unclassified->profile_photo_url)->toBe('https://example.test/headshot.jpg')
        ->and($unclassified->profile_photo_status)->toBe('needs_review')
        ->and($unclassified->profile_photo_last_validated_at)->toBeNull();
});

test('--apply-quarantine --dry-run changes nothing', function () {
    $bad = seedPhotoPolitician(null);
    $bad->update(['profile_photo_url' => 'https://example.test/Flag_of_Maine.png', 'profile_photo_status' => 'quarantined']);
    PoliticianPhotoQuarantine::create([
        'politician_id' => $bad->id, 'photo_url' => 'https://example.test/Flag_of_Maine.png',
        'status' => 'pending', 'validator' => 'heuristic', 'confidence' => 0.95, 'reason' => 'url indicates logo/symbol',
    ]);

    Artisan::call('politicians:validate-profile-photos', ['--apply-quarantine' => true, '--dry-run' => true]);

    expect($bad->fresh()->profile_photo_url)->toBe('https://example.test/Flag_of_Maine.png')
        ->and(Artisan::output())->toContain('cleared=1');
});
