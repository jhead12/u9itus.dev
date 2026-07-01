<?php

namespace App\Http\Controllers\Standalone;

use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Enums\CitizenAdType;
use App\Http\Controllers\Concerns\HandlesCampaignVideoUpload;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCitizenCampaignRequest;
use App\Http\Requests\UpdateCitizenCampaignRequest;
use App\Models\CitizenCampaign;
use App\Services\PlatformSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Standalone Citizen Controller
 *
 * Handles citizen-specific features in standalone mode:
 * - Dashboard
 * - Campaign CRUD (create, edit, delete drafts; submit for review)
 * - Video uploads (shared S3 pipeline via HandlesCampaignVideoUpload trait)
 *
 * Sprint 7.5 scope: citizens can CREATE and SUBMIT campaigns; admins APPROVE
 * them via AdminController. Voter-side view sessions + payouts and Stripe
 * billing for citizens are deferred to later sprints.
 */
class CitizenController extends Controller
{
    use HandlesCampaignVideoUpload;

    // ── Helpers ────────────────────────────────────────────────────────────

    private function inferMediaTypeFromUrl(?string $url, ?string $fallback = null): ?string
    {
        $value = trim((string) ($url ?? ''));
        if ($value === '') {
            return $fallback;
        }

        if (preg_match('/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/))/i', $value) === 1) {
            return 'youtube';
        }

        if (preg_match('/vimeo\.com\/(?:video\/)?\d+/i', $value) === 1) {
            return 'vimeo';
        }

        if (preg_match('/\.m3u8(\?.*)?$/i', $value) === 1) {
            return 'hls_stream';
        }

        return $fallback ?? 'direct_file';
    }

    private function isIosUserAgent(?string $userAgent): bool
    {
        return preg_match('/\b(iPhone|iPad|iPod)\b/i', $userAgent ?? '') === 1;
    }

    /**
     * @return array{0:int,1:int}
     */
    private function videoDurationBounds(): array
    {
        $min = max(1, (int) PlatformSettingsService::get(
            'min_video_duration',
            null,
            (int) config('u9itus.min_video_duration', 10)
        ));
        $max = max($min, (int) PlatformSettingsService::get(
            'max_video_duration',
            null,
            (int) config('u9itus.max_video_duration', 180)
        ));

        return [$min, $max];
    }

    /**
     * Guard: ensure the current user's citizen owns the campaign.
     */
    private function authorizeOwnership(CitizenCampaign $campaign): void
    {
        $citizen = Auth::user()?->citizen;
        abort_unless($citizen && (int) $campaign->citizen_id === (int) $citizen->id, 403);
    }

    // ── Dashboard ──────────────────────────────────────────────────────────

    public function dashboard()
    {
        $user    = Auth::user();
        $citizen = $user->citizen;

        $campaignCount = $citizen
            ? $citizen->campaigns()->count()
            : 0;

        return view('standalone.citizen.dashboard', [
            'user'          => $user,
            'citizen'       => $citizen,
            'campaignCount' => $campaignCount,
        ]);
    }

    // ── Campaign CRUD ──────────────────────────────────────────────────────

    /** List the citizen's campaigns. */
    public function campaigns(Request $request)
    {
        $citizen = Auth::user()->citizen;
        abort_unless($citizen, 403);

        $campaigns = $citizen->campaigns()
            ->latest()
            ->paginate(15);

        return view('standalone.citizen.campaigns.index', [
            'citizen'   => $citizen,
            'campaigns' => $campaigns,
        ]);
    }

    /** Show campaign create form. */
    public function createCampaign()
    {
        $citizen = Auth::user()->citizen;
        abort_unless($citizen, 403);

        $citizenRate     = (float) PlatformSettingsService::get('citizen_revenue_per_view', null, 0.75);
        $ballotIssueRate = (float) PlatformSettingsService::get('ballot_issue_revenue_per_view', null, 1.00);

        return view('standalone.citizen.campaigns.create', [
            'citizen'         => $citizen,
            'adTypes'         => CitizenAdType::cases(),
            'citizenRate'     => $citizenRate,
            'ballotIssueRate' => $ballotIssueRate,
        ]);
    }

