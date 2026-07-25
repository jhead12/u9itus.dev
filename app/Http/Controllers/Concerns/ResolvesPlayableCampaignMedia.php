<?php

namespace App\Http\Controllers\Concerns;

use App\Models\CitizenCampaign;
use App\Models\PoliticalCampaign;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Shared campaign media playback resolution.
 *
 * Campaign videos are stored on a private S3 bucket/disk (see
 * HandlesCampaignVideoUpload); the stable disk URL saved on the model is not
 * directly fetchable by a browser. Before rendering a player, resolve it into
 * a short-lived signed URL. YouTube/Vimeo links and already-public URLs pass
 * through unchanged.
 *
 * Used by VoterController, CitizenCampaignVoterController, and
 * CitizenController wherever a campaign's media_url is about to be rendered
 * into a <video>/<source> tag.
 */
trait ResolvesPlayableCampaignMedia
{
    private function resolvePlayableCampaignMediaUrl(PoliticalCampaign|CitizenCampaign $campaign): ?string
    {
        $rawMediaUrl = trim((string) ($campaign->media_url ?? ''));
        if ($rawMediaUrl === '') {
            return null;
        }

        $mediaType = strtolower((string) ($campaign->media_type ?? ''));
        $isS3Like = str_contains($rawMediaUrl, '.amazonaws.com')
            || str_contains($rawMediaUrl, '/s3/')
            || str_contains($rawMediaUrl, 's3.')
            || str_starts_with($rawMediaUrl, 'campaigns/');

        if (in_array($mediaType, ['youtube', 'vimeo'], true) || ! $isS3Like) {
            return $rawMediaUrl;
        }

        $resolvedUrl = $rawMediaUrl;
        $disk = config('filesystems.disks.s3') ? 's3' : (string) config('filesystems.default', 'local');

        try {
            $isAbsoluteUrl = filter_var($rawMediaUrl, FILTER_VALIDATE_URL) !== false;
            $path = $rawMediaUrl;
            if ($isAbsoluteUrl) {
                $urlParts = parse_url($rawMediaUrl);
                $path = ltrim((string) ($urlParts['path'] ?? ''), '/');
            }

            $bucketName = (string) config('filesystems.disks.s3.bucket', '');
            if ($path !== '' && $bucketName !== '' && str_starts_with($path, $bucketName . '/')) {
                $path = substr($path, strlen($bucketName) + 1);
            }

            if ($path !== '') {
                $resolvedUrl = Storage::disk($disk)->temporaryUrl($path, now()->addMinutes(30));
            }
        } catch (Throwable $e) {
            Log::warning('Unable to generate temporary media URL for campaign playback', [
                'campaign_type' => get_class($campaign),
                'campaign_id'   => $campaign->id,
                'media_type'    => $campaign->media_type,
                'error'         => $e->getMessage(),
            ]);
        }

        return $resolvedUrl;
    }
}
