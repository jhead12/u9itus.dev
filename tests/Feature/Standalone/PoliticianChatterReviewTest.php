<?php

use App\Models\Politician;
use App\Models\PoliticianChatterItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function chatterAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create(['platform' => 'standalone', 'user_type' => 'admin']);
    $admin->assignRole('admin');
    skipOnboarding($admin, 'admin');

    return $admin;
}

it('keeps collected chatter private until an admin publishes it', function () {
    $politician = Politician::factory()->create(['page_published' => true, 'is_active' => true]);
    $admin = chatterAdmin();

    $this->actingAs($admin)->post(route('admin.politician-chatter.store'), [
        'politician_id' => $politician->id,
        'platform' => 'x',
        'source_url' => 'https://x.com/example/status/123',
        'source_author' => '@example',
        'headline' => 'A public narrative is gaining attention',
        'summary' => 'The source makes a claim that has not been independently confirmed.',
        'claim_status' => 'unverified',
        'likes' => 1200,
    ])->assertRedirect();

    $item = PoliticianChatterItem::sole();
    expect($item->moderation_status)->toBe('pending')
        ->and($item->engagement_metrics)->toBe(['likes' => 1200])
        ->and(PoliticianChatterItem::publiclyVisible()->count())->toBe(0)
        ->and($item->moderationLogs()->first()->action)->toBe('collected');

    $this->actingAs($admin)->get(route('admin.politician-chatter.index'))
        ->assertOk()
        ->assertSee('A public narrative is gaining attention');

    auth()->logout();
    $this->get(route('politician.public.show', $politician->slug))
        ->assertOk()
        ->assertDontSee('A public narrative is gaining attention');

    $this->actingAs($admin)->post(route('admin.politician-chatter.moderate', $item), [
        'action' => 'publish',
        'moderation_note' => 'Source and neutral wording reviewed.',
    ])->assertRedirect();

    expect(PoliticianChatterItem::publiclyVisible()->sole()->id)->toBe($item->id)
        ->and($item->fresh()->reviewed_by_user_id)->toBe($admin->id)
        ->and($item->moderationLogs()->latest()->first()->action)->toBe('publish');

    auth()->logout();
    $this->get(route('politician.public.show', [$politician->slug, 'refresh' => 1]))
        ->assertOk()
        ->assertSee('Public chatter')
        ->assertSee('A public narrative is gaining attention');
});

it('requires admin access and rejects unsafe or duplicate source submissions', function () {
    $politician = Politician::factory()->create();
    $payload = [
        'politician_id' => $politician->id,
        'platform' => 'x',
        'source_url' => 'javascript:alert(1)',
        'headline' => 'Unsafe source',
        'summary' => 'This should not be accepted.',
        'claim_status' => 'unverified',
    ];

    $this->post(route('admin.politician-chatter.store'), $payload)->assertRedirect();

    $admin = chatterAdmin();
    $this->actingAs($admin)->post(route('admin.politician-chatter.store'), $payload)
        ->assertSessionHasErrors('source_url');

    $payload['source_url'] = 'https://example.com/source/1';
    $this->actingAs($admin)->post(route('admin.politician-chatter.store'), $payload)->assertRedirect();
    $this->actingAs($admin)->post(route('admin.politician-chatter.store'), $payload)
        ->assertSessionHasErrors('source_url');
});
