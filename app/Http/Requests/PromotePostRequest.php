<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PromotePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'days' => ['required', 'integer', 'min:1', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return [
            'days.required' => 'Choose how many days to promote this post.',
            'days.integer'  => 'Days must be a whole number.',
            'days.min'      => 'Promotion must be at least 1 day.',
            'days.max'      => 'You can promote a post for up to 30 days at a time.',
        ];
    }
}