    /** Store a new campaign. */
    public function storeCampaign(CreateCitizenCampaignRequest $request)
    {
        $citizen = Auth::user()->citizen;
        abort_unless($citizen, 403);

        $data          = $request->validated();
        $uploadedVideo = $request->file('video');
        unset($data['video']);

        if ($uploadedVideo) {
            unset($data['media_url']);
            $data['media_type'] = 'direct_file';
        } elseif (! empty($data['media_url'])) {
            $data['media_type'] = $this->inferMediaTypeFromUrl(
                $data['media_url'],
                $data['media_type'] ?? 'direct_file'
            );
        }

        $tier = ($data['citizen_ad_type'] ?? null) === CitizenAdType::BallotIssue->value
            ? 'ballot_issue'
            : 'citizen';

        $revenuePerView = (float) PlatformSettingsService::get(
            $tier . '_revenue_per_view',
            null,
            $tier === 'ballot_issue' ? 1.00 : 0.75
        );

        $data['citizen_id']       = $citizen->id;
        $data['status']           = CampaignStatus::Draft->value;
        $data['revenue_per_view'] = $revenuePerView;
        // Always recompute total_budget from views × rate (never trust form input)
        $data['total_budget']     = round(
            (float) ($data['total_views_requested'] ?? 0) * $revenuePerView,
            2
        );

        $campaign = CitizenCampaign::create($data);

        if ($uploadedVideo) {
            $mediaUrl = $this->storeCampaignVideoAndGetUrl($uploadedVideo, $campaign);

            if (! $mediaUrl) {
                return redirect()
                    ->route('citizen.campaigns.show', $campaign)
                    ->withErrors(['video' => 'Campaign created, but video upload failed. Please check storage settings and try again.']);
            }

            $campaign->update([
                'media_url'  => $mediaUrl,
                'media_type' => 'direct_file',
            ]);
        }

        return redirect()
            ->route('citizen.campaigns.show', $campaign)
            ->with('success', 'Campaign created! Upload a video and submit for review when ready.');
    }

    /** Show a single campaign. */
    public function showCampaign(CitizenCampaign $campaign)
    {
        $this->authorizeOwnership($campaign);

        return view('standalone.citizen.campaigns.show', [
            'campaign' => $campaign,
        ]);
    }

    /** Show campaign edit form (draft-only). */
    public function editCampaign(CitizenCampaign $campaign)
    {
        $this->authorizeOwnership($campaign);

        $rawStatus = (string) ($campaign->getRawOriginal('status') ?? '');
        abort_unless(
            in_array($rawStatus, [CampaignStatus::Draft->value, CampaignStatus::Cancelled->value], true),
            403,
            'Only draft or cancelled campaigns can be edited.'
        );

        $citizenRate     = (float) PlatformSettingsService::get('citizen_revenue_per_view', null, 0.75);
        $ballotIssueRate = (float) PlatformSettingsService::get('ballot_issue_revenue_per_view', null, 1.00);

        return view('standalone.citizen.campaigns.edit', [
            'campaign'        => $campaign,
            'adTypes'         => CitizenAdType::cases(),
            'citizenRate'     => $citizenRate,
            'ballotIssueRate' => $ballotIssueRate,
        ]);
    }

