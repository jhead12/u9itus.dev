<?php

namespace App\Http\Requests;

use App\Services\PlatformSettingsService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates campaign draft save requests.
 *
 * Draft mode allows saving incomplete campaigns with minimal validation.
 * Most fields are optional to allow users to save progress at any stage.
 */
class SaveCampaignDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $minDuration = max(1, (int) PlatformSettingsService::get('min_video_duration', null, (int) config('u9itus.min_video_duration', 10)));
        $maxDuration = max($minDuration, (int) PlatformSettingsService::get('max_video_duration', null, (int) config('u9itus.max_video_duration', 180)));

        return [
            'title'                 => 'nullable|string|max:255',
            'message_summary'       => 'nullable|string|max:2000',
            'video_blurb'           => 'nullable|string|max:5000',
            'campaign_type'         => 'nullable|in:video,live_feed,q_and_a',
            'governance_level'      => 'nullable|string|in:' . implode(',', array_keys(config('u9itus.governance_levels', []))),
            'media_url'             => 'nullable|url',
            'media_duration'        => "nullable|integer|min:{$minDuration}|max:{$maxDuration}",
            'live_feed_url'         => 'nullable|url',
            'live_scheduled_at'     => 'nullable|date|after:now',
            'total_budget'          => 'nullable|numeric|min:0',
            'total_views_requested' => 'nullable|integer|min:0',
            'target_states'         => 'nullable|array',
            'target_states.*'       => 'string|max:2',
            'target_cities'         => 'nullable|array',
            'target_cities.*'       => 'string|max:255',
            'target_districts'           => 'nullable|array',
            'target_districts.*'         => 'string|max:255',
            'scheduled_start_at'         => 'nullable|date|after:now',
            'scheduled_end_at'           => 'nullable|date|after:scheduled_start_at',
            'allow_repeat_views'         => 'nullable|boolean',
            'repeat_view_cooldown_hours' => 'nullable|integer|min:1|max:720',
            'max_views_per_voter'        => 'nullable|integer|min:1|max:10',
        ];
    }
}
