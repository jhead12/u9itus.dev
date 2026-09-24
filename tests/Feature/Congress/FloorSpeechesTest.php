<?php

use App\Models\CongressFloorSpeech;
use App\Models\Politician;
use App\Models\PoliticianTopic;
use App\Services\CongressionalRecordImporter;
use App\Services\IssueClassifierService;
use App\Services\PoliticianTopicSignalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    config([
        'services.congress.api_key' => 'test-key',
        'services.anthropic.api_key' => null,
        'u9itus.issues.llm_fallback' => false,
    ]);
    $this->guns = PoliticianTopic::create(['name' => 'Gun Control', 'slug' => 'gun-control', 'is_active' => true, 'sort_order' => 1, 'keywords' => ['gun', 'firearm']]);
    $this->jack = Politician::factory()->create(['full_name' => 'Brian Jack', 'bioguide_id' => 'J000301', 'page_published' => true, 'is_active' => true]);
    $this->scanlon = Politician::factory()->create(['full_name' => 'Mary Gay Scanlon', 'bioguide_id' => 'S001205', 'page_published' => true, 'is_active' => true]);
});

function recordArticle(string $id, string $class, string $section, string $title, array $members): string
{
    $people = '';
    foreach ($members as [$bioguide, $label, $role]) {
        $people .= "<congMember bioGuideId=\"{$bioguide}\" chamber=\"H\" role=\"{$role}\"><name type=\"parsed\">{$label}</name></congMember>";
    }

    return <<<XML
<relatedItem type="constituent" ID="id-{$id}">
  <titleInfo><title>{$title}</title></titleInfo>
  <location><url displayLabel="HTML rendition">https://www.govinfo.gov/content/pkg/CREC-2025-07-15/html/{$id}.htm</url></location>
  <identifier type="preferred citation">171 Cong. Rec. H3257</identifier>
  <extension><granuleClass>{$class}</granuleClass><accessId>{$id}</accessId><subGranuleClass>{$section}</subGranuleClass>
    <granuleDate>2025-07-15</granuleDate><time from="12:10:00" to="12:10:00"/>{$people}</extension>
</relatedItem>
XML;
}

function recordFeeds(): void
{
    $sentence = 'This rule brings forward legislation that matters to the families in my district and across the country. ';
    $debate = "<html><body><pre>\n[Congressional Record Volume 171, Number 121 (Tuesday, July 15, 2025)]\n[House]\n[Pages H3257-H3266]\n"
        ."From the Congressional Record Online through the Government Publishing Office [www.gpo.gov]\n"
        ."  Mr. JACK. Mr. Speaker, I rise in support of the rule.\n".str_repeat($sentence, 8)."\n"
        ."  The SPEAKER pro tempore. The gentlewoman is recognized.\n"
        ."  Ms. SCANLON. Mr. Speaker, I oppose this rule because it blocks a vote on\ngun violence prevention.\n".str_repeat($sentence, 8)."\n"
        ."                          [[Page H3258]]\n"
        ."  Mr. JACK. I yield back.\n"
        ."  (Mr. JACK asked and was given permission to revise and extend his remarks.)\n</pre></body></html>";

    $mods = '<?xml version="1.0"?><mods xmlns="http://www.loc.gov/mods/v3">'
        .recordArticle('CREC-2025-07-15-pt1-PgH3257-5', 'HOUSE', 'ALLOTHER', 'PROVIDING FOR CONSIDERATION OF H.R. 3633', [
            ['J000301', 'Mr. JACK', 'SPEAKING'], ['S001205', 'Ms. SCANLON', 'SPEAKING'], ['A000055', 'Aderholt', 'VOTING'],
        ])
        .recordArticle('CREC-2025-07-15-pt1-PgH3249-4', 'HOUSE', 'HONORING', 'HONORING A CONSTITUENT', [['J000301', 'Mr. JACK', 'SPEAKING']])
        .recordArticle('CREC-2025-07-15-pt1-PgH3249-1', 'HOUSE', 'PRAYER', 'PRAYER', [])
        .'</mods>';

    Http::fake([
        'api.govinfo.gov/packages/CREC-2025-07-15/mods*' => Http::response($mods),
        'api.govinfo.gov/packages/CREC-2025-07-19/*' => Http::response(['message' => 'The requested resource does not exist.'], 400),
        'api.govinfo.gov/packages/*' => Http::response('', 404),
        'www.govinfo.gov/content/pkg/CREC-2025-07-15/html/CREC-2025-07-15-pt1-PgH3257-5.htm' => Http::response($debate),
    ]);
}

it('imports substantive Record articles split so each member keeps only their own words', function () {
    recordFeeds();

    $stats = app(CongressionalRecordImporter::class)->importDay(Carbon::parse('2025-07-15'));

    expect($stats)->toBe(['articles' => 1, 'speeches' => 2, 'skipped' => 0]);
    $jack = CongressFloorSpeech::where('bioguide_id', 'J000301')->sole();
    $scanlon = CongressFloorSpeech::where('bioguide_id', 'S001205')->sole();

    expect($jack->body)->toStartWith('Mr. Speaker, I rise in support of the rule.')
        ->toContain('I yield back.')
        ->not->toContain('oppose this rule')
        ->not->toContain('[[Page')
        ->not->toContain('revise and extend')
        ->and($scanlon->body)->toContain('I oppose this rule because it blocks a vote on gun violence prevention.')
        ->not->toContain('The gentlewoman is recognized')
        ->and($jack->kind)->toBe('floor')
        ->and($jack->record_time)->toBe('12:10:00')
        ->and($jack->cspanUrl())->toBe('https://www.c-span.org/congress/?chamber=house&date=2025-07-15');

    // A second run skips articles already stored without refetching their text.
    expect(app(CongressionalRecordImporter::class)->importDay(Carbon::parse('2025-07-15'))['skipped'])->toBe(1);
    expect(app(CongressionalRecordImporter::class)->importDay(Carbon::parse('2025-07-19')))->toBeNull();
});

