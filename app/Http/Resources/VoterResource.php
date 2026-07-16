<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms Voter model data for API responses.
 *
 * Hides sensitive fields: device_fingerprint, ip_address, internal IDs, and
 * PII (email, phone) — SEC-4. Voter contact PII is never returned over the API;
 * the authenticated voter already knows their own details.
 */
class VoterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid'                       => $this->uuid,
            'full_name'                  => $this->full_name,
            'state'                      => $this->state,
            'city'                       => $this->city,
            'zip_code'                   => $this->zip_code,
            'referral_code'              => $this->referral_code,
            'payment_method'             => $this->payment_method,
            'wallet_balance'             => $this->wallet_balance,
            'total_earned'               => $this->total_earned,
            'pending_earnings'           => $this->pending_earnings,
            'total_views'                => $this->total_views,
            'trust_score'                => $this->trust_score,
            'is_verified'                => $this->is_verified,
            'preferred_governance_levels' => $this->preferred_governance_levels,
            'earlybank_member_id'        => $this->earlybank_member_id,
            'earlybank_linked_at'        => $this->earlybank_linked_at,
            'created_at'                 => $this->created_at,
        ];
    }
}
