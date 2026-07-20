<?php

namespace Database\Factories;

use App\Enums\EventRsvpStatus;
use App\Models\CivicEvent;
use App\Models\EventRsvp;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EventRsvpFactory extends Factory
{
    protected $model = EventRsvp::class;

    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'event_id' => fn () => CivicEvent::factory(),
            'user_id' => fn () => \App\Models\User::factory(),
            'status' => $this->faker->randomElement(EventRsvpStatus::cases()),
            'guest_count' => $this->faker->numberBetween(1, 2),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
