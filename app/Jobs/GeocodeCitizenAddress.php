<?php

namespace App\Jobs;

use App\Models\Citizen;
use App\Services\DistrictLookupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Resolves a Citizen's postal address to lat/lng via the Census geocoder
 * (DistrictLookupService, which already makes this exact call for district
 * lookup — reused here rather than adding a second geocoding integration),
 * so their business can be plotted on the map once they opt in
 * (Citizen::show_on_map). Queued because a profile save shouldn't block on
 * a third-party HTTP round-trip; fails soft — a failed/ambiguous geocode
 * just leaves latitude/longitude null, and the pin simply doesn't render.
 */
class GeocodeCitizenAddress implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 30;

    public function __construct(public readonly int $citizenId)
    {
    }

    public function handle(DistrictLookupService $lookupService): void
    {
        $citizen = Citizen::find($this->citizenId);
        if (! $citizen) {
            return;
        }

        $address = $citizen->fullAddress();
        if ($address === '') {
            return;
        }

        $result = $lookupService->lookup($address);
        $latitude = $result['latitude'] ?? null;
        $longitude = $result['longitude'] ?? null;

        if ($latitude === null || $longitude === null) {
            Log::info('GeocodeCitizenAddress: no coordinates resolved', [
                'citizen_id' => $citizen->id,
            ]);

            return;
        }

        $citizen->update([
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }
}
