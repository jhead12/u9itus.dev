<?php

namespace Database\Factories;

use App\Models\Advertiser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CampaignFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'advertiser_id' => Advertiser::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'campaign_type' => 'video',
            'media_duration' => fake()->numberBetween(10, 20),
            'total_budget' => fake()->numberBetween(100, 1000),
            'payment_per_view' => fake()->randomFloat(2, 0.50, 5.00),
            'head_enterprises_fee_percent' => 15.0,
            'total_views_requested' => fake()->numberBetween(50, 500),
            'views_completed' => 0,
            'max_views_per_viewer' => 1,
            'min_watch_time_percent' => 80,
            'status' => 'draft',
            'approval_status' => 'pending',
            'payment_status' => 'pending',
        ];
    }
}
