<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms PoliticalCampaign model data for API responses.
 *
 * Hides internal fields: stripe_payment_intent_id, head_enterprises_fee_percent.
 */
class CampaignResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid'                   => $this->uuid,
            'title'                  => $this->title,
            'message_summary'        => $this->message_summary,
            'campaign_type'          => $this->campaign_type,
            'governance_level'       => $this->governance_level,
            'media_url'              => $this->media_url,
            'media_duration'         => $this->media_duration,
            'thumbnail_url'          => $this->thumbnail_url,
            'live_feed_url'          => $this->live_feed_url,
            'live_scheduled_at'      => $this->live_scheduled_at,
            'revenue_per_view'       => $this->revenue_per_view,
            'voter_payout_per_view'  => $this->voter_payout_per_view,
            'total_budget'           => $this->total_budget,
            'amount_spent'           => $this->amount_spent,
            'total_views_requested'  => $this->total_views_requested,
            'views_completed'        => $this->views_completed,
            'status'                 => $this->status,
            'approval_status'        => $this->approval_status,
            'target_states'          => $this->target_states,
            'target_cities'          => $this->target_cities,
            'target_districts'       => $this->target_districts,
            'started_at'             => $this->started_at,
            'completed_at'           => $this->completed_at,
            'politician'             => new PoliticianResource($this->whenLoaded('politician')),
            'view_sessions_count'    => $this->whenCounted('viewSessions'),
            'created_at'             => $this->created_at,
        ];
    }
}
