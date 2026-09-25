<?php

use App\Models\CandidateNewsArticle;
use App\Models\Politician;
use App\Models\PoliticianEndorsement;
use App\Models\User;
use App\Services\CandidateNewsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function endorsementEditor(array $permissions = ['civic.view', 'civic.edit']): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'staff:Civic '.implode('+', $permissions), 'guard_name' => 'web']);
    foreach ($permissions as $permission) {
        $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $editor = User::factory()->create(['platform' => 'standalone', 'user_type' => 'admin']);
    $editor->assignRole('admin', $role->name);
    skipOnboarding($editor, 'admin');

    return $editor;
}

function detectedEndorsement(Politician $politician, array $attributes = []): PoliticianEndorsement
{
    $article = CandidateNewsArticle::create([
        'politician_id' => $politician->id, 'candidate_name' => $politician->full_name,
        'headline' => $attributes['headline'] ?? "Hispanic Caucus Endorses Congresswoman {$politician->full_name}'s Dignity Act for Immigrants",
        'source_name' => 'Daily News', 'source_url' => 'https://news.example.com/'.uniqid(), 'provider' => 'google_news',
        'content_type' => 'news', 'verification_status' => 'verified', 'published_at' => now()->subDay(), 'source_hash' => uniqid(),
    ]);
    unset($attributes['headline']);

    return PoliticianEndorsement::create(array_merge([
        'politician_id' => $politician->id, 'group_key' => 'us_representative', 'label' => 'U.S. Representative',
        'endorser_key' => '', 'endorser_name' => null, 'matched_phrase' => "Caucus Endorses Congresswoman {$politician->full_name}'s Dignity Act",
        'confidence' => 0.95, 'source_article_id' => $article->id, 'source_url' => 'https://news.example.com/source',
        'detected_article_ids' => [$article->id], 'match_count' => 1,
    ], $attributes));
}

test('detected endorsements stay private until an editor confirms them', function () {
    $politician = Politician::factory()->create(['full_name' => 'Veronica Escobar', 'page_published' => true, 'is_active' => true]);
    $endorsement = detectedEndorsement($politician);

    expect(PoliticianEndorsement::listedFor($politician))->toBeEmpty();

    $endorsement->update(['status' => PoliticianEndorsement::STATUS_CONFIRMED]);
    expect(PoliticianEndorsement::listedFor($politician))->toHaveCount(1);

    $endorsement->update(['status' => PoliticianEndorsement::STATUS_DISMISSED]);
    expect(PoliticianEndorsement::listedFor($politician))->toBeEmpty();
});

test('an editor reviews an endorsement as the candidate, their bill, or not an endorsement', function () {
    $politician = Politician::factory()->create(['full_name' => 'Veronica Escobar', 'page_published' => true, 'is_active' => true]);
    $endorsement = detectedEndorsement($politician);
    $editor = endorsementEditor();

    $this->actingAs($editor)->get(route('admin.endorsements.index'))->assertOk()
        ->assertSee('Endorsement review')->assertSee('Veronica Escobar')
        // The headline names a bill, so the bill title is suggested for the reviewer.
        ->assertSee('value="Dignity Act"', false)->assertSee('The headline mentions a bill');

    $this->actingAs($editor)->post(route('admin.endorsements.review', $endorsement), ['action' => 'confirm_bill', 'bill_title' => ''])
        ->assertSessionHasErrors('bill_title');
    $this->actingAs($editor)->post(route('admin.endorsements.review', $endorsement), ['action' => 'confirm_bill', 'bill_title' => 'Dignity Act', 'review_note' => 'Caucus backed the bill'])
        ->assertRedirect();
    $endorsement->refresh();
    expect($endorsement->status)->toBe('confirmed')->and($endorsement->kind)->toBe('bill')
        ->and($endorsement->bill_title)->toBe('Dignity Act')->and($endorsement->reviewed_by_user_id)->toBe($editor->id)
        ->and($endorsement->reviewed_at)->not->toBeNull();

    $this->actingAs($editor)->post(route('admin.endorsements.review', $endorsement), ['action' => 'return_to_review']);
    expect($endorsement->refresh()->status)->toBe('detected')->and($endorsement->reviewed_by_user_id)->toBeNull();

    $this->actingAs($editor)->post(route('admin.endorsements.review', $endorsement), ['action' => 'confirm_candidate']);
    expect($endorsement->refresh()->kind)->toBe('candidate')->and($endorsement->bill_title)->toBeNull();

    $this->actingAs($editor)->post(route('admin.endorsements.review', $endorsement), ['action' => 'dismiss']);
    expect($endorsement->refresh()->status)->toBe('dismissed');
});