it('keeps a Claude quote only when it is the speaker\'s exact words', function () {
    config(['services.anthropic.api_key' => 'test', 'u9itus.issues.llm_fallback' => true]);
    $text = "Mr. Speaker, I oppose this rule because it blocks a vote on gun violence prevention.\n\nFamilies deserve a vote.";
    $reply = fn (string $quote) => Http::response(['content' => [['text' => json_encode([
        'topic_id' => $this->guns->id, 'confidence' => 0.9, 'stance' => 'oppose',
        'position' => 'Opposes the rule for blocking a gun violence prevention vote.', 'quote' => $quote,
    ])]]]);

    Http::fake(['api.anthropic.com/*' => Http::sequence()
        ->pushResponse($reply('I oppose this rule because it blocks a vote on gun violence prevention.'))
        ->pushResponse($reply('Guns are destroying our country.'))]);
    $classifier = app(IssueClassifierService::class);

    $real = $classifier->analyzeStatement('PROVIDING FOR CONSIDERATION', $text);
    $invented = $classifier->analyzeStatement('PROVIDING FOR CONSIDERATION', $text);

    expect($real)->toMatchArray(['topic_slug' => 'gun-control', 'stance' => 'oppose'])
        ->and($real['quote'])->toBe('I oppose this rule because it blocks a vote on gun violence prevention.')
        ->and($invented['quote'])->toBeNull()
        ->and($invented['position'])->not->toBeNull();
});

it('tags by title when Claude is unavailable, and leaves vague titles untagged', function () {
    $tagged = CongressFloorSpeech::create(['granule_id' => 'G1', 'bioguide_id' => 'S001205', 'chamber' => 'house', 'spoken_on' => '2025-07-15', 'title' => 'INTRODUCTION OF THE GUN SAFETY INCENTIVE ACT', 'body' => 'Schools and students deserve safety.', 'source_url' => 'https://example.gov/1']);
    $vague = CongressFloorSpeech::create(['granule_id' => 'G2', 'bioguide_id' => 'S001205', 'chamber' => 'house', 'spoken_on' => '2025-07-15', 'title' => 'DELIVERING ON AMERICA FIRST', 'body' => 'We passed a gun bill and a firearm bill.', 'source_url' => 'https://example.gov/2']);

    $this->artisan('congress:analyze-floor-speeches')->assertSuccessful();

    expect($tagged->fresh()->topic_key)->toBe('gun-control')
        ->and($tagged->fresh()->analysis_method)->toBe('keyword')
        ->and($vague->fresh()->topic_key)->toBeNull()
        ->and($vague->fresh()->analyzed_at)->not->toBeNull();
});

it('shows recent speeches on the profile and searches them on the speeches page', function () {
    $make = fn (string $id, string $title, string $body, ?string $topic) => CongressFloorSpeech::create([
        'granule_id' => $id, 'bioguide_id' => 'S001205', 'chamber' => 'house', 'spoken_on' => '2025-07-15',
        'title' => $title, 'body' => $body, 'word_count' => 100, 'source_url' => "https://www.govinfo.gov/{$id}.htm",
        'topic_key' => $topic, 'stance' => $topic ? 'oppose' : null, 'position_summary' => $topic ? 'Opposes the rule.' : null,
    ]);
    $make('G1', 'GUN VIOLENCE PREVENTION', 'Background checks save lives.', 'gun-control');
    $make('G2', 'TARIFFS ON FARMERS', 'These tariffs hurt farm exports.', null);

    $this->get(route('politician.public.show', $this->scanlon->slug))
        ->assertOk()
        ->assertSee('On the Floor')
        ->assertSee('Gun Violence Prevention')
        ->assertSee('Opposes the rule.')
        ->assertSee('Watch that day on C-SPAN')
        ->assertSee('Search all 2');

    $this->get(route('politician.public.speeches', ['slug' => $this->scanlon->slug, 'q' => 'tariffs']))
        ->assertOk()
        ->assertSee('Tariffs on Farmers')
        ->assertDontSee('Gun Violence Prevention')
        ->assertSee('noindex, follow', false);

    $this->get(route('politician.public.speeches', ['slug' => $this->scanlon->slug, 'topic' => 'gun-control']))
        ->assertOk()
        ->assertSee('Gun Violence Prevention')
        ->assertDontSee('Tariffs on Farmers');

    $this->get(route('politician.public.speeches', $this->jack->slug))->assertNotFound();
});

it('scores repeated speeches on a topic toward a badge', function () {
    config(['u9itus.issues.signal_threshold' => 1.0]);
    foreach (range(1, 3) as $i) {
        CongressFloorSpeech::create(['granule_id' => "G{$i}", 'bioguide_id' => 'S001205', 'chamber' => 'house', 'spoken_on' => now()->subDays(10 * $i)->toDateString(), 'title' => 'GUNS', 'body' => 'Text.', 'source_url' => 'https://example.gov', 'topic_key' => 'gun-control', 'topic_confidence' => 0.9]);
    }

    $signal = app(PoliticianTopicSignalService::class)->compute($this->scanlon)->firstWhere('topic_id', $this->guns->id);

    expect($signal->floor_speech_count)->toBe(3)
        ->and((float) $signal->total_score)->toBeGreaterThanOrEqual(1.0);
});
