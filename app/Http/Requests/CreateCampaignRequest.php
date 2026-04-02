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
        $minDuration = max(30, (int) config('u9itus.min_video_duration', 30));
        $maxDuration = max(60, (int) config('u9itus.max_video_duration', 300), $minDuration);
        $videoMimeTypes = ['video/mp4', 'video/webm'];

        if ($this->isIosClient()) {
            $videoMimeTypes[] = 'video/quicktime';
        }

        return [
            'title'                 => 'required|string|max:255',
            'message_summary'       => 'nullable|string|max:2000',
            'campaign_type'         => 'required|in:video,live_feed,q_and_a',
            'governance_level'      => 'required|string|in:' . implode(',', array_keys(config('u9itus.governance_levels', []))),
            'media_url'             => 'nullable|url',
            'media_type'            => 'nullable|in:youtube,vimeo,direct_file,s3_cloudfront,hls_stream',
            'video'                 => 'nullable|file|mimetypes:' . implode(',', $videoMimeTypes) . '|max:' . ((int) config('u9itus.max_video_size_mb', 1024) * 1024),
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
            'qa_items'                   => 'nullable|array',
            'qa_items.*.question'        => 'nullable|string|max:500',
            'qa_items.*.answer'          => 'nullable|string|max:2000',
            'engagement_survey'          => 'nullable|array',
            'engagement_survey.question' => 'nullable|string|max:200',
            'engagement_survey.options'  => 'nullable|array',
            'engagement_survey.options.*.text'  => 'nullable|string|max:100',
            'engagement_survey.options.*.value' => 'nullable|string|max:10',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $minBudget = (float) PlatformSettingsService::get('revenue_per_view', null, (float) config('u9itus.revenue_per_view', 1.00)) * 10;
        $minDuration = max(30, (int) config('u9itus.min_video_duration', 30));
        $maxDuration = max(60, (int) config('u9itus.max_video_duration', 300), $minDuration);

        return [
            'total_budget.min'          => 'Minimum campaign budget is $' . $minBudget . ' (10 views).',
            'total_views_requested.min' => 'You must request at least 10 views.',
            'media_duration.min'        => 'Video must be at least ' . $minDuration . ' seconds.',
            'media_duration.max'        => 'Video cannot exceed ' . $maxDuration . ' seconds.',
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

            $video = $this->file('video');
            if ($video && ! $this->isIosClient() && $video->getMimeType() === 'video/quicktime') {
                $validator->errors()->add('video', 'MOV uploads are only allowed from iOS devices. Use MP4 or WebM on non-iOS devices.');
            }

            $this->validateQaItems($validator);
            $this->validateEngagementSurvey($validator);
        });
    }

    private function validateQaItems(Validator $validator): void
    {
        $qaItems = $this->parseArrayInput($this->input('qa_items'));
        if ($this->has('qa_items') && ! is_array($qaItems)) {
            $validator->errors()->add('qa_items', 'Q&A items must be valid structured data.');
            return;
        }

        if (! is_array($qaItems)) {
            return;
        }

        $nonEmptyItems = collect($qaItems)
            ->filter(fn ($item) => is_array($item))
            ->map(fn ($item) => [
                'question' => trim((string) ($item['question'] ?? '')),
                'answer'   => trim((string) ($item['answer'] ?? '')),
            ])
            ->filter(fn ($item) => $item['question'] !== '' || $item['answer'] !== '')
            ->values();

        foreach ($nonEmptyItems as $idx => $item) {
            if ($item['question'] === '' || $item['answer'] === '') {
                $validator->errors()->add('qa_items', "Q&A pair at index {$idx} must include both a question and an answer.");
                break;
            }
        }
    }

    private function validateEngagementSurvey(Validator $validator): void
    {
        $survey = $this->parseArrayInput($this->input('engagement_survey'));
        if ($this->has('engagement_survey') && ! is_array($survey)) {
            $validator->errors()->add('engagement_survey', 'Engagement survey must be valid structured data.');
            return;
        }

        if (! is_array($survey)) {
            return;
        }

        $question = trim((string) ($survey['question'] ?? ''));
        $options = collect($survey['options'] ?? [])
            ->filter(fn ($option) => is_array($option))
            ->map(fn ($option) => trim((string) ($option['text'] ?? '')))
            ->filter(fn ($text) => $text !== '')
            ->values();

        // Survey is optional. Ignore untouched/default empty survey fields.
        if ($question === '' && $options->isEmpty()) {
            return;
        }

        if ($question === '') {
            $validator->errors()->add('engagement_survey', 'Survey question is required when adding a survey.');
        }

        if ($options->count() < 2) {
            $validator->errors()->add('engagement_survey', 'Survey must have at least 2 answer options.');
        }
    }

    /**
     * @param mixed $input
     * @return array<int|string, mixed>|null
     */
    private function parseArrayInput($input): ?array
    {
        $parsed = null;

        if (is_array($input)) {
            $parsed = $input;
        } elseif (is_string($input)) {
            $trimmed = trim($input);
            if ($trimmed !== '') {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    $parsed = $decoded;
                }
            }
        }

        return $parsed;
    }

    private function isIosClient(): bool
    {
        $ua = $this->userAgent() ?? '';
        return preg_match('/\b(iPhone|iPad|iPod)\b/i', $ua) === 1;
    }
}
