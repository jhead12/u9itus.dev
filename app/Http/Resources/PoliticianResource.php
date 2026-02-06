<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms Politician model data for API responses.
 */
class PoliticianResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid'               => $this->uuid,
            'full_name'          => $this->full_name,
            'political_office'   => $this->political_office,
            'governance_level'   => $this->governance_level,
            'district'           => $this->district,
            'party_affiliation'  => $this->party_affiliation,
            'state'              => $this->state,
            'city'               => $this->city,
            'website_url'        => $this->website_url,
            'bio'                => $this->bio,
            'profile_photo_url'  => $this->profile_photo_url,
            'verified_official'  => $this->verified_official,
            'total_spent'        => $this->total_spent,
            'total_campaigns'    => $this->total_campaigns,
            'total_views_received' => $this->total_views_received,
            'is_active'          => $this->is_active,
            'campaigns'          => CampaignResource::collection($this->whenLoaded('campaigns')),
            'created_at'         => $this->created_at,
        ];
    }
}