    /** Update a campaign (draft-only). */
    public function updateCampaign(UpdateCitizenCampaignRequest $request, CitizenCampaign $campaign)
    {
        // Request authorize() already checks ownership.
        $rawStatus = (string) ($campaign->getRawOriginal('status') ?? '');
        abort_unless(
            in_array($rawStatus, [CampaignStatus::Draft->value, CampaignStatus::Cancelled->value], true),
            403,
            'Only draft or cancelled campaigns can be edited.'
        );

        $validated     = $request->validated();
        $uploadedVideo = $request->file('video');
        unset($validated['video']);

        if ($uploadedVideo) {
            unset($validated['media_url']);
            $validated['media_type'] = 'direct_file';
        } elseif (! empty($validated['media_url'])) {
            $validated['media_type'] = $this->inferMediaTypeFromUrl(
                $validated['media_url'],
                $validated['media_type'] ?? 'direct_file'
            );
        }

        $adType = $validated['citizen_ad_type']
            ?? $campaign->citizen_ad_type?->value;
        $tier = $adType === CitizenAdType::BallotIssue->value ? 'ballot_issue' : 'citizen';

        $revenuePerView = (float) PlatformSettingsService::get(
            $tier . '_revenue_per_view',
            null,
            $tier === 'ballot_issue' ? 1.00 : 0.75
        );

        // Always recompute total_budget from views × rate.
        $validated['total_budget'] = round(
            (float) ($validated['total_views_requested'] ?? $campaign->total_views_requested) * $revenuePerView,
            2
        );
        $validated['revenue_per_view'] = $revenuePerView;

        $campaign->update($validated);

        if ($uploadedVideo) {
            $mediaUrl = $this->storeCampaignVideoAndGetUrl($uploadedVideo, $campaign);

            if (! $mediaUrl) {
                return redirect()
                    ->route('citizen.campaigns.show', $campaign)
                    ->withErrors(['video' => 'Campaign updated, but video upload failed. Please check storage settings and try again.']);
            }

            $campaign->update([
                'media_url'  => $mediaUrl,
                'media_type' => 'direct_file',
            ]);
        }

        return redirect()
            ->route('citizen.campaigns.show', $campaign)
            ->with('success', 'Campaign updated successfully.');
    }

    /** Delete a draft/cancelled campaign. */
    public function destroyCampaign(CitizenCampaign $campaign)
    {
        $this->authorizeOwnership($campaign);

        $rawStatus = (string) ($campaign->getRawOriginal('status') ?? '');
        abort_unless(
            in_array($rawStatus, [CampaignStatus::Draft->value, CampaignStatus::Cancelled->value], true),
            403,
            'Only draft or cancelled campaigns can be deleted.'
        );

        $campaign->delete();

        return redirect()
            ->route('citizen.campaigns.index')
            ->with('success', 'Campaign deleted.');
    }

    /** Submit a draft campaign for admin approval. */
    public function submitForReview(CitizenCampaign $campaign)
    {
        $this->authorizeOwnership($campaign);

        $rawStatus = (string) ($campaign->getRawOriginal('status') ?? '');
        abort_unless(
            in_array($rawStatus, [CampaignStatus::Draft->value, CampaignStatus::Cancelled->value], true),
            422,
            'Only draft or cancelled campaigns can be submitted for review.'
        );

        abort_unless(
            $campaign->media_url || $campaign->live_feed_url,
            422,
            'Please upload a video or set a live stream URL before submitting.'
        );

        if ($campaign->isBallotIssue() && empty($campaign->pac_registration_id)) {
            return back()->withErrors([
                'pac_registration_id' => 'Ballot-issue campaigns require a PAC registration ID before submission.',
            ]);
        }

        $campaign->update([
            'status'          => CampaignStatus::PendingApproval->value,
            'approval_status' => ApprovalStatus::Pending->value,
        ]);

        return back()->with('success', 'Campaign submitted for review. You will be notified once approved.');
    }

    // ── Video uploads (shared S3 pipeline via trait) ───────────────────────

