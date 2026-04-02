<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        $campaign = $this->route('campaign');
        $politician = auth()->user()?->politician;
        return $politician && $campaign && (int) $campaign->politician_id === (int) $politician->id;
    }

    public function rules(): array
    {
        $minBudget = (float) config('u9itus.revenue_per_view', 1.00) * 10;
        $governanceLevels = implode(',', array_keys(config('u9itus.governance_levels', [])));
        $videoMimeTypes = ['video/mp4', 'video/webm'];

        if ($this->isIosClient()) {
            $videoMimeTypes[] = 'video/quicktime';
        }

        return [
            'title'                    => ['sometimes', 'required', 'string', 'max:255'],
            'message_summary'          => ['nullable', 'string', 'max:2000'],
            'campaign_type'            => ['sometimes', 'required', 'in:video,live_feed,q_and_a'],
            'governance_level'         => ['sometimes', 'required', 'string', 'in:' . $governanceLevels],
            'media_url'                => ['nullable', 'url'],
            'media_type'               => ['nullable', 'in:youtube,vimeo,direct_file,s3_cloudfront,hls_stream'],
            'video'                    => ['nullable', 'file', 'mimetypes:' . implode(',', $videoMimeTypes), 'max:' . ((int) config('u9itus.max_video_size_mb', 100) * 1024)],
            'total_budget'             => ['sometimes', 'required', 'numeric', 'min:' . number_format($minBudget, 2, '.', '')],
            'total_views_requested'    => ['sometimes', 'required', 'integer', 'min:10'],
            'target_states'            => ['nullable', 'array'],
            'target_states.*'          => ['string', 'max:2'],
            'target_cities'            => ['nullable', 'array'],
            'target_cities.*'          => ['string', 'max:100'],
            'target_districts'         => ['nullable', 'array'],
            'target_districts.*'       => ['string', 'max:100'],
            'target_governance_levels' => ['nullable', 'array'],
            'min_watch_time_percent'   => ['nullable', 'integer', 'min:50', 'max:100'],
            'live_scheduled_at'          => ['nullable', 'date', 'after:now'],
            'live_feed_url'              => ['nullable', 'url'],
            // Phase 14 — Scheduling
            'scheduled_start_at'         => ['nullable', 'date'],
            'scheduled_end_at'           => ['nullable', 'date', 'after:scheduled_start_at'],
            // Phase 14 — Repeat Viewing
            'allow_repeat_views'         => ['nullable', 'boolean'],
            'repeat_view_cooldown_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'max_views_per_voter'        => ['nullable', 'integer', 'min:1', 'max:10'],
            // Phase 3 (Sprint 3) — Topics and Q&A
            'topic_ids'                  => ['nullable', 'array', 'max:5'],
            'topic_ids.*'                => ['integer', 'exists:politician_topics,id'],
            'intro_text'                 => ['nullable', 'string', 'max:1000'],
            'qa_items'                   => ['nullable', 'array'],
            'qa_items.*.question'        => ['nullable', 'string', 'max:500'],
            'qa_items.*.answer'          => ['nullable', 'string', 'max:2000'],
            'engagement_survey'          => ['nullable', 'array'],
            'engagement_survey.question' => ['nullable', 'string', 'max:200'],
            'engagement_survey.options'  => ['nullable', 'array'],
            'engagement_survey.options.*.text'  => ['nullable', 'string', 'max:100'],
            'engagement_survey.options.*.value' => ['nullable', 'string', 'max:10'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
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
