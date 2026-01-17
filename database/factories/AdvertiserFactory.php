<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdvertiserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'company_name' => fake()->company(),
            'business_type' => fake()->randomElement(['Retail', 'Technology', 'Healthcare', 'Finance']),
            'website_url' => fake()->url(),
            'business_street' => fake()->streetAddress(),
            'business_city' => fake()->city(),
            'business_state' => fake()->stateAbbr(),
            'business_zip' => fake()->postcode(),
            'business_country' => 'USA',
            'contact_name' => fake()->name(),
            'contact_phone' => fake()->phoneNumber(),
            'contact_email' => fake()->companyEmail(),
            'total_spent' => 0,
            'rating' => 0,
        ];
    }
}
