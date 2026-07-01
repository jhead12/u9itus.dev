<?php

namespace Database\Factories;

use App\Models\Citizen;
use Illuminate\Database\Eloquent\Factories\Factory;

class CitizenFactory extends Factory
{
    protected $model = Citizen::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'full_name' => $this->faker->name(),
            'business_name' => $this->faker->optional()->company(),
            'state' => $this->faker->stateAbbr(),
            'city' => $this->faker->city(),
            'address_line_1' => $this->faker->streetAddress(),
            'address_line_2' => null,
            'zip' => $this->faker->postcode(),
            'bio' => $this->faker->optional()->paragraph(),
            'profile_photo_url' => $this->faker->optional()->imageUrl(),
            'is_active' => true,
        ];
    }

    /**
     * Mark the citizen as having completed Stripe Identity verification.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_verified_at' => now(),
            'verified_at' => now(),
        ]);
    }
}
