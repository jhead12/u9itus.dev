<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of a ViewSession.
 *
 * Intentionally omits sensitive fraud/IP fields.
 */
class ViewSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'                  => $this->uuid,
            'status'                => $this->status,
            'payment_status'        => $this->payment_status,
            'watch_time_seconds'    => $this->watch_time_seconds,
            'completion_percentage' => $this->completion_percentage,
            'voter_payout_amount'   => $this->voter_payout_amount,
            'started_at'            => $this->started_at?->toIso8601String(),
            'completed_at'          => $this->completed_at?->toIso8601String(),
            'expires_at'            => $this->expires_at?->toIso8601String(),
            'campaign'              => $this->whenLoaded('campaign', fn () => [
                'uuid'    => $this->campaign->uuid,
                'title'   => $this->campaign->title,
                'payout'  => $this->campaign->voter_payout_per_view,
            ]),
        ];
    }
}
