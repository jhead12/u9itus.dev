<?php

use App\Models\CongressBill;
use App\Models\CongressFloorSpeech;
use App\Models\CongressVote;
use App\Models\CongressVoteTopic;
use App\Models\Politician;
use App\Models\PoliticianTopic;
use App\Services\BillTopicResolver;
use App\Services\CongressionalRecordImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    config(['services.congress.api_key' => 'test-key', 'services.anthropic.api_key' => null, 'u9itus.issues.llm_fallback' => false]);
    $this->finance = PoliticianTopic::create(['name' => 'Jobs & Economy', 'slug' => 'jobs-economy', 'is_active' => true, 'sort_order' => 1, 'policy_areas' => ['Finance and Financial Sector']]);
    $this->guns = PoliticianTopic::create(['name' => 'Gun Control', 'slug' => 'gun-control', 'is_active' => true, 'sort_order' => 2, 'keywords' => ['gun', 'firearm']]);
    $this->iran = PoliticianTopic::where('slug', 'iran-conflict')->sole();
});

function billSpeech(array $billRefs, string $title = 'DIGITAL ASSET MARKET CLARITY ACT OF 2025'): CongressFloorSpeech
{
    static $n = 0;
    $n++;

    return CongressFloorSpeech::create([
        'granule_id' => "CREC-2025-07-17-pt1-PgH{$n}", 'bioguide_id' => 'S001205', 'chamber' => 'house', 'spoken_on' => '2025-07-17',
        'title' => $title, 'body' => 'Remarks.', 'source_url' => 'https://example.gov', 'bill_refs' => $billRefs,
    ]);
}

function fakeCongressBill(string $path, string $title, ?string $policyArea): void
{
    Http::fake(["api.congress.gov/v3/bill/{$path}*" => Http::response(['bill' => ['title' => $title, 'policyArea' => $policyArea ? ['name' => $policyArea] : null]])]);
}

