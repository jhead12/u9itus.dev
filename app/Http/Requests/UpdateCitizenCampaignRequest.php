<?php

namespace App\Http\Requests;

use App\Enums\CitizenAdType;
use App\Models\CitizenCampaign;
use App\Services\PlatformSettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates citizen campaign update requests (draft-only edits).
 *
 * See CreateCitizenCampaignRequest for the shared business rules.
 */
class UpdateCitizenCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CitizenCampaign|null $campaign */
        $campaign = $this->route('campaign');
        $citizen  = auth()->user()?->citizen;

        return $citizen && $campaign && (int) $campaign->citizen_id === (int) $citizen->id;
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
            'title'                 => ['sometimes', 'required', 'string', 'max:255'],
            'message_summary'       => ['nullable', 'string', 'max:2000'],
            'video_blurb'           => ['nullable', 'string', 'max:5000'],
            'call_to_action_url'    => ['nullable', 'url', 'max:2048'],
            'call_to_action_label'  => ['nullable', 'string', 'max:60'],
            'campaign_type'         => ['sometimes', 'required', 'in:video,live_feed'],
            'citizen_ad_type'       => ['sometimes', 'required', 'in:' . $adTypes],
            'media_url'             => ['nullable', 'url'],
            'media_type'            => ['nullable', 'in:youtube,vimeo,direct_file,s3_cloudfront,hls_stream'],
            'video'                 => ['nullable', 'file', 'mimetypes:' . implode(',', $videoMimeTypes), 'max:' . ((int) config('u9itus.max_video_size_mb', 1024) * 1024)],
            'media_duration'        => $mediaDurationRules,
            'live_feed_url'         => ['nullable', 'url'],
            'live_scheduled_at'     => ['nullable', 'date', 'after:now'],
            'total_budget'          => ['sometimes', 'required', 'numeric', 'min:' . number_format($minBudget, 2, '.', '')],
            'total_views_requested' => ['sometimes', 'required', 'integer', 'min:10'],
            'target_zip'            => ['sometimes', 'required', 'digits:5'],
            'target_zip_radius'     => ['nullable', 'integer', 'min:1', 'max:100'],
            'pac_registration_id'   => ['required_if:citizen_ad_type,ballot_issue', 'nullable', 'string', 'min:3', 'max:50'],
            'daily_view_cap'        => ['nullable', 'integer', 'min:10', 'max:5000'],
            'min_watch_time_percent'     => ['nullable', 'integer', 'min:50', 'max:100'],
            'scheduled_start_at'         => ['nullable', 'date'],
            'scheduled_end_at'           => ['nullable', 'date', 'after:scheduled_start_at'],
            'allow_repeat_views'         => ['nullable', 'boolean'],
            'repeat_view_cooldown_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'max_views_per_voter'        => ['nullable', 'integer', 'min:1', 'max:10'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $video = $this->file('video');
            if ($video && ! $this->isIosClient() && $video->getMimeType() === 'video/quicktime') {
                $validator->errors()->add('video', 'MOV uploads are only allowed from iOS devices. Use MP4 or WebM on non-iOS devices.');
            }
        });
    }

    protected function citizenPricingTier(): string
    {
        /** @var CitizenCampaign|null $campaign */
        $campaign = $this->route('campaign');
        $adType   = $this->input('citizen_ad_type', $campaign?->citizen_ad_type?->value);

        return $adType === CitizenAdType::BallotIssue->value ? 'ballot_issue' : 'citizen';
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
