<?php

use App\Models\Citizen;
use App\Models\DistrictNewsArticle;
use App\Models\PoliticianTopic;
use App\Models\User;
use App\Models\WorkspaceWidget;
use Spatie\Permission\Models\Role;

function workspaceCitizen(array $citizenAttrs = []): User
{
    Role::firstOrCreate(['name' => 'citizen', 'guard_name' => 'web']);

    $user = User::factory()->create(['user_type' => 'citizen', 'platform' => 'standalone']);
    $user->assignRole('citizen');
    skipOnboarding($user, 'citizen');

    Citizen::factory()->create(array_merge([
        'user_id' => $user->id,
        'state' => 'CA',
        'city' => 'Fresno',
    ], $citizenAttrs));

    return $user;
}

it('seeds default widgets on first visit and lets a citizen add, reorder, and remove widgets', function () {
    $user = workspaceCitizen();

    $this->actingAs($user)->get(route('citizen.workspace.index'))->assertOk();
    expect(WorkspaceWidget::where('user_id', $user->id)->count())->toBeGreaterThan(0);

    $this->post(route('citizen.workspace.widgets.store'), ['widget_key' => 'citizen_campaigns_overview'])->assertRedirect();
    $widget = WorkspaceWidget::where('user_id', $user->id)->where('widget_key', 'citizen_campaigns_overview')->sole();

    $this->postJson(route('citizen.workspace.widgets.layout'), [
        'widgets' => [['id' => $widget->id, 'position' => 0, 'width' => 'full']],
    ])->assertOk();
    expect($widget->fresh()->width)->toBe('full');

    $this->delete(route('citizen.workspace.widgets.destroy', $widget))->assertRedirect();
    expect(WorkspaceWidget::find($widget->id))->toBeNull();
});

it('does not let a citizen delete another citizen\'s widget', function () {
    $owner = workspaceCitizen();
    $other = workspaceCitizen();

    $widget = WorkspaceWidget::create([
        'user_id' => $other->id,
        'widget_key' => 'citizen_activity_snapshot',
        'position' => 0,
        'width' => 'full',
    ]);

    $this->actingAs($owner)->delete(route('citizen.workspace.widgets.destroy', $widget))->assertForbidden();
    expect(WorkspaceWidget::find($widget->id))->not->toBeNull();
});

it('lets a citizen add and remove an interest topic, which then filters their local news widget', function () {
    $user = workspaceCitizen(['state' => 'CA']);
    $citizen = Citizen::where('user_id', $user->id)->sole();

    $health = PoliticianTopic::factory()->create(['name' => 'Health', 'slug' => 'health', 'is_active' => true, 'voter_selectable' => true, 'auto_earned_only' => false]);
    PoliticianTopic::factory()->create(['name' => 'Housing', 'slug' => 'housing', 'is_active' => true, 'voter_selectable' => true, 'auto_earned_only' => false]);

    DistrictNewsArticle::create([
        'district_code' => 'CA-99', 'state' => 'CA', 'headline' => 'Health clinic opens',
        'source_url' => 'https://example.com/health', 'source_hash' => 'hash-health',
        'topic_key' => 'health', 'verification_status' => 'verified', 'published_at' => now(),
    ]);
    DistrictNewsArticle::create([
        'district_code' => 'CA-99', 'state' => 'CA', 'headline' => 'Housing bond passes',
        'source_url' => 'https://example.com/housing', 'source_hash' => 'hash-housing',
        'topic_key' => 'housing', 'verification_status' => 'verified', 'published_at' => now(),
    ]);

    $this->actingAs($user)->post(route('citizen.badges.store', $health->id))->assertRedirect();
    expect($citizen->badges()->where('topic_id', $health->id)->exists())->toBeTrue();

    $response = $this->actingAs($user)->get(route('citizen.workspace.index'))->assertOk();
    $response->assertSee('Health clinic opens')->assertDontSee('Housing bond passes');

    $this->actingAs($user)->delete(route('citizen.badges.destroy', $health->id))->assertRedirect();
    expect($citizen->badges()->where('topic_id', $health->id)->exists())->toBeFalse();
});

it('a citizen without a citizen profile cannot open the workspace', function () {
    Role::firstOrCreate(['name' => 'citizen', 'guard_name' => 'web']);
    $user = User::factory()->create(['user_type' => 'citizen', 'platform' => 'standalone']);
    $user->assignRole('citizen');
    skipOnboarding($user, 'citizen');

    $this->actingAs($user)->get(route('citizen.workspace.index'))->assertForbidden();
});