test('reviewing endorsements requires civic edit permission', function () {
    $endorsement = detectedEndorsement(Politician::factory()->create(['is_active' => true]));
    $viewer = endorsementEditor(['civic.view']);

    $this->actingAs($viewer)->get(route('admin.endorsements.index'))->assertOk();
    $this->actingAs($viewer)->post(route('admin.endorsements.review', $endorsement), ['action' => 'confirm_candidate'])->assertForbidden();
    expect($endorsement->refresh()->status)->toBe('detected');
});

test('rebuilding detections replaces only rows awaiting review and never overrides an editor', function () {
    $politician = Politician::factory()->create(['full_name' => 'Jane Smith', 'state' => 'CA', 'page_published' => true, 'is_active' => true]);
    foreach (['Governor Newsom endorses Jane Smith for State Senate', 'AFL-CIO endorses Jane Smith'] as $i => $headline) {
        CandidateNewsArticle::create(['politician_id' => $politician->id, 'candidate_name' => 'Jane Smith', 'headline' => $headline,
            'source_name' => 'Daily News', 'source_url' => "https://news.example.com/{$i}", 'provider' => 'google_news',
            'content_type' => 'news', 'verification_status' => 'verified', 'published_at' => now()->subDays($i + 1), 'source_hash' => "h{$i}"]);
    }
    $service = app(CandidateNewsService::class);
    $service->detectEndorsementsForStoredArticles(politicianId: $politician->id);
    $rows = PoliticianEndorsement::where('politician_id', $politician->id)->get();
    expect($rows)->not->toBeEmpty()->and($rows->every(fn ($e) => $e->status === 'detected'))->toBeTrue();

    $confirmed = $rows->first();
    $confirmed->update(['status' => 'confirmed', 'kind' => 'bill', 'bill_title' => 'Clean Water Act', 'matched_phrase' => 'editor kept']);
    $service->detectEndorsementsForStoredArticles(politicianId: $politician->id);

    $confirmed->refresh();
    expect($confirmed->status)->toBe('confirmed')->and($confirmed->kind)->toBe('bill')
        ->and($confirmed->matched_phrase)->toBe('editor kept')
        ->and(PoliticianEndorsement::where('politician_id', $politician->id)->count())->toBe($rows->count());
});

test('the map economy tab lists confirmed endorsements and names an endorsed bill', function () {
    $politician = Politician::factory()->create(['full_name' => 'Veronica Escobar', 'slug' => 'veronica-escobar', 'state' => 'TX', 'page_published' => true, 'is_active' => true]);
    detectedEndorsement($politician, ['status' => 'confirmed', 'kind' => 'bill', 'bill_title' => 'Dignity Act', 'endorser_name' => 'Hispanic Caucus', 'endorser_key' => 'hispanic-caucus']);
    detectedEndorsement($politician, ['group_key' => 'governor', 'label' => 'Governor']);

    $economy = $this->getJson('/api/v1/map/candidate-economy?'.http_build_query(['slug' => 'veronica-escobar', 'full_name' => 'Veronica Escobar', 'state' => 'TX']))->assertOk();
    expect($economy->json('endorsements'))->toHaveCount(1)
        ->and($economy->json('endorsements.0.badge_text'))->toBe('Hispanic Caucus backed their bill: Dignity Act')
        ->and($economy->json('endorsements.0.kind'))->toBe('bill');
});
