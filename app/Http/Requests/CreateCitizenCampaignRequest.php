<?php

namespace App\Http\Requests;

use App\Enums\CitizenAdType;
use App\Services\PlatformSettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates citizen campaign creation requests.
 *
 * Business rules (per doc/Creative.md Sprint 7.5):
 *   - Ad type drives pricing tier: ballot_issue → $1.00/view, all others → $0.75/view.
 *   - Minimum budget: 10 views × tier-scoped rate.
 *   - Minimum views: 10.
 *   - Video duration: 10–180 seconds (shared platform settings).
 *   - campaign_type is restricted to video|live_feed (no Q&A for citizens).
 *   - target_zip + optional radius replace politician-only governance_level targeting.
 *   - pac_registration_id required only for ballot_issue.
 */
class CreateCitizenCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware (role:citizen)
    }

    /**
     * Checkboxes are omitted from the request when unchecked, so normalize
     * the repeat-view toggle to a boolean up front (lets a sponsor disable it).
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'allow_repeat_views' => $this->boolean('allow_repeat_views'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tier           = $this->citizenPricingTier();
        $revenuePerView = $this->revenuePerViewForTier($tier);
        $minBudget      = $revenuePerView * 10;
        $minDuration    = max(1, (int) PlatformSettingsService::get('min_video_duration', null, (int) config('u9itus.min_video_duration', 10)));
        $maxDuration    = max($minDuration, (int) PlatformSettingsService::get('max_video_duration', null, (int) config('u9itus.max_video_duration', 180)));
        $videoMimeTypes = ['video/mp4', 'video/webm'];

        if ($this->isIosClient()) {
            $videoMimeTypes[] = 'video/quicktime';
        }

        $mediaDurationRules = ['nullable', 'integer'];
        if (! $this->hasFile('video')) {
            $mediaDurationRules[] = 'min:' . $minDuration;
            $mediaDurationRules[] = 'max:' . $maxDuration;
        }

        $adTypes = implode(',', array_map(fn (CitizenAdType $c) => $c->value, CitizenAdType::cases()));

        return [
            'title'                 => 'required|string|max:255',
            'message_summary'       => 'nullable|string|max:2000',
            'video_blurb'           => 'nullable|string|max:5000',
            'call_to_action_url'    => 'nullable|url|max:2048',
            'call_to_action_label'  => 'nullable|string|max:60',
            'campaign_type'         => 'required|in:video,live_feed',
            'citizen_ad_type'       => 'required|in:' . $adTypes,
            'media_url'             => 'nullable|url',
            'media_type'            => 'nullable|in:youtube,vimeo,direct_file,s3_cloudfront,hls_stream',
            'video'                 => 'nullable|file|mimetypes:' . implode(',', $videoMimeTypes) . '|max:' . ((int) config('u9itus.max_video_size_mb', 1024) * 1024),
            'media_duration'        => $mediaDurationRules,
            'live_feed_url'         => 'required_if:campaign_type,live_feed|nullable|url',
            'live_scheduled_at'     => 'required_if:campaign_type,live_feed|nullable|date|after:now',
            'total_budget'          => "required|numeric|min:{$minBudget}",
            'total_views_requested' => 'required|integer|min:10',
            'target_zip'            => 'required|digits:5',
            'target_zip_radius'     => 'nullable|integer|min:1|max:100',
            'pac_registration_id'   => 'required_if:citizen_ad_type,ballot_issue|nullable|string|min:3|max:50',
            'daily_view_cap'        => 'nullable|integer|min:10|max:5000',
            // Scheduling
            'scheduled_start_at'         => 'nullable|date|after:now',
            'scheduled_end_at'           => 'nullable|date|after:scheduled_start_at',
            // Repeat Viewing
            'allow_repeat_views'         => 'nullable|boolean',
            'repeat_view_cooldown_hours' => 'nullable|integer|min:1|max:720',
            'max_views_per_voter'        => 'nullable|integer|min:1|max:10',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $tier        = $this->citizenPricingTier();
        $minBudget   = $this->revenuePerViewForTier($tier) * 10;
        $minDuration = max(1, (int) PlatformSettingsService::get('min_video_duration', null, (int) config('u9itus.min_video_duration', 10)));
        $maxDuration = max($minDuration, (int) PlatformSettingsService::get('max_video_duration', null, (int) config('u9itus.max_video_duration', 180)));

        return [
            'campaign_type.in'          => 'Citizen campaigns must be either video or live_feed.',
            'total_budget.min'          => 'Minimum campaign budget is $' . $minBudget . ' (10 views at the ' . $tier . ' rate).',
            'total_views_requested.min' => 'You must request at least 10 views.',
            'media_duration.min'        => 'Video must be at least ' . $minDuration . ' seconds.',
            'media_duration.max'        => 'Video cannot exceed ' . $maxDuration . ' seconds.',
            'target_zip.digits'         => 'Target ZIP must be exactly 5 digits.',
            'pac_registration_id.required_if' => 'Ballot-issue campaigns require a PAC registration ID.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $campaignType    = (string) $this->input('campaign_type', '');
            $needsVideoAsset = $campaignType === 'video';

            if ($needsVideoAsset && ! $this->filled('media_url') && ! $this->hasFile('video')) {
                $validator->errors()->add('media_url', 'Provide a video URL or upload a video file.');
            }

            $video = $this->file('video');
            if ($video && ! $this->isIosClient() && $video->getMimeType() === 'video/quicktime') {
                $validator->errors()->add('video', 'MOV uploads are only allowed from iOS devices. Use MP4 or WebM on non-iOS devices.');
            }
        });
    }

    /**
     * Resolve the pricing tier from the submitted citizen_ad_type.
     */
    protected function citizenPricingTier(): string
    {
        return $this->input('citizen_ad_type') === CitizenAdType::BallotIssue->value
            ? 'ballot_issue'
            : 'citizen';
    }

    protected function revenuePerViewForTier(string $tier): float
    {
        return (float) PlatformSettingsService::get(
            $tier . '_revenue_per_view',
            null,
            $tier === 'ballot_issue' ? 1.00 : 0.75
        );
    }

    private function isIosClient(): bool
    {
        $ua = $this->userAgent() ?? '';
        return preg_match('/\b(iPhone|iPad|iPod)\b/i', $ua) === 1;
    }
}
