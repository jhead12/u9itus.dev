<?php

use App\Models\Politician;
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
