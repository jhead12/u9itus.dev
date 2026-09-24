<?php

use App\Models\Politician;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// No sitting House member ever showed as running for re-election: the Congress
// import creates them seated / not running and the primary-result sync only
// reads candidate records. This command reads each incumbent's race article.

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake([
        'en.wikipedia.org/w/api.php*' => function ($request) {
            $page = (string) ($request['page'] ?? '');

            return Http::response(['parse' => ['wikitext' => str_contains($page, 'Senate')
                ? "===Republican primary===\n====Nominee====\n* [[Sue Senator]]\n"
                : implode("\n", [
                    '==District 1==', '====Advanced to general====', '* [[Ann Incumbent]]', '* Bo Challenger',
                    '==District 2==', '====Eliminated in primary====', '* [[Lee Loser]]',
                    '==District 3==', '====Advanced to general====', '* [[Carol Mover]]', '* [[Rick Crawford]]',
                    '==District 4==', '====Advanced to general====', '* [[Someone Else]]',
                ])]]);
        },
        '*' => Http::response('', 404),
    ]);
});

function incumbent(string $name, ?string $district, array $overrides = []): Politician
{
    return Politician::factory()->create(array_merge([
        'full_name' => $name,
        'state' => 'CA',
        'district' => $district,
        'political_office' => $district ? 'United States Representative' : 'United States Senator',
        'governance_level' => 'Federal',
        'term_status' => 'seated',
        'is_active' => true,
        'is_running_candidate' => false,
        'term_ends_on' => '2027-01-03',
    ], $overrides));
}

it('marks incumbents running or not from their race article', function () {
    $advanced = incumbent('Ann Incumbent', 'CA-1');
    $lost = incumbent('Lee Loser', 'CA-2', ['is_running_candidate' => true]);
    $moved = incumbent('Carol Mover', 'CA-4');
    $nickname = incumbent('Eric A. "Rick" Crawford', 'CA-3');
    $retiring = incumbent('Pat Retiring', 'CA-4', ['is_running_candidate' => true]);
    $senator = incumbent('Sue Senator', null);
    $notUp = incumbent('Nell Notup', null, ['term_ends_on' => '2029-01-03']);

    $this->artisan('politicians:sync-incumbent-runs', ['--state' => ['CA'], '--year' => 2026])->assertSuccessful();

    expect($advanced->fresh()->is_running_candidate)->toBeTrue()
        ->and($lost->fresh()->is_running_candidate)->toBeFalse()
        ->and($lost->fresh()->term_status)->toBe('seated')
        ->and($moved->fresh()->is_running_candidate)->toBeTrue()
        ->and($nickname->fresh()->is_running_candidate)->toBeTrue()
        ->and($retiring->fresh()->is_running_candidate)->toBeTrue()
        ->and($senator->fresh()->is_running_candidate)->toBeTrue()
        ->and($notUp->fresh()->is_running_candidate)->toBeFalse();
});

it('writes nothing on --dry-run', function () {
    $advanced = incumbent('Ann Incumbent', 'CA-1');

    $this->artisan('politicians:sync-incumbent-runs', ['--state' => ['CA'], '--year' => 2026, '--dry-run' => true])
        ->expectsOutputToContain('1 changed')
        ->assertSuccessful();

    expect($advanced->fresh()->is_running_candidate)->toBeFalse();
});
