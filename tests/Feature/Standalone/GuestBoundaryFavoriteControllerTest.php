<?php

use App\Support\GuestBoundaryCookie;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// postJson/getJson/deleteJson drop cookies unless withCredentials() is set
// (Illuminate\Foundation\Testing\Concerns\MakesHttpRequests::prepareCookiesForJsonRequest()).
beforeEach(fn () => $this->withCredentials());

// ── Store ──────────────────────────────────────────────────────────────────────

test('guest can save a district boundary to the cookie', function () {
    $res = $this->postJson(route('map.boundaries.store'), [
        'type' => 'district',
        'state_abbr' => 'CA',
        'district_number' => '12',
        'label' => "California's 12th",
    ]);

    $res->assertOk()->assertJson(['ok' => true, 'created' => true]);

    $cookie = $res->getCookie(GuestBoundaryCookie::BOUNDARIES_COOKIE);
    expect($cookie)->not->toBeNull();

    $payload = json_decode($cookie->getValue(), true);
    expect($payload['items'])->toHaveCount(1);
    expect($payload['items'][0]['state_abbr'])->toBe('CA');
    expect($payload['items'][0]['district_number'])->toBe('12');
});

test('saving the same guest boundary twice is idempotent', function () {
    $first = $this->postJson(route('map.boundaries.store'), [
        'type' => 'district', 'state_abbr' => 'CA', 'district_number' => '12', 'label' => "California's 12th",
    ]);

    $cookieValue = $first->getCookie(GuestBoundaryCookie::BOUNDARIES_COOKIE)->getValue();

    $second = $this->withCookie(GuestBoundaryCookie::BOUNDARIES_COOKIE, $cookieValue)
        ->postJson(route('map.boundaries.store'), [
            'type' => 'district', 'state_abbr' => 'ca', 'district_number' => '12', 'label' => "California's 12th",
        ]);

    $second->assertOk()->assertJson(['ok' => true, 'created' => false]);

    $payload = json_decode($second->getCookie(GuestBoundaryCookie::BOUNDARIES_COOKIE)?->getValue() ?? $cookieValue, true);
    expect($payload['items'] ?? json_decode($cookieValue, true)['items'])->toHaveCount(1);
});

test('guest boundary cookie is capped at 25 items', function () {
    $items = [];
    for ($i = 1; $i <= GuestBoundaryCookie::MAX_ITEMS; $i++) {
        $items[] = [
            'type' => 'district',
            'state_abbr' => 'CA',
            'district_number' => (string) $i,
            'city_name' => null,
            'label' => "District {$i}",
            'lat' => null,
            'lng' => null,
            'favorited_at' => now()->toIso8601String(),
        ];
    }
    $cookieValue = json_encode(['v' => 1, 'items' => $items]);

    $res = $this->withCookie(GuestBoundaryCookie::BOUNDARIES_COOKIE, $cookieValue)
        ->postJson(route('map.boundaries.store'), [
            'type' => 'district', 'state_abbr' => 'CA', 'district_number' => '99', 'label' => 'District 99',
        ]);

    $res->assertStatus(422)->assertJson(['ok' => false, 'error' => 'limit_reached']);
});

// ── Index ──────────────────────────────────────────────────────────────────────

test('guest boundaries list is empty with no cookie', function () {
    $this->getJson(route('map.boundaries.index'))
        ->assertOk()->assertJsonPath('boundaries', []);
});

test('guest can list saved boundaries from the cookie', function () {
    $cookieValue = json_encode(['v' => 1, 'items' => [[
        'type' => 'city', 'state_abbr' => 'CA', 'district_number' => null,
        'city_name' => 'Los Angeles', 'label' => 'Los Angeles, CA', 'lat' => 34.05, 'lng' => -118.24,
    ]]]);

    $res = $this->withCookie(GuestBoundaryCookie::BOUNDARIES_COOKIE, $cookieValue)
        ->getJson(route('map.boundaries.index'));

    $res->assertOk()->assertJsonPath('boundaries.0.city_name', 'Los Angeles');
});

// ── Destroy ────────────────────────────────────────────────────────────────────

test('guest can remove a saved boundary from the cookie', function () {
    $saveRes = $this->postJson(route('map.boundaries.store'), [
        'type' => 'district', 'state_abbr' => 'CA', 'district_number' => '12', 'label' => "California's 12th",
    ]);
    $key = $saveRes->json('id');
    $cookieValue = $saveRes->getCookie(GuestBoundaryCookie::BOUNDARIES_COOKIE)->getValue();

    $res = $this->withCookie(GuestBoundaryCookie::BOUNDARIES_COOKIE, $cookieValue)
        ->deleteJson(route('map.boundaries.destroy', $key));

    $res->assertOk()->assertJson(['ok' => true, 'deleted' => true]);

    $payload = json_decode($res->getCookie(GuestBoundaryCookie::BOUNDARIES_COOKIE)->getValue(), true);
    expect($payload['items'])->toHaveCount(0);
});
