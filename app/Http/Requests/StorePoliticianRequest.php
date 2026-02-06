<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates politician profile creation requests.
 */
class StorePoliticianRequest extends FormRequest
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
        return [
            'full_name'         => 'required|string|max:255',
            'political_office'  => 'nullable|string|in:' . implode(',', config('dial4dough.political_offices', [])),
            'governance_level'  => 'nullable|string|in:' . implode(',', array_keys(config('dial4dough.governance_levels', []))),
            'district'          => 'nullable|string|max:255',
            'party_affiliation' => 'nullable|string|max:100',
            'state'             => 'nullable|string|max:2',
            'city'              => 'nullable|string|max:255',
            'website_url'       => 'nullable|url|max:500',
            'bio'               => 'nullable|string|max:2000',
            'wix_member_id'     => 'nullable|string',
            'wix_site_id'       => 'nullable|integer|exists:wix_sites,id',
        ];
    }
}
