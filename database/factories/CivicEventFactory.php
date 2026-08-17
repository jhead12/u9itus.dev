<?php

namespace Database\Factories;

use App\Enums\CivicEventStatus;
use App\Enums\CivicEventType;
use App\Models\CivicEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CivicEventFactory extends Factory
{
    protected $model = CivicEvent::class;

    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('now', '+30 days');

        return [
            'uuid' => Str::uuid(),
            'slug' => $this->faker->unique()->slug(4),
            'host_type' => \App\Models\Citizen::class,
            'host_id' => fn () => \App\Models\Citizen::factory(),
            'event_type' => $this->faker->randomElement(CivicEventType::cases()),
            'status' => CivicEventStatus::Published,
            'title' => $this->faker->sentence(5),
            'description' => $this->faker->paragraph(),
            'location_name' => $this->faker->city() . ', ' . $this->faker->stateAbbr(),
            'venue_name' => $this->faker->optional()->company(),
            'address' => $this->faker->optional()->streetAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->stateAbbr(),
            'zip' => $this->faker->optional()->postcode(),
            'latitude' => $this->faker->latitude(),
            'longitude' => $this->faker->longitude(),
            'starts_at' => $start,
            'ends_at' => (clone $start)->modify('+2 hours'),
            'timezone' => 'America/New_York',
            'capacity' => $this->faker->optional()->numberBetween(20, 500),
            'rsvp_requires_approval' => false,
            'is_virtual' => $this->faker->boolean(20),
            'virtual_url' => null,
            'image_url' => null,
            'banner_url' => null,
            'goal_amount_cents' => null,
            'group_id' => null,
            'related_post_id' => null,
        ];
    }
}
