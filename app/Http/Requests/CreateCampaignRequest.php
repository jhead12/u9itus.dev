<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates campaign creation requests.
 *
 * Business rules:
 *   - Minimum budget: 10 views × $0.60 = $6
 *   - Minimum views: 10
 *   - Video duration: 30–300 seconds
 */
class CreateCampaignRequest extends FormRequest
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
        $minBudget   = config('u9itus.revenue_per_view', 0.60) * 10;
        $minDuration = config('u9itus.min_video_duration', 30);
        $maxDuration = config('u9itus.max_video_duration', 300);

        return [
            'title'                 => 'required|string|max:255',
            'message_summary'       => 'nullable|string|max:2000',
            'campaign_type'         => 'required|in:video,live_feed',
            'governance_level'      => 'nullable|string|in:' . implode(',', array_keys(config('u9itus.governance_levels', []))),
            'media_url'             => 'required_if:campaign_type,video|nullable|url',
            'media_duration'        => "required_if:campaign_type,video|nullable|integer|min:{$minDuration}|max:{$maxDuration}",
            'live_feed_url'         => 'required_if:campaign_type,live_feed|nullable|url',
            'live_scheduled_at'     => 'required_if:campaign_type,live_feed|nullable|date|after:now',
            'total_budget'          => "required|numeric|min:{$minBudget}",
            'total_views_requested' => 'required|integer|min:10',
            'target_states'         => 'nullable|array',
            'target_states.*'       => 'string|max:2',
            'target_cities'         => 'nullable|array',
            'target_cities.*'       => 'string|max:255',
            'target_districts'      => 'nullable|array',
            'target_districts.*'    => 'string|max:255',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'total_budget.min'          => 'Minimum campaign budget is $' . (config('u9itus.revenue_per_view', 0.60) * 10) . ' (10 views).',
            'total_views_requested.min' => 'You must request at least 10 views.',
            'media_duration.min'        => 'Video must be at least ' . config('u9itus.min_video_duration', 30) . ' seconds.',
            'media_duration.max'        => 'Video cannot exceed ' . config('u9itus.max_video_duration', 300) . ' seconds.',
        ];
    }
}
