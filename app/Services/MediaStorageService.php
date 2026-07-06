<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Platform-wide media storage service.
 *
 * Single entry point for all image uploads (hero banners, background photos,
 * audio cover art, etc.). Uses whichever disk is set in FILESYSTEM_DISK
 * (local in dev, s3 in production) — consistent with HandlesCampaignVideoUpload.
 *
 * Security:
 *   - Validates real file content via finfo, not client-claimed mime.
 *   - Re-encodes through GD to strip EXIF data and embedded payloads.
 *   - Enforces 5 MB server-side cap regardless of client-side validation.
 *   - Only deletes files that live under a managed path prefix to avoid
 *     accidentally removing externally-hosted URLs or other assets.
 */
class MediaStorageService
{
    /** Maximum permitted image size in bytes (5 MB). */
    private const MAX_BYTES = 5 * 1024 * 1024;

    /** Allowed real mime types (verified by finfo, not user input). */
    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    /**
     * Store an image from an UploadedFile.
     *
     * @param  UploadedFile  $source    Uploaded file instance
     * @param  string        $directory Storage sub-directory, e.g. 'hero-banners'
     * @param  int           $ownerId   Used to namespace the filename (politician/voter id)
     * @return string|null   Public URL, or null on failure
     */
    public function storeImage(UploadedFile $source, string $directory, int $ownerId): ?string
    {
        try {
            return $this->storeUploadedImage($source, $directory, $ownerId);
        } catch (\Throwable $e) {
            Log::error('MediaStorageService: image store failed', [
                'directory' => $directory,
                'owner_id'  => $ownerId,
                'error'     => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Delete a previously-stored file by its URL.
     *
     * Only deletes files that map to a managed path under the configured disk.
     * External URLs (e.g. https://example.com/banner.jpg) are silently ignored.
     */
    public function deleteByUrl(?string $url): void
    {
        if (! $url) {
            return;
        }

        $disk    = $this->disk();
        $storage = Storage::disk($disk);

        // Derive the storage path from the URL.
        // For S3 the URL is typically https://bucket.s3.region.amazonaws.com/path
        // For local it is http://app.test/storage/path
        // In both cases we try to strip the known base URL prefix.
        $path = $this->urlToPath($url, $disk);

        if (! $path) {
            return; // External URL — do not touch.
        }

        try {
            if ($storage->exists($path)) {
                $storage->delete($path);
            }
        } catch (\Throwable $e) {
            Log::warning('MediaStorageService: delete failed', [
                'url'   => $url,
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private function storeUploadedImage(UploadedFile $file, string $directory, int $ownerId): ?string
    {
        if ($file->getSize() > self::MAX_BYTES) {
            Log::warning('MediaStorageService: uploaded file exceeds 5 MB', [
                'size'      => $file->getSize(),
                'directory' => $directory,
            ]);
            return null;
        }

        $bytes = file_get_contents($file->getRealPath());
        if ($bytes === false) {
            return null;
        }

        return $this->encodeAndStore($bytes, $directory, $ownerId);
    }

    /**
     * Validate real mime via finfo, re-encode through GD (strips EXIF/payloads),
     * and write to the configured storage disk.
     */
    private function encodeAndStore(string $bytes, string $directory, int $ownerId): ?string
    {
        // ── 1. Validate real mime type ────────────────────────────────────
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->buffer($bytes);

        if (! in_array($realMime, self::ALLOWED_MIMES, true)) {
            Log::warning('MediaStorageService: rejected disallowed mime type', [
                'mime'      => $realMime,
                'directory' => $directory,
            ]);
            return null;
        }

        // ── 2. Re-encode through GD ───────────────────────────────────────
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            Log::warning('MediaStorageService: GD could not decode image', [
                'mime'      => $realMime,
                'directory' => $directory,
            ]);
            return null;
        }

        // Preserve alpha for PNG / WebP
        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        // Always re-encode as WebP for consistency; fall back to JPEG for GIF
        if ($realMime === 'image/gif') {
            imagejpeg($image, null, 90);
            $ext = 'jpg';
        } else {
            imagewebp($image, null, 88);
            $ext = 'webp';
        }
        $encoded = ob_get_clean();
        imagedestroy($image);

        if (! $encoded) {
            Log::warning('MediaStorageService: GD re-encode produced empty output', [
                'directory' => $directory,
            ]);
            return null;
        }

        // ── 3. Write to disk ──────────────────────────────────────────────
        $disk     = $this->disk();
        $filename = "{$directory}/{$ownerId}-" . time() . "-" . bin2hex(random_bytes(4)) . ".{$ext}";

        Storage::disk($disk)->put($filename, $encoded, 'public');

        return Storage::disk($disk)->url($filename);
    }

    /**
     * Attempt to convert a stored URL back to its disk path for deletion.
     * Returns null when the URL appears to be external (not managed by us).
     */
    private function urlToPath(string $url, string $disk): ?string
    {
        if ($disk === 's3') {
            // S3 URL base: configured via AWS_URL or derived from bucket+region
            $s3Base = rtrim((string) config('filesystems.disks.s3.url', ''), '/');
            if ($s3Base && str_starts_with($url, $s3Base . '/')) {
                return substr($url, strlen($s3Base) + 1);
            }
            // Fallback: try the bucket endpoint pattern
            $bucket = (string) config('filesystems.disks.s3.bucket', '');
            $region = (string) config('filesystems.disks.s3.region', '');
            if ($bucket && $region) {
                $patterns = [
                    "https://{$bucket}.s3.{$region}.amazonaws.com/",
                    "https://{$bucket}.s3.amazonaws.com/",
                    "https://s3.{$region}.amazonaws.com/{$bucket}/",
                ];
                foreach ($patterns as $pattern) {
                    if (str_starts_with($url, $pattern)) {
                        return substr($url, strlen($pattern));
                    }
                }
            }
            return null;
        }

        // Local disk: URL looks like https://app.test/storage/path
        $storageBase = rtrim(url('storage'), '/') . '/';
        if (str_starts_with($url, $storageBase)) {
            return substr($url, strlen($storageBase));
        }

        return null; // External URL — do not delete.
    }

    private function disk(): string
    {
        return (string) config('filesystems.default', 'local');
    }
}
