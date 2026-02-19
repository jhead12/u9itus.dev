<?php

namespace Database\Factories;

use App\Models\Politician;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PoliticianFactory extends Factory
{
    protected $model = Politician::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'uuid' => Str::uuid(),
            'full_name' => $this->faker->name(),
            'political_office' => $this->faker->randomElement(['Mayor', 'Senator', 'Representative', 'Governor']),
            'governance_level' => $this->faker->randomElement(['Local', 'State', 'Federal']),
            'district' => $this->faker->optional()->numerify('District ##'),
            'party_affiliation' => $this->faker->randomElement(['Democrat', 'Republican', 'Independent']),
            'state' => $this->faker->stateAbbr(),
            'city' => $this->faker->city(),
            'website_url' => $this->faker->url(),
            'bio' => $this->faker->paragraph(),
            'profile_photo_url' => $this->faker->imageUrl(),
            'verified_official' => false,
            'kyc_status' => 'pending',
            'stripe_customer_id' => null,
            'total_spent' => 0.00,
            'total_campaigns' => 0,
            'total_views_received' => 0,
            'is_active' => true,
        ];
    }
}
