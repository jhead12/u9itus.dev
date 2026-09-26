<?php

use App\Models\Politician;
use App\Models\User;
use App\Support\AdminAccess;
use Spatie\Permission\Models\Role;

function collapsibleSidebarPolitician(): User
{
    Role::findOrCreate('politician', 'web');
    $user = User::factory()->create(['user_type' => 'politician', 'platform' => 'standalone']);
    $user->assignRole('politician');
    skipOnboarding($user, 'politician');
    Politician::factory()->create(['user_id' => $user->id]);
    return $user;
}

function collapsibleSidebarCitizen(): User
{
    Role::findOrCreate('citizen', 'web');
    $user = User::factory()->create(['user_type' => 'citizen', 'platform' => 'standalone']);
    $user->assignRole('citizen');
    skipOnboarding($user, 'citizen');
    \App\Models\Citizen::factory()->create(['user_id' => $user->id]);
    return $user;
}

function collapsibleSidebarAdmin(): User
{
    $user = User::factory()->create(['user_type' => 'admin', 'platform' => 'standalone']);
    $user->assignRole(['admin', AdminAccess::OWNER]);
    skipOnboarding($user, 'admin');
    return $user;
}

// Count the attribute on elements only — the layout's <style> and <script>
// blocks also mention it in selectors like [data-sidebar-body].
function sidebarAttributeCount(string $html, string $attribute): int
{
    return preg_match_all('/\s'.preg_quote($attribute, '/').'[\s>=]/', $html);
}

it('renders each politician sidebar section as a collapsible toggle with a matching body', function () {
    $user = collapsibleSidebarPolitician();

    $html = $this->actingAs($user)->get(route('politician.dashboard'))->assertOk()->getContent();

    foreach (['Overview', 'Campaigns', 'Events', 'Insights', 'Account'] as $section) {
        $key = "politician:{$section}";
        expect($html)->toContain('data-sidebar-key="'.$key.'"');
    }
    expect(sidebarAttributeCount($html, 'data-sidebar-toggle'))->toBe(sidebarAttributeCount($html, 'data-sidebar-body'));
});

it('renders each citizen sidebar section as a collapsible toggle with a matching body', function () {
    $user = collapsibleSidebarCitizen();

    $html = $this->actingAs($user)->get(route('citizen.dashboard'))->assertOk()->getContent();

    foreach (['Overview', 'Campaigns', 'Posts', 'Events', 'Account'] as $section) {
        $key = "citizen:{$section}";
        expect($html)->toContain('data-sidebar-key="'.$key.'"');
    }
    expect(sidebarAttributeCount($html, 'data-sidebar-toggle'))->toBe(sidebarAttributeCount($html, 'data-sidebar-body'));
});

it('renders each visible admin sidebar section as a collapsible toggle with a matching body', function () {
    $owner = collapsibleSidebarAdmin();

    $html = $this->actingAs($owner)->get(route('admin.dashboard'))->assertOk()->getContent();

    foreach (['admin:Overview', 'admin:Accounts', 'admin:Candidates &amp; Data'] as $key) {
        expect($html)->toContain('data-sidebar-key="'.$key.'"');
    }
    expect(sidebarAttributeCount($html, 'data-sidebar-toggle'))->toBe(sidebarAttributeCount($html, 'data-sidebar-body'));
});

it('does not rely on @apply in the sidebar style block, since it silently no-ops under the Vite build', function () {
    $owner = collapsibleSidebarAdmin();

    $html = $this->actingAs($owner)->get(route('admin.dashboard'))->assertOk()->getContent();

    // Looks for @apply used as a CSS declaration (inside a rule body), not the
    // word appearing in an explanatory comment.
    expect($html)->not->toMatch('/[{;]\s*@apply\b/');
});

it('produces well-formed sidebar HTML with every toggle aria-controls resolving to exactly one element and a labeled nav landmark', function ($route, $userFactory) {
    $user = $userFactory();
    $html = $this->actingAs($user)->get(route($route))->assertOk()->getContent();

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML($html);
    $fatalErrors = array_filter(libxml_get_errors(), fn ($e) => $e->level === LIBXML_ERR_FATAL);
    libxml_clear_errors();
    expect($fatalErrors)->toBeEmpty();

    $xpath = new DOMXPath($dom);

    $nav = $xpath->query('//nav[@aria-label]');
    expect($nav->length)->toBeGreaterThan(0);

    $toggles = $xpath->query('//button[@data-sidebar-toggle]');
    expect($toggles->length)->toBeGreaterThan(0);

    foreach ($toggles as $toggle) {
        $id = $toggle->getAttribute('aria-controls');
        expect($id)->not->toBe('');
        expect($xpath->query("//*[@id='".$id."']")->length)->toBe(1);
        expect(trim($toggle->textContent))->not->toBe('');
    }

    $chevrons = $xpath->query('//*[contains(@class, "sidebar-chevron")]');
    expect($chevrons->length)->toBeGreaterThan(0);
    foreach ($chevrons as $chevron) {
        expect($chevron->getAttribute('aria-hidden'))->toBe('true');
    }
})->with([
    'politician dashboard' => ['politician.dashboard', 'collapsibleSidebarPolitician'],
    'citizen dashboard' => ['citizen.dashboard', 'collapsibleSidebarCitizen'],
    'admin dashboard' => ['admin.dashboard', 'collapsibleSidebarAdmin'],
]);
