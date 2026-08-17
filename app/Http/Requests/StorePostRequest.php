<?php

namespace App\Http\Requests;

use App\Models\PoliticianTopic;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a new blog post.
 */
class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $topicIds = PoliticianTopic::pluck('id')->implode(',');

        return [
            'title' => 'required|string|max:120',
            'subtitle' => 'nullable|string|max:255',
            'excerpt' => 'nullable|string|max:500',
            'body' => 'nullable|string|max:50000',
            'featured_image_url' => 'nullable|url|max:2048',
            'featured_image_file' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'topic_ids' => 'nullable|array',
            'topic_ids.*' => 'integer|in:'.$topicIds,
            'location_name' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'state' => 'nullable|string|size:2',
            'city' => 'nullable|string|max:100',
            'zip' => 'nullable|string|max:20',
            'meta_title' => 'nullable|string|max:70',
            'meta_description' => 'nullable|string|max:160',
            'canonical_url' => 'nullable|url|max:2048',
            'allow_comments' => 'nullable|boolean',
        ];
    }
}
