<?php

use App\Console\Commands\EnrichStatewideOfficeholders;
use App\Models\Politician;
use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

uses(RefreshDatabase::class);

function refMatchPolitician(array $overrides = []): Politician
{
    $p = new Politician(array_merge([
        'uuid' => (string) Str::uuid(),
        'full_name' => 'Rick Larsen',
        'state' => 'WA',
        'political_office' => 'United States Representative',
        'governance_level' => 'Federal',
        'is_active' => true,
        'page_published' => true,
        'verified_official' => false,
        'user_id' => null,
        'website_url' => 'https://example.test',
    ], $overrides));
    $p->slug = $overrides['slug'] ?? Str::slug($p->full_name);
    $p->save();

    return $p;
}

function refMatchUpsert(string $office, string $name, ?string $party = null): void
{
    $cmd = new EnrichStatewideOfficeholders;
    $cmd->setLaravel(app());
    $cmd->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));

    $method = new ReflectionMethod($cmd, 'upsertPolitician');
    $method->setAccessible(true);
    $method->invoke($cmd, 'TX', $office, 'State', null, $name, $party, null, null, false, 0, 0, 0);
}

function refMatchTexasCandidate(string $office, string $name, array $overrides = []): Politician
{
    return refMatchPolitician(array_merge([
        'full_name' => $name,
        'state' => 'TX',
        'political_office' => $office,
        'governance_level' => 'State',
        'party_affiliation' => 'Democratic',
    ], $overrides));
}

test('a slug whose leading digits equal another politician id resolves by slug only', function () {
    $victim = refMatchPolitician(['full_name' => 'Rick Larsen']);
    $target = refMatchPolitician(['full_name' => 'Gina Hinojosa', 'slug' => $victim->id.'c8-governor-austin-gina-hinojosa']);

    $matched = Politician::byReference($victim->id.'c8-governor-austin-gina-hinojosa')->pluck('id')->all();

    expect($matched)->toBe([$target->id]);
});

test('a purely numeric reference still matches by id, and a slug still matches by slug', function () {
    $a = refMatchPolitician(['full_name' => 'Rick Larsen']);
    $b = refMatchPolitician(['full_name' => 'Gina Hinojosa']);

    expect(Politician::byReference((string) $a->id)->pluck('id')->all())->toBe([$a->id])
        ->and(Politician::byReference($b->slug)->pluck('id')->all())->toBe([$b->id]);
});

test('a non-numeric reference never puts the id column in the query', function () {
    // MySQL casts 'id = "758c8-governor-…"' to id = 758, so the id comparison must be absent
    // altogether. SQLite compares as text and would hide that, hence the SQL assertion.
    $sql = Politician::byReference('758c8-governor-austin-gina-hinojosa')->toSql();

    expect($sql)->toMatch('/[`"]slug[`"]/')->not->toMatch('/[`"]id[`"]/');
    expect(Politician::byReference('758')->toSql())->toMatch('/[`"]id[`"]/');
});

test('statewide enrichment never renames a challenger row into the incumbent', function () {
    $challenger = refMatchTexasCandidate('Governor', 'Gina Hinojosa');

    refMatchUpsert('Governor', 'Greg Abbott', 'Republican');

    expect($challenger->fresh()->full_name)->toBe('Gina Hinojosa')
        ->and($challenger->fresh()->party_affiliation)->toBe('Democratic')
        ->and(Politician::where('full_name', 'Greg Abbott')->where('political_office', 'Governor')->count())->toBe(1)
        ->and(Politician::where('political_office', 'Governor')->count())->toBe(2);
});

test('statewide enrichment updates the incumbent row in place instead of duplicating it', function () {
    $incumbent = refMatchTexasCandidate('Governor', 'Greg Abbott', ['party_affiliation' => null]);

    refMatchUpsert('Governor', 'Greg Abbott', 'Republican');

    expect(Politician::where('political_office', 'Governor')->count())->toBe(1)
        ->and($incumbent->fresh()->party_affiliation)->toBe('Republican')
        ->and($incumbent->fresh()->term_status)->toBe('seated');
});

test('statewide enrichment leaves a verified officeholder row alone without --force', function () {
    $verified = refMatchTexasCandidate('Governor', 'Someone Verified', ['verified_official' => true]);

    refMatchUpsert('Governor', 'Greg Abbott', 'Republican');

    expect($verified->fresh()->full_name)->toBe('Someone Verified')
        ->and(Politician::where('political_office', 'Governor')->count())->toBe(1);
});
