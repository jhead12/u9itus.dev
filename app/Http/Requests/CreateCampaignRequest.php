<?php

namespace App\Http\Requests;

use App\Services\PlatformSettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates campaign creation requests.
 *
 * Business rules:
 *   - Minimum budget: 10 views × configured rate
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
        $revenuePerView = (float) PlatformSettingsService::get('revenue_per_view', null, (float) config('u9itus.revenue_per_view', 1.00));
        $minBudget   = $revenuePerView * 10;
        $minDuration = config('u9itus.min_video_duration', 30);
        $maxDuration = config('u9itus.max_video_duration', 300);

        return [
            'title'                 => 'required|string|max:255',
            'message_summary'       => 'nullable|string|max:2000',
            'campaign_type'         => 'required|in:video,live_feed,q_and_a',
            'governance_level'      => 'nullable|string|in:' . implode(',', array_keys(config('u9itus.governance_levels', []))),
            'media_url'             => 'nullable|url',
            'media_type'            => 'nullable|in:youtube,vimeo,direct_file,s3_cloudfront,hls_stream',
            'video'                 => 'nullable|file|mimetypes:video/mp4,video/quicktime,video/webm|max:' . ((int) config('u9itus.max_video_size_mb', 100) * 1024),
            'media_duration'        => "nullable|integer|min:{$minDuration}|max:{$maxDuration}",
            'live_feed_url'         => 'required_if:campaign_type,live_feed|nullable|url',
            'live_scheduled_at'     => 'required_if:campaign_type,live_feed|nullable|date|after:now',
            'total_budget'          => "required|numeric|min:{$minBudget}",
            'total_views_requested' => 'required|integer|min:10',
            'target_states'         => 'nullable|array',
            'target_states.*'       => 'string|max:2',
            'target_cities'         => 'nullable|array',
            'target_cities.*'       => 'string|max:255',
            'target_districts'           => 'nullable|array',
            'target_districts.*'         => 'string|max:255',
            // Phase 14 — Scheduling
            'scheduled_start_at'         => 'nullable|date|after:now',
            'scheduled_end_at'           => 'nullable|date|after:scheduled_start_at',
            // Phase 14 — Repeat Viewing
            'allow_repeat_views'         => 'nullable|boolean',
            'repeat_view_cooldown_hours' => 'nullable|integer|min:1|max:720',
            'max_views_per_voter'        => 'nullable|integer|min:1|max:10',
            // Phase 3 (Sprint 3) — Topics and Q&A
            'topic_ids'                  => 'nullable|array|max:5',
            'topic_ids.*'                => 'integer|exists:politician_topics,id',
            'intro_text'                 => 'nullable|string|max:1000',
            'qa_items'                   => 'nullable|json',
            'engagement_survey'          => 'nullable|json',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $minBudget = (float) PlatformSettingsService::get('revenue_per_view', null, (float) config('u9itus.revenue_per_view', 1.00)) * 10;

        return [
            'total_budget.min'          => 'Minimum campaign budget is $' . $minBudget . ' (10 views).',
            'total_views_requested.min' => 'You must request at least 10 views.',
            'media_duration.min'        => 'Video must be at least ' . config('u9itus.min_video_duration', 30) . ' seconds.',
            'media_duration.max'        => 'Video cannot exceed ' . config('u9itus.max_video_duration', 300) . ' seconds.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $campaignType = (string) $this->input('campaign_type', '');
            $needsVideoAsset = in_array($campaignType, ['video', 'q_and_a'], true);

            // Media asset requirement
            if ($needsVideoAsset && ! $this->filled('media_url') && ! $this->hasFile('video')) {
                $validator->errors()->add('media_url', 'Provide a video URL or upload a video file.');
            }

            // Q&A items structure validation
            if ($this->filled('qa_items')) {
                $qaItems = json_decode($this->input('qa_items'), true);
                if (! is_array($qaItems)) {
                    $validator->errors()->add('qa_items', 'Q&A items must be a valid JSON array.');
                } else {
                    foreach ($qaItems as $idx => $item) {
                        if (! isset($item['question']) || ! isset($item['answer'])) {
                            $validator->errors()->add('qa_items', "Q&A pair at index {$idx} must have 'question' and 'answer' fields.");
                            break;
                        }
                        if (! is_string($item['question']) || ! is_string($item['answer'])) {
                            $validator->errors()->add('qa_items', "Q&A pair at index {$idx} must have string values for 'question' and 'answer'.");
                            break;
                        }
                    }
                }
            }

            // Engagement survey validation
            if ($this->filled('engagement_survey')) {
                $survey = json_decode($this->input('engagement_survey'), true);
                if (! is_array($survey)) {
                    $validator->errors()->add('engagement_survey', 'Engagement survey must be valid JSON.');
                } else {
                    if (! isset($survey['question'])) {
                        $validator->errors()->add('engagement_survey', 'Survey must have a "question" field.');
                    }
                    if (! isset($survey['options']) || ! is_array($survey['options']) || count($survey['options']) < 2) {
                        $validator->errors()->add('engagement_survey', 'Survey must have at least 2 options.');
                    }
                }
            }
        });
    }
}
