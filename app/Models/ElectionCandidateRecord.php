<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ElectionCandidateRecord extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        // Scraped candidates feed the map's per-state cache (see
        // Politician::forgetMapCacheFor() for the same reasoning) — bust it
        // whenever a record is written or removed so newly-discovered or
        // newly-eliminated challengers show up without waiting on the TTL.
        $bust = function (self $record): void {
            $state = strtoupper(trim((string) $record->state));
            if ($state !== '') {
                Cache::forget("map_state_candidates_{$state}");
            }
        };

        static::saved($bust);
        static::deleted($bust);
    }

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
