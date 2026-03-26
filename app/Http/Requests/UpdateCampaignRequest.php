<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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

        return [
            'title'                    => ['sometimes', 'required', 'string', 'max:255'],
            'message_summary'          => ['nullable', 'string', 'max:2000'],
            'campaign_type'            => ['sometimes', 'required', 'in:video,live_feed,q_and_a'],
            'governance_level'         => ['nullable', 'string', 'max:100'],
            'video'                    => ['nullable', 'file', 'mimetypes:video/mp4,video/quicktime,video/webm', 'max:' . ((int) config('u9itus.max_video_size_mb', 100) * 1024)],
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
        ];
    }
}
