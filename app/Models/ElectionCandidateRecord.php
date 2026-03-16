<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ElectionCandidateRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'source',
        'external_candidate_id',
        'full_name',
        'political_office',
        'governance_level',
        'state',
        'county',
        'city',
        'district',
        'party_affiliation',
        'election_date',
        'payload',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'election_date' => 'date',
            'last_seen_at' => 'datetime',
        ];
    }

    public function identityLinks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CandidateIdentityLink::class);
    }

    public function matchReviews(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CandidateMatchReview::class);
    }
}
