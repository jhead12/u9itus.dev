<?php

namespace Database\Factories;

use App\Models\CandidateMatchReview;
use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use Illuminate\Database\Eloquent\Factories\Factory;

class CandidateMatchReviewFactory extends Factory
{
    protected $model = CandidateMatchReview::class;

    public function definition(): array
    {
        return [
            'politician_id' => Politician::factory(),
            'election_candidate_record_id' => ElectionCandidateRecord::factory(),
            'match_score' => fake()->randomFloat(4, 0.75, 0.89),
            'match_breakdown' => [
                'name' => 0.95,
                'office' => 0.70,
                'state' => 1.00,
                'geo' => 0.55,
                'party' => 0.50,
            ],
            'status' => CandidateMatchReview::STATUS_PENDING,
            'reason' => null,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
        ];
    }
}
