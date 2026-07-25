<?php

use App\Models\Politician;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function seedNewsPolitician(string $state, string $name): Politician
{
    return Politician::create([
        'uuid' => Str::uuid(),
        'full_name' => $name,
        'state' => $state,
        'political_office' => 'Governor',
        'governance_level' => 'State',
        'is_running_candidate' => false,
        'term_status' => 'seated',
        'is_active' => true,
        'page_published' => true,
        'verified_official' => false,
        'user_id' => null,
        'slug' => Str::slug($name),
        'total_views_received' => 10,
    ]);
}

test('--state limits the refresh queue to a single state', function () {
    seedNewsPolitician('CA', 'Jane Doe');
    seedNewsPolitician('TX', 'John Smith');

    Artisan::call('candidates:refresh-news', ['--state' => 'CA', '--dry-run' => true, '--limit' => 10]);

    $output = Artisan::output();
    expect($output)->toContain('Jane Doe');
    expect($output)->not->toContain('John Smith');
});

test('omitting --state queues candidates from every state', function () {
    seedNewsPolitician('CA', 'Jane Doe');
    seedNewsPolitician('TX', 'John Smith');

    Artisan::call('candidates:refresh-news', ['--dry-run' => true, '--limit' => 10]);

    $output = Artisan::output();
    expect($output)->toContain('Jane Doe');
    expect($output)->toContain('John Smith');
});

test('--upcoming-only limits the queue to running candidates, ignoring traffic order', function () {
    $running = seedNewsPolitician('CA', 'New Candidate');
    $running->update(['is_running_candidate' => true, 'total_views_received' => 0]);

    $incumbent = seedNewsPolitician('CA', 'High Traffic Incumbent');
    $incumbent->update(['is_running_candidate' => false, 'total_views_received' => 10000]);

    Artisan::call('candidates:refresh-news', ['--dry-run' => true, '--limit' => 10, '--upcoming-only' => true]);

    $output = Artisan::output();
    expect($output)->toContain('New Candidate');
    expect($output)->not->toContain('High Traffic Incumbent');
});
