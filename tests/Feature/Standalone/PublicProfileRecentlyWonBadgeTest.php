<?php

use App\Models\Politician;

test('shows the Recently Won badge for a politician who won within the last 30 days', function () {
    $politician = Politician::factory()->create([
        'page_published' => true,
        'is_active' => true,
        'term_status' => 'seated',
        'won_at' => now()->subDays(10),
    ]);

    $response = $this->get('/p/'.$politician->slug);

    $response->assertOk();
    $response->assertSee('Recently Won');
});

test('hides the Recently Won badge once 30 days have passed', function () {
    $politician = Politician::factory()->create([
        'page_published' => true,
        'is_active' => true,
        'term_status' => 'seated',
        'won_at' => now()->subDays(45),
    ]);

    $response = $this->get('/p/'.$politician->slug);

    $response->assertOk();
    $response->assertDontSee('Recently Won');
});

test('hides the Recently Won badge when won_at is set but the politician is no longer seated', function () {
    $politician = Politician::factory()->create([
        'page_published' => true,
        'is_active' => true,
        'term_status' => 'lost',
        'won_at' => now()->subDays(5),
    ]);

    $response = $this->get('/p/'.$politician->slug);

    $response->assertOk();
    $response->assertDontSee('Recently Won');
});

test('hides the Recently Won badge when won_at was never recorded', function () {
    $politician = Politician::factory()->create([
        'page_published' => true,
        'is_active' => true,
        'term_status' => 'seated',
        'won_at' => null,
    ]);

    $response = $this->get('/p/'.$politician->slug);

    $response->assertOk();
    $response->assertDontSee('Recently Won');
});
