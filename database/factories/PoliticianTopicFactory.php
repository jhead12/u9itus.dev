<?php

namespace Database\Factories;

use App\Models\PoliticianTopic;
use Illuminate\Database\Eloquent\Factories\Factory;

class PoliticianTopicFactory extends Factory
{
    protected $model = PoliticianTopic::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'name' => ucfirst($name),
            'slug' => \Illuminate\Support\Str::slug($name),
            'sort_order' => $this->faker->numberBetween(0, 100),
            'is_active' => true,
            'voter_selectable' => true,
            'auto_earned_only' => false,
        ];
    }
}
