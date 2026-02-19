<?php

namespace Database\Factories;

use App\Models\Voter;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class VoterFactory extends Factory
{
    protected $model = Voter::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'uuid' => Str::uuid(),
            'full_name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'state' => $this->faker->stateAbbr(),
            'city' => $this->faker->city(),
            'zip_code' => $this->faker->postcode(),
            'congressional_district' => null,
            'preferred_governance_levels' => null,
            'referred_by_voter_id' => null,
            'referral_code' => strtoupper(Str::random(8)),
            'payment_method' => 'wallet',
            'paypal_email' => null,
            'cashapp_tag' => null,
            'wallet_balance' => 0.00,
            'total_earned' => 0.00,
            'pending_earnings' => 0.00,
            'total_views' => 0,
            'trust_score' => 100.00,
            'device_fingerprint' => null,
            'is_verified' => false,
            'is_active' => true,
            'flagged_for_fraud' => false,
            'last_view_at' => null,
        ];
    }
}
