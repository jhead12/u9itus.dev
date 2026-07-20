<?php

namespace App\Http\Requests;

use App\Enums\EventRsvpStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EventRsvpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_map(fn ($s) => $s->value, EventRsvpStatus::cases()))],
            'guest_count' => ['required', 'integer', 'min:1', 'max:10'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
