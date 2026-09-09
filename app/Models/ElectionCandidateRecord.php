<?php

namespace App\Models;

use App\Support\PoliticianDataRules;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ElectionCandidateRecord extends Model
{
    use HasFactory;

    /**
     * Automated discovery pipeline (RssCandidateDiscoverySource → CandidateLead
     * → CandidateLeadPromoter). Rows from this source are unverified news-
     * headline extractions until matched to a real Politician — the map
     * controller and prune command treat them accordingly.
     */
    public const DISCOVERY_SOURCE = 'candidate_discovery';

    protected static function booted(): void
    {
        // Reject news-headline artifacts ("Former L.A. Mayor Antonio",
        // "Eric Swalwell Won More", "Job Creator") from the discovery
        // pipeline at write time so a regression there can't repopulate the
        // map. Scoped to candidate_discovery — curated feeds/imports and
        // admin corrections are left alone. Returning false aborts the save
        // quietly; the promoter also pre-checks and marks the lead rejected.
        static::saving(function (self $record): bool {
            if ((string) $record->source !== self::DISCOVERY_SOURCE) {
                return true;
            }

            $violation = PoliticianDataRules::headlineFragmentViolation($record->full_name);
            if ($violation !== null) {
                Log::info('ElectionCandidateRecord: rejected discovery name-quality violation', [
                    'full_name' => $record->full_name,
                    'reason' => $violation,
                ]);

                return false;
            }

            return true;
        });

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

    public function identityLinks(): HasMany
    {
        return $this->hasMany(CandidateIdentityLink::class);
    }

    public function matchReviews(): HasMany
    {
        return $this->hasMany(CandidateMatchReview::class);
    }
}
