<?php

use App\Models\Politician;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function corruptedRow(int $id, array $attrs): Politician
{
    $model = Politician::factory()->make(array_merge([
        'id' => $id,
        'state' => 'CA',
        'slug' => Str::uuid().'-politician',
    ], $attrs));
    $model->saveQuietly();

    return $model->refresh();
}

it('restores a name that still matches the exact corrupted value, and deactivates a Party placeholder row', function () {
    corruptedRow(733, ['full_name' => 'D. Menefee']);
    corruptedRow(5403, ['full_name' => 'Party', 'is_active' => true]);

    $this->artisan('politicians:remediate-name-corruption-2026-09-11', ['--apply' => true])
        ->assertExitCode(0);

    expect(Politician::find(733)->full_name)->toBe('Christian D. Menefee')
        ->and(Politician::find(5403)->full_name)->toBe('Party')
        ->and(Politician::find(5403)->is_active)->toBeFalse();
});

it('skips a row that has already been hand-edited away from the expected corrupted value', function () {
    corruptedRow(1352, ['full_name' => 'Ahmed Actually Fixed By Hand']);

    $this->artisan('politicians:remediate-name-corruption-2026-09-11', ['--apply' => true])
        ->assertExitCode(0);

    expect(Politician::find(1352)->full_name)->toBe('Ahmed Actually Fixed By Hand');
});

it('does nothing in dry-run mode', function () {
    corruptedRow(1540, ['full_name' => 'Hurd']);
    corruptedRow(5404, ['full_name' => 'Party', 'is_active' => true]);

    $this->artisan('politicians:remediate-name-corruption-2026-09-11')
        ->assertExitCode(0);

    expect(Politician::find(1540)->full_name)->toBe('Hurd')
        ->and(Politician::find(5404)->is_active)->toBeTrue();
});

it('skips ids that are missing entirely', function () {
    $this->artisan('politicians:remediate-name-corruption-2026-09-11', ['--apply' => true])
        ->assertExitCode(0);
});
