<?php

namespace App\Http\Requests;

use App\Enums\CivicEventStatus;
use App\Enums\CivicEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CivicEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && (auth()->user()?->hasAnyRole(['citizen', 'politician']) ?? false);
    }

    public function rules(): array
    {
        $statuses = array_map(fn ($s) => $s->value, CivicEventStatus::cases());
        $types = array_map(fn ($t) => $t->value, CivicEventType::cases());

        return [
            'event_type' => ['required', Rule::in($types)],
            'status' => ['required', Rule::in($statuses)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:20000'],
            'location_name' => ['required', 'string', 'max:255'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:10'],
            'zip' => ['nullable', 'string', 'max:20'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'timezone' => ['required', 'string', 'max:50'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'rsvp_requires_approval' => ['boolean'],
            'is_virtual' => ['boolean'],
            'virtual_url' => ['nullable', 'url', 'max:500'],
            'topics' => ['nullable', 'array'],
            'topics.*' => ['integer', 'exists:politician_topics,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'rsvp_requires_approval' => $this->boolean('rsvp_requires_approval'),
            'is_virtual' => $this->boolean('is_virtual'),
        ]);
    }
}