    /** Handle direct video file upload for a citizen campaign. */
    public function uploadVideo(Request $request, CitizenCampaign $campaign)
    {
        $this->authorizeOwnership($campaign);

        $rawStatus = (string) ($campaign->getRawOriginal('status') ?? $campaign->status?->value ?? $campaign->status);
        if (! in_array($rawStatus, [CampaignStatus::Draft->value, CampaignStatus::Paused->value], true)) {
            $statusLabel = ucfirst(str_replace('_', ' ', $rawStatus ?: 'unknown'));
            $message = "Video uploads are only allowed when the campaign is Draft or Paused. Current status: {$statusLabel}.";

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->withErrors(['video' => $message]);
        }

        $maxMb = (int) config('u9itus.max_video_size_mb', 1024);
        [$minSec, $maxSec] = $this->videoDurationBounds();
        $videoMimeTypes = ['video/mp4', 'video/webm'];

        if ($this->isIosUserAgent($request->userAgent())) {
            $videoMimeTypes[] = 'video/quicktime';
        }

        $request->validate([
            'video' => [
                'required',
                'file',
                'mimetypes:' . implode(',', $videoMimeTypes),
                'max:' . ($maxMb * 1024),
            ],
        ]);

        $file = $request->file('video');

        if ($file && ! $this->isIosUserAgent($request->userAgent()) && $file->getMimeType() === 'video/quicktime') {
            return back()->withErrors([
                'video' => 'MOV uploads are only allowed from iOS devices. Use MP4 or WebM on non-iOS devices.',
            ]);
        }

        // Optional ffprobe duration check (only when the binary is available)
        $ffprobe = trim(shell_exec('which ffprobe 2>/dev/null') ?? '');
        if ($ffprobe) {
            $tmpPath  = $file->getRealPath();
            $duration = (float) shell_exec(
                escapeshellcmd($ffprobe)
                . ' -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 '
                . escapeshellarg($tmpPath)
                . ' 2>/dev/null'
            );

            if ($duration > 0) {
                if ($duration < $minSec) {
                    return back()->withErrors(['video' => "Video is too short ({$duration}s). Minimum is {$minSec} seconds."]);
                }
                if ($duration > $maxSec) {
                    $rounded = round($duration, 1);
                    return back()->withErrors(['video' => "Video is too long ({$rounded}s). Maximum is {$maxSec} seconds. Please trim your video and re-upload."]);
                }
                $campaign->media_duration = (int) round($duration);
            }
        }

        $disk = (string) config('filesystems.default', 'local');

        // Delete old video if present
        if ($campaign->media_url) {
            try {
                $oldPath = parse_url($campaign->media_url, PHP_URL_PATH);
                if ($oldPath) {
                    Storage::disk($disk)->delete(ltrim($oldPath, '/'));
                }
            } catch (Throwable $e) {
                Log::warning('Could not delete old citizen campaign video', [
                    'campaign_id' => $campaign->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        $url = $this->storeCampaignVideoAndGetUrl($file, $campaign);

        if (! $url) {
            return back()->withErrors([
                'video' => 'Video upload failed due to a storage configuration issue. Please contact support or try again later.',
            ]);
        }

        $campaign->update(array_filter([
            'media_url'      => $url,
            'media_type'     => 'direct_file',
            'media_duration' => $campaign->media_duration,
        ], fn ($v) => $v !== null));

        return back()->with('success', 'Video uploaded successfully.');
    }

    /** Get a pre-signed S3 upload URL for large direct-to-S3 browser uploads. */
    public function getS3UploadUrl(Request $request, CitizenCampaign $campaign)
    {
        $this->authorizeOwnership($campaign);

        $rawStatus = (string) ($campaign->getRawOriginal('status') ?? '');
        abort_unless(
            in_array($rawStatus, [CampaignStatus::Draft->value, CampaignStatus::Paused->value], true),
            403
        );

        $request->validate([
            'filename'     => 'required|string|max:255',
            'content_type' => 'required|in:video/mp4,video/quicktime,video/webm',
        ]);

        $filename    = $request->input('filename');
        $contentType = $request->input('content_type');

        if ($contentType === 'video/quicktime' && ! $this->isIosUserAgent($request->userAgent())) {
            return response()->json([
                'error' => 'MOV uploads are only allowed from iOS devices.',
            ], 422);
        }

        try {
            $s3Path = "campaigns/{$campaign->id}/uploads/" . time() . '-' . $filename;

            $s3Client = \Aws\sdk::createClient('s3');
            $cmd = $s3Client->getCommand('PutObject', [
                'Bucket'      => config('filesystems.disks.s3.bucket'),
                'Key'         => $s3Path,
                'ContentType' => $contentType,
            ]);

            $preRequest    = $s3Client->createPresignedRequest($cmd, '+20 minutes');
            $presignedUrl  = (string) $preRequest->getUri();

            return response()->json([
                'presigned_url' => $presignedUrl,
                's3_path'       => $s3Path,
                'expires_in'    => 1200,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate S3 presigned URL for citizen campaign', [
                'campaign_id' => $campaign->id,
                'error'       => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Unable to generate upload URL. Please try again.',
            ], 500);
        }
    }

    /** Finalize an S3-uploaded video and queue transcoding. */
    public function processS3UploadedVideo(Request $request, CitizenCampaign $campaign)
    {
        $this->authorizeOwnership($campaign);

        $rawStatus = (string) ($campaign->getRawOriginal('status') ?? '');
        abort_unless(
            in_array($rawStatus, [CampaignStatus::Draft->value, CampaignStatus::Paused->value], true),
            403
        );

        $request->validate([
            's3_path'   => 'required|string',
            'filename'  => 'required|string|max:255',
            'file_size' => 'required|integer|min:1|max:' . ((int) config('u9itus.max_video_size_mb', 1024) * 1024 * 1024),
        ]);

        $s3Path = $request->input('s3_path');

        try {
            if (! Storage::disk('s3')->exists($s3Path)) {
                return back()->withErrors(['video' => 'Uploaded file not found in storage. Please try uploading again.']);
            }

            $duration = null;
            try {
                $ffprobe = trim(shell_exec('which ffprobe 2>/dev/null') ?? '');
                if ($ffprobe) {
                    $s3Url    = Storage::disk('s3')->url($s3Path);
                    $duration = (float) shell_exec(
                        escapeshellcmd($ffprobe)
                        . ' -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 '
                        . escapeshellarg($s3Url)
                        . ' 2>/dev/null'
                    );

                    if ($duration > 0) {
                        [$minSec, $maxSec] = $this->videoDurationBounds();

                        if ($duration < $minSec) {
                            Storage::disk('s3')->delete($s3Path);
                            return back()->withErrors(['video' => "Video is too short ({$duration}s). Minimum is {$minSec} seconds."]);
                        }
                        if ($duration > $maxSec) {
                            $rounded = round($duration, 1);
                            Storage::disk('s3')->delete($s3Path);
                            return back()->withErrors(['video' => "Video is too long ({$rounded}s). Maximum is {$maxSec} seconds."]);
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Could not extract duration from S3 citizen video', [
                    'error' => $e->getMessage(),
                ]);
            }

            $transcodingService = app(\App\Services\VideoTranscodingService::class);
            $destinationPath    = $transcodingService->generateTranscodedFilename(
                (string) $campaign->id,
                $request->input('filename')
            );

            if ($campaign->media_url) {
                try {
                    $oldPath = parse_url($campaign->media_url, PHP_URL_PATH);
                    if ($oldPath) {
                        Storage::disk('s3')->delete(ltrim($oldPath, '/'));
                    }
                } catch (\Exception $e) {
                    Log::warning('Could not delete old citizen video from S3', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $campaign->update([
                'media_url'      => Storage::disk('s3')->url($s3Path),
                'media_type'     => 'direct_file',
                'media_duration' => $duration ? (int) round($duration) : null,
            ]);

            \App\Jobs\TranscodeS3VideoJob::dispatch(
                $campaign,
                $s3Path,
                $destinationPath
            );

            return back()->with('success', 'Video uploaded! Your file is now being processed. This may take a few minutes for large files.');
        } catch (\Exception $e) {
            Log::error('Failed to process S3-uploaded video for citizen campaign', [
                'campaign_id' => $campaign->id,
                'error'       => $e->getMessage(),
            ]);

            return back()->withErrors(['video' => 'Error processing your video. Please try again.']);
        }
    }
}
