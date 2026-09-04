<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * The /api/v1/mcp/* group pins Accept: application/json (ForceJsonRequest), so a
 * caller that hand-rolls a fetch without the header still gets a JSON error
 * instead of a 302 redirect to an HTML page.
 */
it('returns a JSON 422 for an out-of-range param with no Accept header', function () {
    $res = $this->call('GET', '/api/v1/mcp/candidates', ['state' => 'ca', 'limit' => 100]);

    $res->assertStatus(422);
    expect($res->headers->get('Content-Type'))->toContain('json')
        ->and($res->json('errors.limit'))->not->toBeNull();
});

it('returns a JSON 404 for an unknown candidate uuid with no Accept header', function () {
    $res = $this->call('GET', '/api/v1/mcp/candidates/'.Str::uuid()->toString());

    $res->assertStatus(404);
    expect($res->headers->get('Content-Type'))->toContain('json')
        ->and($res->json('message'))->not->toBeNull();
});

it('still serves a normal JSON body on the happy path with no Accept header', function () {
    $this->call('GET', '/api/v1/mcp/candidates', ['limit' => 5])
        ->assertOk()
        ->assertJsonStructure(['query', 'count', 'total', 'limit', 'offset', 'has_more', 'results']);
});
