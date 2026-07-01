<?php

namespace App\Http\Controllers\Concerns;

use App\Models\CitizenCampaign;
use App\Models\PoliticalCampaign;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Shared campaign video upload plumbing.
 *
 * Persisting long temporary signed URLs can overflow the media_url column,
 * so we store the stable disk URL here and only generate temporary signed
 * URLs when rendering for playback.
 *
 * Used by PoliticianController, CitizenController, and AdminController.
 * Campaign type is accepted as a union so any controller with either a
 * political or citizen campaign can reuse the same storage path
 * ("campaigns/{id}/video") and disk selection.
 */
trait HandlesCampaignVideoUpload
{
    protected function storeCampaignVideoAndGetUrl(
        UploadedFile $video,
        PoliticalCampaign|CitizenCampaign $campaign
    ): ?string {
        $disk  = (string) config('filesystems.default', 'local');
        $disks = (array) config('filesystems.disks', []);

        if (! array_key_exists($disk, $disks)) {
            Log::error('Campaign video upload failed: filesystem disk is not configured', [
                'campaign_type' => get_class($campaign),
                'campaign_id'   => $campaign->id,
                'disk'          => $disk,
            ]);

            return null;
        }

        try {
            $path = $video->store("campaigns/{$campaign->id}/video", $disk);

            if (! is_string($path) || $path === '') {
                Log::error('Campaign video upload failed: storage returned empty path', [
                    'campaign_type' => get_class($campaign),
                    'campaign_id'   => $campaign->id,
                    'disk'          => $disk,
                ]);

                return null;
            }

            return Storage::disk($disk)->url($path);
        } catch (Throwable $e) {
            Log::error('Campaign video upload failed with exception', [
                'campaign_type' => get_class($campaign),
                'campaign_id'   => $campaign->id,
                'disk'          => $disk,
                'error'         => $e->getMessage(),
            ]);

            return null;
        }
    }
}
