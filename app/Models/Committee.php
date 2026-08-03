<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A durable, deduplicated registry of FEC committee IDs seen in
 * independent-expenditure (Schedule E) data — built up by
 * App\Services\FECService::resolveCommitteeNames() so a committee's name
 * only ever needs resolving once across all candidates/runs, and so it can
 * later be hand-linked to a curated Organization record (logo, website,
 * description).
 */
class Committee extends Model
{
    protected $fillable = [
        'fec_committee_id',
        'name',
        'name_resolved_at',
        'organization_id',
        'first_seen_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'name_resolved_at' => 'datetime',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
