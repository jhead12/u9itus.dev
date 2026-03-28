<?php

namespace Database\Factories;

use App\Enums\ViewSessionStatus;
use App\Enums\ViewPaymentStatus;
use App\Models\PoliticalCampaign;
use App\Models\ViewSession;
use App\Models\Voter;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ViewSessionFactory extends Factory
{
    protected $model = ViewSession::class;

    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'political_campaign_id' => PoliticalCampaign::factory(),
            'voter_id' => Voter::factory(),
            'status' => ViewSessionStatus::Assigned->value,
            'started_at' => null,
            'completed_at' => null,
            'expires_at' => now()->addHours(24),
            'watch_time_seconds' => 0,
            'completion_percentage' => 0.00,
            'voter_payout_amount' => 0.50,
            'platform_revenue' => 0.45,
            'referral_commission' => 0.050,
            'payment_status' => ViewPaymentStatus::Pending->value,
            'paid_at' => null,
            'ip_address' => $this->faker->ipv4(),
            'device_fingerprint' => null,
            'user_agent' => $this->faker->userAgent(),
            'fraud_score' => 0.00,
            'fraud_flags' => null,
        ];
    }

    public function completed(): self
    {
        return $this->state([
            'status' => ViewSessionStatus::Completed->value,
            'started_at' => now()->subMinutes(2),
            'completed_at' => now(),
            'watch_time_seconds' => 60,
            'completion_percentage' => 100.00,
        ]);
    }
}
