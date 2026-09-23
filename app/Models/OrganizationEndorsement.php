<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A client organization's own curated endorsement/position badge for its
 * white-label portal (e.g. "Endorsed by Our Union", "Vote YES on Prop 1").
 *
 * Deliberately separate from PoliticianEndorsement, which is populated only
 * by App\Services\EndorsementClassifier from news-article text — see that
 * model's docblock. Gating candidate endorsements to organizations whose
 * org_type allows it (Organization::canEndorseCandidates()) happens in
 * App\Http\Requests\StoreOrganizationEndorsementRequest, at write time.
 */
class OrganizationEndorsement extends Model
{
    protected $table = 'organization_endorsements';

    protected $fillable = [
        'organization_id',
        'politician_id',
        'ballot_measure_id',
        'position',
        'label',
        'note',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function politician(): BelongsTo
    {
        return $this->belongsTo(Politician::class);
    }

    public function ballotMeasure(): BelongsTo
    {
        return $this->belongsTo(BallotMeasure::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
