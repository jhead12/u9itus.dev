<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoyaltyViewerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'gender' => fake()->randomElement(['male', 'female', 'other']),
            'age_range' => fake()->randomElement(['18-24', '25-34', '35-44', '45-54', '55+']),
            'payment_method' => 'paypal',
            'paypal_email' => fake()->email(),
            'total_earned' => 0,
            'pending_earnings' => 0,
            'total_views' => 0,
            'trust_score' => 100,
        ];
    }
}
