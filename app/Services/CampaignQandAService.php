<?php

namespace App\Services;

use App\Models\PoliticalCampaign;
use Illuminate\Support\Collection;

/**
 * Service for managing Q&A campaign content and topic associations.
 * Handles Q&A pair validation, storage, and profile presentation.
 */
class CampaignQandAService
{
    /**
     * Parse and validate Q&A items from JSON string or array.
     *
     * @param string|array|null $qaData
     * @return array|null Validated Q&A pairs or null
     */
    public function parseQAItems($qaData): ?array
    {
        if (empty($qaData)) {
            return null;
        }

        // Convert JSON string to array if needed
        if (is_string($qaData)) {
            $qaData = json_decode($qaData, true);
        }

        if (!is_array($qaData)) {
            return null;
        }

        // Validate structure and sanitize
        $validated = [];
        foreach ($qaData as $item) {
            if (isset($item['question'], $item['answer'])
                && is_string($item['question'])
                && is_string($item['answer'])
                && !empty(trim($item['question']))
                && !empty(trim($item['answer']))) {
                $validated[] = [
                    'question' => trim($item['question']),
                    'answer' => trim($item['answer']),
                ];
            }
        }

        return count($validated) > 0 ? $validated : null;
    }

    /**
     * Parse and validate engagement survey payload.
     *
     * @param string|array|null $surveyData
     * @return array|null Validated survey structure or null
     */
    public function parseEngagementSurvey($surveyData): ?array
    {
        if (empty($surveyData)) {
            return null;
        }

        // Convert JSON string to array if needed
        if (is_string($surveyData)) {
            $surveyData = json_decode($surveyData, true);
        }

        if (!is_array($surveyData)) {
            return null;
        }

        // Validate required fields
        if (!isset($surveyData['question']) || !is_string($surveyData['question'])) {
            return null;
        }

        if (!isset($surveyData['options']) || !is_array($surveyData['options']) || count($surveyData['options']) < 2) {
            return null;
        }

        // Validate options
        $options = [];
        foreach ($surveyData['options'] as $opt) {
            if (isset($opt['text'], $opt['value']) && is_string($opt['text']) && is_string($opt['value'])) {
                $options[] = [
                    'text' => trim($opt['text']),
                    'value' => trim($opt['value']),
                ];
            }
        }

        if (count($options) < 2) {
            return null;
        }

        return [
            'question' => trim($surveyData['question']),
            'options' => $options,
            'cta_text' => $surveyData['cta_text'] ?? 'Vote',
            'cta_url' => $surveyData['cta_url'] ?? null,
        ];
    }

    /**
     * Sync campaign topics from topic IDs array.
     *
     * @param PoliticalCampaign $campaign
     * @param array|null $topicIds
     * @return void
     */
    public function syncTopics(PoliticalCampaign $campaign, ?array $topicIds): void
    {
        if (empty($topicIds)) {
            $campaign->topics()->detach();
            return;
        }

        $campaign->topics()->sync($topicIds);
    }

    /**
     * Get formatted Q&A content for public display.
     *
     * @param PoliticalCampaign $campaign
     * @return array{intro: string|null, items: array, count: int}
     */
    public function getQADisplayData(PoliticalCampaign $campaign): array
    {
        return [
            'intro' => $campaign->intro_text,
            'items' => $campaign->qa_items ?? [],
            'count' => count($campaign->qa_items ?? []),
            'has_content' => ($campaign->campaign_type === 'q_and_a') 
                && (!empty($campaign->intro_text) || !empty($campaign->qa_items)),
        ];
    }

    /**
     * Get topics as display list with icon.
     *
     * @param PoliticalCampaign $campaign
     * @return Collection
     */
    public function getTopicsForDisplay(PoliticalCampaign $campaign): Collection
    {
        return $campaign->topics()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($topic) => [
                'id' => $topic->id,
                'name' => $topic->name,
                'slug' => $topic->slug,
                'icon' => $topic->icon,
            ]);
    }
}