test('the importer keeps the bills in the most central place of an article, leaving out passing mentions', function () {
    $mods = simplexml_load_string('<?xml version="1.0"?><mods xmlns="http://www.loc.gov/mods/v3"><relatedItem type="constituent" ID="id-A">
        <titleInfo><title>DIGITAL ASSET MARKET CLARITY ACT OF 2025</title></titleInfo>
        <location><url displayLabel="HTML rendition">https://www.govinfo.gov/a.htm</url></location>
        <extension><granuleClass>HOUSE</granuleClass><accessId>CREC-A</accessId><subGranuleClass>ALLOTHER</subGranuleClass><granuleDate>2025-07-17</granuleDate>
          <congMember bioGuideId="S001205" chamber="H" role="SPEAKING"><name type="parsed">Ms. SCANLON</name></congMember>
          <bill congress="119" context="FIRSTPARAGRAPH" number="580" type="HRES"/>
          <bill congress="119" context="OTHER" number="580" type="HRES"/>
          <bill congress="119" context="HEADERLINE" number="3633" type="HR"/>
          <bill congress="119" context="OTHER" number="1" type="HR"/>
        </extension></relatedItem></mods>');

    expect(app(CongressionalRecordImporter::class)->articles($mods)[0]['bills'])->toBe(['119-hr-3633']);
});

test('importing a day again fills in bills for speeches imported before bills were read', function () {
    $speech = billSpeech([]);
    $speech->update(['granule_id' => 'CREC-A', 'bill_refs' => null]);
    Politician::factory()->create(['bioguide_id' => 'S001205', 'page_published' => true, 'is_active' => true]);
    Http::fake(['api.govinfo.gov/packages/CREC-2025-07-17/mods*' => Http::response('<?xml version="1.0"?><mods xmlns="http://www.loc.gov/mods/v3"><relatedItem type="constituent" ID="id-A">
        <titleInfo><title>CLARITY ACT</title></titleInfo><location><url displayLabel="HTML rendition">https://www.govinfo.gov/a.htm</url></location>
        <extension><granuleClass>HOUSE</granuleClass><accessId>CREC-A</accessId><subGranuleClass>ALLOTHER</subGranuleClass><granuleDate>2025-07-17</granuleDate>
          <congMember bioGuideId="S001205" chamber="H" role="SPEAKING"><name type="parsed">Ms. SCANLON</name></congMember>
          <bill congress="119" context="HEADERLINE" number="3633" type="HR"/></extension></relatedItem></mods>')]);

    app(CongressionalRecordImporter::class)->importDay(Carbon::parse('2025-07-17'));

    expect($speech->fresh()->bill_refs)->toBe(['119-hr-3633']);
});

test('a bill takes its topic from title keywords before its policy area, and is fetched once', function () {
    fakeCongressBill('119/hr/3633', 'Digital Asset Market Clarity Act', 'Finance and Financial Sector');
    fakeCongressBill('119/hr/77', 'Firearm Safety Act', 'Crime and Law Enforcement');
    $resolver = app(BillTopicResolver::class);

    expect($resolver->resolve(['119-hr-3633']))->toBe(['topic_slug' => 'jobs-economy', 'bill_key' => '119-hr-3633', 'source' => 'bill'])
        ->and($resolver->resolve(['119-hr-77'])['topic_slug'])->toBe('gun-control');

    $resolver->resolve(['119-hr-3633']);
    Http::assertSentCount(2);
    expect(CongressBill::firstWhere('bill_key', '119-hr-3633')->policy_area)->toBe('Finance and Financial Sector');
});

test('a roll call an editor tagged decides the topic of speeches on that bill', function () {
    $vote = CongressVote::create(['chamber' => 'senate', 'congress' => 119, 'session' => 1, 'roll_number' => 372, 'voted_at' => '2025-06-27', 'bill_number' => 'S.J.Res. 59', 'title' => 'War powers']);
    CongressVoteTopic::create(['congress_vote_id' => $vote->id, 'topic_id' => $this->iran->id, 'yea_stance' => 'oppose', 'yea_label' => 'Voted to limit', 'nay_label' => 'Voted against limiting']);
    Http::fake();

    expect(app(BillTopicResolver::class)->resolve(['119-sjres-59']))->toBe(['topic_slug' => 'iran-conflict', 'bill_key' => '119-sjres-59', 'source' => 'vote']);
    Http::assertNothingSent();
});

test('the analyzer files bill debates under the bill topic and reads other speeches as before', function () {
    fakeCongressBill('119/hr/3633', 'Digital Asset Market Clarity Act', 'Finance and Financial Sector');
    $debate = billSpeech(['119-hr-3633'], 'DIGITAL ASSET MARKET CLARITY ACT OF 2025');
    $oneMinute = billSpeech([], 'INTRODUCTION OF THE GUN SAFETY INCENTIVE ACT');
    $tribute = billSpeech([], 'HAPPY 105TH BIRTHDAY TO ROY DRINKARD');

    $this->artisan('congress:analyze-floor-speeches')->assertSuccessful();

    expect($debate->fresh()->topic_key)->toBe('jobs-economy')
        ->and($debate->fresh()->topic_source)->toBe('bill')
        ->and((float) $debate->fresh()->topic_confidence)->toBe(0.85)
        ->and($oneMinute->fresh()->topic_key)->toBe('gun-control')
        ->and($oneMinute->fresh()->topic_source)->toBe('keyword')
        ->and($tribute->fresh()->topic_key)->toBeNull()
        ->and($tribute->fresh()->topic_source)->toBeNull();
});

test('with Claude, the bill topic is given to the model and kept over its own pick', function () {
    config(['services.anthropic.api_key' => 'sk-test', 'u9itus.issues.llm_fallback' => true]);
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['text' => json_encode([
        'topic_id' => $this->guns->id, 'confidence' => 0.7, 'stance' => 'support', 'position' => 'Supports the bill.', 'quote' => null, 'topic_stance' => null,
    ])]]])]);

    $result = app(\App\Services\IssueClassifierService::class)->analyzeStatement('CLARITY ACT', 'We need clear rules.', 'jobs-economy');

    expect($result['topic_slug'])->toBe('jobs-economy')
        ->and($result['stance'])->toBe('support');
    Http::assertSent(fn ($request) => str_contains($request['messages'][0]['content'], "filed under topic_id {$this->finance->id}"));
});

test('a speech on several bills with different topics is left to the text, one where they agree is not', function () {
    fakeCongressBill('119/hr/3633', 'Digital Asset Market Clarity Act', 'Finance and Financial Sector');
    fakeCongressBill('119/s/1582', 'GENIUS Act', 'Finance and Financial Sector');
    fakeCongressBill('119/hr/77', 'Firearm Safety Act', 'Crime and Law Enforcement');
    $resolver = app(BillTopicResolver::class);

    expect($resolver->resolve(['119-hr-3633', '119-hr-77']))->toBeNull()
        ->and($resolver->resolve(['119-s-1582', '119-hr-3633'])['topic_slug'])->toBe('jobs-economy');
});
