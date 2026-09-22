<?php

use App\Models\BallotMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    if (class_exists(\Spatie\Permission\Models\Role::class)) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }
});

function guideAdmin(): User
{
    $admin = User::factory()->create(['platform' => 'standalone', 'user_type' => 'admin']);
    $admin->assignRole('admin', 'staff:Legacy administrator');
    skipOnboarding($admin, 'admin');

    return $admin;
}

function guidePlace(array $extra = []): array
{
    return $extra + ['state' => 'ca', 'level' => 'city', 'locality' => 'Oakland', 'county' => 'Alameda County', 'election_date' => '2026-11-03'];
}

it('shows the import form', function () {
    $this->actingAs(guideAdmin())->get(route('admin.ballot-measures.import'))->assertOk()->assertSee('Import from Voter Guide');
});

it('extracts measures from an uploaded guide for review without saving', function () {
    $file = UploadedFile::fake()->createWithContent('guide.txt', "Measure A: Housing Bond\nBuilds affordable housing.\nA YES vote means: Bonds are issued.");

    $this->actingAs(guideAdmin())
        ->post(route('admin.ballot-measures.import.preview'), guidePlace(['guide_file' => $file]))
        ->assertOk()
        ->assertSee('Measure A: Housing Bond')
        ->assertSee('Bonds are issued.');

    expect(BallotMeasure::count())->toBe(0);
});

it('extracts measures from a link', function () {
    Http::fake(['93.184.216.34/*' => Http::response('<h2>Question 1</h2><p>Charter Change</p><p>Lets voters recall officials.</p>', 200, ['Content-Type' => 'text/html'])]);

    $this->actingAs(guideAdmin())
        ->post(route('admin.ballot-measures.import.preview'), guidePlace(['guide_url' => 'https://93.184.216.34/guide']))
        ->assertOk()
        ->assertSee('Question 1: Charter Change');
});

it('needs a file or a link, and a place for local guides', function () {
    $admin = guideAdmin();

    $this->actingAs($admin)->post(route('admin.ballot-measures.import.preview'), guidePlace())
        ->assertSessionHasErrors('guide_file');

    $this->actingAs($admin)->post(route('admin.ballot-measures.import.preview'), guidePlace(['locality' => '', 'county' => '', 'guide_url' => 'https://93.184.216.34/g']))
        ->assertSessionHasErrors('county');
});

it('saves only the measures the admin kept, as local measures', function () {
    $this->actingAs(guideAdmin())
        ->post(route('admin.ballot-measures.import.store'), guidePlace([
            'measures' => [
                ['include' => '1', 'title' => 'Measure A: Housing Bond', 'measure_number' => 'A', 'summary' => 'Builds housing.'],
                ['title' => 'Measure B: Skipped', 'measure_number' => 'B'],
            ],
        ]))
        ->assertRedirect();

    $measure = BallotMeasure::sole();
    expect($measure->title)->toBe('Measure A: Housing Bond')
        ->and($measure->state)->toBe('CA')
        ->and($measure->level)->toBe('city')
        ->and($measure->locality)->toBe('Oakland')
        ->and($measure->county)->toBe('Alameda County')
        ->and($measure->source)->toBe('voter_guide');
});

it('is not available to non-admins', function () {
    $user = User::factory()->create(['platform' => 'standalone', 'user_type' => 'citizen']);

    $this->actingAs($user)->get(route('admin.ballot-measures.import'))->assertForbidden();
});
