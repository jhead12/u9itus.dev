<?php

use App\Models\Politician;
use Illuminate\Support\Facades\DB;

test('malformed video_links does not crash the public profile page, and repair clears it', function () {
    $politician = Politician::factory()->create([
        'page_published' => true,
        'is_active' => true,
    ]);

    // Simulate legacy bad data: a bare scalar string instead of a JSON array.
    DB::table('politicians')->where('id', $politician->id)->update(['video_links' => '"N/A"']);

    $politician->refresh();

    // Accessor must not throw and must degrade to [].
    expect($politician->video_links)->toBe([]);

    // The public profile page must render (not 500).
    $response = $this->get('/p/'.$politician->slug);
    $response->assertOk();

    // Repair command should detect and null out the malformed column.
    $this->artisan('politicians:repair-profile', ['slug' => $politician->slug])
        ->assertSuccessful();

    $raw = DB::table('politicians')->where('id', $politician->id)->value('video_links');
    expect($raw)->toBeNull();
});
