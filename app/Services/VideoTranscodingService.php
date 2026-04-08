<?php

namespace App\Services;

use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\Coordinate\TimeCode;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Service for video transcoding and optimization using FFmpeg.
 *
 * Handles encoding videos to standardized formats (H.264 MP4),
 * extracting metadata, and managing temporary files during processing.
 */
class VideoTranscodingService
{
    private ?FFMpeg $ffmpeg = null;
    private ?FFProbe $ffprobe = null;

    public function __construct()
    {
        // FFmpeg paths can be customized via environment or will use system defaults
        $ffmpegBin = env('FFMPEG_BIN', 'ffmpeg');
        $ffprobeBin = env('FFPROBE_BIN', 'ffprobe');

        try {
            if ($this->commandExists($ffmpegBin)) {
                $this->ffmpeg = FFMpeg::create([
                    'ffmpeg.binaries'  => $ffmpegBin,
                    'ffprobe.binaries' => $ffprobeBin,
                    'timeout' => 3600, // 1 hour timeout for large files
                ]);
                $this->ffprobe = FFProbe::create([
                    'ffprobe.binaries' => $ffprobeBin,
                ]);
            }
        } catch (\RuntimeException $e) {
            logger()->warning('FFmpeg not available for transcoding', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get the duration of a video file in seconds.
     *
     * @param string $filePath Absolute file path or S3 URL
     * @return int|null Duration in seconds, or null if unable to determine
     */
    public function getDuration(string $filePath): ?int
    {
        if (!$this->ffprobe) {
            return null;
        }

        $duration = null;

        try {
            $stream = $this->ffprobe->streams($filePath)->videos()->first();
            if ($stream && $stream->has('duration')) {
                $duration = (int) round($stream->get('duration'));
            }
        } catch (\RuntimeException $e) {
            logger()->warning('Failed to extract video duration', [
                'path' => $filePath,
                'error' => $e->getMessage(),
            ]);
        }

        return $duration;
    }

    /**
     * Transcode a video to H.264 MP4 format for cross-platform compatibility.
     *
     * @param string $inputPath Source file path (local or S3)
     * @param string $outputPath Destination file path
     * @param array $options Transcoding options
     * @return bool True on success
     */
    public function encodeToH264(string $inputPath, string $outputPath, array $options = []): bool
    {
    public function encodeToH264(string $inputPath, string $outputPath, array $options = []): bool
    {
        if (!$this->ffmpeg) {
            logger()->warning('FFmpeg not available for transcoding');
            return false;
        }

        $success = false;

        try {
            $video = $this->ffmpeg->open($inputPath);

            // Default encoding preset emphasizes file size and compatibility
            $format = new \FFMpeg\Format\Video\X264(
                env('VIDEO_ENCODE_PRESET', 'medium'), // preset: ultrafast, superfast, veryfast, faster, fast, medium, slow, slower, veryslow
            );
            
            // Bitrate and audio settings for good quality at reasonable size
            $format->setKiloBitrate((int) ($options['bitrate_kbps'] ?? 2500));
            $format->setAudioChannels(2);
            $format->setAudioKiloBitrate(128);

            // Save to destination
            $video->save($format, $outputPath);
            $success = true;
        } catch (\RuntimeException $e) {
            logger()->error('Video transcoding failed', [
                'input' => $inputPath,
                'output' => $outputPath,
                'error' => $e->getMessage(),
            ]);
        }

        return $success;
    }

    /**
     * Check if ffmpeg command is available on the system.
     *
     * @param string $command Command name
     * @return bool
     */
    private function commandExists(string $command): bool
    {
        $result = @shell_exec("which {$command} 2>/dev/null");
        return !empty(trim((string) $result));
    }

    /**
     * Determine if FFmpeg is available for transcoding operations.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return $this->ffmpeg !== null && $this->ffprobe !== null;
    }

    /**
     * Generate a unique filename for a transcoded video.
     *
     * @param string $campaignId Campaign ID
     * @param string $originalFilename Original filename
     * @return string Unique filename for transcoded output
     */
    public function generateTranscodedFilename(string $campaignId, string $originalFilename): string
    {
        $ext = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $baseName = pathinfo($originalFilename, PATHINFO_FILENAME);
        $timestamp = now()->format('YmdHis');
        return "campaigns/{$campaignId}/video/{$baseName}-{$timestamp}-transcoded.mp4";
    }
}
