<?php

namespace Database\Factories;

use App\Models\PoliticianViralMoment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PoliticianViralMoment>
 */
class PoliticianViralMomentFactory extends Factory
{
    protected $model = PoliticianViralMoment::class;

    public function definition(): array
    {
        return [
            'politician_id'   => null,
            'run_id'          => null,
            'source'          => $this->faker->randomElement(['youtube', 'cspan', 'news']),
            'source_id'       => Str::random(11),
            'title'           => $this->faker->sentence(8),
            'url'             => $this->faker->url(),
            'thumbnail_url'   => $this->faker->optional(0.8)->imageUrl(),
            'published_at'    => $this->faker->dateTimeBetween('-7 days', 'now'),
            'view_count'      => $this->faker->numberBetween(1000, 500000),
            'like_count'      => $this->faker->numberBetween(50, 50000),
            'comment_count'   => $this->faker->optional()->numberBetween(0, 5000),
            'view_velocity'   => $this->faker->randomFloat(2, 0, 5000),
            'authority_weight'=> 0.60,
            'match_confidence'=> 1.00,
            'moment_score'    => $this->faker->randomFloat(4, 0, 10),
            'is_featured'     => false,
            'captured_at'     => now(),
        ];
    }
}