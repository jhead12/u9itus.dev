<?php

namespace App\Http\Controllers\Standalone;

use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Enums\CitizenAdType;
use App\Http\Controllers\Concerns\HandlesCampaignVideoUpload;
use App\Http\Controllers\Concerns\ResolvesPlayableCampaignMedia;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCitizenCampaignRequest;
use App\Http\Requests\UpdateCitizenCampaignRequest;
use App\Jobs\GeocodeCitizenAddress;
use App\Models\Citizen;
use App\Models\CitizenCampaign;
use App\Models\CitizenPaymentMethod;
use App\Models\CitizenTransaction;
use App\Services\CitizenBillingService;
use App\Services\PlatformSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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
    use ResolvesPlayableCampaignMedia;

    public function __construct(protected CitizenBillingService $billing) {}

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
            'postCount'     => $citizen?->posts()->count() ?? 0,
            'eventCount'    => $citizen?->events()->count() ?? 0,
            'creditBalance' => $citizen?->credit_balance ?? 0,
        ]);
    }

    /** Business location settings: address, category, and the map opt-in. */
    public function settings()
    {
        return view('standalone.citizen.settings', [
            'citizen' => Auth::user()->citizen,
        ]);
    }

    public function updateSettings(Request $request)
    {
        $citizen = Auth::user()->citizen;
        abort_unless($citizen, 404);

        $validated = $request->validate([
            'business_name'     => ['nullable', 'string', 'max:255'],
            'business_category' => ['nullable', 'string', Rule::in(['food', 'retail', 'service', 'nonprofit', 'other'])],
            'address_line_1'    => ['required', 'string', 'max:255'],
            'address_line_2'    => ['nullable', 'string', 'max:255'],
            'city'               => ['required', 'string', 'max:100'],
            'state'              => ['required', 'string', 'size:2'],
            'zip'                => ['required', 'string', 'max:10', 'regex:/^\d{5}(-\d{4})?$/'],
            'show_on_map'        => ['nullable', 'boolean'],
        ]);

        $addressChanged = $citizen->address_line_1 !== $validated['address_line_1']
            || $citizen->address_line_2 !== ($validated['address_line_2'] ?? null)
            || $citizen->city !== $validated['city']
            || $citizen->state !== $validated['state']
            || $citizen->zip !== $validated['zip'];

        $citizen->update([
            'business_name'     => $validated['business_name'] ?? null,
            'business_category' => $validated['business_category'] ?? null,
            'address_line_1'    => $validated['address_line_1'],
            'address_line_2'    => $validated['address_line_2'] ?? null,
            'city'              => $validated['city'],
            'state'             => $validated['state'],
            'zip'               => $validated['zip'],
            'show_on_map'       => $request->boolean('show_on_map'),
        ]);

        // Re-geocode whenever the address actually changed — the old
        // coordinates would otherwise silently point at a stale address.
        if ($addressChanged) {
            $citizen->update(['latitude' => null, 'longitude' => null]);
            GeocodeCitizenAddress::dispatch($citizen->id);
        }

        return back()->with('success', 'Business settings updated.');
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

        $citizenRate     = (float) PlatformSettingsService::get('citizen_revenue_per_view', null, 0.60);
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
            $tier === 'ballot_issue' ? 1.00 : 0.60
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

        if ($resolvedMediaUrl = $this->resolvePlayableCampaignMediaUrl($campaign)) {
            $campaign->media_url = $resolvedMediaUrl;
        }

        return view('standalone.citizen.campaigns.show', [
            'campaign' => $campaign,
        ]);
    }

    /** Preview the campaign as a voter will see it (draft-only). */
    public function reviewCampaign(CitizenCampaign $campaign)
    {
        $this->authorizeOwnership($campaign);

        $rawStatus = (string) ($campaign->getRawOriginal('status') ?? '');
        abort_unless(
            in_array($rawStatus, [CampaignStatus::Draft->value, CampaignStatus::Cancelled->value], true),
            403,
            'Only draft or cancelled campaigns can be reviewed.'
        );

        if (! $campaign->media_url && ! $campaign->live_feed_url) {
            return redirect()
                ->route('citizen.campaigns.show', $campaign)
                ->withErrors(['review' => 'Please upload a video or set a live stream URL before reviewing.']);
        }

        $duration  = (int) ($campaign->media_duration ?? 0);
        $mustWatch = (int) ($campaign->min_watch_time_percent ?? config('u9itus.min_watch_time_percent', 80));
        $payout    = (float) ($campaign->voter_payout_per_view ?? 0.50);

        $audienceCheck = $this->citizenIsInTargetAudience($campaign, Auth::user()?->citizen);

        if ($resolvedMediaUrl = $this->resolvePlayableCampaignMediaUrl($campaign)) {
            $campaign->media_url = $resolvedMediaUrl;
        }

        return view('standalone.citizen.campaigns.review', [
            'campaign'      => $campaign,
            'preview'       => true,
            'duration'      => $duration,
            'mustWatch'     => $mustWatch,
            'payout'        => $payout,
            'zipMatch'      => $audienceCheck['matches'],
            'zipMatchLabel' => $audienceCheck['label'],
            'zipMatchReason' => $audienceCheck['reason'],
        ]);
    }

    /**
     * Determine whether the current citizen's own zip falls inside the campaign's
     * target ZIP + radius. Used only for the review-page eligibility preview.
     *
     * @return array{matches: bool, label: string, reason: string}
     */
    private function citizenIsInTargetAudience(CitizenCampaign $campaign, ?Citizen $citizen): array
    {
        $targetZip = trim((string) ($campaign->target_zip ?? ''));
        $radius    = (int) ($campaign->target_zip_radius ?? 0);
        $citizenZip = trim((string) ($citizen?->zip ?? ''));

        if ($targetZip === '') {
            return [
                'matches' => true,
                'label'   => 'No target ZIP set',
                'reason'  => 'This campaign is not restricted to a specific ZIP code.',
            ];
        }

        if ($citizenZip === '') {
            return [
                'matches' => false,
                'label'   => 'Your ZIP unknown',
                'reason'  => 'We do not have a ZIP code on your citizen profile, so we cannot show whether you would be eligible.',
            ];
        }

        if ($radius <= 0) {
            $exactMatch = $citizenZip === $targetZip;
            return [
                'matches' => $exactMatch,
                'label'   => $exactMatch ? 'ZIP matches' : 'ZIP does not match',
                'reason'  => $exactMatch
                    ? "Your profile ZIP ({$citizenZip}) matches the campaign target."
                    : "Your profile ZIP ({$citizenZip}) does not match the campaign target ({$targetZip}).",
            ];
        }

        try {
            $zipCentroid = app(\App\Services\Marketing\ZipCentroidService::class);
            $center      = $zipCentroid->centroid($targetZip);
            $citizenCentroid = $zipCentroid->centroid($citizenZip);

            if ($center === null || $citizenCentroid === null) {
                $exactMatch = $citizenZip === $targetZip;
                return [
                    'matches' => $exactMatch,
                    'label'   => $exactMatch ? 'ZIP matches' : 'Distance unknown',
                    'reason'  => $exactMatch
                        ? "Your profile ZIP ({$citizenZip}) matches the campaign target."
                        : "Could not resolve the distance between your ZIP ({$citizenZip}) and the target ZIP ({$targetZip}).",
                ];
            }

            $distance = $zipCentroid->distanceMiles($center, $citizenCentroid);
            $matches  = $distance <= $radius;

            return [
                'matches' => $matches,
                'label'   => $matches ? 'Within target radius' : 'Outside target radius',
                'reason'  => sprintf(
                    'Your profile ZIP %s is %.1f miles from the target ZIP %s (radius: %d miles).',
                    $citizenZip,
                    $distance,
                    $targetZip,
                    $radius
                ),
            ];
        } catch (\Throwable $e) {
            return [
                'matches' => false,
                'label'   => 'Eligibility check unavailable',
                'reason'  => 'Unable to verify ZIP radius eligibility right now.',
            ];
        }
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

        $citizenRate     = (float) PlatformSettingsService::get('citizen_revenue_per_view', null, 0.60);
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
            $tier === 'ballot_issue' ? 1.00 : 0.60
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

    // ── Billing & Payment Methods ────────────────────────────────────────────

    /**
     * GET /citizen/billing
     * Credit balance, transaction history, saved cards, and add-funds UI.
     */
    public function billing()
    {
        $citizen = Auth::user()?->citizen;
        abort_unless($citizen, 403);

        $creditBalance = $citizen->credit_balance ?? 0;

        $credits = $citizen->credits()
            ->orderByDesc('created_at')
            ->paginate(15);

        $transactions = $citizen->transactions()
            ->orderByDesc('created_at')
            ->paginate(15);

        $savedPaymentMethods = $citizen->paymentMethods()
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->get();

        return view('standalone.citizen.billing', compact(
            'citizen', 'creditBalance', 'credits', 'transactions', 'savedPaymentMethods'
        ));
    }

    /**
     * POST /citizen/billing/add-funds
     * Create a Stripe PaymentIntent for citizen credit purchase.
     */
    public function addFunds(Request $request)
    {
        $citizen = Auth::user()?->citizen;
        abort_unless($citizen, 403);

        $validated = $request->validate([
            'amount'                  => ['required', 'numeric', 'min:10', 'max:10000'],
            'saved_payment_method_id' => ['nullable', 'integer'],
        ]);

        $options = ['description' => 'Credit top-up for citizen #' . $citizen->id];

        if (! empty($validated['saved_payment_method_id'])) {
            $savedPaymentMethod = CitizenPaymentMethod::where('id', (int) $validated['saved_payment_method_id'])
                ->where('citizen_id', $citizen->id)
                ->first();

            if ($savedPaymentMethod?->stripe_payment_method_id) {
                $options['payment_method_id'] = $savedPaymentMethod->stripe_payment_method_id;
            }
        }

        try {
            $intentData = $this->billing->createPurchaseIntent($citizen, (float) $validated['amount'], $options);
        } catch (\Exception $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['error' => $e->getMessage()], 500);
            }

            return back()->withErrors(['amount' => 'Payment service unavailable: ' . $e->getMessage()]);
        }

        $response = [
            'client_secret'       => $intentData['client_secret'],
            'payment_intent'      => $intentData['payment_intent_id'],
            'amount'              => $intentData['gross_amount'],
            'credits_amount'      => $intentData['credits_amount'],
            'stripe_fee'          => $intentData['stripe_fee'],
            'stripe_fee_percent'  => $intentData['stripe_fee_percent'],
            'publishable_key'     => config('services.stripe.public'),
            'return_url'          => route('citizen.billing.confirm'),
        ];

        if (! empty($options['payment_method_id'])) {
            $response['stripe_payment_method_id'] = $options['payment_method_id'];
        }

        return response()->json($response);
    }

    /**
     * GET /citizen/billing/confirm
     * Handle Stripe redirect after a PaymentIntent completes.
     */
    public function confirmPayment(Request $request)
    {
        $citizen = Auth::user()?->citizen;
        abort_unless($citizen, 403);

        $piId           = $request->query('payment_intent');
        $redirectStatus = $request->query('redirect_status');

        if ($piId && $redirectStatus === 'succeeded') {
            $tx        = null;
            $finalized = false;
            try {
                $tx        = $this->billing->finalizePaymentIntent($piId);
                $finalized = $tx !== null;
            } catch (\Exception $e) {
                Log::error('Citizen confirmPayment finalizePaymentIntent failed: ' . $e->getMessage());
            }

            // Ensure the PaymentIntent belongs to the authenticated citizen.
            if ($tx && (int) $tx->citizen_id !== (int) $citizen->id) {
                abort(403);
            }

            return redirect()->route('citizen.billing')
                ->with($finalized ? 'payment_confirmed' : 'payment_failed', true);
        }

        if (in_array($redirectStatus, ['failed', 'canceled'], true)) {
            return redirect()->route('citizen.billing')
                ->with('payment_failed', true);
        }

        return redirect()->route('citizen.billing');
    }

    /** POST /citizen/billing/update-receipt-email */
    public function updateReceiptEmail(Request $request)
    {
        $citizen = Auth::user()?->citizen;
        abort_unless($citizen, 403);

        $validated = $request->validate([
            'receipt_email' => ['nullable', 'email', 'max:255'],
        ]);

        $citizen->receipt_email = $validated['receipt_email'] ?? null;
        $citizen->saveQuietly();

        return back()->with('success', 'Receipt email updated.');
    }

    /** GET /citizen/billing/invoices */
    public function invoices()
    {
        $citizen = Auth::user()?->citizen;
        abort_unless($citizen, 403);

        $transactions = $citizen->transactions()
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('standalone.citizen.invoices', compact('citizen', 'transactions'));
    }

    /** POST /citizen/billing/setup-intent */
    public function createSetupIntent(Request $request)
    {
        $citizen = Auth::user()?->citizen;
        abort_unless($citizen, 403);

        $stripe = app(\App\Services\StripePaymentService::class);
        try {
            $customerId = $stripe->ensureCustomer($citizen);
            if (! $customerId) {
                return response()->json(['error' => 'Payment service unavailable.'], 500);
            }

            $setupIntent = $stripe->createSetupIntent($customerId);

            return response()->json([
                'client_secret'   => $setupIntent->client_secret,
                'publishable_key' => config('services.stripe.public'),
            ]);
        } catch (\Exception $e) {
            Log::error('Citizen createSetupIntent failed: ' . $e->getMessage());
            return response()->json(['error' => 'Could not initialize card setup.'], 500);
        }
    }

    /** POST /citizen/billing/payment-methods */
    public function storePaymentMethod(Request $request)
    {
        $citizen = Auth::user()?->citizen;
        abort_unless($citizen, 403);

        $validated = $request->validate([
            'payment_method_id' => ['required', 'string', 'regex:/^pm_/'],
        ]);

        $stripe = app(\App\Services\StripePaymentService::class);

        try {
            $paymentMethod = $stripe->retrievePaymentMethod($validated['payment_method_id']);
            $customerId = $stripe->ensureCustomer($citizen);

            if ((string) ($paymentMethod->customer ?? '') !== (string) $customerId) {
                return response()->json(['error' => 'Invalid payment method.'], 422);
            }

            $existing = CitizenPaymentMethod::where('citizen_id', $citizen->id)
                ->where('stripe_payment_method_id', $paymentMethod->id)
                ->first();

            if ($existing) {
                return response()->json([
                    'message' => 'Card already saved.',
                    'id'      => $existing->id,
                ]);
            }

            $card  = $paymentMethod->card ?? null;
            $brand = $card ? ucfirst((string) ($card->brand ?? 'card')) : 'Card';
            $last4 = $card ? (string) ($card->last4 ?? '????') : '????';
            $exp   = $card ? ((string) ($card->exp_month ?? '')) . '/' . ((string) ($card->exp_year ?? '')) : '';
            $label = "{$brand} •••• {$last4}" . ($exp !== '/' ? " expires {$exp}" : '');

            $isDefault = ! CitizenPaymentMethod::where('citizen_id', $citizen->id)->exists();

            $saved = CitizenPaymentMethod::create([
                'citizen_id'               => $citizen->id,
                'stripe_customer_id'       => $customerId,
                'stripe_payment_method_id' => $paymentMethod->id,
                'label'                    => $label,
                'is_default'               => $isDefault,
            ]);

            Log::info('Saved payment method for citizen', [
                'citizen_id' => $citizen->id,
                'pm_id'      => $paymentMethod->id,
                'label'      => $label,
            ]);

            return response()->json([
                'message'    => 'Card saved.',
                'id'         => $saved->id,
                'label'      => $label,
                'is_default' => $saved->is_default,
            ]);
        } catch (\Exception $e) {
            Log::error('Citizen storePaymentMethod failed: ' . $e->getMessage());
            return response()->json(['error' => 'Could not save payment method.'], 500);
        }
    }

    /** DELETE /citizen/billing/payment-methods/{paymentMethod} */
    public function deletePaymentMethod(CitizenPaymentMethod $paymentMethod)
    {
        $citizen = Auth::user()?->citizen;
        abort_unless($citizen && (int) $citizen->id === (int) $paymentMethod->citizen_id, 403);

        $stripe = app(\App\Services\StripePaymentService::class);

        try {
            if ($paymentMethod->stripe_payment_method_id) {
                $stripe->detachPaymentMethod($paymentMethod->stripe_payment_method_id);
            }
        } catch (\Exception $e) {
            Log::warning('Citizen Stripe detach failed (continuing delete): ' . $e->getMessage());
        }

        $wasDefault = $paymentMethod->is_default;
        $citizenId  = $paymentMethod->citizen_id;

        $paymentMethod->delete();

        if ($wasDefault) {
            $next = CitizenPaymentMethod::where('citizen_id', $citizenId)->first();
            $next?->update(['is_default' => true]);
        }

        return response()->json(['message' => 'Card removed.']);
    }

    /** GET /citizen/billing/invoices/{transaction}/details */
    public function invoiceDetails(CitizenTransaction $transaction)
    {
        $citizen = Auth::user()?->citizen;
        abort_unless($citizen && (int) $transaction->citizen_id === (int) $citizen->id, 403);

        return response()->json([
            'data' => [
                'uuid'                   => $transaction->uuid,
                'date'                   => $transaction->created_at?->toIso8601String(),
                'description'            => $transaction->description,
                'transaction_type'       => $transaction->transaction_type,
                'status'                 => $transaction->status,
                'amount'                 => $transaction->amount,
                'currency'               => $transaction->currency,
                'stripe_payment_intent_id' => $transaction->stripe_payment_intent_id,
                'metadata'               => collect($transaction->metadata)->except(['client_secret'])->toArray(),
                'citizen_id'             => $transaction->citizen_id,
            ],
        ]);
    }

    /** POST /citizen/billing/invoices/{transaction}/send-receipt */
    public function sendReceipt(CitizenTransaction $transaction)
    {
        $citizen = Auth::user()?->citizen;
        abort_unless($citizen && (int) $transaction->citizen_id === (int) $citizen->id, 403);

        $this->billing->sendCreditsPurchaseReceiptForTransaction($transaction);

        return back()->with('success', 'Receipt sent.');
    }
}
