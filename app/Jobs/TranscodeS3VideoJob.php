<?php

namespace App\Jobs;

use App\Models\CitizenCampaign;
use App\Models\PoliticalCampaign;
use App\Services\VideoTranscodingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Transcode a campaign video from S3 storage to H.264 MP4 format.
 *
 * This job runs asynchronously, allowing the web process to return immediately
 * after an S3 upload while transcoding happens in the background.
 */
class TranscodeS3VideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public PoliticalCampaign|CitizenCampaign $campaign,
        public string $sourceS3Path,
        public string $destinationS3Path,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(VideoTranscodingService $transcoding): void
    {
        if (!$transcoding->isAvailable()) {
            logger()->error('Transcoding skipped: FFmpeg not available', [
                'campaign_id' => $this->campaign->id,
                'source' => $this->sourceS3Path,
            ]);
            return;
        }

        try {
            // Get temporary local paths for source and output
            $sourceTempPath = $this->downloadFromS3($this->sourceS3Path);
            $destinationTempPath = storage_path("app/temp/transcode-{$this->campaign->id}-" . now()->timestamp . ".mp4");

            if (!$sourceTempPath) {
                logger()->error('Failed to download source video from S3', [
                    'campaign_id' => $this->campaign->id,
                    'source' => $this->sourceS3Path,
                ]);
                return;
            }

            // Perform transcoding
            if (!$transcoding->encodeToH264($sourceTempPath, $destinationTempPath)) {
                logger()->error('Video transcoding failed', [
                    'campaign_id' => $this->campaign->id,
                    'source_temp' => $sourceTempPath,
                ]);
                // Clean up on failure
                @unlink($sourceTempPath);
                @unlink($destinationTempPath);
                return;
            }

            // Extract duration from transcoded video
            $duration = $transcoding->getDuration($destinationTempPath);

            // Upload transcoded video back to S3
            $transcodedS3Path = $this->uploadToS3($destinationTempPath, $this->destinationS3Path);

            if ($transcodedS3Path) {
                // Update campaign with transcoded video URL and metadata
                $this->campaign->update([
                    'media_url' => "s3://{$transcodedS3Path}",
                    'media_type' => 'direct_file',
                    'media_duration' => $duration,
                ]);

                logger()->info('Video transcoding completed', [
                    'campaign_id' => $this->campaign->id,
                    'duration' => $duration,
                    'output' => $transcodedS3Path,
                ]);
            } else {
                logger()->error('Failed to upload transcoded video to S3', [
                    'campaign_id' => $this->campaign->id,
                    'temp_path' => $destinationTempPath,
                ]);
            }

            // Clean up temporary files
            @unlink($sourceTempPath);
            @unlink($destinationTempPath);

        } catch (\Exception $e) {
            logger()->error('Transcoding job error', [
                'campaign_id' => $this->campaign->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Download a file from S3 storage to local temporary directory.
     *
     * @param string $s3Path Path within S3 bucket
     * @return string|null Local file path, or null on failure
     */
    private function downloadFromS3(string $s3Path): ?string
    {
        try {
            $tempDir = storage_path('app/temp');
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $tempPath = $tempDir . '/' . basename($s3Path) . '-' . uniqid();
            $content = Storage::disk('s3')->get($s3Path);

            if (!$content) {
                return null;
            }

            file_put_contents($tempPath, $content);
            return file_exists($tempPath) ? $tempPath : null;
        } catch (\Exception $e) {
            logger()->warning('Failed to download S3 file', [
                'path' => $s3Path,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Upload a file from local temporary directory to S3 storage.
     *
     * @param string $localPath Local file path
     * @param string $s3Path Destination path within S3 bucket
     * @return string|null S3 path on success, null on failure
     */
    private function uploadToS3(string $localPath, string $s3Path): ?string
    {
        try {
            $stream = fopen($localPath, 'r');
            $success = Storage::disk('s3')->put(
                $s3Path,
                $stream,
                ['Visibility' => 'public', 'ContentType' => 'video/mp4']
            );

            if (is_resource($stream)) {
                fclose($stream);
            }

            return $success ? $s3Path : null;
        } catch (\Exception $e) {
            logger()->error('Failed to upload transcoded video to S3', [
                'local_path' => $localPath,
                's3_path' => $s3Path,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        logger()->error('TranscodeS3VideoJob failed', [
            'campaign_id' => $this->campaign->id,
            'source' => $this->sourceS3Path,
            'error' => $exception->getMessage(),
        ]);

        // Optionally notify the politician that transcoding failed
        // Notification::send($this->campaign->politician, new VideoTranscodingFailedNotification());
    }
}
