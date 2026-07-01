<?php

namespace Database\Factories;

use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Enums\CampaignType;
use App\Enums\CitizenAdType;
use App\Enums\PaymentStatus;
use App\Models\Citizen;
use App\Models\CitizenCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CitizenCampaignFactory extends Factory
{
    protected $model = CitizenCampaign::class;

    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'citizen_id' => Citizen::factory(),
            'title' => $this->faker->sentence(3),
            'message_summary' => $this->faker->paragraph(),
            'campaign_type' => CampaignType::Video->value,
            'citizen_ad_type' => CitizenAdType::LocalBusiness->value,
            'media_url' => $this->faker->url(),
            'media_type' => 'youtube',
            'media_duration' => 60,
            'thumbnail_url' => $this->faker->imageUrl(),
            'live_feed_url' => null,
            'live_scheduled_at' => null,
            'live_ended_at' => null,
            'revenue_per_view' => 0.75,
            'voter_payout_per_view' => 0.50,
            'total_budget' => 100.00,
            'amount_spent' => 0.00,
            'head_enterprises_fee_percent' => 15.00,
            'total_views_requested' => 100,
            'views_completed' => 0,
            'target_zip' => $this->faker->postcode(),
            'target_zip_radius' => 10,
            'daily_view_cap' => 500,
            'pac_registration_id' => null,
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

    public function ballotIssue(): self
    {
        return $this->state([
            'citizen_ad_type' => CitizenAdType::BallotIssue->value,
            'pac_registration_id' => strtoupper($this->faker->bothify('PAC-#####')),
            'revenue_per_view' => 1.00,
            'daily_view_cap' => null,
            'approval_status' => ApprovalStatus::Pending->value,
        ]);
    }
}
