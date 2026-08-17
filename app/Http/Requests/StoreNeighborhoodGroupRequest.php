<?php

namespace App\Http\Requests;

use App\Models\NeighborhoodGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNeighborhoodGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && (auth()->user()?->hasAnyRole(['voter', 'citizen']) ?? false);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', Rule::in(array_keys(config('u9itus.us_states', [])))],
            'zip' => ['nullable', 'string', 'regex:/^\d{5}(-\d{4})?$/'],
            'scope' => ['nullable', 'string', Rule::in(NeighborhoodGroup::SCOPES)],
        ];
    }
}
