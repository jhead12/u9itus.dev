<?php

namespace Database\Factories;

use App\Models\ElectionCandidateRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

class ElectionCandidateRecordFactory extends Factory
{
    protected $model = ElectionCandidateRecord::class;

    public function definition(): array
    {
        return [
            'source' => 'local_feed',
            'external_candidate_id' => (string) fake()->unique()->numberBetween(10000, 99999),
            'full_name' => fake()->name(),
            'political_office' => fake()->randomElement(['Mayor', 'City Council Member', 'County Commissioner', 'School Board Trustee']),
            'governance_level' => fake()->randomElement(['City', 'County', 'School Board']),
            'state' => fake()->stateAbbr(),
            'county' => fake()->city() . ' County',
            'city' => fake()->city(),
            'district' => fake()->optional()->numerify('District ##'),
            'party_affiliation' => fake()->randomElement(['Democratic', 'Republican', 'Independent']),
            'election_date' => fake()->dateTimeBetween('-6 months', '+12 months')->format('Y-m-d'),
            'payload' => ['source' => 'seed'],
            'last_seen_at' => now(),
        ];
    }
}
