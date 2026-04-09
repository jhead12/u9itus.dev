<?php

use App\Models\Politician;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('admin data health passes when published politician directory records are valid', function () {
    Politician::factory()->create([
        'page_published' => true,
        'full_name' => 'Alicia Rivera',
    ]);

    $exitCode = Artisan::call('admin:data-health');

    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain('Data health check passed.');
});

test('admin data health fails when published politician records are missing required directory data', function () {
    $missingSlug = Politician::factory()->create([
        'page_published' => true,
        'full_name' => 'Jordan Blake',
    ]);

    $missingName = Politician::factory()->create([
        'page_published' => true,
        'full_name' => 'Placeholder Name',
    ]);

    DB::table('politicians')
        ->where('id', $missingSlug->id)
        ->update(['slug' => '']);

    DB::table('politicians')
        ->where('id', $missingName->id)
        ->update(['full_name' => '']);

    $exitCode = Artisan::call('admin:data-health', [
        '--limit' => 5,
    ]);
    $output = Artisan::output();

    expect($exitCode)->toBe(1);
    expect($output)->toContain('published_missing_slug');
    expect($output)->toContain('published_missing_name');
    expect($output)->toContain('#' . $missingSlug->id);
    expect($output)->toContain('#' . $missingName->id);
});
