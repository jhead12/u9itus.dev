<?php

namespace Database\Factories;

use App\Enums\CampaignType;
use App\Enums\CampaignStatus;
use App\Enums\ApprovalStatus;
use App\Enums\PaymentStatus;
use App\Models\PoliticalCampaign;
use App\Models\Politician;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PoliticalCampaignFactory extends Factory
{
    protected $model = PoliticalCampaign::class;

    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'politician_id' => Politician::factory(),
            'title' => $this->faker->sentence(3),
            'message_summary' => $this->faker->paragraph(),
            'campaign_type' => CampaignType::Video->value,
            'governance_level' => $this->faker->randomElement(['Local', 'State', 'Federal']),
            'media_url' => $this->faker->url(),
            'media_duration' => 60,
            'thumbnail_url' => $this->faker->imageUrl(),
            'live_feed_url' => null,
            'live_scheduled_at' => null,
            'live_ended_at' => null,
            'revenue_per_view' => 0.60,
            'voter_payout_per_view' => 0.25,
            'total_budget' => 1000.00,
            'amount_spent' => 0.00,
            'head_enterprises_fee_percent' => 15.00,
            'total_views_requested' => 1000,
            'views_completed' => 0,
            'target_states' => null,
            'target_cities' => null,
            'target_districts' => null,
            'target_governance_levels' => null,
            'min_watch_time_percent' => 80,
            'status' => CampaignStatus::Draft->value,
            'approval_status' => ApprovalStatus::Pending->value,
            'payment_status' => PaymentStatus::Pending->value,
            'stripe_payment_intent_id' => null,
            'approved_at' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    public function active(): self
    {
        return $this->state([
            'status' => CampaignStatus::Active->value,
            'approval_status' => ApprovalStatus::Approved->value,
        ]);
    }

    public function pending(): self
    {
        return $this->state([
            'status' => CampaignStatus::PendingApproval->value,
        ]);
    }
}
